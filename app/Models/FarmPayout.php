<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * FarmPayout — kỳ thanh toán cho farm theo chu kỳ (weekly/biweekly/monthly).
 *
 * Lifecycle:
 *   draft   — cron tích lũy hàng ngày (farms:snapshot-daily)
 *   pending — kết thúc kỳ, chờ kế toán chuyển khoản
 *   paid    — đã chuyển, ghi paid_at + transaction_ref
 *   cancelled — huỷ (vd: gộp sang kỳ sau, sai số liệu)
 *
 * net_payout = gross_revenue + adjustment (adjustment có thể âm cho phạt/bù).
 */
class FarmPayout extends Model
{
    use HasFactory;

    protected $table = 'farm_payouts';

    protected $fillable = [
        'farm_id',
        'period_start',
        'period_end',
        'total_sold',
        'gross_revenue',
        'adjustment',
        'net_payout',
        'status',
        'paid_at',
        'payment_method',
        'transaction_ref',
        'note',
    ];

    protected $casts = [
        'period_start'  => 'date',
        'period_end'    => 'date',
        'paid_at'       => 'datetime',
        'total_sold'    => 'decimal:2',
        'gross_revenue' => 'decimal:2',
        'adjustment'    => 'decimal:2',
        'net_payout'    => 'decimal:2',
    ];

    public function farm()
    {
        return $this->belongsTo(Farm::class);
    }
}
