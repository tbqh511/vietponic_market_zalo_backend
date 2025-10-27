<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Banner extends Model
{
    use HasFactory;

    protected $table = 'banners';
    public $timestamps = false;
    protected $fillable = ['image', 'rendered_width', 'rendered_height', 'rendered_aspect', 'intrinsic_width', 'intrinsic_height', 'intrinsic_aspect'];
}
