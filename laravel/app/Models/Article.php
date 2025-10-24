<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\File;
use Intervention\Image\ImageManagerStatic as Image;
use kornrunner\Blurhash\Blurhash;

class Article extends Model
{
    use HasFactory;
    public function getimageAttribute($image)
    {



        // return false;
        return $image != '' ? url('') . config('global.IMG_PATH') . config('global.ARTICLE_IMG_PATH') . $image : '';
    }
}
