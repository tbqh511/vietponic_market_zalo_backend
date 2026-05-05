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
            'customer_id' => $this->id,
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

    public function getProfileAttribute($image)
    {
        return $image != ''
            ? url('') . config('global.IMG_PATH') . config('global.USER_IMG_PATH') . $image
            : url('') . config('global.IMG_PATH') . config('global.USER_IMG_PATH') . '1693209486.1303.png';
    }
}
