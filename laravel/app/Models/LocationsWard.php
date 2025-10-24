<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class LocationsWard extends Model
{
    use HasFactory;

    // Định nghĩa mối quan hệ one-to-many với bảng Property
    public function properties()
    {
        return $this->hasMany('\App\Models\Property', 'ward_code', 'code');
    }

    // Định nghĩa accessor để lấy số lượng bất động sản trong mỗi phường
    public function getPropertiesCountAttribute()
    {
        // Sử dụng phương thức count() để đếm số bất động sản trong mỗi phường
        return $this->properties()->count();
    }

}
