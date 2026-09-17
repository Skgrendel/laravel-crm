<?php

namespace Addons\WhatsApp\Models;

use Addons\WhatsApp\Contracts\WhatsAppConversation as WhatsAppConversationContract;
use Illuminate\Database\Eloquent\Model;
use Webkul\Contact\Models\PersonProxy;
use Webkul\Lead\Models\LeadProxy;

class WhatsAppConversation extends Model implements WhatsAppConversationContract
{
    /**
     * Table name.
     *
     * @var string
     */
    protected $table = 'whatsapp_conversations';

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'remote_jid',
        'phone_number',
        'person_id',
        'lead_id',
        'last_message_at',
    ];

    /**
     * The attributes that are castable.
     *
     * @var array
     */
    protected $casts = [
        'last_message_at' => 'datetime',
    ];

    /**
     * The Person matched to this conversation's phone number, if any.
     */
    public function person()
    {
        return $this->belongsTo(PersonProxy::modelClass());
    }

    /**
     * The Lead this conversation feeds — the conversation belongs to the
     * Lead (whoever owns the Lead sees the full history), not to a fixed
     * agent, so reassigning the Lead's `user_id` is how a conversation
     * changes hands (see docs/whatsapp-addon.md).
     */
    public function lead()
    {
        return $this->belongsTo(LeadProxy::modelClass());
    }

    /**
     * All messages exchanged in this conversation, oldest first.
     */
    public function messages()
    {
        // `id` breaks ties: several messages can share a `sent_at` second
        // (WhatsApp only reports whole seconds), and without it their order
        // inside that second is whatever the database happens to return.
        return $this->hasMany(WhatsAppMessageProxy::modelClass(), 'conversation_id')
            ->orderBy('sent_at')
            ->orderBy('id');
    }
}
