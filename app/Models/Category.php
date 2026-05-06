<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

class Category extends Model
{
    use HasFactory;

    protected $table = 'categories';

    protected $fillable = [
        'category',
        'image',
        'status',
        'sequence',
        'parameter_types',
        'order'
    ];
    protected $hidden = [
        'updated_at'
    ];

    public function parameter()
    {
        return $this->hasMany(parameter::class);
    }
    public function properties()
    {
        return $this->hasMany(Property::class);
    }

    public function getImageAttribute($image)
    {
        return $image != "" ? url('') . config('global.IMG_PATH') . config('global.CATEGORY_IMG_PATH') . $image : '';
    }
}
