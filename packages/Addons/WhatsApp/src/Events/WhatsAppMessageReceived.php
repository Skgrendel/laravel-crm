<?php

namespace Addons\WhatsApp\Events;

use Addons\WhatsApp\Models\WhatsAppMessage;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * Broadcast the instant a WhatsApp message (any of the 3 types) is stored,
 * so the chat panel on an open Lead updates without a page refresh.
 *
 * `ShouldBroadcast` (queued), not `ShouldBroadcastNow` — the live-update is
 * a nice-to-have, not the addon's core function (storing the message /
 * capturing the lead). With the default `QUEUE_CONNECTION=sync` this still
 * runs inline for now, so the call sites (WebhookController, ChatController)
 * additionally wrap `broadcast()` in a try/catch: a Reverb outage should
 * never turn into a 500 on the webhook or a failed message send. If this
 * installation ever moves to a real queue connection + worker, broadcasting
 * failures then just retry via the queue instead of being swallowed.
 */
class WhatsAppMessageReceived implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets;

    public function __construct(
        public WhatsAppMessage $message,
        public int $leadId,
    ) {}

    /**
     * @return array<Channel>
     */
    public function broadcastOn(): array
    {
        return [
            new PrivateChannel('whatsapp.lead.'.$this->leadId),
        ];
    }

    public function broadcastAs(): string
    {
        return 'message.new';
    }

    public function broadcastWith(): array
    {
        return [
            'id' => $this->message->id,
            'type' => $this->message->type,
            'body' => $this->message->body,
            'media_type' => $this->message->media_type,
            'sent_at' => $this->message->sent_at->toIso8601String(),
        ];
    }
}
