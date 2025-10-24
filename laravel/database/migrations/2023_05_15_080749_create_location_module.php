<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        /**
        * Create locations_administrative_regions.
        */
        Schema::create('locations_administrative_regions', function (Blueprint $table) {
            $table->integer('id')->primary();
            $table->string('name', 255);
            $table->string('name_en', 255);
            $table->string('code_name', 255)->nullable();
            $table->string('code_name_en', 255)->nullable();
        });

        /**
        * Create locations_administrative_units.
        */
        Schema::create('locations_administrative_units', function (Blueprint $table) {
            $table->integer('id')->primary();
            $table->string('full_name')->nullable();
            $table->string('full_name_en')->nullable();
            $table->string('short_name')->nullable();
            $table->string('short_name_en')->nullable();
            $table->string('code_name')->nullable();
            $table->string('code_name_en')->nullable();
            $table->timestamps();
        });

        /**
        * Create locations_provinces.
        */
        Schema::create('locations_provinces', function (Blueprint $table) {
            $table->string('code', 20)->primary();
            $table->string('name', 255);
            $table->string('name_en', 255)->nullable();
            $table->string('full_name', 255);
            $table->string('full_name_en', 255)->nullable();
            $table->string('code_name', 255)->nullable();
            $table->integer('administrative_unit_id')->nullable();
            $table->integer('administrative_region_id')->nullable();
            $table->timestamps();

            // Foreign key constraints
            $table->foreign('administrative_region_id')->references('id')->on('locations_administrative_regions');
            $table->foreign('administrative_unit_id')->references('id')->on('locations_administrative_units');

            // Index column
            $table->index('administrative_region_id', 'idx_provinces_region');
            $table->index('administrative_unit_id', 'idx_provinces_unit');
        });

        /**
        * Create locations_districts.
        */
        Schema::create('locations_districts', function (Blueprint $table) {
            $table->string('code', 20)->primary();
            $table->string('name', 255);
            $table->string('name_en', 255)->nullable();
            $table->string('full_name', 255)->nullable();
            $table->string('full_name_en', 255)->nullable();
            $table->string('code_name', 255)->nullable();
            $table->string('province_code', 20);
            $table->integer('administrative_unit_id')->nullable();
            $table->timestamps();

            // Foreign keys
            $table->foreign('administrative_unit_id')
                ->references('id')
                ->on('locations_administrative_units')
                ->onDelete('cascade');
            $table->foreign('province_code')
                ->references('code')
                ->on('locations_provinces')
                ->onDelete('cascade');

            // Indexes
            $table->index('administrative_unit_id');
            $table->index('province_code');
        });

        /**
        * Create locations_wards.
        */
        Schema::create('locations_wards', function (Blueprint $table) {
            $table->string('code', 20)->primary();
            $table->string('name', 255);
            $table->string('name_en', 255)->nullable();
            $table->string('full_name', 255)->nullable();
            $table->string('full_name_en', 255)->nullable();
            $table->string('code_name', 255)->nullable();
            $table->string('district_code', 20)->nullable();
            $table->integer('administrative_unit_id')->nullable();

            // Foreign keys
            $table->foreign('administrative_unit_id')->references('id')->on('locations_administrative_units');
            $table->foreign('district_code')->references('code')->on('locations_districts');

            $table->index('district_code');
            $table->index('administrative_unit_id');
        });

        /**
        * Create locations_streets.
        */
        Schema::create('locations_streets', function (Blueprint $table) {
            $table->string('code', 20)->primary();
            $table->string('street_name');
            $table->string('district_code', 20)->nullable();
            $table->string('ward_code', 20)->nullable();
            $table->timestamps();

            // Correct:
            $table->foreign('district_code')->references('code')->on('locations_districts')->onUpdate('CASCADE')->onDelete('CASCADE');
            $table->foreign('ward_code')->references('code')->on('locations_wards')->onUpdate('CASCADE')->onDelete('CASCADE');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('locations_administrative_regions');
        Schema::dropIfExists('locations_administrative_units');
        Schema::dropIfExists('locations_provinces');
        Schema::dropIfExists('locations_districts');
        Schema::dropIfExists('locations_wards');
        Schema::dropIfExists('locations_streets');
    }
};
