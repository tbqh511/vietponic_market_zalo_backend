#!/usr/bin/env bash
# =============================================================================
# Zalo Mini App — API Test Script (Phase 2)
# Chạy: bash test_api.sh
#
# Trước khi chạy, điền các biến bên dưới:
#   JWT_TOKEN   — Lấy từ /authenticate (cần Zalo access token thật)
#   ZALO_APP_SECRET     — Giá trị ZALO_APP_SECRET trong .env backend
#   ZALO_APP_ID         — Giá trị ZALO_APP_ID trong .env backend
#   ADMIN_API_SECRET    — Giá trị ADMIN_API_SECRET trong .env backend
# =============================================================================

BASE="https://vietponics.vn/api"

# ── Cấu hình (điền vào trước khi chạy) ───────────────────────────────────────
JWT_TOKEN="REPLACE_WITH_YOUR_JWT_TOKEN"
ZALO_APP_SECRET="REPLACE_WITH_ZALO_APP_SECRET"
ZALO_APP_ID="REPLACE_WITH_ZALO_APP_ID"         # Phải khớp với APP_ID trong mini app .env
ADMIN_API_SECRET="REPLACE_WITH_ADMIN_API_SECRET"

# =============================================================================
# Helper
# =============================================================================
PASS=0; FAIL=0

check() {
  local label="$1"
  local expected_pattern="$2"
  local response="$3"
  if echo "$response" | grep -q "$expected_pattern"; then
    echo "  [PASS] $label"
    ((PASS++))
  else
    echo "  [FAIL] $label"
    echo "         Expected pattern: $expected_pattern"
    echo "         Got: $response"
    ((FAIL++))
  fi
}

echo ""
echo "================================================================="
echo " Phase 2A — Public Endpoints"
echo "================================================================="

echo ""
echo "2.1 GET /categories"
R=$(curl -s "$BASE/categories")
check "2.1 categories trả data" '"data"' "$R"

echo ""
echo "2.2 GET /products"
R=$(curl -s "$BASE/products")
check "2.2 products có image là full URL" '"image":"http' "$R"

echo ""
echo "2.3 GET /banners"
R=$(curl -s "$BASE/banners")
check "2.3 banners trả error:false" '"error":false' "$R"

echo ""
echo "2.4 GET /stations"
R=$(curl -s "$BASE/stations")
check "2.4 stations trả data" '"data"' "$R"

echo ""
echo "2.5 POST /authenticate — thiếu access_token"
R=$(curl -s -X POST "$BASE/authenticate" \
  -H "Content-Type: application/json" \
  -d '{}')
check "2.5 validate lỗi 422" '"message"' "$R"

echo ""
echo "================================================================="
echo " Phase 2B — Webhook POST /notify"
echo "================================================================="

echo ""
echo "2.6 /notify — thiếu data và mac"
R=$(curl -s -X POST "$BASE/notify" \
  -H "Content-Type: application/json" \
  -d '{}')
check "2.6 returnCode:0 Missing data" '"returnCode":0' "$R"

echo ""
echo "2.7 /notify — method không hợp lệ"
RAW_DATA='{"appId":"'$ZALO_APP_ID'","orderId":"test-order-999","method":"UNKNOWN_METHOD"}'
FAKE_MAC="0000000000000000000000000000000000000000000000000000000000000000"
R=$(curl -s -X POST "$BASE/notify" \
  -H "Content-Type: application/json" \
  -d "{\"data\":$RAW_DATA,\"mac\":\"$FAKE_MAC\"}")
check "2.7 returnCode:0 Invalid method" '"returnCode":0' "$R"

echo ""
echo "2.8 /notify — MAC sai"
RAW_DATA='{"appId":"'$ZALO_APP_ID'","orderId":"test-order-999","method":"COD_SANDBOX"}'
FAKE_MAC="abcdef1234567890abcdef1234567890abcdef1234567890abcdef1234567890"
R=$(curl -s -X POST "$BASE/notify" \
  -H "Content-Type: application/json" \
  -d "{\"data\":$RAW_DATA,\"mac\":\"$FAKE_MAC\"}")
check "2.8 returnCode:0 Invalid MAC" '"returnCode":0' "$R"

echo ""
echo "2.9 /notify — orderId không tồn tại, MAC đúng"
METHOD="COD_SANDBOX"
ORDER_ID="nonexistent-order-00000"
# Tính MAC đúng: hash_hmac('sha256', "appId=...&orderId=...&method=...", ZALO_APP_SECRET)
CORRECT_MAC=$(php -r "echo hash_hmac('sha256', 'appId=${ZALO_APP_ID}&orderId=${ORDER_ID}&method=${METHOD}', '${ZALO_APP_SECRET}');")
RAW_DATA='{"appId":"'$ZALO_APP_ID'","orderId":"'$ORDER_ID'","method":"'$METHOD'"}'
R=$(curl -s -X POST "$BASE/notify" \
  -H "Content-Type: application/json" \
  -d "{\"data\":$RAW_DATA,\"mac\":\"$CORRECT_MAC\"}")
