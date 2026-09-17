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
        'last_inbound_at',
        'last_outbound_at',
        'agent_last_read_at',
        'first_response_seconds',
    ];

    /**
     * The attributes that are castable.
     *
     * @var array
     */
    protected $casts = [
        'last_message_at' => 'datetime',
        'last_inbound_at' => 'datetime',
        'last_outbound_at' => 'datetime',
        'agent_last_read_at' => 'datetime',
        'first_response_seconds' => 'integer',
    ];

    /**
     * The customer wrote last and nobody has answered. This is what the
     * inbox sorts on — a conversation nobody is waiting on is not urgent
     * however recent it is.
     */
    public function isWaiting(): bool
    {
        if (! $this->last_inbound_at) {
            return false;
        }

        return ! $this->last_outbound_at || $this->last_outbound_at < $this->last_inbound_at;
    }

    public function isUnread(): bool
    {
        if (! $this->last_inbound_at) {
            return false;
        }

        return ! $this->agent_last_read_at || $this->agent_last_read_at < $this->last_inbound_at;
    }

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
