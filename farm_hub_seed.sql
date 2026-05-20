-- ============================================================================
-- FARM HUB DEMO SEED — Dữ liệu test cho FARM-ABCFARM-001 (farm_id = 1)
-- ============================================================================
-- Mục đích: Tạo dữ liệu mẫu trải đều trong 90 ngày qua để test các chức năng
--           Farm Hub dashboard: Analytics (7/30/90 ngày), Orders incoming,
--           Payouts (công nợ), Stock-In (khai báo nhập), Inventory snapshot,
--           Top products, AI hint.
--
-- Áp dụng cho: ABC Farm (id=1, code='FARM-ABCFARM-001', owner_customer_id=11)
--
-- HƯỚNG DẪN CHẠY:
--   1. Mở phpMyAdmin → chọn database vietponics
--   2. Tab SQL → paste toàn bộ file này → Go
--   3. Sau khi chạy, customer id=11 sẽ là farm partner của ABC Farm và có thể
--      đăng nhập Farm Hub ngay lập tức.
--
-- LƯU Ý:
--   - Script idempotent ở mức cao nhất có thể (dùng INSERT IGNORE, DELETE trước
--     khi seed). Chạy lại không phá data cũ KHÔNG thuộc seed này.
--   - Mọi đơn đều status='delivered' có delivered_at hợp lệ (FarmDashboardService
--     chỉ tính đơn delivered).
--   - 5 đơn cuối ở trạng thái delivering/pending để test /farm/orders/incoming.
-- ============================================================================

SET @FARM_ID  := 1;
SET @OWNER_ID := 11;   -- owner_customer_id từ screenshot

-- ----------------------------------------------------------------------------
-- 1) BIẾN CUSTOMER 11 THÀNH OWNER ĐÃ DUYỆT CỦA FARM ID=1
-- ----------------------------------------------------------------------------
-- EnsureFarmPartner middleware kiểm tra: role='farm_partner',
-- farm_partner_status='approved', farm_id=<farm>, isActive=1.
UPDATE customers
SET role                = 'farm_partner',
    farm_partner_status = 'approved',
    farm_id             = @FARM_ID,
    farm_role           = 'owner',
    isActive            = 1
WHERE id = @OWNER_ID;

-- Đảm bảo farm.owner_customer_id sync với customer
UPDATE farms
SET owner_customer_id = @OWNER_ID,
    is_active         = 1,
    approved_at       = COALESCE(approved_at, NOW())
WHERE id = @FARM_ID;

-- ----------------------------------------------------------------------------
-- 2) DỌN DỮ LIỆU SEED CŨ CHO FARM NÀY (tránh trùng khi chạy lại)
-- ----------------------------------------------------------------------------
-- Xoá order_items + orders thuộc các đơn seed cũ (note có prefix '[SEED-FARM1]')
DELETE oi FROM zalo_order_items oi
INNER JOIN zalo_orders o ON o.id = oi.order_id
WHERE o.note LIKE '[SEED-FARM1]%';

DELETE FROM zalo_deliveries WHERE order_id IN (
    SELECT id FROM zalo_orders WHERE note LIKE '[SEED-FARM1]%'
);

DELETE FROM zalo_orders WHERE note LIKE '[SEED-FARM1]%';

-- Xoá batch + pivot + payout cũ của farm
DELETE FROM farm_stock_batches WHERE farm_id = @FARM_ID AND note LIKE '[SEED]%';
DELETE FROM farm_payouts       WHERE farm_id = @FARM_ID AND note LIKE '[SEED]%';
-- KHÔNG xoá farm_product để giữ pivot cũ — sẽ insert IGNORE bên dưới.

-- ----------------------------------------------------------------------------
-- 3) GẮN 6 SẢN PHẨM CHO FARM (pivot farm_product)
-- ----------------------------------------------------------------------------
-- Lấy 6 product đầu tiên của database. cost_price = 60% giá bán.
-- Nếu pivot đã tồn tại → IGNORE.
INSERT IGNORE INTO farm_product (farm_id, product_id, cost_price, is_primary, created_at, updated_at)
SELECT @FARM_ID,
       p.id,
       ROUND(p.price * 0.60, 2),
       CASE WHEN @rn := @rn + 1 = 1 THEN 1 ELSE 0 END,
       NOW(),
       NOW()
