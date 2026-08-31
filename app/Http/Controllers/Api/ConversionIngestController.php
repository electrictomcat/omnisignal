<?php

namespace App\Http\Controllers\Api;

use App\Jobs\UploadHostedConversion;
use App\Models\License;
use App\Support\IngestToken;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Cache;

/**
 * Receives conversions from a customer's site for channels we host.
 *
 * A site sends here instead of to the ad platform when the platform needs
 * credentials that cannot live on the customer's server - Google Ads being the
 * case that forced this to exist.
 */
class ConversionIngestController extends Controller
{
    /** Most conversions accepted in a single request. */
    protected const MAX_BATCH = 50;

    /** How long an event_id is remembered for de-duplication. */
    protected const DEDUPE_DAYS = 7;

    public function __invoke(Request $request): JsonResponse
    {
        $license = IngestToken::resolve(
            (string) $request->input('domain', ''),
            (string) $request->bearerToken(),
        );

        if (! $license) {
            // Deliberately identical for a bad token, an unknown domain and a
            // deactivated one: the caller learns nothing about which.
            return response()->json([
                'error' => 'Unauthenticated. Check the licence key and that this domain is activated.',
            ], 401);
        }

        $validated = $request->validate([
            'domain' => ['required', 'string', 'max:255'],
            'conversions' => ['required', 'array', 'min:1', 'max:'.self::MAX_BATCH],
            'conversions.*.event_name' => ['required', 'string', 'max:255'],
            'conversions.*.event_id' => ['required', 'string', 'max:255'],
            'conversions.*.value' => ['nullable', 'numeric'],
            'conversions.*.currency' => ['nullable', 'string', 'size:3'],
            'conversions.*.timestamp' => ['nullable', 'integer'],
            'conversions.*.gclid' => ['nullable', 'string', 'max:255'],
            'conversions.*.gbraid' => ['nullable', 'string', 'max:255'],
            'conversions.*.wbraid' => ['nullable', 'string', 'max:255'],
            'conversions.*.email' => ['nullable', 'string', 'max:255'],
            'conversions.*.phone' => ['nullable', 'string', 'max:64'],
        ]);

        $hosted = $license->hostedChannels();

        if ($hosted === []) {
            return response()->json([
                'accepted' => 0,
                'results' => [],
                'message' => 'No ad platform is connected to this licence. Connect one at omnisignal.dev/portal.',
            ], 200);
        }

        $results = [];
        $accepted = 0;

        foreach ($validated['conversions'] as $conversion) {
            $eventId = (string) $conversion['event_id'];
            $dedupeKey = "ingest:{$license->id}:".sha1($eventId);

            // A retry from the site must not produce a second conversion.
            if (! Cache::add($dedupeKey, true, now()->addDays(self::DEDUPE_DAYS))) {
                $results[] = ['event_id' => $eventId, 'status' => 'duplicate'];

                continue;
            }

            UploadHostedConversion::dispatch($license->id, $conversion, $hosted);

            $results[] = ['event_id' => $eventId, 'status' => 'queued'];
            $accepted++;
        }

        return response()->json([
            'accepted' => $accepted,
            'channels' => $hosted,
            'results' => $results,
        ], 202);
    }

    /**
     * Tell a site which hosted channels are live for its licence.
     *
     * The plugin calls this so it only forwards what we can actually deliver,
     * rather than posting conversions that will be dropped at the far end.
     */
    public function channels(Request $request): JsonResponse
    {
        $license = IngestToken::resolve(
            (string) $request->input('domain', ''),
            (string) $request->bearerToken(),
        );

        if (! $license instanceof License) {
            return response()->json(['error' => 'Unauthenticated.'], 401);
        }

        return response()->json([
            'tier' => $license->tier,
            'channels' => $license->hostedChannels(),
        ]);
    }
}
