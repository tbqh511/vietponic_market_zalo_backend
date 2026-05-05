<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class AssignParameters extends Model
{
    use HasFactory;

    protected $table = 'assign_parameters';

    protected $fillable = ['modal_id'];

    public function modal()
    {
        return $this->morphTo();
    }

    public function parameter()
    {
        return $this->belongsTo(parameter::class);
    }

    public function getValueAttribute($value)
    {
        $a = json_decode($value, true);
        return $a === null ? $value : $a;
    }
}