FROM (SELECT @rn := 0) r,
     (SELECT id, price FROM zalo_products ORDER BY id ASC LIMIT 6) p;

-- ----------------------------------------------------------------------------
-- 4) TẠO BATCH TỒN KHO (farm_stock_batches)
-- ----------------------------------------------------------------------------
-- Mục tiêu:
--   - Mỗi sản phẩm có 2-3 batch active (test FEFO)
--   - 1 batch sắp hết hạn (≤7 ngày) → trigger expiring_soon cảnh báo
--   - 1 SKU có total_remaining <= 5 → trigger low_stock
--   - Có batch nhập hôm nay (cho productsToday endpoint)
-- ----------------------------------------------------------------------------
-- Batch chung: 3 batch / product cho 5 SKU đầu, SKU thứ 6 chỉ 1 batch nhỏ (low_stock)
INSERT INTO farm_stock_batches
    (farm_id, product_id, batch_date, quantity_in, quantity_sold, cost_price,
     expire_date, status, note, created_at, updated_at)
SELECT @FARM_ID,
       p.id,
       DATE_SUB(CURDATE(), INTERVAL 30 DAY),    -- batch_date
       100.00,                                   -- quantity_in
       45.00,                                    -- quantity_sold (đã bán bớt)
       ROUND(p.price * 0.60, 2),
       DATE_ADD(CURDATE(), INTERVAL 20 DAY),    -- expire_date (xa)
       'active',
       '[SEED] batch cũ 30d',
       NOW(), NOW()
FROM (SELECT id, price FROM zalo_products ORDER BY id ASC LIMIT 5) p;

-- Batch nhập 7 ngày trước — 1 trong số đó sắp hết hạn trong 5 ngày (cảnh báo)
INSERT INTO farm_stock_batches
    (farm_id, product_id, batch_date, quantity_in, quantity_sold, cost_price,
     expire_date, status, note, created_at, updated_at)
SELECT @FARM_ID,
       p.id,
       DATE_SUB(CURDATE(), INTERVAL 7 DAY),
       60.00,
       20.00,
       ROUND(p.price * 0.60, 2),
       CASE
           WHEN @i := @i + 1 IN (1, 2) THEN DATE_ADD(CURDATE(), INTERVAL 5 DAY)
           ELSE DATE_ADD(CURDATE(), INTERVAL 15 DAY)
       END,
       'active',
       '[SEED] batch 7d',
       NOW(), NOW()
FROM (SELECT @i := 0) r,
     (SELECT id, price FROM zalo_products ORDER BY id ASC LIMIT 5) p;

-- Batch nhập HÔM NAY (cho /farm/products/today endpoint hiển thị "stocked today")
INSERT INTO farm_stock_batches
    (farm_id, product_id, batch_date, quantity_in, quantity_sold, cost_price,
     expire_date, status, note, created_at, updated_at)
SELECT @FARM_ID,
       p.id,
       CURDATE(),
       50.00,
       0.00,
       ROUND(p.price * 0.60, 2),
       DATE_ADD(CURDATE(), INTERVAL 10 DAY),
       'active',
       '[SEED] batch hôm nay',
       NOW(), NOW()
FROM (SELECT id, price FROM zalo_products ORDER BY id ASC LIMIT 5) p;

-- SKU thứ 6 — batch nhỏ để test low_stock (total_remaining <= 5)
INSERT INTO farm_stock_batches
    (farm_id, product_id, batch_date, quantity_in, quantity_sold, cost_price,
     expire_date, status, note, created_at, updated_at)
SELECT @FARM_ID,
       p.id,
       DATE_SUB(CURDATE(), INTERVAL 2 DAY),
       10.00,
       6.00,                                     -- còn lại 4 → low_stock
       ROUND(p.price * 0.60, 2),
       DATE_ADD(CURDATE(), INTERVAL 3 DAY),    -- gần hết hạn → expiring_soon
       'active',
       '[SEED] low-stock SKU',
       NOW(), NOW()
