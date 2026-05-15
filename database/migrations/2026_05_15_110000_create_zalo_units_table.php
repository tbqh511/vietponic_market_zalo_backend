<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('zalo_units', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('code', 32)->unique();
            $table->string('label', 64);
            $table->enum('system_unit_type', ['g', 'ml', 'piece']);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        DB::table('zalo_units')->insert([
            ['code' => 'bo',   'label' => 'bó',   'system_unit_type' => 'g',     'is_active' => true],
            ['code' => 'kg',   'label' => 'kg',   'system_unit_type' => 'g',     'is_active' => true],
            ['code' => 'g',    'label' => 'g',    'system_unit_type' => 'g',     'is_active' => true],
            ['code' => 'hop',  'label' => 'hộp',  'system_unit_type' => 'g',     'is_active' => true],
            ['code' => 'goi',  'label' => 'gói',  'system_unit_type' => 'g',     'is_active' => true],
            ['code' => 'chai', 'label' => 'chai', 'system_unit_type' => 'ml',    'is_active' => true],
            ['code' => 'lit',  'label' => 'lít',  'system_unit_type' => 'ml',    'is_active' => true],
            ['code' => 'ml',   'label' => 'ml',   'system_unit_type' => 'ml',    'is_active' => true],
            ['code' => 'cai',  'label' => 'cái',  'system_unit_type' => 'piece', 'is_active' => true],
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('zalo_units');
    }
};
