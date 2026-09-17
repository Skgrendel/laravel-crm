<?php

namespace Addons\WhatsApp\Http\Controllers;

use Addons\WhatsApp\Events\WhatsAppMessageReceived;
use Addons\WhatsApp\Repositories\WhatsAppConversationRepository;
use Addons\WhatsApp\Repositories\WhatsAppMessageRepository;
use Addons\WhatsApp\Repositories\WhatsAppSettingRepository;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Webkul\Admin\Http\Controllers\Controller;

/**
 * Fase 2.3 — the chat panel on the Lead view reads/writes through here.
 * History is whatever the webhook (2.2) already stored; sending is the one
 * new write path, proxied through the baileys-whatsapp-service microservice
 * (this installation's own session — see WhatsAppSettingRepository).
 */
class ChatController extends Controller
{
    public function __construct(
        protected WhatsAppConversationRepository $whatsAppConversationRepository,
        protected WhatsAppMessageRepository $whatsAppMessageRepository,
        protected WhatsAppSettingRepository $whatsAppSettingRepository,
    ) {}

    /**
     * Full message history for the Lead's WhatsApp conversation, oldest
     * first. Empty list (not a 404) when the Lead has no conversation yet —
     * the panel just renders empty until a first message arrives.
     */
    public function index(int $leadId): JsonResponse
    {
        $conversation = $this->conversationForLead($leadId);

        return response()->json([
            'messages' => $conversation ? $conversation->messages()->get(['id', 'type', 'body', 'media_type', 'sent_at']) : [],
        ]);
    }

    /**
     * Send a message from the CRM. Requires a conversation to already
     * exist for this Lead — there's no "start a new WhatsApp conversation"
     * flow yet (passive capture only creates one when the customer writes
     * in first), consistent with the 2.2 scope.
     */
    public function store(Request $request, int $leadId): JsonResponse
    {
        $request->validate(['message' => 'required|string']);

        $conversation = $this->conversationForLead($leadId);

        if (! $conversation) {
            return response()->json([
                'message' => trans('whatsapp::app.chat.no-conversation'),
            ], 422);
        }

        $settings = $this->whatsAppSettingRepository->getSettings();

        if (! $settings->enabled || empty($settings->service_url) || empty($settings->session_id) || empty($settings->api_key)) {
            return response()->json([
                'message' => trans('whatsapp::app.chat.not-configured'),
            ], 422);
        }

        try {
            $response = Http::withHeaders(['X-Api-Key' => $settings->api_key])
                ->timeout(15)
                ->post(rtrim($settings->service_url, '/')."/sessions/{$settings->session_id}/send", [
                    /**
                     * The stored JID, not `phone_number`: WhatsApp addresses
                     * plenty of contacts by `@lid` (hidden identity), where
                     * the digits are an internal id, not a reachable number.
                     * Rebuilding `<digits>@s.whatsapp.net` from those sends
                     * to nobody — Baileys still returns a message id, so it
                     * looks like it worked while nothing is delivered.
                     */
                    'to' => $conversation->remote_jid ?: $conversation->phone_number,
                    'message' => $request->input('message'),
                ]);
        } catch (\Throwable $exception) {
            return response()->json([
                'message' => trans('whatsapp::app.chat.send-failed', ['error' => $exception->getMessage()]),
            ], 422);
        }

        if (! $response->successful()) {
            return response()->json([
                'message' => trans('whatsapp::app.chat.send-failed', ['error' => $response->json('error') ?? $response->status()]),
            ], 422);
        }

        $waMessageId = $response->json('waMessageId');
        $sentAt = now();

        /**
         * Written optimistically here rather than waiting for the
         * microservice to echo it back through the webhook — that webhook
         * delivery is deduped by `wa_message_id`, so when it does arrive
         * it's a no-op against this row, not a duplicate.
         */
        $message = $this->whatsAppMessageRepository->create([
            'conversation_id' => $conversation->id,
            'wa_message_id' => $waMessageId,
            'type' => 'sent_api',
            'body' => $request->input('message'),
            'sent_at' => $sentAt,
        ]);

        $this->whatsAppConversationRepository->touchLastMessageAt($conversation->id, $sentAt);

        $this->broadcastSafely($message, $leadId);

        return response()->json([
            'message' => $message,
        ]);
    }

    /**
     * Live-updating the chat panel is a nice-to-have — a broadcasting
     * failure (e.g. Reverb unreachable) must never turn into a failed send:
     * the message is already stored and delivered via the microservice by
     * the time this runs.
     */
    protected function broadcastSafely($message, int $leadId): void
    {
        try {
            broadcast(new WhatsAppMessageReceived($message, $leadId));
        } catch (\Throwable $exception) {
            Log::warning('WhatsApp message broadcast failed; message was still sent.', [
                'message_id' => $message->id,
                'lead_id'    => $leadId,
                'error'      => $exception->getMessage(),
            ]);
        }
    }

    protected function conversationForLead(int $leadId)
    {
        return $this->whatsAppConversationRepository
            ->getModel()
            ->newQuery()
            ->where('lead_id', $leadId)
            ->first();
    }
}
