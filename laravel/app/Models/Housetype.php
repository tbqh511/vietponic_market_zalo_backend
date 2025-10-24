<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Housetype extends Model
{
    use HasFactory;

    protected $table ='house_types';

    protected $fillable = [
        'type'
    ];
    protected $hidden = [
        'updated_at',
        'deleted_at'
    ];
}
