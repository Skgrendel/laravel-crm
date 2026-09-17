<?php

namespace Addons\Zadarma\Models;

use Addons\Zadarma\Contracts\ZadarmaSetting as ZadarmaSettingContract;
use Illuminate\Database\Eloquent\Model;

class ZadarmaSetting extends Model implements ZadarmaSettingContract
{
    /**
     * Table name.
     *
     * @var string
     */
    protected $table = 'zadarma_settings';

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'enabled',
        'api_key',
        'api_secret',
        'webhook_secret',
    ];

    /**
     * The attributes that are castable.
     *
     * @var array
     */
    protected $casts = [
        'enabled'    => 'boolean',
        'api_key'    => 'encrypted',
        'api_secret' => 'encrypted',
    ];

    /**
     * The attributes that should be hidden for arrays.
     *
     * @var array
     */
    protected $hidden = [
        'api_key',
        'api_secret',
        'webhook_secret',
    ];
}
