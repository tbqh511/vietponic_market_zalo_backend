<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Tymon\JWTAuth\Contracts\JWTSubject;


class Customer extends Authenticatable implements JWTSubject
{
    use HasFactory, Notifiable;

    protected $fillable = [
        'name',
        'email',
        'firebase_id',
        'mobile',
        'profile',
        'address',
        'fcm_id',
        'logintype',
        'isActive',
        'affiliate_code',
        'referred_by_customer_id',
        'affiliate_status',
        'affiliate_approved_at',
        'affiliate_bank_name',
        'affiliate_bank_bin',
        'affiliate_bank_account',
        'affiliate_bank_holder',
        // Farm Partner Hub — role mặc định 'customer'. Chỉ admin được set.
        'role',
        'farm_partner_status',
        // Farm mà customer này thuộc về (owner HOẶC staff). NULL = không thuộc farm.
        // Mọi điểm gác Hub (middleware, controller) đọc farm_id để xác định scope.
        // Owner thì giá trị này trùng farms.owner_customer_id ngược lại.
        'farm_id',
        'farm_role',
        // Tài khoản ngân hàng riêng cho Farm Partner (tách với affiliate_bank_*
        // để 1 customer có thể nhận 2 dòng tiền vào 2 TK khác nhau).
        'farm_bank_name',
        'farm_bank_account',
        'farm_bank_holder',
        // Dữ liệu đăng ký Farm Partner do customer tự nhập (lưu để admin xem khi duyệt).
        'farm_application_name',
        'farm_application_address',
        'farm_application_description',
        'farm_applied_at',
        // Zalo OA follow state — kênh OA Message chỉ gửi được khi khách đã follow OA.
        // firebase_id = Zalo user_id là khoá map từ OA follow webhook.
        'oa_followed',
        'oa_followed_at',
        'default_shipping_address',
    ];

    protected $casts = [
        'affiliate_approved_at'    => 'datetime',
        'farm_id'                  => 'integer',
        'referred_by_customer_id'  => 'integer',
        'oa_followed'              => 'boolean',
        'oa_followed_at'           => 'datetime',
        'farm_applied_at'          => 'datetime',
        'default_shipping_address' => 'array',
    ];

    protected $hidden = [
        'api_token',
    ];

    public function getJWTIdentifier()
    {
        return $this->getKey();
    }

    public function getJWTCustomClaims()
    {
        // is_farm_partner = customer đang là farm partner đã được duyệt.
        // Middleware zalo.farm cũng phải verify lại bằng DB (không tin claim suông)
        // vì JWT có TTL ~30 phút, admin có thể suspend trong khoảng đó.
        return [
            'customer_id'     => $this->id,
            'is_farm_partner' => $this->isFarmPartner(),
        ];
    }

    /**
     * True nếu customer này có quyền truy cập Farm Hub.
     * Điều kiện: role='farm_partner' AND farm_partner_status='approved'.
     * 'requested' / 'suspended' / 'none' đều KHÔNG được vào hub.
     */
    public function isFarmPartner(): bool
    {
        return $this->role === 'farm_partner'
            && $this->farm_partner_status === 'approved';
    }

    public function orders()
    {
        return $this->hasMany(ZaloOrder::class, 'customer_id');
    }

    public function property()
    {
        return $this->hasMany(Property::class, 'added_by');
    }

    public function referrer()
    {
        return $this->belongsTo(Customer::class, 'referred_by_customer_id');
    }

    public function referrals()
    {
        return $this->hasMany(Customer::class, 'referred_by_customer_id');
    }

    public function commissionsEarned()
    {
        return $this->hasMany(AffiliateCommission::class, 'referrer_customer_id');
    }

    public function payouts()
    {
        return $this->hasMany(AffiliatePayout::class, 'referrer_customer_id');
    }

    /**
     * Farm mà customer này thuộc về (owner HOẶC staff). 1 customer chỉ thuộc
     * tối đa 1 farm. Để phân biệt vai trò, dùng farm_role hoặc helper
     * isFarmOwner() / isFarmStaff().
     *
     * Lưu ý migration: trước task Farm Staff, farm() là hasOne qua
     * owner_customer_id (chỉ owner mới có $customer->farm). Sau migration
     * 2026_05_19_100000, cả staff cũng có $customer->farm — code nào trước
     * đây dựa vào farm() để check "là owner" cần đổi qua isFarmOwner().
     */
    public function farm()
    {
        return $this->belongsTo(Farm::class, 'farm_id');
    }

    /**
     * True nếu customer là chủ farm (nhận payout). Một farm có thể có nhiều owner
     * nhưng chỉ 1 TK ngân hàng (canonical: farms.owner_customer_id).
     */
    public function isFarmOwner(): bool
    {
        return $this->farm_role === 'owner';
    }

    /** Quản lý vận hành — quyền như owner trừ xem payout tài chính. */
    public function isFarmAdmin(): bool
    {
        return $this->farm_role === 'admin';
    }

    /** Nhân viên đóng gói — claim / start-packing / confirm-packed. */
    public function isFarmPacker(): bool
    {
        return $this->farm_role === 'packer';
    }

    /** Nhân viên giao hàng nội bộ — pickup / deliver. */
    public function isFarmShipper(): bool
    {
        return $this->farm_role === 'shipper';
    }

    /**
     * Có thể thực hiện thao tác vận hành: confirm order, assign packer, handoff.
     * = owner HOẶC admin.
     */
    public function canManageFarm(): bool
    {
        return $this->isFarmOwner() || $this->isFarmAdmin();
    }

    /**
     * Có thể làm packing (claim, start, confirm-packed).
     * = owner HOẶC admin HOẶC packer.
     */
    public function canPack(): bool
    {
        return $this->canManageFarm() || $this->isFarmPacker();
    }

    /**
     * True nếu customer là thành viên (không phải chủ) của farm.
     * Covers admin, packer, shipper — dùng cho log labels và guard tổng quát.
     */
    public function isFarmStaff(): bool
    {
        return in_array($this->farm_role, ['admin', 'packer', 'shipper']);
    }

    public function getProfileAttribute($image)
    {
        if ($image == '') return null;
        // Zalo avatar URLs (https://…) được lưu raw — trả nguyên, không prepend path local.
        if (str_starts_with($image, 'http://') || str_starts_with($image, 'https://')) {
            return $image;
        }
        return url('') . config('global.IMG_PATH') . config('global.USER_IMG_PATH') . $image;
    }
}
