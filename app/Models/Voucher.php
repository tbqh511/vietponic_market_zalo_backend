<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Voucher extends Model
{
    use HasFactory;

    protected $table = 'vouchers';

    public const TYPE_PERCENT = 'percent';
    public const TYPE_FIXED = 'fixed';
    public const TYPE_FREE_SHIPPING = 'free_shipping';

    protected $fillable = [
        'code',
        'name',
        'description',
        'discount_type',
        'discount_value',
        'max_discount_amount',
        'min_order_amount',
        'max_uses',
        'max_uses_per_customer',
        'used_count',
        'valid_from',
        'valid_to',
        'is_active',
        'is_public',
    ];

    protected $casts = [
        'discount_value'        => 'decimal:2',
        'valid_from'            => 'datetime',
        'valid_to'              => 'datetime',
        'is_active'             => 'boolean',
        'is_public'             => 'boolean',
    ];

    public function redemptions()
    {
        return $this->hasMany(VoucherRedemption::class, 'voucher_id');
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopePublic($query)
    {
        return $query->where('is_public', true);
    }

    public function scopeWithinDate($query, ?Carbon $at = null)
    {
        $at ??= Carbon::now();
        return $query
            ->where(function ($q) use ($at) {
                $q->whereNull('valid_from')->orWhere('valid_from', '<=', $at);
            })
            ->where(function ($q) use ($at) {
                $q->whereNull('valid_to')->orWhere('valid_to', '>=', $at);
            });
    }

    /**
     * Kiểm tra voucher có dùng được không. Trả true hoặc message lỗi VN.
     * Pattern theo StockService::checkAvailability().
     */
    public function isUsable(int $customerId, int $subtotal): true|string
    {
        if (!$this->is_active) {
            return 'Mã giảm giá đã bị tạm dừng';
        }
        $now = Carbon::now();
        if ($this->valid_from && $now->lt($this->valid_from)) {
            return 'Mã giảm giá chưa đến thời gian áp dụng';
        }
        if ($this->valid_to && $now->gt($this->valid_to)) {
            return 'Mã giảm giá đã hết hạn';
        }
        if (!is_null($this->max_uses) && $this->used_count >= $this->max_uses) {
            return 'Mã giảm giá đã hết lượt sử dụng';
        }
        if ($subtotal < (int) $this->min_order_amount) {
            return 'Đơn tối thiểu ' . number_format($this->min_order_amount, 0, ',', '.') . 'đ';
        }
        if ($this->max_uses_per_customer > 0) {
            $usedByCustomer = VoucherRedemption::query()
                ->where('voucher_id', $this->id)
                ->where('customer_id', $customerId)
                ->count();
            if ($usedByCustomer >= $this->max_uses_per_customer) {
                return 'Bạn đã sử dụng mã này';
            }
        }
        return true;
    }
}