FROM (SELECT id, price FROM zalo_products ORDER BY id ASC LIMIT 1 OFFSET 5) p;

-- ----------------------------------------------------------------------------
-- 5) TẠO ORDERS + ORDER_ITEMS TRẢI ĐỀU 90 NGÀY
-- ----------------------------------------------------------------------------
-- Strategy: dùng helper table tally (1..N) để sinh nhiều row trong 1 INSERT.
-- Mỗi ngày trong 90 ngày qua sẽ có 1-3 đơn (random) cho farm này.
-- ----------------------------------------------------------------------------
-- Tạo tally table tạm (90 dòng = 90 ngày) bằng cách join nhiều bảng nhỏ
DROP TEMPORARY TABLE IF EXISTS _tally_days;
CREATE TEMPORARY TABLE _tally_days (n INT PRIMARY KEY);
INSERT INTO _tally_days (n)
SELECT a.N + b.N * 10
FROM (SELECT 0 AS N UNION SELECT 1 UNION SELECT 2 UNION SELECT 3 UNION SELECT 4
      UNION SELECT 5 UNION SELECT 6 UNION SELECT 7 UNION SELECT 8 UNION SELECT 9) a
CROSS JOIN
     (SELECT 0 AS N UNION SELECT 1 UNION SELECT 2 UNION SELECT 3 UNION SELECT 4
      UNION SELECT 5 UNION SELECT 6 UNION SELECT 7 UNION SELECT 8) b
WHERE a.N + b.N * 10 < 90;

-- Helper: tally cho 1..3 đơn / ngày
DROP TEMPORARY TABLE IF EXISTS _tally_orders_per_day;
CREATE TEMPORARY TABLE _tally_orders_per_day (k INT PRIMARY KEY);
INSERT INTO _tally_orders_per_day VALUES (1), (2), (3);

-- ---- 5a. INSERT ZALO_ORDERS (status='delivered') ----
-- 90 ngày × 1-3 đơn/ngày = ~180 đơn
-- delivered_at = created_at + 1 ngày (giả định flow giao trong 1 ngày)
INSERT INTO zalo_orders
    (customer_id, status, payment_status, payment_method,
     created_at, received_at, delivered_at,
     total, subtotal, shipping_fee, note)
SELECT
    @OWNER_ID                                                      AS customer_id,
    'delivered'                                                    AS status,
    'paid'                                                         AS payment_status,
    CASE WHEN (d.n + o.k) % 2 = 0 THEN 'BANK' ELSE 'COD' END       AS payment_method,
    -- created_at: rải đều trong 90 ngày, giờ ngẫu nhiên (8h-20h)
    TIMESTAMP(
        DATE_SUB(CURDATE(), INTERVAL d.n DAY),
        SEC_TO_TIME(((d.n + o.k * 7) % 12 + 8) * 3600 + ((d.n * 13) % 60) * 60)
    )                                                              AS created_at,
    TIMESTAMP(
        DATE_SUB(CURDATE(), INTERVAL d.n DAY),
        SEC_TO_TIME(((d.n + o.k * 7) % 12 + 8) * 3600)
    )                                                              AS received_at,
    -- delivered_at: cùng ngày hoặc +1 ngày (đơn xa hơn → +1, gần đây → cùng ngày)
    TIMESTAMP(
        DATE_SUB(CURDATE(), INTERVAL GREATEST(d.n - 1, 0) DAY),
        SEC_TO_TIME(((d.n + o.k * 7) % 12 + 10) * 3600)
    )                                                              AS delivered_at,
    0                                                              AS total,         -- sẽ update sau khi có items
    0                                                              AS subtotal,
    20000                                                          AS shipping_fee,
    CONCAT('[SEED-FARM1] day-', d.n, ' order-', o.k)               AS note
FROM _tally_days d
CROSS JOIN _tally_orders_per_day o
-- Bỏ bớt đơn ngẫu nhiên (chỉ giữ ~70% combo) để doanh thu lên xuống tự nhiên
WHERE ((d.n * 7 + o.k * 11) % 10) < 7;

