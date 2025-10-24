<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class parameter extends Model
{
    use HasFactory;

    protected $table = 'parameters';

    protected $fillable = [
        'name',
        'category_id',
        'options',
        'order'
    ];


    protected $hidden = ["created_at", "updated_at"];

    public function getTypeValuesAttribute($value)
    {
        $a = json_decode($value, true);
        if ($a == NULL) {
            return $value;
        } else {
            return (json_decode($value, true));
        }
    }
    public function getImageAttribute($image)
    {
        return $image != "" ? url('') . config('global.IMG_PATH') . '/' . config('global.PARAMETER_IMAGE_PATH')  . $image : "";
    }
    public function assigned_parameter()
    {
        return $this->hasOne(AssignParameters::class, 'parameter_id');
    }
}
