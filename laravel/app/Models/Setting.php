<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Setting extends Model
{
    use HasFactory;

    public $table = "settings";

    protected $fillable = [
        'type',
        'data'
    ];
    protected $hidden = [
        'updated_at',
        'deleted_at'
    ];
}