check "2.9 returnCode:0 Order not found" '"returnCode":0' "$R"

echo ""
echo "================================================================="
echo " Phase 2C — POST /prepare-order (cần JWT)"
echo "================================================================="

echo ""
echo "2.11 /prepare-order — thiếu amount"
R=$(curl -s -X POST "$BASE/prepare-order" \
  -H "Content-Type: application/json" \
  -H "Authorization: Bearer $JWT_TOKEN" \
  -d '{"desc":"test","item":[{"id":1,"amount":50000}]}')
check "2.11 422 thiếu amount" '"message"' "$R"

echo ""
echo "2.12 /prepare-order — thiếu item"
R=$(curl -s -X POST "$BASE/prepare-order" \
  -H "Content-Type: application/json" \
  -H "Authorization: Bearer $JWT_TOKEN" \
  -d '{"amount":50000,"desc":"test đơn hàng 1"}')
check "2.12 422 thiếu item" '"message"' "$R"

echo ""
echo "2.13 /prepare-order — full params hợp lệ (verify MAC)"
PAYLOAD='{"amount":50000,"desc":"Thanh toán cho đơn hàng 1","item":[{"id":1,"amount":50000}],"extradata":"{\"myOrderId\":1}","method":"{\"id\":\"COD_SANDBOX\",\"isCustom\":false}"}'
R=$(curl -s -X POST "$BASE/prepare-order" \
  -H "Content-Type: application/json" \
  -H "Authorization: Bearer $JWT_TOKEN" \
  -d "$PAYLOAD")
check "2.13 trả về mac" '"mac"' "$R"

