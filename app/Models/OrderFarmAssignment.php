<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * OrderFarmAssignment — "phiếu đóng gói" cho cặp (order, farm).
 *
 * Xem docblock migration 2026_06_04_000001 để hiểu vì sao đơn vị phân công là
 * (order_id, farm_id) chứ không phải toàn đơn.
 *
 * Status: unassigned → assigned → packing → packed.
 */
class OrderFarmAssignment extends Model
{
    protected $table = 'order_farm_assignments';

    public const STATUS_UNASSIGNED = 'unassigned';
    public const STATUS_ASSIGNED   = 'assigned';
    public const STATUS_PACKING    = 'packing';
    public const STATUS_PACKED     = 'packed';

    protected $fillable = [
        'order_id',
        'farm_id',
        'assigned_customer_id',
        'assigned_by_user_id',
        'assigned_by_customer_id',
        'status',
        'assigned_at',
        'packing_started_at',
        'packed_at',
        'note',
    ];

    protected $casts = [
        'order_id'                => 'integer',
        'farm_id'                 => 'integer',
        'assigned_customer_id'    => 'integer',
        'assigned_by_user_id'     => 'integer',
        'assigned_by_customer_id' => 'integer',
        'assigned_at'             => 'datetime',
        'packing_started_at'      => 'datetime',
        'packed_at'               => 'datetime',
    ];

    public function order()
    {
        return $this->belongsTo(ZaloOrder::class, 'order_id');
    }

    public function farm()
    {
        return $this->belongsTo(Farm::class, 'farm_id');
    }

    public function assignedCustomer()
    {
        return $this->belongsTo(Customer::class, 'assigned_customer_id');
    }
}
