<?php

namespace Addons\WhatsApp\Models;

use Addons\WhatsApp\Contracts\WhatsAppMessage as WhatsAppMessageContract;
use Illuminate\Database\Eloquent\Model;

class WhatsAppMessage extends Model implements WhatsAppMessageContract
{
    /**
     * Table name.
     *
     * @var string
     */
    protected $table = 'whatsapp_messages';

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'conversation_id',
        'wa_message_id',
        'type',
        'body',
        'sent_at',
    ];

    /**
     * The attributes that are castable.
     *
     * @var array
     */
    protected $casts = [
        'sent_at' => 'datetime',
    ];

    /**
     * The conversation this message belongs to.
     */
    public function conversation()
    {
        return $this->belongsTo(WhatsAppConversationProxy::modelClass());
    }
}
