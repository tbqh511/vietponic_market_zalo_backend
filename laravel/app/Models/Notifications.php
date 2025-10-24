<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Notifications extends Model
{
    use HasFactory;

    protected $table ='notification';

    protected $fillable = [
        'title',
        'message',
        'image',
        'type',
        'send_type',
        'customers_id',
        'propertys_id',
    ];


    protected $hidden = [
        'updated_at',
        'deleted_at'
    ];
}