-- ---- 5b. INSERT ZALO_DELIVERIES (1 row/order) ----
INSERT INTO zalo_deliveries (order_id, type, address, name, phone)
SELECT o.id,
       'home',
       CONCAT('123 Đường Test, Q.', (o.id % 12) + 1, ', TP.HCM'),
       'Khách hàng test',
       '0900000000'
FROM zalo_orders o
WHERE o.note LIKE '[SEED-FARM1]%';

-- ---- 5c. INSERT ZALO_ORDER_ITEMS (2-4 items / order) ----
-- Mỗi order có 2-4 items thuộc farm này. Sản phẩm chọn từ 6 SKU đã gán.
-- Phải gán farm_id, farm_stock_batch_id (1 batch active bất kỳ của farm),
-- cost_price_snapshot để dashboard tính được revenue + profit.
-- ---------------------------------------------------------------
-- Lấy 1 batch_id active cho mỗi product (để gán cho order_item)
DROP TEMPORARY TABLE IF EXISTS _batch_by_product;
CREATE TEMPORARY TABLE _batch_by_product (
    product_id BIGINT UNSIGNED PRIMARY KEY,
    batch_id   BIGINT UNSIGNED NOT NULL,
    cost_price DECIMAL(12,2) NOT NULL
);
INSERT INTO _batch_by_product (product_id, batch_id, cost_price)
SELECT b.product_id, MIN(b.id), b.cost_price
FROM farm_stock_batches b
WHERE b.farm_id = @FARM_ID AND b.status = 'active'
GROUP BY b.product_id, b.cost_price;

-- 4 item slots / order — chọn product theo modulo của (order_id + slot)
-- để mỗi đơn có mix sản phẩm khác nhau, tổng dataset trải đều 6 SKU.
DROP TEMPORARY TABLE IF EXISTS _tally_slots;
CREATE TEMPORARY TABLE _tally_slots (s INT PRIMARY KEY);
INSERT INTO _tally_slots VALUES (1), (2), (3), (4);

DROP TEMPORARY TABLE IF EXISTS _farm_products;
CREATE TEMPORARY TABLE _farm_products (
    idx        INT AUTO_INCREMENT PRIMARY KEY,
    product_id BIGINT UNSIGNED NOT NULL,
    name       VARCHAR(255) NOT NULL,
    price      BIGINT NOT NULL
);
INSERT INTO _farm_products (product_id, name, price)
SELECT p.id, p.name, p.price
FROM zalo_products p
INNER JOIN farm_product fp ON fp.product_id = p.id AND fp.farm_id = @FARM_ID
ORDER BY p.id ASC;

-- Số sản phẩm farm có (dùng để modulo)
SET @PRODUCT_COUNT := (SELECT COUNT(*) FROM _farm_products);

INSERT INTO zalo_order_items
    (order_id, product_id, farm_stock_batch_id, farm_id, cost_price_snapshot,
     name, price, quantity, unit_label, system_unit, conversion_factor, system_total)
SELECT
    o.id                                                                AS order_id,
    fp.product_id                                                       AS product_id,
    bp.batch_id                                                         AS farm_stock_batch_id,
    @FARM_ID                                                            AS farm_id,
    bp.cost_price                                                       AS cost_price_snapshot,
    fp.name                                                             AS name,
    fp.price                                                            AS price,
    -- quantity: 1-3 đơn vị, không cùng số → variation
    ((o.id + s.s) % 3) + 1                                              AS quantity,
    'kg'                                                                AS unit_label,
    'g'                                                                 AS system_unit,
    1000                                                                AS conversion_factor,
    (((o.id + s.s) % 3) + 1) * 1000                                     AS system_total
FROM zalo_orders o
CROSS JOIN _tally_slots s
INNER JOIN _farm_products fp ON fp.idx = ((o.id + s.s) % @PRODUCT_COUNT) + 1
INNER JOIN _batch_by_product bp ON bp.product_id = fp.product_id
WHERE o.note LIKE '[SEED-FARM1]%'
  -- Bỏ slot 4 cho ~50% đơn (variation 2-4 items)
  AND NOT (s.s = 4 AND (o.id % 2) = 0)
  -- Bỏ slot 3 cho ~25% đơn (variation 2-3 items đôi khi)
  AND NOT (s.s = 3 AND (o.id % 4) = 0);

