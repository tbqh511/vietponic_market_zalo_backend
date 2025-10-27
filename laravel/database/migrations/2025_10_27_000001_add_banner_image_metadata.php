<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::table('banners', function (Blueprint $table) {
            $table->unsignedInteger('rendered_width')->default(74)->after('image');
            $table->unsignedInteger('rendered_height')->default(40)->after('rendered_width');
            $table->string('rendered_aspect')->nullable()->after('rendered_height');

            $table->unsignedInteger('intrinsic_width')->nullable()->after('rendered_aspect');
            $table->unsignedInteger('intrinsic_height')->nullable()->after('intrinsic_width');
            $table->string('intrinsic_aspect')->nullable()->after('intrinsic_height');
        });
    }

    public function down()
    {
        Schema::table('banners', function (Blueprint $table) {
            $table->dropColumn([
                'rendered_width', 'rendered_height', 'rendered_aspect',
                'intrinsic_width', 'intrinsic_height', 'intrinsic_aspect'
            ]);
        });
    }
};
