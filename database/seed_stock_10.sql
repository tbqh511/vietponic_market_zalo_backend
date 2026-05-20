-- ============================================================================
-- Seed tồn kho = 10 cho TẤT CẢ sản phẩm trong zalo_products
-- ----------------------------------------------------------------------------
-- Cơ chế tồn kho hiện tại (sau migration 2026_05_18_000001_drop_legacy_farm_and_stock_tables):
--   stock_available của 1 product = SUM(quantity_remaining)
--   trên các farm_stock_batches WHERE status = 'active' AND quantity_remaining > 0
--
-- Cột stock cũ trên zalo_products ĐÃ BỊ DROP — không thể UPDATE trực tiếp.
-- Phải tạo batch trong farm_stock_batches.
--
-- Script này:
--   1. Đảm bảo có 1 farm "SEED" để gắn batch (idempotent: INSERT IGNORE theo code).
--   2. Với mỗi product CHƯA có batch active còn remaining, tạo 1 batch quantity_in=10.
--   3. Đồng bộ pivot farm_product (cost_price = 70% giá bán làm placeholder).
--
-- An toàn:
--   - KHÔNG đụng tới sản phẩm đã có tồn (không nhân đôi).
--   - Chạy lại nhiều lần ⇒ không tạo batch trùng.
--   - quantity_remaining là STORED generated column trên MySQL/MariaDB — KHÔNG
--     insert trực tiếp, DB tự tính = quantity_in - quantity_sold.
--
-- Cách chạy (production MySQL):
--   mysql -u <user> -p <db_name> < database/seed_stock_10.sql
-- ============================================================================

START TRANSACTION;

-- ── 1. Tạo farm "SEED" nếu chưa có ──────────────────────────────────────────
INSERT INTO farms (code, name, description, address, commission_rate, payment_cycle, is_active, approved_at, created_at, updated_at)
SELECT 'SEED', 'Seed Farm (dev/demo stock)', 'Farm dùng để seed tồn kho mặc định — không phải đối tác thật.',
       'N/A', 1.0000, 'monthly', 1, NOW(), NOW(), NOW()
WHERE NOT EXISTS (SELECT 1 FROM farms WHERE code = 'SEED');

SET @seed_farm_id := (SELECT id FROM farms WHERE code = 'SEED' LIMIT 1);

-- ── 2. Gắn product vào pivot farm_product (cost_price tạm = 70% giá bán) ────
INSERT INTO farm_product (farm_id, product_id, cost_price, is_primary, created_at, updated_at)
SELECT @seed_farm_id, p.id, ROUND(p.price * 0.7, 2), 0, NOW(), NOW()
FROM zalo_products p
WHERE NOT EXISTS (
    SELECT 1 FROM farm_product fp
    WHERE fp.farm_id = @seed_farm_id AND fp.product_id = p.id
);

-- ── 3. Tạo batch active quantity_in = 10 cho product chưa có tồn ────────────
--    Chỉ tạo cho product hiện KHÔNG có batch active còn remaining (tránh nhân đôi).
INSERT INTO farm_stock_batches
    (farm_id, product_id, batch_date, quantity_in, quantity_sold, cost_price, expire_date, status, note, created_at, updated_at)
SELECT
    @seed_farm_id,
    p.id,
    CURDATE(),
    10,
    0,
    ROUND(p.price * 0.7, 2),
    NULL,
    'active',
    'Seed mặc định = 10 (chạy từ database/seed_stock_10.sql)',
    NOW(),
    NOW()
FROM zalo_products p
WHERE NOT EXISTS (
    SELECT 1 FROM farm_stock_batches b
    WHERE b.product_id = p.id
      AND b.status = 'active'
      AND b.quantity_remaining > 0
);

COMMIT;

-- ── Kiểm tra kết quả (chạy riêng để xem) ────────────────────────────────────
-- SELECT p.id, p.name,
--        COALESCE(SUM(CASE WHEN b.status='active' THEN b.quantity_remaining END), 0) AS stock_available
-- FROM zalo_products p
-- LEFT JOIN farm_stock_batches b ON b.product_id = p.id
-- GROUP BY p.id, p.name
-- ORDER BY p.id;