-- ---- 5d. UPDATE total/subtotal CHO ZALO_ORDERS ----
UPDATE zalo_orders o
INNER JOIN (
    SELECT order_id,
           SUM(price * quantity) AS sub
    FROM zalo_order_items
    GROUP BY order_id
) agg ON agg.order_id = o.id
SET o.subtotal = agg.sub,
    o.total    = agg.sub + o.shipping_fee
WHERE o.note LIKE '[SEED-FARM1]%';

-- ----------------------------------------------------------------------------
-- 6) TẠO ĐƠN ĐANG ĐẾN (status = pending/confirmed/preparing/delivering)
--    để test endpoint /farm/orders/incoming
-- ----------------------------------------------------------------------------
INSERT INTO zalo_orders
    (customer_id, status, payment_status, payment_method,
     created_at, received_at, total, subtotal, shipping_fee, note)
VALUES
    (@OWNER_ID, 'pending',    'pending', 'COD',  NOW() - INTERVAL 1 HOUR,  NOW() - INTERVAL 1 HOUR,  0, 0, 20000, '[SEED-FARM1] incoming-1 pending'),
    (@OWNER_ID, 'confirmed',  'pending', 'COD',  NOW() - INTERVAL 2 HOUR,  NOW() - INTERVAL 2 HOUR,  0, 0, 20000, '[SEED-FARM1] incoming-2 confirmed'),
    (@OWNER_ID, 'preparing',  'paid',    'BANK', NOW() - INTERVAL 4 HOUR,  NOW() - INTERVAL 4 HOUR,  0, 0, 20000, '[SEED-FARM1] incoming-3 preparing'),
    (@OWNER_ID, 'delivering', 'paid',    'BANK', NOW() - INTERVAL 6 HOUR,  NOW() - INTERVAL 6 HOUR,  0, 0, 20000, '[SEED-FARM1] incoming-4 delivering'),
    (@OWNER_ID, 'delivering', 'pending', 'COD',  NOW() - INTERVAL 10 HOUR, NOW() - INTERVAL 10 HOUR, 0, 0, 20000, '[SEED-FARM1] incoming-5 delivering');

-- Tạo deliveries + items cho 5 đơn incoming vừa tạo
INSERT INTO zalo_deliveries (order_id, type, address, name, phone)
SELECT o.id, 'home',
       CONCAT('Số ', o.id, ' đường Lê Lợi, Q.1, TP.HCM'),
       CONCAT('Nguyễn Văn ', CHAR(64 + (o.id % 26) + 1)),
       '0911222333'
FROM zalo_orders o
WHERE o.note LIKE '[SEED-FARM1] incoming-%';

-- 2 items mỗi đơn incoming
INSERT INTO zalo_order_items
    (order_id, product_id, farm_stock_batch_id, farm_id, cost_price_snapshot,
     name, price, quantity, unit_label, system_unit, conversion_factor, system_total)
SELECT
    o.id,
    fp.product_id,
    bp.batch_id,
    @FARM_ID,
    bp.cost_price,
    fp.name,
    fp.price,
    ((o.id + s.s) % 2) + 1,
    'kg',
    'g',
    1000,
    (((o.id + s.s) % 2) + 1) * 1000
FROM zalo_orders o
CROSS JOIN _tally_slots s
INNER JOIN _farm_products fp ON fp.idx = ((o.id + s.s) % @PRODUCT_COUNT) + 1
INNER JOIN _batch_by_product bp ON bp.product_id = fp.product_id
WHERE o.note LIKE '[SEED-FARM1] incoming-%'
  AND s.s <= 2;

-- Update total/subtotal cho incoming
UPDATE zalo_orders o
INNER JOIN (
    SELECT order_id, SUM(price * quantity) AS sub
    FROM zalo_order_items
    GROUP BY order_id
) agg ON agg.order_id = o.id
SET o.subtotal = agg.sub,
    o.total    = agg.sub + o.shipping_fee
