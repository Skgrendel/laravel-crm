<?php

namespace Addons\Zadarma\Http\Controllers;

use Addons\Zadarma\Repositories\ZadarmaCallLogRepository;
use Addons\Zadarma\Repositories\ZadarmaExtensionMappingRepository;
use Addons\Zadarma\Repositories\ZadarmaSettingRepository;
use Addons\Zadarma\Services\PhoneLeadMatcher;
use Addons\Zadarma\Services\ZadarmaClient;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Webkul\Admin\Http\Controllers\Controller;

class PhoneController extends Controller
{
    public function __construct(
        protected ZadarmaSettingRepository $zadarmaSettingRepository,
        protected ZadarmaExtensionMappingRepository $zadarmaExtensionMappingRepository,
        protected ZadarmaCallLogRepository $zadarmaCallLogRepository,
        protected PhoneLeadMatcher $phoneLeadMatcher,
    ) {}

    /**
     * Render the standalone softphone popup page (no admin shell — it's
     * meant to be opened via `window.open` and stay independent of the
     * CRM's page-by-page navigation, so an active call doesn't drop when
     * the agent moves to a different Lead in the main window).
     */
    public function show(): View
    {
        $extension = $this->zadarmaExtensionMappingRepository->findExtensionByUserId(
            auth()->guard('user')->id()
        );

        $recentCalls = $extension
            ? $this->zadarmaCallLogRepository->recentForExtension($extension)
            : collect();

        return view('zadarma::phone.show', [
            'extension'   => $extension,
            'recentCalls' => $recentCalls,
        ]);
    }

    /**
     * Issue a temporary WebRTC key for the current user's mapped SIP
     * extension, so the popup can initialize Zadarma's softphone widget.
     */
    public function webrtcKey(): JsonResponse
    {
        $settings = $this->zadarmaSettingRepository->getSettings();

        $extension = $this->zadarmaExtensionMappingRepository->findExtensionByUserId(
            auth()->guard('user')->id()
        );

        if (! $settings->enabled || empty($settings->api_key) || empty($settings->api_secret)) {
            return response()->json([
                'message' => trans('zadarma::app.phone.not-configured'),
            ], 422);
        }

        if (empty($extension)) {
            return response()->json([
                'message' => trans('zadarma::app.phone.no-extension'),
            ], 422);
        }

        try {
            $client = new ZadarmaClient($settings->api_key, $settings->api_secret);

            $key = $client->getWebrtcKey($extension);

            return response()->json([
                'key'   => $key,
                'login' => $extension,
            ]);
        } catch (\Throwable $exception) {
            return response()->json([
                'message' => trans('zadarma::app.phone.key-error', ['error' => $exception->getMessage()]),
            ], 422);
        }
    }

    /**
     * Resolve a phone number (from the softphone's own incoming-call
     * screen-pop polling) to a matching Lead, so the main CRM window can
     * navigate to it. Same matching rule as CallActivityRecorder.
     */
    public function lookupLead(Request $request): JsonResponse
    {
        $request->validate(['number' => 'required|string']);

        $lead = $this->phoneLeadMatcher->findLeadByPhone($request->input('number'));

        if (! $lead) {
            return response()->json(['found' => false]);
        }

        return response()->json([
            'found' => true,
            'lead_id' => $lead->id,
            'url' => route('admin.leads.edit', $lead->id),
        ]);
    }
}
