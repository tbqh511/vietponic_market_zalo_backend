<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('crm_hosts', function (Blueprint $table) {
            $table->increments('id');
            $table->string('name', 255);
            $table->string('gender', 10);
            $table->string('contact', 255);
            $table->unsignedInteger('age')->default(0)->nullable();
            $table->string('company', 255)->nullable();
            $table->string('about', 255)->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('crm_hosts');
    }
};
