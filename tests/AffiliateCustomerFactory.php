<?php

namespace Tests;

use App\Models\Customer;

trait AffiliateCustomerFactory
{
    protected function makeCustomer(array $overrides = []): Customer
    {
        static $seq = 0;
        $seq++;
        $defaults = [
            'name' => 'Customer ' . $seq,
            'firebase_id' => '',
            'email' => 'cust' . $seq . '_' . uniqid() . '@test.local',
            'mobile' => '09' . str_pad((string) (10000000 + $seq), 8, '0', STR_PAD_LEFT),
            'address' => '',
            'logintype' => 'mobile',
            'isActive' => 1,
        ];
        return Customer::create(array_merge($defaults, $overrides));
    }

    protected function makeApprovedAffiliate(string $code, array $overrides = []): Customer
    {
        return $this->makeCustomer(array_merge([
            'affiliate_code' => $code,
            'affiliate_status' => 'approved',
            'affiliate_approved_at' => now(),
        ], $overrides));
    }
}
