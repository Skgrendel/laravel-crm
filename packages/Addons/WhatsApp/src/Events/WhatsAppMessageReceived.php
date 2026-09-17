<?php

namespace Addons\WhatsApp\Events;

use Addons\WhatsApp\Models\WhatsAppMessage;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * Broadcast the instant a WhatsApp message (any of the 3 types) is stored,
 * so the chat panel on an open Lead updates without a page refresh.
 * `ShouldBroadcastNow` (not the queued `ShouldBroadcast`) so this doesn't
 * silently do nothing on an install with no queue worker running — this
 * addon has no other queue dependency, and message volume per lead is low
 * enough that broadcasting synchronously from the webhook request is fine.
 */
class WhatsAppMessageReceived implements ShouldBroadcastNow
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
            'sent_at' => $this->message->sent_at->toIso8601String(),
        ];
    }
}
