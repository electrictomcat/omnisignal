<?php

namespace App\Http\Controllers;

use App\Mail\PortalAccessLink;
use App\Models\License;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\URL;

/**
 * Self-service licence portal.
 *
 * Access is proven by control of the purchase email address: a lookup sends a
 * signed, short-lived link to the address on file and reveals nothing to the
 * requester. Previously any visitor who knew (or guessed) a customer's email
 * was shown that customer's licence keys, and could deactivate their domains.
 */
class PortalController extends Controller
{
    /** How long an emailed access link stays valid. */
    protected const LINK_TTL_MINUTES = 30;

    public function index(Request $request): View
    {
        return view('portal', [
            'licenses' => collect(),
            'unlocked' => false,
            'email' => null,
        ]);
    }

    /**
     * Email a signed access link. Always reports the same thing, so the form
     * cannot be used to test whether an address is a customer.
     */
    public function lookup(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'email' => ['required', 'string', 'email', 'max:255'],
        ]);

        $email = strtolower(trim($validated['email']));

        if (License::where('customer_email', $email)->exists()) {
            $url = URL::temporarySignedRoute(
                'portal.show',
                now()->addMinutes(self::LINK_TTL_MINUTES),
                ['email' => $email],
            );

            Mail::to($email)->send(new PortalAccessLink($url, self::LINK_TTL_MINUTES));
        }

        return redirect()
            ->route('portal')
            ->with('status', "If {$email} has a licence with us, an access link is on its way. It expires in "
                .self::LINK_TTL_MINUTES.' minutes.');
    }

    /**
     * Show the licences for the address the signed link was issued for.
     *
     * The `signed` middleware on the route rejects a tampered or expired link,
     * so reaching here proves the caller received the email.
     */
    public function show(Request $request): View
    {
        $email = strtolower(trim((string) $request->query('email')));

        $licenses = License::query()
            ->with('connections')
            ->where('customer_email', $email)
            ->latest()
            ->get();

        // Re-sign for the session so the deactivate form does not need the
        // original link, and so the link cannot be replayed after it expires.
        $request->session()->put('portal_email', $email);

        return view('portal', [
            'licenses' => $licenses,
            'unlocked' => true,
            'email' => $email,
        ]);
    }

    /**
     * Deactivate one domain from a licence the current session owns.
     */
    public function deactivateDomain(Request $request): RedirectResponse
    {
        $email = $request->session()->get('portal_email');

        if (! $email) {
            return redirect()
                ->route('portal')
                ->withErrors(['portal' => 'Your session has expired. Request a new access link to continue.']);
        }

        $validated = $request->validate([
            'license_id' => ['required', 'integer'],
            'domain' => ['required', 'string', 'max:255'],
        ]);

        // Scoped to the authenticated email rather than trusting the submitted
        // ID, so a guessed license_id resolves to nothing.
        $license = License::query()
            ->where('id', $validated['license_id'])
            ->where('customer_email', $email)
            ->first();

        if (! $license) {
            return back()->withErrors(['portal' => 'That licence is not on your account.']);
        }

        $domain = trim($validated['domain']);

        if (! $license->deactivate($domain)) {
            return back()->withErrors(['portal' => "'{$domain}' is not currently activated on that licence."]);
        }

        return back()->with('success', "'{$domain}' was deactivated. You can now activate the licence on another site.");
    }
}
