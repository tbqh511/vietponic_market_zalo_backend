<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('policies', function (Blueprint $table) {
            $table->id();
            // slug ổn định để mini-app fetch theo khoá (vd: dieu-kien-giao-dich-chung)
            $table->string('slug', 120)->unique();
            $table->string('title');
            // nội dung HTML soạn bằng TinyMCE
            $table->longText('content')->nullable();
            // sắp xếp hiển thị trong danh sách công khai
            $table->unsignedInteger('sort_order')->default(0);
            // is_active=false → ẩn khỏi mini-app nhưng vẫn lưu để chỉnh sửa
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(['is_active', 'sort_order']);
        });
    }

    public function down()
    {
        Schema::dropIfExists('policies');
    }
};
