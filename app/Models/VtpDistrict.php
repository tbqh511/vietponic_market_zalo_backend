<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class VtpDistrict extends Model
{
    protected $table = 'vtp_districts';
    public $timestamps = false;
    public $incrementing = false;
    protected $keyType = 'int';

    protected $fillable = ['id', 'province_id', 'code', 'name', 'status', 'synced_at'];

    protected $casts = [
        'status'    => 'integer',
        'synced_at' => 'datetime',
    ];

    public function province()
    {
        return $this->belongsTo(VtpProvince::class, 'province_id');
    }

    public function wards()
    {
        return $this->hasMany(VtpWard::class, 'district_id');
    }
}
