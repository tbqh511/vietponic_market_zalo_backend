<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class VtpTrackingEvent extends Model
{
    protected $table = 'zalo_vtp_tracking_events';
    public $timestamps = false;

    protected $fillable = [
        'order_id', 'vtp_order_number', 'status_code', 'status_name',
        'location', 'note', 'employee_name', 'employee_phone',
        'reason_code', 'is_returning', 'status_at', 'raw_payload', 'created_at',
    ];

    protected $casts = [
        'status_at'    => 'datetime',
        'created_at'   => 'datetime',
        'is_returning' => 'boolean',
        'raw_payload'  => 'array',
    ];

    public function order()
    {
        return $this->belongsTo(ZaloOrder::class, 'order_id');
    }
}
