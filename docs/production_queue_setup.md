# Production Queue & Scheduler Setup

Hướng dẫn deploy queue worker + cron `schedule:run` trên server production. Bắt buộc hoàn thành **trước khi** đổi `COD_SANDBOX/BANK_SANDBOX` sang `COD/BANK` thật.

---

## Bối cảnh

Backend dispatch 3 loại job:

| Job | Khi nào fire | Nếu queue không chạy |
|---|---|---|
| `CheckPaymentStatus` | Sau `/link`, backoff 30s → 2min → 10min | Đơn online không bao giờ tự verify với Zalo `get-status` — stock bị giữ vĩnh viễn nếu webhook `/notify` miss |
| `CreateVtpOrderOnPayment` (listener) | Khi `OrderPaymentSucceeded` fire | Đơn BANK/ZALOPAY/MOMO đã trả tiền nhưng không tạo VTP → không có đơn vận chuyển |
| `CheckRefundStatus` | Sau khi `ZaloPayRefundClient` trả `processing` | Refund mãi ở trạng thái `processing`, kế toán không thấy để xử lý manual |

Ngoài ra, cron `schedule:run` chạy `orders:auto-cancel-stale` mỗi 5 phút — đây là **safety net duy nhất** release stock khi mọi cơ chế khác fail.

---

## 1. Đổi `.env` production

```env
QUEUE_CONNECTION=database
```

Sau khi đổi, deploy code (đã có migration `2026_05_22_000001_create_jobs_table.php`) rồi chạy:

```bash
php artisan migrate --force
php artisan config:cache
```

Verify bảng tồn tại:

```bash
php artisan tinker --execute="echo Schema::hasTable('jobs') ? 'jobs OK' : 'MISSING';"
# kỳ vọng: "jobs OK"
```

---

## 2. Cài queue worker bằng Supervisor

Tạo `/etc/supervisor/conf.d/vietponics-worker.conf` (đổi `APP_PATH`, `USER` cho phù hợp):

```ini
[program:vietponics-worker]
process_name=%(program_name)s_%(process_num)02d
command=php /var/www/vietponics/artisan queue:work database --queue=default --sleep=3 --tries=3 --max-time=3600
autostart=true
autorestart=true
stopasgroup=true
killasgroup=true
user=www-data
numprocs=2
redirect_stderr=true
stdout_logfile=/var/www/vietponics/storage/logs/worker.log
stopwaitsecs=3600
```

`numprocs=2` đủ cho lưu lượng hiện tại (dispatch < 10 job/phút khi peak). Nếu burst cao hơn, tăng lên 4.

`--max-time=3600` — worker tự restart mỗi giờ để tránh memory leak từ long-lived PHP process. Quan trọng vì Laravel queue worker không reload code khi `git pull` — phải `supervisorctl restart` sau mỗi deploy.

Apply:

```bash
sudo supervisorctl reread
sudo supervisorctl update
sudo supervisorctl start vietponics-worker:*
sudo supervisorctl status   # kỳ vọng: 2 process RUNNING
```

Sau mỗi deploy code, **luôn** chạy:

```bash
php artisan queue:restart   # signal worker tự graceful restart
# HOẶC nếu cần restart ngay:
sudo supervisorctl restart vietponics-worker:*
```

---

## 3. Cài cron `schedule:run`

Thêm vào `crontab -e` của user `www-data` (hoặc user chạy app):

```cron
* * * * * cd /var/www/vietponics && php artisan schedule:run >> /dev/null 2>&1
```

Verify:

```bash
crontab -u www-data -l | grep schedule:run
```

