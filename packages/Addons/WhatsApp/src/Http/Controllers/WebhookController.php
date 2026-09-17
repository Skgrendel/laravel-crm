<?php

namespace Addons\WhatsApp\Http\Controllers;

use Addons\WhatsApp\Repositories\WhatsAppConversationRepository;
use Addons\WhatsApp\Repositories\WhatsAppMessageRepository;
use Addons\WhatsApp\Repositories\WhatsAppSettingRepository;
use Addons\WhatsApp\Services\WhatsAppLeadCreator;
use Addons\WhatsApp\Services\WhatsAppWebhookSignature;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Log;
use Webkul\Admin\Http\Controllers\Controller;

/**
 * Receives events pushed by the baileys-whatsapp-service microservice (a
 * separate repo — see its README for the full payload contract). One Krayin
 * installation only ever hears about its own session (PRODERI or ACOFICUM),
 * since each has its own `webhookUrl`/`webhookSecret` configured on the
 * microservice side.
 */
class WebhookController extends Controller
{
    public function __construct(
        protected WhatsAppSettingRepository $whatsAppSettingRepository,
        protected WhatsAppMessageRepository $whatsAppMessageRepository,
        protected WhatsAppConversationRepository $whatsAppConversationRepository,
        protected WhatsAppLeadCreator $whatsAppLeadCreator,
    ) {}

    public function handle(Request $request): Response
    {
        $settings = $this->whatsAppSettingRepository->getSettings();

        if (! $settings->enabled || empty($settings->webhook_secret)) {
            abort(404);
        }

        if (! WhatsAppWebhookSignature::verify($request->getContent(), (string) $request->header('X-Webhook-Signature'), $settings->webhook_secret)) {
            Log::warning('WhatsApp webhook rejected: signature mismatch.');

            abort(403);
        }

        $payload = $request->json()->all();

        match ($payload['type'] ?? null) {
            'message' => $this->handleMessage($payload),
            'session.connected' => $this->whatsAppSettingRepository->updateConnectionStatus('connected', $payload['number'] ?? null),
            'session.disconnected' => $this->whatsAppSettingRepository->updateConnectionStatus('disconnected'),
            default => Log::info('WhatsApp webhook: unhandled event type', ['type' => $payload['type'] ?? null]),
        };

        return response()->noContent();
    }

    /**
     * Record the message (deduped by `waMessageId`) and resolve/create the
     * Lead for this conversation — the passive lead-capture deliverable.
     * Every message type is recorded for a complete history, but only
     * `received`/`sent_api`/first-contact `echo` messages should ever
     * surface as if a customer wrote in; the CRM UI (2.3) is responsible for
     * rendering `echo` distinctly, this controller just stores the fact.
     */
    protected function handleMessage(array $payload): void
    {
        $waMessageId = $payload['waMessageId'] ?? null;
        $remoteJid = $payload['remoteJid'] ?? null;
        $number = $payload['number'] ?? null;

        if (empty($waMessageId) || empty($remoteJid) || empty($number)) {
            Log::warning('WhatsApp webhook: message event missing required fields.', $payload);

            return;
        }

        if ($this->whatsAppMessageRepository->alreadyProcessed($waMessageId)) {
            return;
        }

        $conversation = $this->whatsAppLeadCreator->resolveConversation($remoteJid, $number);

        $sentAt = isset($payload['timestamp'])
            ? Carbon::createFromTimestamp($payload['timestamp'])
            : now();

        $this->whatsAppMessageRepository->create([
            'conversation_id' => $conversation->id,
            'wa_message_id' => $waMessageId,
            'type' => $payload['messageType'] ?? 'received',
            'body' => $payload['text'] ?? null,
            'sent_at' => $sentAt,
        ]);

        $this->whatsAppConversationRepository->touchLastMessageAt($conversation->id, $sentAt);
    }
}
