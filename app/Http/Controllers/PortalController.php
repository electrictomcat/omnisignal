<?php

namespace App\Http\Controllers;

use App\Models\License;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

class PortalController extends Controller
{
    public function index(Request $request): View
    {
        $query = trim((string) $request->input('q', session('portal_query', '')));
        $licenses = collect();

        if (! empty($query)) {
            $licenses = License::query()
                ->where('customer_email', strtolower($query))
                ->orWhere('license_key', $query)
                ->orWhere('order_id', $query)
                ->latest()
                ->get();
        }

        return view('portal', [
            'query' => $query,
            'licenses' => $licenses,
        ]);
    }

    public function lookup(Request $request): RedirectResponse
    {
        $request->validate([
            'query' => 'required|string',
        ]);

        $query = trim($request->input('query'));

        return redirect()->route('portal', ['q' => $query])
            ->with('portal_query', $query);
    }

    public function deactivateDomain(Request $request): RedirectResponse
    {
        $request->validate([
            'license_id' => 'required|exists:licenses,id',
            'domain' => 'required|string',
        ]);

        $license = License::findOrFail($request->input('license_id'));
        $domain = trim($request->input('domain'));

        $license->deactivate($domain);

        return back()->with('success', "Domain '{$domain}' was successfully deactivated from your license.");
    }
}
