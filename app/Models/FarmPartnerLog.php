<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class FarmPartnerLog extends Model
{
    protected $fillable = [
        'customer_id',
        'farm_partner_id',
        'action',
        'old_status',
        'new_status',
        'changed_by',
        'change_reason',
    ];

    public function customer()
    {
        return $this->belongsTo(Customer::class);
    }

    public function admin()
    {
        return $this->belongsTo(User::class, 'changed_by');
    }
}
