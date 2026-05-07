# Kế hoạch Test Thanh toán Zalo Mini App

> Hoàn thành tất cả các phase theo thứ tự trước khi chuyển sang Production.

---

## Phase 1 — Chuẩn bị môi trường & Config

> **Tiền đề bắt buộc** — phải pass toàn bộ trước khi test các phase tiếp theo.

| # | Test case | Mô tả | Tag |
|---|-----------|--------|-----|
| 1 | Kiểm tra biến môi trường | Xác nhận `ZALO_CHECK_OUT_SECRET` và `ZALO_APP_SECRET` đã được set trong `.env`. Dùng: `php artisan tinker` → `env("ZALO_CHECK_OUT_SECRET")` | Backend |
| 2 | Kiểm tra Queue worker đang chạy | Chạy: `php artisan queue:listen` — đảm bảo job `CheckPaymentStatus` sẽ được xử lý sau 20 phút. | Backend |
| 3 | Webhook endpoint có public URL | Dùng ngrok hoặc tương tự để expose `POST /api/notify` ra ngoài. Zalo cần gọi được endpoint này từ server của họ. | Backend |
| 4 | Deploy Mini App lên Zalo Sandbox | `zmp deploy` → chọn môi trường sandbox. Xác nhận App ID khớp với key trong `.env` và `app-config.json`. | UI |

---

## Phase 2 — Auth & Định danh người dùng

| # | Test case | Mô tả | Tag |
|---|-----------|--------|-----|
| 5 | `POST /authenticate` — tài khoản mới | Gọi với `access_token` hợp lệ từ Zalo SDK. Kỳ vọng: tạo Customer mới, trả JWT token. | API |
| 6 | `POST /authenticate` — tài khoản đã tồn tại | Gọi lần 2 với cùng `access_token`. Kỳ vọng: không tạo thêm Customer mới, trả JWT của Customer cũ. | API |
| 7 | JWT Middleware — token hợp lệ | Gọi `GET /api/orders` với `Authorization: Bearer <token>`. Kỳ vọng: `200 OK`. | API |
| 8 | JWT Middleware — token hết hạn / sai | Gọi với token giả hoặc expired. Kỳ vọng: `401` với message `"Token is Invalid"` hoặc `"Token is Expired"`. | API |
| 9 | Customer bị vô hiệu hoá | Đặt `isActive=0` trong DB, sau đó gọi API protected. Kỳ vọng: `401 "Tài khoản đã bị vô hiệu hoá"`. | API |

---

## Phase 3 — Tạo đơn hàng

| # | Test case | Mô tả | Tag |
|---|-----------|--------|-----|
| 10 | Chọn phương thức thanh toán (Sandbox) | Trên Mini App: `selectPaymentMethod` với `COD_SANDBOX` và `BANK_SANDBOX`. Kỳ vọng: UI hiển thị 2 lựa chọn, chọn được. | UI |
| 11 | `POST /orders` — tạo đơn hàng mới | Sau khi chọn method, FE gọi `/api/orders` với `items` + `delivery`. Kỳ vọng: `201` với `orderId` trả về, đơn xuất hiện trong DB. | API |
| 12 | `POST /orders` — shipping vs pickup | Test đặt 1 đơn giao hàng (`type: shipping`) và 1 đơn lấy tại điểm (`type: pickup`). Kiểm tra bảng `zalo_deliveries` lưu đúng loại. | API |
| 13 | `POST /orders` — giỏ hàng rỗng | Gọi với mảng `items` rỗng. Kỳ vọng: API trả lỗi validation, không tạo đơn. | API |

---

## Phase 4 — MAC & Zalo Checkout SDK ⭐

> **Quan trọng** — đây là phần cốt lõi của toàn bộ luồng thanh toán.

| # | Test case | Mô tả | Tag |
|---|-----------|--------|-----|
| 14 | `POST /prepare-order` — tạo MAC đúng | Gọi với `amount` + `desc` + `item` đầy đủ. Tự tính MAC theo công thức `ksort → join → HMAC-SHA256` và so khớp với giá trị trả về. | API, Backend |
| 15 | `POST /prepare-order` — thiếu field | Gọi thiếu `amount` hoặc `item`. Kỳ vọng: `422 Validation Error`, không tạo MAC. | API |
| 16 | `createOrder` SDK — COD_SANDBOX | SDK kích hoạt giao dịch với method `COD_SANDBOX`. Kỳ vọng: UI Zalo Pay mở, sau khi xác nhận nhận được `checkoutSdkOrderId`. | UI, E2E |
| 17 | `createOrder` SDK — BANK_SANDBOX | Tương tự nhưng dùng `BANK_SANDBOX`. Kiểm tra UI khác biệt so với COD. | UI, E2E |
| 18 | `POST /link` — liên kết orderId | Sau khi có `checkoutSdkOrderId`, gọi `/link`. Kỳ vọng: DB lưu `checkout_sdk_order_id` vào đơn hàng, job được dispatch. | API, Backend |

---

## Phase 5 — Webhook Notify SDK (từ Zalo)

