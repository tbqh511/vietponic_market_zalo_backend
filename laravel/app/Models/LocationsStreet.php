<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class LocationsStreet extends Model
{
    use HasFactory;

    public function district()
    {
        return $this->belongsTo(LocationsDistrict::class, 'district_code', 'code');
    }

    public function ward()
    {
        return $this->belongsTo(LocationsWard::class, 'ward_code', 'code');
    }
}