Schedule trong [Kernel.php](../app/Console/Kernel.php#L29-L55) bao gồm:
- `orders:auto-cancel-stale` mỗi 5 phút — release stock cho đơn online stuck
- `vtp:retry-cancel` mỗi 30 phút — retry hủy VTP nếu lần đầu fail
- `vtp:sync-locations` weekly — refresh danh mục tỉnh/huyện VTP
- `vtp:refresh-token` weekly
- `farms:snapshot-daily` 23:30 — Farm Partner Hub stats

---

## 4. Smoke test trên server

Sau khi setup, chạy tuần tự:

```bash
# 4.1 — Dispatch test job, verify worker pick up
php artisan tinker --execute="dispatch(function() { \Log::info('queue smoke test ' . now()); });"
sleep 5
tail -5 storage/logs/laravel.log | grep "queue smoke test"
# kỳ vọng: 1 dòng log mới với timestamp ~5s trước

# 4.2 — Verify auto-cancel command chạy được (dry-run, không sửa DB)
php artisan orders:auto-cancel-stale --dry-run
# kỳ vọng: "Không có đơn nào cần auto-cancel." HOẶC list các đơn match

# 4.3 — Tail worker log 1 phút để xem có error không
tail -f storage/logs/worker.log
```

---

## 5. Monitoring (sau go-live)

24 giờ đầu, theo dõi:

```bash
# Số job đang chờ trong queue (kỳ vọng: thường ~0, peak < 50)
php artisan tinker --execute="echo DB::table('jobs')->count();"

# Số job failed (kỳ vọng: 0; nếu > 0 → đọc bảng failed_jobs để debug)
php artisan tinker --execute="echo DB::table('failed_jobs')->count();"

# Tail log channel viettelpost — bắt CreateVtpOrderOnPayment fail
tail -f storage/logs/viettelpost-*.log
```

Khi có `failed_jobs`:

```bash
# Xem chi tiết
php artisan queue:failed

# Retry 1 job cụ thể (lấy ID từ lệnh trên)
php artisan queue:retry <uuid>

# Retry tất cả
php artisan queue:retry all
```

---

## Rollback nếu cần

Đổi ngược về `sync` chỉ khi queue worker có vấn đề và cần quick fix (sẽ làm web request chậm + mất retry logic):

```env
QUEUE_CONNECTION=sync
```

```bash
php artisan config:cache
sudo supervisorctl stop vietponics-worker:*   # tránh worker đọc job cũ
```

**Lưu ý:** Các job đang nằm trong bảng `jobs` sẽ không được xử lý cho tới khi bật worker lại. Trước khi rollback, drain queue:

```bash
php artisan queue:work --stop-when-empty   # xử lý hết job hiện có rồi dừng
```

---

## ⚠️ ZaloPay refund endpoint — chưa confirm

Probe ngày 2026-05-22 bằng `php artisan zalopay:probe-refund` với sandbox keys:

| Endpoint | HTTP | Body |
|---|---|---|
| `POST payment-mini.zalo.me/api/transaction/refund` | 200 | `[]` (rỗng) |
| `POST payment-mini.zalo.me/api/transaction/query-refund` | 200 | `[]` (rỗng) |

Response header `server: za-ngx-srv` xác nhận endpoint là Zalo thật (không phải 404 catch-all), nhưng response **không có** field `returnCode` mà [`ZaloPayRefundClient`](../app/Services/ZaloPayRefundClient.php#L97-L119) đang parse.

**Hệ quả:** `requestRefund()` sẽ trả `Unknown returnCode: null` → [`RefundService`](../app/Services/RefundService.php#L67-L75) fallback `pending_manual`. Không crash, không user impact — chỉ kế toán phải xử lý manual qua `POST /orders/{id}/refund/confirm-manual`.

**3 giả thuyết cần loại trừ trước go-live:**

1. Sandbox luôn trả empty với fake mRefundId; prod keys + đơn thật sẽ trả đúng shape → cần test với 1 đơn ZALOPAY thật đã thanh toán.
2. Thiếu header bắt buộc (vd `x-mini-app-id`, OA access token) → cần Zalo support confirm contract.
3. Mini App KHÔNG có refund qua HTTP — phải dùng OA Open API hoặc merchant dashboard → cần đổi luồng.

**Action items:**

- [ ] Gửi ticket ZaloPay support với output probe (`200 + []`) để xác nhận endpoint đúng cho Mini App channel.
- [ ] Khi go-live: setup alert (Slack/email) khi có order chuyển `refund_status='pending_manual'` để kế toán xử lý trong SLA.
- [ ] Re-run probe sau khi nhận response từ Zalo: `php artisan zalopay:probe-refund` và `--query` — kỳ vọng `✅ OK_REJECTED` (returnCode=2 với fake mRefundId).
- [ ] Nếu confirm endpoint dùng shape khác → update `ZaloPayRefundClient::requestRefund()` parser cho phù hợp.

Cho tới khi resolve, đặt giả định: **toàn bộ refund ZaloPay đi qua manual queue**, không có auto-refund.

---

## Checklist trước khi đổi sandbox → production

- [ ] `QUEUE_CONNECTION=database` trong `.env` prod
- [ ] Migration `2026_05_22_000001_create_jobs_table` đã chạy (`php artisan migrate:status`)
- [ ] Supervisor config tạo + 2 worker process đang RUNNING
- [ ] Cron `* * * * * php artisan schedule:run` đã có trong `crontab -u www-data -l`
- [ ] Smoke test 4.1 pass (queue pick up job test)
- [ ] Smoke test 4.2 pass (`orders:auto-cancel-stale --dry-run` không error)
- [ ] Webhook URL `https://vietponics.vn/api/notify` đã khai báo trong Zalo Developer Console (Payment → Webhook)
- [ ] Set alert (Slack/email) khi `failed_jobs` count > 0
- [ ] Re-run `php artisan zalopay:probe-refund` với prod keys — kỳ vọng `OK_REJECTED`. Nếu vẫn `OK_OTHER` (body rỗng), chấp nhận manual-only refund và confirm với kế toán.
- [ ] Set alert khi `refund_status='pending_manual'` xuất hiện — kế toán cần xử lý trong SLA (đề xuất: 24h cho ZaloPay/MoMo, 48h cho BANK).
