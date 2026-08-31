<?php

namespace App\Jobs;

use App\Models\ChannelConnection;
use App\Models\License;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;
use OmniSignal\Drivers\GoogleAdsDriver;
use OmniSignal\DTO\ConversionPayload;
use OmniSignal\Http\HttpClient;

/**
 * Uploads one customer's conversion to the channels we host for them.
 *
 * Per-tenant credentials rule out the Laravel package here: its drivers read
 * global config, which is right for a single-tenant install and wrong for us.
 * The PHP SDK's drivers take their configuration per instance, so they are the
 * correct engine for this side.
 */
class UploadHostedConversion implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public int $timeout = 60;

    /** @var array<int, int> */
    public array $backoff = [30, 300];

    /**
     * @param  array<string, mixed>  $conversion
     * @param  array<int, string>  $channels
     */
    public function __construct(
        public int $licenseId,
        public array $conversion,
        public array $channels,
    ) {}

    public function handle(): void
    {
        $license = License::with('connections')->find($this->licenseId);

        if (! $license || ! $license->isActive()) {
            Log::info("[Hosted] Licence {$this->licenseId} is no longer active; dropping conversion.");

            return;
        }

        foreach ($this->channels as $channel) {
            $connection = $license->connections->firstWhere('channel', $channel);

            if (! $connection instanceof ChannelConnection || ! $connection->isUsable()) {
                continue;
            }

            match ($channel) {
                'google' => $this->uploadToGoogle($connection),
                default => Log::warning("[Hosted] No uploader for channel '{$channel}'."),
            };
        }
    }

    protected function uploadToGoogle(ChannelConnection $connection): void
    {
        // Resolved rather than constructed so the upload path itself can be
        // exercised in tests instead of only the code around it.
        $driver = new GoogleAdsDriver([
            // Ours: the OAuth application and the developer token, which is
            // issued against our manager account.
            'client_id' => config('services.google_ads.client_id'),
            'client_secret' => config('services.google_ads.client_secret'),
            'developer_token' => config('services.google_ads.developer_token'),
            'login_customer_id' => config('services.google_ads.login_customer_id'),
            'api_version' => config('services.google_ads.api_version', 'v23'),

            // Theirs: authorised in the portal, encrypted at rest.
            'refresh_token' => $connection->credential('refresh_token'),
            'customer_id' => $connection->credential('customer_id'),
            'conversion_action' => $connection->credential('conversion_action'),
        ], app(HttpClient::class));

        $result = $driver->upload([$this->payload()]);

        if ($result['success']) {
            $connection->markVerified();

            return;
        }

        $reason = $result['errors'][0] ?? 'Unknown error.';

        // An authorisation failure is the customer's to fix, and they need to
        // be told rather than have us retry it 3 times an hour forever.
        if ($this->isAuthFailure($reason)) {
            $connection->markNeedsReauth($reason);
            Log::warning("[Hosted] Google Ads authorisation failed for licence {$this->licenseId}: {$reason}");

            return;
        }

        Log::error("[Hosted] Google Ads upload failed for licence {$this->licenseId}: {$reason}");

        throw new \RuntimeException("Google Ads upload failed: {$reason}");
    }

    protected function payload(): ConversionPayload
    {
        $c = $this->conversion;

        return new ConversionPayload(
            eventName: (string) $c['event_name'],
            value: isset($c['value']) ? (float) $c['value'] : null,
            currency: $c['currency'] ?? 'USD',
            orderId: (string) $c['event_id'],
            timestamp: (int) ($c['timestamp'] ?? time()),
            userData: array_filter([
                'email' => $c['email'] ?? null,
                'phone' => $c['phone'] ?? null,
            ]),
            gclid: $c['gclid'] ?? null,
            gbraid: $c['gbraid'] ?? null,
            wbraid: $c['wbraid'] ?? null,
        );
    }

    protected function isAuthFailure(string $reason): bool
    {
        foreach (['invalid_grant', 'UNAUTHENTICATED', 'PERMISSION_DENIED', 'HTTP 401', 'HTTP 403', 'refresh token'] as $needle) {
            if (str_contains($reason, $needle)) {
                return true;
            }
        }

        return false;
    }
}
