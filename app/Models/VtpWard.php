<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class VtpWard extends Model
{
    protected $table = 'vtp_wards';
    public $timestamps = false;
    public $incrementing = false;
    protected $keyType = 'int';

    protected $fillable = ['id', 'district_id', 'name', 'status', 'synced_at'];

    protected $casts = [
        'status'    => 'integer',
        'synced_at' => 'datetime',
    ];

    public function district()
    {
        return $this->belongsTo(VtpDistrict::class, 'district_id');
    }
}