| # | Test case | Mô tả | Tag |
|---|-----------|--------|-----|
| 19 | `POST /notify` — MAC hợp lệ, COD_SANDBOX | Simulate Zalo gọi webhook với `data={appId, orderId, method:"COD_SANDBOX"}` và MAC đúng. Kỳ vọng: `returnCode:1`, `payment_method` cập nhật trong DB. | API, Backend |
| 20 | `POST /notify` — MAC hợp lệ, BANK_SANDBOX | Tương tự với `method: BANK_SANDBOX`. | API, Backend |
| 21 | `POST /notify` — MAC sai | Gửi với MAC bị thay đổi 1 ký tự. Kỳ vọng: `returnCode:0 "Invalid MAC"`, DB không thay đổi. | API, Backend |
| 22 | `POST /notify` — method không hợp lệ | Gửi `method="UNKNOWN_METHOD"`. Kỳ vọng: `returnCode:0 "Invalid method"`. | API, Backend |
| 23 | `POST /notify` — orderId không tồn tại | Gửi `orderId` ngẫu nhiên không có trong DB. Kỳ vọng: `returnCode:0 "Order not found"`. | API, Backend |

---

## Phase 6 — Xác nhận kết quả & PaymentDone

| # | Test case | Mô tả | Tag |
|---|-----------|--------|-----|
| 24 | `checkTransaction` — `resultCode: 1` (Thành công) | Hoàn thành giao dịch sandbox. Kỳ vọng: toast "Thanh toán thành công", giỏ hàng xoá, điều hướng `/orders`. | E2E, UI |
| 25 | `checkTransaction` — `resultCode: 0` (Đang xử lý) | Simulate giao dịch pending. Kỳ vọng: toast thông báo "Giao dịch đang xử lý". | E2E |
| 26 | `checkTransaction` — `resultCode < 0` (Thất bại) | Simulate giao dịch thất bại. Kỳ vọng: toast lỗi, đơn hàng không bị xoá trong DB. | E2E |
| 27 | Job `CheckPaymentStatus` sau 20 phút | Đặt đơn `COD_SANDBOX`, chờ hoặc giảm delay để test. Kỳ vọng: gọi Zalo API `get-status`, cập nhật `payment_status` thành `success`/`failed`. | Backend, E2E |

---

## Phase 7 — Quản lý đơn hàng (Admin)

| # | Test case | Mô tả | Tag |
|---|-----------|--------|-----|
| 28 | Danh sách đơn hàng (Admin) | Vào `/admin/zalo-orders`, kiểm tra đơn vừa tạo hiển thị đúng. Filter theo `status` và `customer_id`. | UI |
| 29 | `PATCH /orders/:id/status` — cập nhật trạng thái | Cập nhật status từ `pending` → `confirmed` → `delivering` → `delivered`. Xác nhận các trạng thái hợp lệ, từ chối trạng thái sai. | API |
| 30 | `GET /orders` — lịch sử đơn của user | Gọi với JWT của customer. Kỳ vọng: chỉ trả đơn của customer đó, không lộ đơn của người khác. | API |

---

## Phase 8 — Regression & Production Readiness

> **Bước cuối cùng** — chỉ thực hiện sau khi tất cả các phase trên đã pass.

| # | Test case | Mô tả | Tag |
|---|-----------|--------|-----|
| 31 | Đổi channels sang `COD` và `BANK` | Thay `COD_SANDBOX` → `COD`, `BANK_SANDBOX` → `BANK` trong `hooks.ts`. Test toàn bộ luồng lại với phương thức thật. | UI, E2E |
| 32 | Đổi key sang production (`.env`) | Cập nhật `ZALO_CHECK_OUT_SECRET` và `ZALO_APP_SECRET` sang giá trị production. Kiểm tra MAC vẫn đúng. | Backend |
| 33 | Test trên thiết bị thật qua QR | Scan QR từ Zalo Developer Console, chạy toàn bộ luồng từ chọn sản phẩm → thanh toán → xem đơn. | E2E, UI |
| 34 | Kiểm tra log lỗi production | Sau khi go-live: theo dõi `storage/logs/laravel.log` 24h đầu. Đặc biệt chú ý lỗi Notify SDK và `CheckPaymentStatus` job. | Backend |

---

## Tóm tắt

| Phase | Nội dung | Số test |
|-------|----------|---------|
| 1 | Chuẩn bị môi trường & Config | 4 |
| 2 | Auth & Định danh người dùng | 5 |
| 3 | Tạo đơn hàng | 4 |
| 4 | MAC & Zalo Checkout SDK | 5 |
| 5 | Webhook Notify SDK | 5 |
| 6 | Xác nhận kết quả & PaymentDone | 4 |
| 7 | Quản lý đơn hàng (Admin) | 3 |
| 8 | Regression & Production Readiness | 4 |
| **Tổng** | | **34** |

---

> **Lưu ý:** Phase 1–3 nên chạy trước để có nền tảng. Phase 4–6 chạy song song vì liên quan trực tiếp đến nhau. Phase 8 chỉ thực hiện sau khi tất cả các phase trước đã pass hoàn toàn.
