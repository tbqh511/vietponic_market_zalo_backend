<?php

namespace Database\Seeders;

use App\Models\Voucher;
use Illuminate\Database\Seeder;

class VoucherSeeder extends Seeder
{
    /**
     * Seed 3 voucher MVP để manual test luồng checkout (ORDER-09..13).
     * Idempotent: updateOrCreate theo `code` nên chạy lại an toàn.
     */
    public function run(): void
    {
        $vouchers = [
            [
                'code'                  => 'GIAM20K',
                'name'                  => 'Giảm 20.000đ',
                'description'           => 'Giảm cố định 20.000đ cho đơn từ 100.000đ',
                'discount_type'         => Voucher::TYPE_FIXED,
                'discount_value'        => 20000,
                'max_discount_amount'   => null,
                'min_order_amount'      => 100000,
                'max_uses'              => null,
                'max_uses_per_customer' => 1,
                'is_active'             => true,
                'is_public'             => true,
            ],
            [
                'code'                  => 'SALE10',
                'name'                  => 'Giảm 10% (tối đa 50.000đ)',
                'description'           => 'Giảm 10% giá trị đơn, tối đa 50.000đ',
                'discount_type'         => Voucher::TYPE_PERCENT,
                'discount_value'        => 10,
                'max_discount_amount'   => 50000,
                'min_order_amount'      => 0,
                'max_uses'              => null,
                'max_uses_per_customer' => 1,
                'is_active'             => true,
                'is_public'             => true,
            ],
            [
                'code'                  => 'FREESHIP',
                'name'                  => 'Miễn phí vận chuyển',
                'description'           => 'Miễn phí vận chuyển cho đơn giao hàng',
                'discount_type'         => Voucher::TYPE_FREE_SHIPPING,
                'discount_value'        => 0,
                'max_discount_amount'   => null,
                'min_order_amount'      => 0,
                'max_uses'              => null,
                'max_uses_per_customer' => 1,
                'is_active'             => true,
                'is_public'             => true,
            ],
        ];

        foreach ($vouchers as $data) {
            Voucher::updateOrCreate(['code' => $data['code']], $data);
        }
    }
}
