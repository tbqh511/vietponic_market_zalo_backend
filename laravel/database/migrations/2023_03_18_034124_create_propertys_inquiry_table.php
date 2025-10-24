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
        Schema::create('propertys_inquiry', function (Blueprint $table) {
            $table->id();
            $table->bigInteger('propertys_id')->unsigned();
            $table->bigInteger('customers_id')->unsigned();
            $table->tinyInteger('status')->default(0)->comment('0 : Pending 1:Accept  2: Complete 3:Cancle');
            $table->foreign('propertys_id')->references('id')->on('propertys')->onDelete('cascade');
            $table->foreign('customers_id')->references('id')->on('customers')->onDelete('cascade');
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
        Schema::disableForeignKeyConstraints();
        Schema::dropIfExists('propertys_inquiry');
        Schema::enableForeignKeyConstraints();
    }
};
