<?php

namespace Addons\WhatsApp\Http\Controllers;

use Addons\WhatsApp\Repositories\WhatsAppSettingRepository;
use Addons\WhatsApp\Services\WhatsAppClient;
use Illuminate\Http\JsonResponse;
use Webkul\Admin\Http\Controllers\Controller;

/**
 * Fase 2.5 — live session status, QR, and reconnect, read straight from the
 * baileys-whatsapp-service microservice rather than only the cached
 * `last_status`/`connected_number` the webhook pushes (2.2), so the settings
 * screen reflects reality even if a webhook delivery was missed.
 */
class SessionController extends Controller
{
    public function __construct(
        protected WhatsAppSettingRepository $whatsAppSettingRepository,
    ) {}

    public function status(): JsonResponse
    {
        try {
            return response()->json($this->client()->getStatus());
        } catch (\Throwable $exception) {
            return response()->json(['message' => $exception->getMessage()], 422);
        }
    }

    public function qr(): JsonResponse
    {
        try {
            return response()->json(['qr' => $this->client()->getQr()]);
        } catch (\Throwable $exception) {
            return response()->json(['message' => $exception->getMessage()], 422);
        }
    }

    public function reconnect(): JsonResponse
    {
        try {
            $this->client()->reconnect();

            return response()->json(['message' => trans('whatsapp::app.settings.index.reconnect-success')]);
        } catch (\Throwable $exception) {
            return response()->json(['message' => $exception->getMessage()], 422);
        }
    }

    protected function client(): WhatsAppClient
    {
        $settings = $this->whatsAppSettingRepository->getSettings();

        if (empty($settings->service_url) || empty($settings->session_id) || empty($settings->api_key)) {
            throw new \RuntimeException(trans('whatsapp::app.settings.index.not-configured'));
        }

        return new WhatsAppClient($settings->service_url, $settings->session_id, $settings->api_key);
    }
}
