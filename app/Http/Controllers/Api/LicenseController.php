<?php

namespace App\Http\Controllers\Api;

use App\Models\License;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

class LicenseController extends Controller
{
    /**
     * Validate a license key.
     */
    public function validateKey(Request $request): JsonResponse
    {
        $request->validate([
            'license_key' => 'required|string',
            'domain' => 'nullable|string',
        ]);

        $key = trim($request->input('license_key'));
        $domain = $request->input('domain');

        $license = License::where('license_key', $key)->first();

        if (! $license) {
            return response()->json([
                'valid' => false,
                'message' => 'License key not found.',
            ], 404);
        }

        if (! $license->isActive()) {
            return response()->json([
                'valid' => false,
                'status' => $license->status,
                'message' => 'License is inactive or expired.',
            ], 403);
        }

        $isActivated = $domain ? $license->isActivatedFor($domain) : true;

        return response()->json([
            'valid' => true,
            'tier' => $license->tier,
            'status' => $license->status,
            'activation_limit' => $license->activation_limit,
            'activation_count' => $license->activation_count,
            'is_activated_for_domain' => $isActivated,
            'expires_at' => $license->expires_at?->toIso8601String(),
        ]);
    }

    /**
     * Activate a domain instance under a license key.
     */
    public function activate(Request $request): JsonResponse
    {
        $request->validate([
            'license_key' => 'required|string',
            'domain' => 'required|string',
        ]);

        $key = trim($request->input('license_key'));
        $domain = trim($request->input('domain'));

        $license = License::where('license_key', $key)->first();

        if (! $license) {
            return response()->json([
                'activated' => false,
                'message' => 'License key not found.',
            ], 404);
        }

        if (! $license->isActive()) {
            return response()->json([
                'activated' => false,
                'message' => 'License is inactive or expired.',
            ], 403);
        }

        $success = $license->activate($domain);

        if (! $success) {
            return response()->json([
                'activated' => false,
                'message' => "Activation limit reached ({$license->activation_count}/{$license->activation_limit} domains used). Please upgrade your plan on omnisignal.dev.",
            ], 422);
        }

        return response()->json([
            'activated' => true,
            'tier' => $license->tier,
            'domain' => $domain,
            'activation_count' => $license->activation_count,
            'activation_limit' => $license->activation_limit,
        ]);
    }

    /**
     * Deactivate a domain instance.
     */
    public function deactivate(Request $request): JsonResponse
    {
        $request->validate([
            'license_key' => 'required|string',
            'domain' => 'required|string',
        ]);

        $key = trim($request->input('license_key'));
        $domain = trim($request->input('domain'));

        $license = License::where('license_key', $key)->first();

        if (! $license) {
            return response()->json(['deactivated' => false, 'message' => 'License not found.'], 404);
        }

        $license->deactivate($domain);

        return response()->json([
            'deactivated' => true,
            'domain' => $domain,
            'activation_count' => $license->activation_count,
        ]);
    }
}
