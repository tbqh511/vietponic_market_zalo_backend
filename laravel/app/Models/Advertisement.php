<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Advertisement extends Model
{
    use HasFactory;
    // public function user()
    // {
    //     return $this->hasOne(User::class, 'id');
    // }
    protected $fillable = ['status'];

    public function customer()
    {
        return $this->belongsTo(Customer::class);
    }
    public function getimageAttribute($image)
    {
        return url('') . config('global.IMG_PATH') . config('global.ARTICLE_IMG_PATH') . $image;
    }
}
