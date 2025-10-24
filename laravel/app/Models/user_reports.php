<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class user_reports extends Model
{
    use HasFactory;
    public function customer()
    {
        return $this->hasOne(Customer::class, 'id', 'customer_id');
    }
    public function property()
    {
        return $this->hasOne(Property::class, 'id', 'property_id');
    }
    public function reason()
    {
        return $this->hasOne(report_reasons::class, 'id', 'reason_id');
    }
}
