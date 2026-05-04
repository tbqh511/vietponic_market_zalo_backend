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
        if (!$image) return '';
        // If already a full URL (e.g. from a previous double-save bug), return as-is
        if (str_starts_with($image, 'http://') || str_starts_with($image, 'https://')) {
            return $image;
        }
        return asset($image);
    }
}
