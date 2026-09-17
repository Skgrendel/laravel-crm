<?php

namespace Addons\WhatsApp\Repositories;

use Webkul\Core\Eloquent\Repository;

class WhatsAppConversationRepository extends Repository
{
    /**
     * Specify Model class name
     *
     * @return mixed
     */
    public function model()
    {
        return 'Addons\WhatsApp\Contracts\WhatsAppConversation';
    }

    /**
     * Find or create the conversation for a WhatsApp chat. `remote_jid` is
     * WhatsApp's own stable id for the chat, so this is the dedup key across
     * retried webhook deliveries — not the phone number, which is only
     * derived from it for lookups elsewhere (e.g. matching an existing Lead).
     */
    public function findOrCreateByRemoteJid(string $remoteJid, string $phoneNumber)
    {
        return $this->model->newQuery()->firstOrCreate(
            ['remote_jid' => $remoteJid],
            ['phone_number' => $phoneNumber]
        );
    }

    public function touchLastMessageAt(int $conversationId, \DateTimeInterface $sentAt): void
    {
        $this->model->newQuery()
            ->whereKey($conversationId)
            ->where(function ($query) use ($sentAt) {
                $query->whereNull('last_message_at')->orWhere('last_message_at', '<', $sentAt);
            })
            ->update(['last_message_at' => $sentAt]);
    }
}
