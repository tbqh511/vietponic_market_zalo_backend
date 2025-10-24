<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Slider extends Model
{
    use HasFactory;

    protected $fillable = [
        'image',
        'sequence',
        'category_id',
        'propertys_id'
    ];
    protected $hidden = [
        'created_at',
        'updated_at'
    ];
    protected $casts = [
        'sequence' => 'integer',
    ];


    public function category(){
        return $this->hasOne(Category::class,'id','category_id')->select('id','category');
    }

    public function property(){
        return $this->hasOne(Property::class,'id','propertys_id')->select('id','title');
    }
}
