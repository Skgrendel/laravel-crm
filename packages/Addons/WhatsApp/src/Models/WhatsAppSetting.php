<?php

namespace Addons\WhatsApp\Models;

use Addons\WhatsApp\Contracts\WhatsAppSetting as WhatsAppSettingContract;
use Illuminate\Database\Eloquent\Model;

class WhatsAppSetting extends Model implements WhatsAppSettingContract
{
    /**
     * Table name.
     *
     * @var string
     */
    protected $table = 'whatsapp_settings';

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'enabled',
        'session_id',
        'service_url',
        'api_key',
        'webhook_secret',
        'default_owner_id',
        'assignment_mode',
        'assignment_user_ids',
        'assignment_cursor',
        'last_status',
        'connected_number',
    ];

    /**
     * The attributes that are castable.
     *
     * @var array
     */
    protected $casts = [
        'enabled'    => 'boolean',
        'api_key'    => 'encrypted',
        'webhook_secret' => 'encrypted',
        'assignment_user_ids' => 'array',
        'assignment_cursor' => 'integer',
    ];

    /**
     * The attributes that should be hidden for arrays.
     *
     * @var array
     */
    protected $hidden = [
        'api_key',
        'webhook_secret',
    ];
}
