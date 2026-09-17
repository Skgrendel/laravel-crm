<?php

namespace Addons\Zadarma\Http\Controllers;

use Addons\Zadarma\Repositories\ZadarmaSettingRepository;
use Addons\Zadarma\Services\ZadarmaClient;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Webkul\Admin\Http\Controllers\Controller;

class SettingsController extends Controller
{
    public function __construct(
        protected ZadarmaSettingRepository $zadarmaSettingRepository,
    ) {}

    /**
     * Display the Zadarma integration settings.
     */
    public function index(): View
    {
        $settings = $this->zadarmaSettingRepository->getSettings();

        return view('zadarma::settings.index', compact('settings'));
    }

    /**
     * Update the Zadarma integration settings.
     */
    public function update(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'enabled'        => 'sometimes|boolean',
            'api_key'        => 'nullable|string',
            'api_secret'     => 'nullable|string',
            'webhook_secret' => 'nullable|string',
        ]);

        $data['enabled'] = $request->boolean('enabled');

        $settings = $this->zadarmaSettingRepository->getSettings();

        /**
         * Keep existing credentials when the field is left blank, so the
         * admin doesn't have to re-paste secrets every time they touch this
         * form (e.g. just to flip the toggle).
         */
        foreach (['api_key', 'api_secret', 'webhook_secret'] as $secretField) {
            if (empty($data[$secretField])) {
                unset($data[$secretField]);
            }
        }

        $this->zadarmaSettingRepository->update($data, $settings->id);

        session()->flash('success', trans('zadarma::app.settings.index.update-success'));

        return redirect()->route('admin.settings.zadarma.index');
    }

    /**
     * Test the configured credentials against the Zadarma API. Falls back to
     * the already-saved credentials when the form fields are left blank, so
     * "Probar conexión" also works right after a save without retyping secrets.
     */
    public function testConnection(Request $request): JsonResponse
    {
        $request->validate([
            'api_key'    => 'nullable|string',
            'api_secret' => 'nullable|string',
        ]);

        $settings = $this->zadarmaSettingRepository->getSettings();

        $apiKey = $request->filled('api_key') ? $request->input('api_key') : $settings->api_key;
        $apiSecret = $request->filled('api_secret') ? $request->input('api_secret') : $settings->api_secret;

        if (empty($apiKey) || empty($apiSecret)) {
            return response()->json([
                'message' => trans('zadarma::app.settings.index.connection-failed', ['error' => trans('zadarma::app.settings.index.missing-credentials')]),
            ], 422);
        }

        try {
            $client = new ZadarmaClient($apiKey, $apiSecret);

            $client->getBalance();

            return response()->json([
                'message' => trans('zadarma::app.settings.index.connection-success'),
            ]);
        } catch (\Throwable $exception) {
            return response()->json([
                'message' => trans('zadarma::app.settings.index.connection-failed', ['error' => $exception->getMessage()]),
            ], 422);
        }
    }
}
