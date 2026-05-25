<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Policy extends Model
{
    use HasFactory;

    protected $table = 'policies';

    protected $fillable = [
        'slug', 'title', 'content', 'sort_order', 'is_active',
    ];

    protected $casts = [
        'is_active'  => 'boolean',
        'sort_order' => 'integer',
    ];

    /**
     * 4 loại chính sách gợi ý cho quản trị viên khi tạo mới.
     * Không ràng buộc cứng — có thể tạo slug tuỳ ý.
     */
    public const SUGGESTED = [
        'dieu-kien-giao-dich-chung' => 'Điều kiện giao dịch chung',
        'chinh-sach-van-chuyen'     => 'Chính sách vận chuyển',
        'chinh-sach-thanh-toan'     => 'Chính sách thanh toán',
        'chinh-sach-bao-mat'        => 'Chính sách bảo mật',
    ];

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }
}
