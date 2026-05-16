<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class VtpProvince extends Model
{
    protected $table = 'vtp_provinces';
    public $timestamps = false;
    public $incrementing = false;
    protected $keyType = 'int';

    protected $fillable = ['id', 'code', 'name', 'status', 'synced_at'];

    protected $casts = [
        'status'    => 'integer',
        'synced_at' => 'datetime',
    ];

    public function districts()
    {
        return $this->hasMany(VtpDistrict::class, 'province_id');
    }
}
