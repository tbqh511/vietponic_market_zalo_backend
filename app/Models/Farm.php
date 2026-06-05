<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * Farm — đối tác cung cấp rau cho Vietponics.
 *
 * Một farm có một "owner" là Customer (đăng nhập Zalo, vào Farm Hub).
 * Quan hệ với sản phẩm là many-to-many qua bảng pivot farm_product —
 * nhiều farm có thể cùng cung cấp 1 SKU, tồn kho thực tế tính theo
 * batch (farm_stock_batches) chứ KHÔNG còn cột stock trên zalo_products.
 *
 * is_active + approved_at: farm phải được admin duyệt mới hiển thị
 * trên frontend / được phân bổ đơn. scopeActive() bao trọn điều kiện này.
 */
class Farm extends Model
{
    use HasFactory;

    protected $table = 'farms';

    protected $fillable = [
        'code',
        'name',
        'owner_customer_id',
        'logo',
        'cover_image',
        'description',
        'address',
        'lat',
        'lng',
        'commission_rate',
        'payment_cycle',
        'is_active',
        'is_packing_hub',
        'approved_at',
        'approved_by',
    ];

    protected $casts = [
        'is_active'         => 'boolean',
        'is_packing_hub'    => 'boolean',
        'approved_at'       => 'datetime',
        'lat'               => 'decimal:7',
        'lng'               => 'decimal:7',
        'commission_rate'   => 'decimal:4',
        'owner_customer_id' => 'integer',
        'approved_by'       => 'integer',
    ];

    /**
     * Chủ farm — Customer có role='farm_partner' và farm_partner_status='approved'.
     * Nullable vì có thể tạo farm trước, gán owner sau (admin workflow).
     */
    public function owner()
    {
        return $this->belongsTo(Customer::class, 'owner_customer_id');
    }

    /**
     * Tất cả thành viên thuộc farm (owner + staff). Dùng để liệt kê toàn bộ
     * người có quyền vào Farm Hub của farm này.
     */
    public function members()
    {
        return $this->hasMany(Customer::class, 'farm_id');
    }

    /**
     * Chỉ staff (nhân viên/người vận hành) — không bao gồm owner. Owner lấy
     * qua quan hệ owner(). Staff không nhận payout, chỉ thao tác Hub.
     */
    public function staff()
    {
        return $this->hasMany(Customer::class, 'farm_id')
            ->where('farm_role', 'staff');
    }

    /**
     * Admin (User) đã duyệt farm này — để audit.
     */
    public function approver()
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    /**
     * Sản phẩm farm cung cấp. cost_price trên pivot là giá vốn mặc định
     * cho cặp farm-product (override được khi nhập batch).
     */
    public function products()
    {
        return $this->belongsToMany(ZaloProduct::class, 'farm_product', 'farm_id', 'product_id')
            ->withPivot('cost_price', 'is_primary')
            ->withTimestamps();
    }

    /**
     * Tất cả batch nhập của farm (kể cả depleted/expired) — dùng cho lịch sử.
     * Để query tồn kho thực, dùng activeBatches() bên dưới.
     */
    public function stockBatches()
    {
        return $this->hasMany(FarmStockBatch::class);
    }

    /**
     * Batch còn hàng & còn hiệu lực (status='active', quantity_remaining > 0).
     */
    public function activeBatches()
    {
        return $this->hasMany(FarmStockBatch::class)
            ->where('status', 'active')
            ->where('quantity_remaining', '>', 0);
    }

    public function payouts()
    {
        return $this->hasMany(FarmPayout::class);
    }

    /**
     * Order items đã được phân bổ từ farm này — nguồn dữ liệu chính cho
     * báo cáo doanh thu & Farm Hub dashboard.
     */
    public function orderItems()
    {
        return $this->hasMany(ZaloOrderItem::class, 'farm_id');
    }

    /**
     * Phiếu đóng gói (order, farm) thuộc farm này — nguồn dữ liệu cho khâu
     * phân công & đóng gói đơn.
     */
    public function assignments()
    {
        return $this->hasMany(OrderFarmAssignment::class, 'farm_id');
    }

    /**
     * Farm "đang hoạt động" = đã được admin duyệt và đang bật.
     * Dùng scope này ở mọi nơi cần lọc farm public (frontend, allocation).
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true)
            ->whereNotNull('approved_at');
    }

    /**
     * Scope farm là "bộ phận đóng gói" (Package Hub) đang hoạt động.
     */
    public function scopePackingHub($query)
    {
        return $query->active()->where('is_packing_hub', true);
    }

    /**
     * Package Hub "chính" — single source of truth khi cần CHỌN 1 hub để gán
     * phiếu đóng gói. Cho phép nhiều hub (is_packing_hub=true), nhưng đơn vị
     * đóng gói cần 1 hub xác định → chọn id nhỏ nhất (deterministic).
     *
     * Trả null nếu chưa có hub nào → khâu đóng gói coi như "tắt" (không tạo
     * phiếu, incoming rỗng cho mọi farm). Mọi nơi sinh phiếu phải dùng helper
     * này để không lệch logic chọn hub.
     */
    public static function primaryPackingHub(): ?self
    {
        return static::query()->packingHub()->orderBy('id')->first();
    }
}
