<?php

namespace Tests;

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Schema;

/**
 * Base TestCase cho tất cả Zalo-related tests.
 *
 * Thay vì chạy toàn bộ migrations (bao gồm migration 'users' có INSERT lỗi với SQLite),
 * class này chỉ tạo đúng các bảng cần thiết cho luồng thanh toán Zalo.
 * Dùng DatabaseTransactions để mỗi test tự rollback – không cần migrate:fresh.
 */
abstract class ZaloTestCase extends TestCase
{
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();
        $this->createZaloSchema();
    }

    /** Tạo schema tối thiểu cho Zalo payment flow tests */
    private function createZaloSchema(): void
    {
        // customers – cần cho JWT auth nếu test cần customer
        if (!Schema::hasTable('customers')) {
            Schema::create('customers', function ($table) {
                $table->bigIncrements('id');
                $table->string('name');
                $table->string('firebase_id')->default('');
                $table->string('email')->default('');
                $table->string('mobile')->nullable()->unique();
                $table->text('profile')->nullable();
                $table->text('address')->nullable();
                $table->text('fcm_id')->nullable();
                $table->string('logintype')->default('zalo');
                $table->tinyInteger('isActive')->default(1);
                $table->text('api_token')->nullable();
                $table->boolean('notification')->default(0);
                $table->boolean('subscription')->default(0);
                $table->timestamps();
            });
        }

        // zalo_categories – cần vì zalo_products có FK tới đây
        if (!Schema::hasTable('zalo_categories')) {
            Schema::create('zalo_categories', function ($table) {
                $table->unsignedBigInteger('id')->primary();
                $table->string('name');
            });
        }

        // zalo_products – cần cho store order (server-side price validation)
        if (!Schema::hasTable('zalo_products')) {
            Schema::create('zalo_products', function ($table) {
                $table->unsignedBigInteger('id')->primary();
                $table->unsignedBigInteger('category_id')->nullable();
                $table->string('name');
                $table->bigInteger('price')->default(0);
                $table->bigInteger('original_price')->nullable();
                $table->string('image')->nullable();
                $table->text('detail')->nullable();
            });
        }

        // zalo_orders – bảng chính
        if (!Schema::hasTable('zalo_orders')) {
            Schema::create('zalo_orders', function ($table) {
                $table->bigIncrements('id'); // auto-increment để insert không cần chỉ định id
                $table->unsignedBigInteger('customer_id')->nullable();
                $table->string('status')->nullable();
                $table->string('payment_status')->nullable();
                $table->dateTime('created_at')->nullable();
                $table->dateTime('received_at')->nullable();
                $table->bigInteger('total')->default(0);
                $table->text('note')->nullable();
                $table->string('payment_method')->nullable();
                $table->string('checkout_sdk_order_id')->nullable();
            });
        }

        // zalo_order_items
        if (!Schema::hasTable('zalo_order_items')) {
            Schema::create('zalo_order_items', function ($table) {
                $table->bigIncrements('id');
                $table->unsignedBigInteger('order_id');
                $table->unsignedBigInteger('product_id')->nullable();
                $table->string('name');
                $table->bigInteger('price')->default(0);
                $table->integer('quantity')->default(1);
                $table->string('image')->nullable();
                $table->text('detail')->nullable();
                $table->foreign('order_id')->references('id')->on('zalo_orders')->onDelete('cascade');
            });
        }

        // zalo_deliveries
        if (!Schema::hasTable('zalo_deliveries')) {
            Schema::create('zalo_deliveries', function ($table) {
                $table->bigIncrements('id');
                $table->unsignedBigInteger('order_id');
                $table->string('type')->nullable();
                $table->string('alias')->nullable();
                $table->text('address')->nullable();
                $table->string('name')->nullable();
                $table->string('phone')->nullable();
                $table->unsignedBigInteger('station_id')->nullable();
                $table->string('station_name')->nullable();
                $table->string('station_image')->nullable();
                $table->decimal('lat', 10, 6)->nullable();
                $table->decimal('lng', 10, 6)->nullable();
                $table->foreign('order_id')->references('id')->on('zalo_orders')->onDelete('cascade');
            });
        }
    }
}
