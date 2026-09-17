<?php

namespace Addons\Zadarma\Models;

use Addons\Zadarma\Contracts\ZadarmaCallLog as ZadarmaCallLogContract;
use Illuminate\Database\Eloquent\Model;
use Webkul\Activity\Models\ActivityProxy;
use Webkul\Lead\Models\LeadProxy;

class ZadarmaCallLog extends Model implements ZadarmaCallLogContract
{
    /**
     * Table name.
     *
     * @var string
     */
    protected $table = 'zadarma_call_logs';

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'pbx_call_id',
        'direction',
        'caller_id',
        'called_number',
        'internal_extension',
        'duration',
        'disposition',
        'is_recorded',
        'call_id_with_rec',
        'lead_id',
        'activity_id',
    ];

    /**
     * The attributes that are castable.
     *
     * @var array
     */
    protected $casts = [
        'is_recorded' => 'boolean',
        'duration'    => 'integer',
    ];

    /**
     * Get the lead this call was matched to, if any.
     */
    public function lead()
    {
        return $this->belongsTo(LeadProxy::modelClass());
    }

    /**
     * Get the activity created for this call, if any.
     */
    public function activity()
    {
        return $this->belongsTo(ActivityProxy::modelClass());
    }
}
