<?php

namespace App\Models;

use App\Models\Customer;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;

/**
 * OrderPackingLog — nhật ký hoạt động đóng gói (append-only audit trail).
 *
 * Chỉ INSERT, không bao giờ UPDATE/DELETE. Dùng để truy vết sự cố: ai đã làm
 * gì trên đơn nào, lúc nào. actor là Customer (app) hoặc User (admin web).
 *
 * Dùng OrderPackingLog::record() để ghi log thống nhất từ mọi nơi.
 */
class OrderPackingLog extends Model
{
    protected $table = 'order_packing_logs';

    // Chỉ có created_at (append-only) — tắt updated_at.
    public $timestamps = false;

    public const ACTION_ASSIGNED        = 'assigned';
    public const ACTION_REASSIGNED      = 'reassigned';
    public const ACTION_UNASSIGNED      = 'unassigned';
    public const ACTION_CLAIMED         = 'claimed';
    public const ACTION_PACKING_STARTED = 'packing_started';
    public const ACTION_PACKED          = 'packed';
    public const ACTION_ORDER_CONFIRMED = 'order_confirmed';
    public const ACTION_HANDED_OFF      = 'handed_off';
    public const ACTION_STATUS_CHANGED  = 'status_changed';

    protected $fillable = [
        'order_id',
        'farm_id',
        'actor_customer_id',
        'actor_user_id',
        'actor_label',
        'action',
        'from_status',
        'to_status',
        'meta',
        'created_at',
    ];

    protected $casts = [
        'order_id'          => 'integer',
        'farm_id'           => 'integer',
        'actor_customer_id' => 'integer',
        'actor_user_id'     => 'integer',
        'meta'              => 'array',
        'created_at'        => 'datetime',
    ];

    /**
     * Ghi một dòng nhật ký. $actor là Customer (thao tác qua Mini App) hoặc
     * User (admin web) — tự suy ra cột FK + actor_label phù hợp. Truyền null
     * cho hành động hệ thống tự sinh.
     *
     * @param  Customer|User|null  $actor
     */
    public static function record(
        int $orderId,
        ?int $farmId,
        $actor,
        string $action,
        ?string $fromStatus = null,
        ?string $toStatus = null,
        ?array $meta = null
    ): self {
        $actorCustomerId = null;
        $actorUserId     = null;
        $actorLabel      = 'Hệ thống';

        if ($actor instanceof Customer) {
            $actorCustomerId = $actor->id;
            $role            = $actor->isFarmOwner() ? 'chủ farm' : ($actor->isFarmStaff() ? 'nhân viên' : 'khách');
            $actorLabel      = trim(($actor->name ?: 'Khách') . " ({$role})");
        } elseif ($actor instanceof User) {
            $actorUserId = $actor->id;
            $actorLabel  = trim(($actor->name ?: 'Admin') . ' (admin)');
        }

        return self::create([
            'order_id'          => $orderId,
            'farm_id'           => $farmId,
            'actor_customer_id' => $actorCustomerId,
            'actor_user_id'     => $actorUserId,
            'actor_label'       => $actorLabel,
            'action'            => $action,
            'from_status'       => $fromStatus,
            'to_status'         => $toStatus,
            'meta'              => $meta,
            'created_at'        => now(),
        ]);
    }

    public function order()
    {
        return $this->belongsTo(ZaloOrder::class, 'order_id');
    }
}
