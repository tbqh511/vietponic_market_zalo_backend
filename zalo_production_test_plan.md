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

## Phase 8 — BANK Payment Failure Scenarios ⭐

> **Phase mới** — verify đơn online (BANK/ZALOPAY/MOMO) được auto-recover khi
> thanh toán fail, user huỷ giữa chừng, hoặc webhook không tới. Tiền đề: VTP
> createOrder cho BANK đã dời sang `CreateVtpOrderOnPayment` listener, và
> `orders:auto-cancel-stale` đã được schedule.

### 8.1. Auto-cancel job

| # | Test case | Mô tả | Tag |
|---|-----------|--------|-----|
| 31 | `orders:auto-cancel-stale --dry-run` | Chạy lệnh khi DB không có đơn match. Kỳ vọng: in `"Không có đơn nào cần auto-cancel."`, exit 0. | Backend |
| 32 | Auto-cancel khi `payment_status='failed'` | Tạo đơn BANK_SANDBOX, set `payment_status='failed'` qua tinker. Chạy `orders:auto-cancel-stale`. Kỳ vọng: đơn chuyển `cancelled`, `cancelled_by='system'`, stock được release, voucher release (nếu có). | Backend, E2E |
| 33 | Auto-cancel khi `payment_status='pending'` quá ngưỡng | Tạo đơn BANK_SANDBOX, backdate `created_at` 31 phút qua. Chạy job. Kỳ vọng: đơn cancel với reason `"Auto-cancel: thanh toán quá hạn (30 phút)"`. | Backend |
| 34 | KHÔNG cancel đơn COD pending | Tạo đơn COD_SANDBOX, backdate 1 giờ. Chạy job. Kỳ vọng: đơn KHÔNG bị cancel (COD có thể chờ lâu). | Backend |
| 35 | KHÔNG cancel đơn BANK pending mới (< 30 phút) | Tạo đơn BANK_SANDBOX vừa đặt. Chạy job. Kỳ vọng: đơn KHÔNG bị cancel. | Backend |
| 36 | KHÔNG cancel đơn `payment_status='success'` | Tạo đơn BANK_SANDBOX, set `payment_status='success'`. Chạy job. Kỳ vọng: đơn KHÔNG bị cancel. | Backend |
| 37 | Idempotency — chạy job 2 lần liên tiếp | Sau khi đơn đã bị auto-cancel ở lần 1, chạy lại job. Kỳ vọng: không cancel lại, stock không bị release âm. | Backend |
| 38 | Race condition với webhook | Đơn pending > 30 phút. Webhook `/notify` resultCode=1 đến CHÍNH XÁC khi job đang lock đơn. Kỳ vọng: hoặc webhook thắng (đơn success, không cancel) hoặc job thắng (đơn cancel, webhook return order not found gracefully). Không có inconsistent state. | Backend, E2E |

### 8.2. VTP timing — chỉ tạo sau payment success

| # | Test case | Mô tả | Tag |
|---|-----------|--------|-----|
| 39 | COD shipping → VTP tạo ngay ở `store()` | Đặt đơn COD_SANDBOX type=shipping. Kỳ vọng: ngay sau `/checkout` response, `zalo_deliveries.vtp_order_number` đã có giá trị. | API, Backend |
| 40 | BANK shipping pending → CHƯA có VTP | Đặt đơn BANK_SANDBOX type=shipping nhưng không thanh toán. Kỳ vọng: `vtp_order_number IS NULL` cho tới khi payment success. | API, Backend |
| 41 | BANK shipping success → VTP tạo qua listener | Hoàn tất thanh toán BANK_SANDBOX. Kỳ vọng: sau khi `/notify` set `payment_status='success'`, listener `CreateVtpOrderOnPayment` fire và `vtp_order_number` được populate (verify trong log channel `viettelpost`). | Backend, E2E |
| 42 | BANK pickup success → KHÔNG tạo VTP | Đặt đơn BANK_SANDBOX type=pickup. Thanh toán xong. Kỳ vọng: listener skip, `vtp_order_number IS NULL` (pickup không cần VTP). | Backend |
| 43 | Listener idempotent | Trigger event `OrderPaymentSucceeded` 2 lần cho cùng đơn (vd webhook retry). Kỳ vọng: VTP chỉ tạo 1 lần, lần 2 skip qua guard `!empty(vtp_order_number)`. | Backend |

### 8.3. User failure scenarios (E2E)

