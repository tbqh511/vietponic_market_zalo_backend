<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ZaloCategory extends Model
{
    use HasFactory;

    protected $table = 'zalo_categories';
    public $timestamps = false;
    protected $fillable = ['id', 'name', 'image'];
    public $incrementing = false;
    protected $keyType = 'int';

    public function getImageAttribute($image)
    {
        return $image != "" ? asset($image) : '';
    }
}
