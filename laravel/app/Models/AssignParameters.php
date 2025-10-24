<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class AssignParameters extends Model
{
    use HasFactory;

    protected $table = 'assign_parameters';

    protected $fillable = [

        'modal_id',

    ];

    public function modal()
    {
        return $this->morphTo();
    }
    public function parameter()
    {
        return  $this->belongsTo(parameter::class);
    }


    public function getValueAttribute($value)
    {
        $a = json_decode($value, true); 
        if ($a == NULL) {
            return $value;
        } else {
            return (json_decode($value, true));
        }
    }
//     public function getValueAttribute($value)
// {
//     // Try to decode JSON strings
//     $decoded = json_decode($value, true);
//     if ($decoded !== null) {
//         return $decoded;
//     }

//     // Try to convert numeric strings to numbers
//     if (is_numeric($value)) {
//         if (strpos($value, '.') !== false) {
//             return floatval($value);
//         } else {
//             return intval($value);
//         }
//     }

//     // Otherwise return the original string
//     return $value;
// }

}