| # | Test case | Mô tả | Tag |
|---|-----------|--------|-----|
| 44 | Tài khoản BANK không đủ tiền | Trên thiết bị thật: chọn BANK_SANDBOX với tài khoản test có balance=0. Kỳ vọng: SDK trả `resultCode=-1`, toast lỗi. Kiểm tra log có request `/notify` đến không. | E2E, UI |
| 45 | User huỷ giữa SDK | Mở SDK BANK_SANDBOX, bấm Back/Cancel. Kỳ vọng: KHÔNG có event `PaymentDone`. Đơn ở `payment_status='pending'`. Sau 30 phút, auto-cancel job xử lý. | E2E |
| 46 | User đóng app sau khi mở SDK | Mở SDK BANK_SANDBOX, kill app process. Kỳ vọng: đơn `payment_status='pending'`. `CheckPaymentStatus` poll job 30s/2min/10min không thấy success. Sau 30 phút từ `created_at`, auto-cancel job xử lý. Stock được release. | E2E |
| 47 | Webhook /notify không tới (network failure) | Block outbound từ Zalo bằng firewall (hoặc thay ngrok URL). Thanh toán BANK_SANDBOX thành công ở phía Zalo nhưng webhook không tới backend. Kỳ vọng: `CheckPaymentStatus` poll job (30s → 2min → 10min) verify với Zalo qua `get-status` và set `payment_status='success'`. Listener tạo VTP. | E2E, Backend |
| 48 | Refund pending_manual khi user cancel sau BANK đã trả | User thanh toán BANK_SANDBOX thành công, sau đó vào `/orders` huỷ đơn. Kỳ vọng: `refund_status='pending_manual'`, đơn xuất hiện trong admin dashboard `/admin/refunds/pending`. | E2E, Backend |
| 49 | Admin confirm manual refund | Admin click "Đã hoàn tiền" trên dashboard. Kỳ vọng: `refund_status='refunded'`, `refunded_at` được set, đơn biến khỏi list, badge counter giảm. | UI, Backend |

> **⚠️ Lưu ý refund ZaloPay (2026-05-22):** Probe `php artisan zalopay:probe-refund` trên sandbox cho thấy endpoint `payment-mini.zalo.me/api/transaction/refund` trả `200 + []` (không có `returnCode`). `ZaloPayRefundClient::requestRefund()` hiện sẽ fallback `pending_manual` cho mọi đơn ZaloPay. Xem [docs/production_queue_setup.md](docs/production_queue_setup.md#%EF%B8%8F-zalopay-refund-endpoint--chưa-confirm) để rõ action items.

---

## Phase 9 — Regression & Production Readiness

> **Bước cuối cùng** — chỉ thực hiện sau khi tất cả các phase trên đã pass.

| # | Test case | Mô tả | Tag |
|---|-----------|--------|-----|
| 50 | Đổi channels sang `COD` và `BANK` | Thay `COD_SANDBOX` → `COD`, `BANK_SANDBOX` → `BANK` trong `hooks.ts`. Test toàn bộ luồng lại với phương thức thật. | UI, E2E |
| 51 | Đổi key sang production (`.env`) | Cập nhật `ZALO_CHECK_OUT_SECRET` và `ZALO_APP_SECRET` sang giá trị production. Kiểm tra MAC vẫn đúng. | Backend |
| 52 | Verify cron `orders:auto-cancel-stale` đang chạy | Trên server production: `crontab -l` có entry `* * * * * php artisan schedule:run`. Tail log để xác nhận lệnh fire mỗi 5 phút. | Backend |
| 53 | Test trên thiết bị thật qua QR | Scan QR từ Zalo Developer Console, chạy toàn bộ luồng từ chọn sản phẩm → thanh toán → xem đơn. | E2E, UI |
| 54 | Kiểm tra log lỗi production | Sau khi go-live: theo dõi `storage/logs/laravel.log` 24h đầu. Đặc biệt chú ý lỗi Notify SDK, `CheckPaymentStatus` job, và channel `viettelpost` (CreateVtpOrderOnPayment fail). | Backend |

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
| 8 | BANK Payment Failure Scenarios | 19 |
| 9 | Regression & Production Readiness | 5 |
| **Tổng** | | **54** |

---

> **Lưu ý:** Phase 1–3 nên chạy trước để có nền tảng. Phase 4–6 chạy song song vì liên quan trực tiếp đến nhau. **Phase 8 phải pass trước Phase 9** — đây là phần verify các fix cho zombie VTP và stock-leak. Phase 9 chỉ thực hiện sau khi tất cả các phase trước đã pass hoàn toàn.
