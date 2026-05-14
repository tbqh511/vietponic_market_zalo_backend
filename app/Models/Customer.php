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
        return [
            'customer_id'     => $this->id,
            'is_farm_partner' => $this->farmPartner()->where('status', 'active')->exists(),
        ];
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

    public function farmPartner()
    {
        return $this->hasOne(FarmPartner::class);
    }

    public function getProfileAttribute($image)
    {
        return $image != ''
            ? url('') . config('global.IMG_PATH') . config('global.USER_IMG_PATH') . $image
            : url('') . config('global.IMG_PATH') . config('global.USER_IMG_PATH') . '1693209486.1303.png';
    }
}
