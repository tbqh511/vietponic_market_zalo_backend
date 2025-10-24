<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class AssignedOutdoorFacilities extends Model
{
    use HasFactory;
    public function outdoorfacilities()
    {
        return $this->belongsTo(OutdoorFacilities::class, 'facility_id');
    }
}
