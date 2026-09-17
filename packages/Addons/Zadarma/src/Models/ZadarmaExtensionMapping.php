<?php

namespace Addons\Zadarma\Models;

use Addons\Zadarma\Contracts\ZadarmaExtensionMapping as ZadarmaExtensionMappingContract;
use Illuminate\Database\Eloquent\Model;
use Webkul\User\Models\UserProxy;

class ZadarmaExtensionMapping extends Model implements ZadarmaExtensionMappingContract
{
    /**
     * Table name.
     *
     * @var string
     */
    protected $table = 'zadarma_extension_mappings';

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'extension',
        'user_id',
    ];

    /**
     * Get the user this extension is assigned to.
     */
    public function user()
    {
        return $this->belongsTo(UserProxy::modelClass());
    }
}
