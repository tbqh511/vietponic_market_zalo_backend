<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $tables = [
            'assigned_outdoor_facilities',
            'outdoor_facilities',
            'property_inquiries',
            'propertys_inquiry',
            'notifications',
            'favourites',
            'articles',
            'packages',
            'user_purchased_packages',
            'advertisements',
            'interested_users',
            'payments',
            'chats',
            'report_reasons',
            'user_reports',
            'crm_hosts',
            'user_tokens',
            'property_legal_images',
        ];

        foreach ($tables as $table) {
            Schema::dropIfExists($table);
        }

        // Remove host_id column from propertys if it exists
        if (Schema::hasColumn('propertys', 'host_id')) {
            Schema::table('propertys', function (Blueprint $table) {
                $table->dropColumn('host_id');
            });
        }
    }

    public function down(): void
    {
        // Intentionally left empty — this is a one-way cleanup migration
    }
};
