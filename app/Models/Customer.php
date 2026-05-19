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
        'affiliate_bank_account',
        'affiliate_bank_holder',
        // Farm Partner Hub — role mặc định 'customer'. Chỉ admin được set.
        'role',
        'farm_partner_status',
        // Tài khoản ngân hàng riêng cho Farm Partner (tách với affiliate_bank_*
        // để 1 customer có thể nhận 2 dòng tiền vào 2 TK khác nhau).
        'farm_bank_name',
        'farm_bank_account',
        'farm_bank_holder',
    ];

    protected $casts = [
        'affiliate_approved_at' => 'datetime',
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
     * Farm mà customer này là owner. Một customer chỉ owner tối đa 1 farm.
     * Chỉ có data khi role='farm_partner' và đã được admin gán owner_customer_id.
     */
    public function farm()
    {
        return $this->hasOne(Farm::class, 'owner_customer_id');
    }

    public function getProfileAttribute($image)
    {
        return $image != ''
            ? url('') . config('global.IMG_PATH') . config('global.USER_IMG_PATH') . $image
            : url('') . config('global.IMG_PATH') . config('global.USER_IMG_PATH') . '1693209486.1303.png';
    }
}
