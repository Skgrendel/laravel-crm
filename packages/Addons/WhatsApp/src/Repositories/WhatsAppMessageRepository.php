<?php

namespace Addons\WhatsApp\Repositories;

use Webkul\Core\Eloquent\Repository;

class WhatsAppMessageRepository extends Repository
{
    /**
     * Specify Model class name
     *
     * @return mixed
     */
    public function model()
    {
        return 'Addons\WhatsApp\Contracts\WhatsAppMessage';
    }

    /**
     * Whether this WhatsApp message has already been stored. The
     * microservice can retry a webhook delivery (e.g. if Krayin responded
     * slowly), so this is checked before recording a message.
     */
    public function alreadyProcessed(string $waMessageId): bool
    {
        return $this->model->newQuery()->where('wa_message_id', $waMessageId)->exists();
    }
}
