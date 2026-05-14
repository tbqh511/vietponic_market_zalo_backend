<?php

use App\Models\Setting;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    public function up(): void
    {
        Setting::firstOrCreate(['type' => 'affiliate_commission_rate'], ['data' => '5']);
        Setting::firstOrCreate(['type' => 'affiliate_auto_approve'], ['data' => '1']);
        Setting::firstOrCreate(['type' => 'affiliate_enabled'], ['data' => '1']);
        Setting::firstOrCreate(['type' => 'affiliate_referral_base_url'], ['data' => 'https://zalo.me/s/']);
    }

    public function down(): void
    {
        Setting::whereIn('type', [
            'affiliate_commission_rate',
            'affiliate_auto_approve',
            'affiliate_enabled',
            'affiliate_referral_base_url',
        ])->delete();
    }
};
