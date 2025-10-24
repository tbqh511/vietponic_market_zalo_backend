<?php

namespace App\Models;

use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Tymon\JWTAuth\Contracts\JWTSubject;


class Customer extends Authenticatable implements JWTSubject
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
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
        'api_token'
    ];

    protected $appends = [
        'customertotalpost'
    ];
    /**
     * Get the identifier that will be stored in the subject claim of the JWT.
     *
     * @return mixed
     */
    public function getJWTIdentifier()
    {
        return $this->getKey();
    }
    /**
     * Return a key value array, containing any custom claims to be added to the JWT.
     *
     * @return array
     */
    public function getJWTCustomClaims()
    {
        return [
            'customer_id' => $this->id
        ];
    }
    public function user_purchased_package()
    {
        return  $this->morphMany(UserPurchasedPackage::class, 'modal');
    }

    public function getCustomerTotalPostAttribute()
    {
        //return Property::where('added_by', $this->id)->get()->count();
        return $this->property()->count();
    }
    public function favourite()
    {
        return $this->hasMany(Favourite::class, 'user_id');
    }
    public function property()
    {
        return $this->hasMany(Property::class, 'added_by');
    }
    public function getProfileAttribute($image)
    {
        return $image != '' ? url('') . config('global.IMG_PATH') . config('global.USER_IMG_PATH') . $image : url('') . config('global.IMG_PATH') . config('global.USER_IMG_PATH').'1693209486.1303.png';
    }
    public function usertokens()
    {
        return $this->hasMany(Usertokens::class, 'customer_id');
    }
    //HuyTBQ: get list locationsWards
    public function getAgentWardsAttribute()
    {
        // Lấy danh sách các ward mà đại lý quản lý
        $wardCodes = $this->property()->distinct()->pluck('ward_code');

        // Lấy danh sách các LocationWard tương ứng với các ward_code
        $wards = LocationsWard::whereIn('code', $wardCodes)->get();
        return $wards;
    }
}