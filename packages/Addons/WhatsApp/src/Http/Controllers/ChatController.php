<?php

namespace Addons\WhatsApp\Http\Controllers;

use Addons\WhatsApp\Events\WhatsAppMessageReceived;
use Addons\WhatsApp\Repositories\WhatsAppConversationRepository;
use Addons\WhatsApp\Repositories\WhatsAppMessageRepository;
use Addons\WhatsApp\Repositories\WhatsAppSettingRepository;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Webkul\Admin\Http\Controllers\Controller;
use Webkul\Lead\Repositories\LeadRepository;

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
            'messages' => $conversation
                ? $conversation->messages()->get(['id', 'type', 'body', 'media_type', 'media_path', 'media_name', 'sent_at'])
                : [],
            'contact' => $this->contactFor($conversation),
        ]);
    }

    /**
     * Who the agent is talking to. The phone comes from the conversation
     * rather than the Person: for `@lid` contacts WhatsApp never revealed a
     * real number, and this is the identifier that actually addresses them.
     */
    protected function contactFor($conversation): ?array
    {
        if (! $conversation) {
            return null;
        }

        $lead = $conversation->lead_id ? app(LeadRepository::class)->find($conversation->lead_id) : null;

        $person = $lead?->person;

        return [
            'name'  => $person?->name ?: $conversation->phone_number,
            'phone' => $conversation->phone_number,
            // A LID is an internal id, not a number anyone can dial — saying
            // so beats showing 18 digits that look like a broken phone.
            'is_hidden_number' => str_ends_with((string) $conversation->remote_jid, '@lid'),
            'owner' => $lead?->user?->name,
        ];
    }

    /**
     * Send a message from the CRM. Requires a conversation to already
     * exist for this Lead — there's no "start a new WhatsApp conversation"
     * flow yet (passive capture only creates one when the customer writes
     * in first), consistent with the 2.2 scope.
     */
    public function store(Request $request, int $leadId): JsonResponse
    {
        $request->validate([
            // Either is enough on its own: a bare caption-less file is a
            // normal thing to send, and so is plain text.
            'message'    => 'required_without:attachment|nullable|string',
            'attachment' => 'required_without:message|nullable|file|max:16384',
        ]);

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

        /**
         * The stored JID, not `phone_number`: WhatsApp addresses plenty of
         * contacts by `@lid` (hidden identity), where the digits are an
         * internal id, not a reachable number. Rebuilding
         * `<digits>@s.whatsapp.net` from those sends to nobody — Baileys
         * still returns a message id, so it looks like it worked while
         * nothing is delivered.
         */
        $to = $conversation->remote_jid ?: $conversation->phone_number;

        $attachment = $request->file('attachment');

        try {
            $response = $attachment
                ? $this->sendAttachment($settings, $to, $attachment, $request->input('message'))
                : $this->sendText($settings, $to, $request->input('message'));
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
         * Stored only once WhatsApp accepted it: a file kept for a send that
         * never happened would show in the history as if it had been
         * delivered. Private disk — served solely through `media()`.
         */
        $media = $attachment
            ? [
                'media_type' => $this->mediaTypeFor($attachment->getMimeType()),
                'media_path' => $attachment->store('whatsapp/'.$conversation->id),
                'media_name' => $attachment->getClientOriginalName(),
                'media_mime' => $attachment->getMimeType(),
            ]
            : [];

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
        ] + $media);

        $this->whatsAppConversationRepository->touchLastMessageAt($conversation->id, $sentAt);

        $this->broadcastSafely($message, $leadId);

        return response()->json([
            'message' => $message,
        ]);
    }

    protected function sendText($settings, string $to, string $message)
    {
        return Http::withHeaders(['X-Api-Key' => $settings->api_key])
            ->timeout(15)
            ->post($this->endpoint($settings, 'send'), [
                'to' => $to,
                'message' => $message,
            ]);
    }

    /**
     * The file travels as the raw request body with its metadata in the
     * query string — the microservice takes it that way to avoid a
     * multipart dependency, and it skips the ~33% inflation base64 would
     * add to every upload. Longer timeout than text: this is bytes over the
     * wire, then Baileys uploading them to WhatsApp.
     */
    protected function sendAttachment($settings, string $to, UploadedFile $attachment, ?string $caption)
    {
        $query = array_filter([
            'to'       => $to,
            'fileName' => $attachment->getClientOriginalName(),
            'mimeType' => $attachment->getMimeType(),
            'caption'  => $caption,
        ]);

        return Http::withHeaders([
            'X-Api-Key'    => $settings->api_key,
            'Content-Type' => $attachment->getMimeType() ?: 'application/octet-stream',
        ])
            ->timeout(60)
            ->withBody($attachment->get(), $attachment->getMimeType() ?: 'application/octet-stream')
            ->post($this->endpoint($settings, 'send-media').'?'.http_build_query($query));
    }

    protected function endpoint($settings, string $action): string
    {
        return rtrim($settings->service_url, '/')."/sessions/{$settings->session_id}/{$action}";
    }

    /**
     * WhatsApp renders these differently, and the chat panel labels them —
     * anything that isn't image/video/audio is a document, matching how the
     * microservice decides what to send.
     */
    protected function mediaTypeFor(?string $mime): string
    {
        foreach (['image', 'video', 'audio'] as $kind) {
            if ($mime && str_starts_with($mime, $kind.'/')) {
                return $kind;
            }
        }

        return 'document';
    }

    /**
     * Attachments live on the private disk, so this is the only way to read
     * one back. Authorisation rides on the Lead the message belongs to: the
     * same check the chat panel itself uses, so a rep can't pull a file out
     * of a colleague's conversation by guessing a message id.
     */
    public function media(int $leadId, int $messageId): StreamedResponse
    {
        $conversation = $this->conversationForLead($leadId);

        $message = $conversation
            ? $this->whatsAppMessageRepository->getModel()
                ->newQuery()
                ->where('conversation_id', $conversation->id)
                ->where('id', $messageId)
                ->first()
            : null;

        abort_if(! $message || ! $message->media_path, 404);

        abort_if(! Storage::exists($message->media_path), 404);

        return Storage::download($message->media_path, $message->media_name);
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