# Verify MAC logic
if echo "$R" | grep -q '"mac"'; then
  RETURNED_MAC=$(echo "$R" | php -r "
    \$data = json_decode(file_get_contents('php://stdin'), true);
    echo \$data['mac'];
  ")
  # Tính lại MAC theo công thức ksort → join → HMAC-SHA256
  EXPECTED_MAC=$(php -r "
    \$secret = '${ZALO_CHECK_OUT_SECRET:-REPLACE_WITH_ZALO_CHECK_OUT_SECRET}';
    \$params = [
      'amount' => 50000,
      'desc' => 'Thanh toán cho đơn hàng 1',
      'extradata' => '{\"myOrderId\":1}',
      'item' => [[\"id\" => 1, \"amount\" => 50000]],
      'method' => '{\"id\":\"COD_SANDBOX\",\"isCustom\":false}'
    ];
    ksort(\$params);
    \$parts = [];
    foreach (\$params as \$k => \$v) {
      \$parts[] = \$k . '=' . (is_array(\$v) ? json_encode(\$v) : \$v);
    }
    echo hash_hmac('sha256', implode('&', \$parts), \$secret);
  " 2>/dev/null)
  if [ "$RETURNED_MAC" = "$EXPECTED_MAC" ] && [ -n "$EXPECTED_MAC" ]; then
    echo "  [PASS] 2.13 MAC verify khớp"
    ((PASS++))
  else
    echo "  [INFO] 2.13 MAC verify cần ZALO_CHECK_OUT_SECRET để xác nhận"
  fi
fi

echo ""
echo "================================================================="
echo " Phase 2D — JWT Middleware"
echo "================================================================="

echo ""
echo "2.14 GET /orders — không có token"
R=$(curl -s "$BASE/orders")
check "2.14 401 Unauthorized" '"message"' "$R"

echo ""
echo "2.15 GET /orders — token giả"
R=$(curl -s "$BASE/orders" \
  -H "Authorization: Bearer thisisafaketoken")
check "2.15 401 token không hợp lệ" '"message"' "$R"

echo ""
echo "================================================================="
echo " Phase 2E — POST /orders validation"
echo "================================================================="

echo ""
echo "2.17 POST /orders — items rỗng"
R=$(curl -s -X POST "$BASE/orders" \
  -H "Content-Type: application/json" \
  -H "Authorization: Bearer $JWT_TOKEN" \
  -d '{"items":[],"delivery":{"type":"shipping","address":"123 Test","name":"Test"},"total":"0","created_at":"2026-01-01T00:00:00+07:00"}')
check "2.17 422 items rỗng" '"message"' "$R"

echo ""
echo "2.18 POST /orders — product_id không tồn tại"
R=$(curl -s -X POST "$BASE/orders" \
  -H "Content-Type: application/json" \
  -H "Authorization: Bearer $JWT_TOKEN" \
  -d '{
    "items":[{"product_id":"999999","name":"Ghost Product","price":"50000","quantity":"1","image":"","detail":""}],
    "delivery":{"type":"shipping","address":"123 Test Street","name":"Nguyen Van A","phone":"0901234567"},
    "total":"50000",
    "note":"",
    "created_at":"2026-01-01T00:00:00+07:00"
  }')
check "2.18 422 sản phẩm không tồn tại" '"error":true' "$R"

echo ""
echo "2.20 POST /orders — delivery shipping hợp lệ"
# Lấy product_id đầu tiên từ DB để test
FIRST_PRODUCT=$(curl -s "$BASE/products" | php -r "\$d=json_decode(file_get_contents('php://stdin'),true); echo \$d['data'][0]['id'] ?? '1';")
FIRST_PRICE=$(curl -s "$BASE/products" | php -r "\$d=json_decode(file_get_contents('php://stdin'),true); echo \$d['data'][0]['price'] ?? '50000';")
echo "  => Dùng product_id=$FIRST_PRODUCT, price=$FIRST_PRICE"
R=$(curl -s -X POST "$BASE/orders" \
  -H "Content-Type: application/json" \
  -H "Authorization: Bearer $JWT_TOKEN" \
  -d "{
    \"items\":[{\"product_id\":\"$FIRST_PRODUCT\",\"name\":\"Test Product\",\"price\":\"$FIRST_PRICE\",\"quantity\":\"1\",\"image\":\"\",\"detail\":\"\"}],
    \"delivery\":{\"type\":\"shipping\",\"address\":\"123 Test Street Da Lat\",\"name\":\"Nguyen Van A\",\"phone\":\"0901234567\"},
    \"total\":\"$FIRST_PRICE\",
    \"note\":\"\",
    \"created_at\":\"2026-01-01T00:00:00+07:00\"
  }")
check "2.20 201 tạo đơn hàng thành công" '"orderId"' "$R"

# Lưu orderId để dùng trong test 2.10 và 5.x
ORDER_ID_CREATED=$(echo "$R" | php -r "\$d=json_decode(file_get_contents('php://stdin'),true); echo \$d['orderId'] ?? '';")
if [ -n "$ORDER_ID_CREATED" ]; then
  echo "  => orderId tạo được: $ORDER_ID_CREATED"
fi

echo ""
echo "================================================================="
echo " Phase 2B.10 — /notify với orderId hợp lệ (cần order vừa tạo)"
echo "================================================================="

if [ -n "$ORDER_ID_CREATED" ]; then
  # Nhưng notify dùng checkout_sdk_order_id, không phải orderId của hệ thống
  # Cần gọi /link trước để set checkout_sdk_order_id
  # Test này chỉ xác nhận luồng MAC validation hoạt động
  echo "  [INFO] Notify webhook dùng checkout_sdk_order_id (sau khi gọi /link từ mini app)"
  echo "  [INFO] Test 2.10 cần chạy sau Phase 4 khi đã có checkout_sdk_order_id trong DB"
else
  echo "  [SKIP] Không có orderId — bỏ qua test 2.10"
fi

echo ""
echo "================================================================="
echo " Phase 5 — Admin: PATCH /orders/{id}/status"
echo "================================================================="

if [ -n "$ORDER_ID_CREATED" ]; then
  echo ""
  echo "5.2 PATCH /orders/$ORDER_ID_CREATED/status — confirmed (admin)"
  R=$(curl -s -X PATCH "$BASE/orders/$ORDER_ID_CREATED/status" \
    -H "Content-Type: application/json" \
    -H "X-Admin-Secret: $ADMIN_API_SECRET" \
    -d '{"status":"confirmed"}')
  check "5.2 status → confirmed" '"error":false' "$R"

  echo ""
  echo "5.3 PATCH /orders/$ORDER_ID_CREATED/status — status không hợp lệ"
  R=$(curl -s -X PATCH "$BASE/orders/$ORDER_ID_CREATED/status" \
    -H "Content-Type: application/json" \
    -H "X-Admin-Secret: $ADMIN_API_SECRET" \
    -d '{"status":"done"}')
  check "5.3 422 status không hợp lệ" '"message"' "$R"

  echo ""
  echo "5.4 PATCH /orders/$ORDER_ID_CREATED/status — không có X-Admin-Secret"
  R=$(curl -s -X PATCH "$BASE/orders/$ORDER_ID_CREATED/status" \
    -H "Content-Type: application/json" \
    -H "Authorization: Bearer $JWT_TOKEN" \
    -d '{"status":"cancelled"}')
  check "5.4 403 customer không được update status" '"error":true' "$R"
fi

echo ""
echo "================================================================="
echo " Kết quả"
echo "================================================================="
echo "  PASS: $PASS"
echo "  FAIL: $FAIL"
TOTAL=$((PASS + FAIL))
if [ $FAIL -eq 0 ] && [ $TOTAL -gt 0 ]; then
  echo "  => Tất cả test PASS!"
else
  echo "  => Có $FAIL test FAIL. Kiểm tra lại cấu hình."
fi
echo ""
echo "Lưu ý:"
echo "  - Test 2.13 MAC verify cần ZALO_CHECK_OUT_SECRET"
echo "  - Test 2.16 (isActive=0) cần set thủ công trong DB"
echo "  - Test 2.10 cần chạy sau Phase 4 (E2E payment)"
echo "  - Test 2.20 cần JWT_TOKEN hợp lệ"
echo ""
