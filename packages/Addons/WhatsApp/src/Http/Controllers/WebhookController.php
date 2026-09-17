<?php

namespace Addons\WhatsApp\Http\Controllers;

use Addons\WhatsApp\Repositories\WhatsAppConversationRepository;
use Addons\WhatsApp\Repositories\WhatsAppMessageRepository;
use Addons\WhatsApp\Repositories\WhatsAppSettingRepository;
use Addons\WhatsApp\Events\WhatsAppMessageReceived;
use Addons\WhatsApp\Mail\SessionDisconnected;
use Addons\WhatsApp\Services\WhatsAppLeadCreator;
use Addons\WhatsApp\Services\WhatsAppWebhookSignature;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Webkul\Admin\Http\Controllers\Controller;
use Webkul\User\Repositories\UserRepository;

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
            'session.disconnected' => $this->handleDisconnection(),
            default => Log::info('WhatsApp webhook: unhandled event type', ['type' => $payload['type'] ?? null]),
        };

        return response()->noContent();
    }

    /**
     * A dropped session is the worst failure this addon has: messages stop
     * arriving, leads stop being captured, and nothing in the CRM looks
     * broken — the chat panel just sits there quietly. So it is logged at
     * error level and mailed to whoever owns lead capture.
     *
     * Only on an actual change of state: the microservice re-announces
     * `disconnected` on every reconnect attempt, and an alert that fires in
     * a loop is an alert people learn to ignore.
     */
    protected function handleDisconnection(): void
    {
        $settings = $this->whatsAppSettingRepository->getSettings();

        $number = $settings->connected_number;

        if (! $this->whatsAppSettingRepository->updateConnectionStatus('disconnected')) {
            return;
        }

        Log::error('WhatsApp session disconnected.', ['number' => $number]);

        $owner = $settings->default_owner_id
            ? app(UserRepository::class)->find($settings->default_owner_id)
            : null;

        if (! $owner?->email) {
            return;
        }

        /**
         * Never let a mail problem turn the webhook into a 500: the
         * microservice would retry it, and the disconnection is already
         * recorded and logged by this point.
         */
        try {
            Mail::to($owner->email)->send(new SessionDisconnected($number));
        } catch (\Throwable $exception) {
            Log::warning('Could not send the WhatsApp disconnection alert.', [
                'error' => $exception->getMessage(),
            ]);
        }
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

        /**
         * The app timezone has to be explicit: `createFromTimestamp()` alone
         * returns a UTC instance, and since the column carries no timezone,
         * Eloquent later reads that wall clock back *as* app-timezone time —
         * shifting every received message by the app's UTC offset (5h30m
         * here, Krayin defaults `app.timezone` to Asia/Kolkata). Messages
         * sent from the CRM used `now()` and were correct, so inbound and
         * outbound drifted apart in the same thread.
         */
        $sentAt = isset($payload['timestamp'])
            ? Carbon::createFromTimestamp($payload['timestamp'], config('app.timezone'))
            : now();

        $message = $this->whatsAppMessageRepository->create([
            'conversation_id' => $conversation->id,
            'wa_message_id' => $waMessageId,
            'type' => $payload['messageType'] ?? 'received',
            'body' => $payload['text'] ?? null,
            'media_type' => $payload['mediaType'] ?? null,
            'sent_at' => $sentAt,
        ]);

        $this->whatsAppConversationRepository->touchLastMessageAt($conversation->id, $sentAt);

        $this->whatsAppConversationRepository->recordMessage(
            $conversation->id,
            $payload['messageType'] ?? 'received',
            $sentAt
        );

        if ($conversation->lead_id) {
            $this->broadcastSafely($message, $conversation->lead_id);
        }
    }

    /**
     * Live-updating the chat panel is a nice-to-have — a broadcasting
     * failure (e.g. Reverb unreachable) must never turn into a 500 on this
     * webhook, which would otherwise take down lead capture along with it.
     */
    protected function broadcastSafely($message, int $leadId): void
    {
        try {
            broadcast(new WhatsAppMessageReceived($message, $leadId));
        } catch (\Throwable $exception) {
            Log::warning('WhatsApp message broadcast failed; message was still stored.', [
                'message_id' => $message->id,
                'lead_id'    => $leadId,
                'error'      => $exception->getMessage(),
            ]);
        }
    }
}
