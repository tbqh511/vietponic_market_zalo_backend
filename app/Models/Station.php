<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Station extends Model
{
    use HasFactory;

    protected $table = 'stations';
    public $timestamps = false;
    protected $fillable = [
        'id', 'name', 'image', 'address', 'lat', 'lng',
        'vtp_province_id', 'vtp_district_id', 'vtp_ward_id', 'vtp_address',
    ];
    protected $casts = [
        'lat' => 'float',
        'lng' => 'float',
        'vtp_province_id' => 'integer',
        'vtp_district_id' => 'integer',
        'vtp_ward_id' => 'integer',
    ];
    public $incrementing = false;
    protected $keyType = 'int';

    public function scopeWithVtp($query)
    {
        // VTP v3 đã bỏ cấp quận/huyện — chỉ còn tỉnh + phường/xã.
        // Trạm hợp lệ khi có ít nhất tỉnh; ward optional nhưng nên có để VTP getPrice khớp.
        return $query->whereNotNull('vtp_province_id');
    }
}