WHERE o.note LIKE '[SEED-FARM1] incoming-%';

-- ----------------------------------------------------------------------------
-- 7) FARM_PAYOUTS — vài kỳ thanh toán (paid / pending / draft)
-- ----------------------------------------------------------------------------
-- ABC Farm payment_cycle = monthly → 1 kỳ / tháng.
-- Tạo 3 kỳ: tháng trước trước (paid), tháng trước (pending), tháng này (draft).
INSERT INTO farm_payouts
    (farm_id, period_start, period_end,
     total_sold, gross_revenue, adjustment, net_payout,
     status, paid_at, payment_method, transaction_ref, note,
     created_at, updated_at)
VALUES
    -- Kỳ -2 tháng: ĐÃ THANH TOÁN
    (@FARM_ID,
     DATE_FORMAT(CURDATE() - INTERVAL 2 MONTH, '%Y-%m-01'),
     LAST_DAY(CURDATE() - INTERVAL 2 MONTH),
     320.00, 8500000.00, 0.00, 8500000.00,
     'paid',
     DATE_FORMAT(CURDATE() - INTERVAL 1 MONTH, '%Y-%m-05 10:00:00'),
     'bank_transfer', 'TXN-DEMO-001', '[SEED] kỳ tháng -2',
     NOW(), NOW()),

    -- Kỳ -1 tháng: CHỜ THANH TOÁN
    (@FARM_ID,
     DATE_FORMAT(CURDATE() - INTERVAL 1 MONTH, '%Y-%m-01'),
     LAST_DAY(CURDATE() - INTERVAL 1 MONTH),
     410.00, 11200000.00, -200000.00, 11000000.00,
     'pending',
     NULL, NULL, NULL, '[SEED] kỳ tháng -1, có điều chỉnh -200k phí đóng gói',
     NOW(), NOW()),

    -- Kỳ này: DRAFT (cron snapshot daily)
    (@FARM_ID,
     DATE_FORMAT(CURDATE(), '%Y-%m-01'),
     LAST_DAY(CURDATE()),
     180.00, 4800000.00, 0.00, 4800000.00,
     'draft',
     NULL, NULL, NULL, '[SEED] kỳ tháng hiện tại (đang tích lũy)',
     NOW(), NOW());

-- ----------------------------------------------------------------------------
-- 8) CLEANUP TEMP TABLES
-- ----------------------------------------------------------------------------
DROP TEMPORARY TABLE IF EXISTS _tally_days;
DROP TEMPORARY TABLE IF EXISTS _tally_orders_per_day;
DROP TEMPORARY TABLE IF EXISTS _tally_slots;
DROP TEMPORARY TABLE IF EXISTS _batch_by_product;
DROP TEMPORARY TABLE IF EXISTS _farm_products;

-- ============================================================================
-- KIỂM TRA NHANH SAU KHI CHẠY (chạy riêng để verify)
-- ============================================================================
-- SELECT COUNT(*) AS delivered_90d FROM zalo_orders
--   WHERE note LIKE '[SEED-FARM1]%' AND status = 'delivered';
--
-- SELECT COUNT(*) AS incoming FROM zalo_orders
--   WHERE note LIKE '[SEED-FARM1] incoming-%';
--
-- SELECT product_id, SUM(quantity_remaining) AS remaining
--   FROM farm_stock_batches WHERE farm_id = 1 AND status = 'active'
--   GROUP BY product_id;
--
-- SELECT * FROM farm_payouts WHERE farm_id = 1 ORDER BY period_end DESC;
--
-- -- Doanh thu 7 ngày qua:
-- SELECT DATE(o.delivered_at) AS day,
--        SUM(oi.price * oi.quantity) AS revenue,
--        COUNT(DISTINCT o.id) AS orders
-- FROM zalo_order_items oi
-- JOIN zalo_orders o ON o.id = oi.order_id
-- WHERE oi.farm_id = 1 AND o.status = 'delivered'
--   AND o.delivered_at >= NOW() - INTERVAL 7 DAY
-- GROUP BY DATE(o.delivered_at) ORDER BY day;
-- ============================================================================
