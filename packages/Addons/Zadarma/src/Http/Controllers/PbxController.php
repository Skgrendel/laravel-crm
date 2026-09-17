<?php

namespace Addons\Zadarma\Http\Controllers;

use Addons\Zadarma\Repositories\ZadarmaSettingRepository;
use Addons\Zadarma\Services\ZadarmaClient;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Webkul\Admin\Http\Controllers\Controller;

/**
 * Manages the one PBX resource Zadarma's API actually exposes as writable:
 * per-extension call forwarding (redirection), plus a read-only view of the
 * account's DID numbers. Confirmed against Zadarma's official SDK source —
 * there is no API for SIP trunks, and no API to change which extension a
 * DID number rings (both are configured only in Zadarma's own panel).
 */
class PbxController extends Controller
{
    public function __construct(
        protected ZadarmaSettingRepository $zadarmaSettingRepository,
    ) {}

    /**
     * Fetch the current call-forwarding settings for an extension.
     */
    public function showRedirection(string $extension): JsonResponse
    {
        try {
            $client = $this->client();

            return response()->json($client->getPbxRedirection($extension));
        } catch (\Throwable $exception) {
            return response()->json(['message' => $exception->getMessage()], 422);
        }
    }

    /**
     * Update (or turn off) call forwarding for an extension.
     */
    public function updateRedirection(Request $request, string $extension): JsonResponse
    {
        $data = $request->validate([
            'type'          => 'required|in:off,phone,voicemail',
            'condition'     => 'required_unless:type,off|in:always,noanswer',
            'destination'   => 'required_unless:type,off|string',
            'set_caller_id' => 'sometimes|boolean',
        ]);

        try {
            $client = $this->client();

            $result = match ($data['type']) {
                'off' => $client->setPbxRedirectionOff($extension),
                'phone' => $client->setPbxPhoneRedirection(
                    $extension,
                    $data['destination'],
                    $data['condition'] === 'always',
                    $request->boolean('set_caller_id')
                ),
                'voicemail' => $client->setPbxVoicemailRedirection(
                    $extension,
                    $data['destination'],
                    $data['condition'] === 'always'
                ),
            };

            return response()->json($result);
        } catch (\Throwable $exception) {
            return response()->json(['message' => $exception->getMessage()], 422);
        }
    }

    /**
     * List the account's DID numbers and which extension each currently
     * rings. Read-only — reassigning that lives in Zadarma's own panel.
     */
    public function directNumbers(): JsonResponse
    {
        try {
            $client = $this->client();

            return response()->json(['numbers' => $client->getDirectNumbers()]);
        } catch (\Throwable $exception) {
            return response()->json(['message' => $exception->getMessage()], 422);
        }
    }

    protected function client(): ZadarmaClient
    {
        $settings = $this->zadarmaSettingRepository->getSettings();

        if (! $settings->enabled || empty($settings->api_key) || empty($settings->api_secret)) {
            throw new \RuntimeException(trans('zadarma::app.phone.not-configured'));
        }

        return new ZadarmaClient($settings->api_key, $settings->api_secret);
    }
}
