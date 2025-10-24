<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class OutdoorFacilities extends Model
{
    use HasFactory;
    public function getImageAttribute($image)
    {
        return $image != "" ? url('') . config('global.IMG_PATH') . '/' . config('global.FACILITY_IMAGE_PATH')  . $image : "";
    }
    public function assign_facilities()
    {
        return $this->hasMany(AssignedOutdoorFacilities::class, 'facility_id');
    }
}
