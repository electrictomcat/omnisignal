<?php

namespace App\Http\Controllers;

use App\Models\ChannelConnection;
use App\Models\License;
use App\Services\GoogleAdsOAuth;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * Connecting a customer's Google Ads account to their licence.
 *
 * Every route here requires the portal session established by the emailed
 * signed link, so a connection can only ever be made by someone who controls
 * the purchase address.
 */
class ChannelConnectionController extends Controller
{
    /** How long the OAuth state token stays valid. */
    protected const STATE_TTL_MINUTES = 15;

    public function __construct(protected GoogleAdsOAuth $oauth) {}

    /**
     * Send the customer to Google's consent screen.
     */
    public function connect(Request $request, string $licenseId): RedirectResponse
    {
        $license = $this->authorizedLicense($request, $licenseId);

        if (! $license instanceof License) {
            return $license;
        }

        if (! $this->oauth->isConfigured()) {
            return back()->withErrors(['connect' => 'Google Ads connections are not available right now. Please contact support.']);
        }

        // One-time state, held server-side. It carries the licence so the
        // callback cannot be pointed at someone else's.
        $state = Str::random(48);

        Cache::put("google_oauth_state:{$state}", [
            'license_id' => $license->id,
            'email' => $request->session()->get('portal_email'),
        ], now()->addMinutes(self::STATE_TTL_MINUTES));

        return redirect()->away($this->oauth->authorizationUrl($state));
    }

    /**
     * Handle Google's redirect back.
     */
    public function callback(Request $request): RedirectResponse
    {
        $state = (string) $request->query('state', '');
        $payload = $state !== '' ? Cache::pull("google_oauth_state:{$state}") : null;

        if (! is_array($payload)) {
            return redirect()->route('portal')
                ->withErrors(['connect' => 'That connection link has expired. Start again from your licence page.']);
        }

        // The session must still belong to the address that began the flow.
        if ($request->session()->get('portal_email') !== ($payload['email'] ?? null)) {
            return redirect()->route('portal')
                ->withErrors(['connect' => 'Your session expired during the connection. Request a new access link and try again.']);
        }

        if ($error = $request->query('error')) {
            return $this->backToPortal($request)
                ->withErrors(['connect' => 'Google declined the connection: '.$error]);
        }

        $code = (string) $request->query('code', '');

        if ($code === '') {
            return $this->backToPortal($request)->withErrors(['connect' => 'Google did not return an authorisation code.']);
        }

        $exchange = $this->oauth->exchangeCode($code);

        if (! $exchange['ok']) {
            return $this->backToPortal($request)->withErrors(['connect' => $exchange['error']]);
        }

        $license = License::find($payload['license_id']);

        if (! $license) {
            return redirect()->route('portal')->withErrors(['connect' => 'That licence no longer exists.']);
        }

        $connection = ChannelConnection::updateOrCreate(
            ['license_id' => $license->id, 'channel' => 'google'],
            [
                'credentials' => ['refresh_token' => $exchange['refresh_token']],
                'status' => 'needs_setup',
                'last_error' => null,
            ],
        );

        Log::info("[Connect] Google Ads authorised for licence {$license->id}.");

        return redirect()->route('portal.connect.google.setup', ['connection' => $connection->id]);
    }

    /**
     * Pick the ad account and conversion action.
     */
    public function setup(Request $request, string $connection)
    {
        $connection = $this->authorizedConnection($request, $connection);

        if (! $connection instanceof ChannelConnection) {
            return $connection;
        }

        $refreshToken = $connection->credential('refresh_token');

        if (! $refreshToken) {
            return redirect()->route('portal')->withErrors(['connect' => 'That connection is incomplete. Reconnect the account.']);
        }

        $accounts = $this->oauth->accessibleAccounts($refreshToken);

        if (! $accounts['ok']) {
            $connection->markNeedsReauth($accounts['error']);

            return $this->backToPortal($request)->withErrors(['connect' => $accounts['error']]);
        }

        $selected = (string) $request->query('account', $connection->account_id ?? '');
        $actions = [];

        if ($selected !== '') {
            $result = $this->oauth->conversionActions($refreshToken, $selected);

            if (! $result['ok']) {
                return view('portal.connect-google', [
                    'connection' => $connection,
                    'accounts' => $accounts['accounts'],
                    'selected' => $selected,
                    'actions' => [],
                    'error' => $result['error'],
                ]);
            }

            $actions = $result['actions'];
        }

        return view('portal.connect-google', [
            'connection' => $connection,
            'accounts' => $accounts['accounts'],
            'selected' => $selected,
            'actions' => $actions,
            'error' => null,
        ]);
    }

    /**
     * Save the chosen account and action, completing the connection.
     */
    public function store(Request $request, string $connection): RedirectResponse
    {
        $connection = $this->authorizedConnection($request, $connection);

        if (! $connection instanceof ChannelConnection) {
            return $connection;
        }

        $validated = $request->validate([
            'account_id' => ['required', 'string', 'max:32'],
            'account_name' => ['nullable', 'string', 'max:255'],
            'conversion_action' => ['required', 'string', 'max:255'],
        ]);

        $connection->forceFill([
            'credentials' => array_merge($connection->credentials ?? [], [
                'customer_id' => preg_replace('/\D/', '', $validated['account_id']),
                'conversion_action' => $validated['conversion_action'],
            ]),
            'account_id' => preg_replace('/\D/', '', $validated['account_id']),
            'account_name' => $validated['account_name'] ?? null,
            'status' => 'connected',
            'last_error' => null,
            'verified_at' => now(),
        ])->save();

        return redirect()->route('portal')
            ->with('success', 'Google Ads is connected. Conversions from your site will now be uploaded to that account.');
    }

    /**
     * Disconnect, discarding the stored credentials.
     */
    public function destroy(Request $request, string $connection): RedirectResponse
    {
        $connection = $this->authorizedConnection($request, $connection);

        if (! $connection instanceof ChannelConnection) {
            return $connection;
        }

        $channel = $connection->channel;
        $connection->delete();

        return redirect()->route('portal')
            ->with('success', ucfirst($channel).' was disconnected and its stored credentials deleted.');
    }

    /**
     * The licence, if this portal session owns it.
     *
     * @return License|RedirectResponse
     */
    protected function authorizedLicense(Request $request, string $licenseId)
    {
        $email = $request->session()->get('portal_email');

        if (! $email) {
            return redirect()->route('portal')
                ->withErrors(['portal' => 'Your session has expired. Request a new access link to continue.']);
        }

        $license = License::query()
            ->where('id', $licenseId)
            ->where('customer_email', $email)
            ->first();

        if (! $license) {
            return redirect()->route('portal')->withErrors(['portal' => 'That licence is not on your account.']);
        }

        return $license;
    }

    /**
     * The connection, if this portal session owns its licence.
     *
     * @return ChannelConnection|RedirectResponse
     */
    protected function authorizedConnection(Request $request, string $connectionId)
    {
        $email = $request->session()->get('portal_email');

        if (! $email) {
            return redirect()->route('portal')
                ->withErrors(['portal' => 'Your session has expired. Request a new access link to continue.']);
        }

        $connection = ChannelConnection::query()
            ->whereHas('license', fn ($query) => $query->where('customer_email', $email))
            ->find($connectionId);

        if (! $connection) {
            return redirect()->route('portal')->withErrors(['portal' => 'That connection is not on your account.']);
        }

        return $connection;
    }

    protected function backToPortal(Request $request): RedirectResponse
    {
        return redirect()->route('portal');
    }
}
