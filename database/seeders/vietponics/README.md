# Vietponics seeding (Zalo Mini App)

Seeder dữ liệu demo cho 2 bảng:

- `zalo_categories` (10 danh mục)
- `zalo_products` (125 sản phẩm)

Seeder sẽ xóa dữ liệu cũ trong các bảng liên quan của Zalo Mini App trước khi insert lại:

- `zalo_deliveries`, `zalo_order_items`, `zalo_orders`
- `zalo_products`, `zalo_categories`
- `banners`, `stations`

## Cách chạy

1. Cấu hình DB trong `.env`
2. Chạy migrate (nếu DB chưa có schema):

```bash
php artisan migrate
```

3. Chạy seeding:

```bash
php artisan db:seed --class=Database\\Seeders\\ZaloCategoryProductSeeder
```

Nếu server đang để `APP_ENV=production`, cần bật cờ cho phép seeding (khuyến nghị bật tạm thời, chạy xong tắt lại):

- Cách 1: set trong `.env`:

```bash
ALLOW_VIETPONICS_SEED=1
```

- Cách 2: set inline khi chạy lệnh (nếu shell/hosting cho phép):

```bash
ALLOW_VIETPONICS_SEED=1 php artisan db:seed --class=Database\\Seeders\\ZaloCategoryProductSeeder
```

Hoặc nếu muốn chạy toàn bộ seeding (bao gồm `languages`, `settings`):

```bash
php artisan db:seed
```

## Nguồn dữ liệu seed

Dữ liệu nằm ở:

- `database/seeders/vietponics/vietponics_seed_data.md`

Seeder đọc trực tiếp file này, parse/validate số lượng (10 categories, 125 products) và quan hệ `category_id` trước khi insert.
