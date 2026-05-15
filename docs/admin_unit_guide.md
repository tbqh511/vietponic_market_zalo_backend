# Hướng dẫn cấu hình & vận hành Đơn vị sản phẩm (Unit)

Tài liệu dành cho admin/quản trị viên Vietponics. Mục tiêu: giúp đội vận hành cấu hình đúng đơn vị bán cho từng sản phẩm, đọc được báo cáo tồn kho/doanh thu theo đơn vị hệ thống, và xử lý các trường hợp đặc biệt (sản phẩm cũ chưa có đơn vị, đổi đơn vị bán, v.v.).

---

## 1. Khái niệm cốt lõi

Mỗi sản phẩm có 3 thông tin về đơn vị:

| Trường | Ý nghĩa | Ví dụ |
|---|---|---|
| **Đơn vị bán (`unit_id` → `zalo_units.label`)** | Đơn vị khách thấy & chọn khi mua | `bó`, `hộp`, `gói`, `chai`, `kg`, `cái` |
| **Đơn vị hệ thống (`system_unit`)** | Đơn vị chuẩn để quy đổi & cộng dồn | `g` (khối lượng), `ml` (thể tích), `piece` (đếm) |
| **Hệ số quy đổi (`conversion_factor`)** | 1 đơn vị bán = bao nhiêu đơn vị hệ thống | `100` (1 bó = 100g), `200` (1 hộp = 200g), `500` (1 chai = 500ml) |

> **Quan trọng — hệ số quy đổi nằm trên *sản phẩm*, không nằm trên *đơn vị*.** Vì vậy "1 hộp cà chua = 200g" và "1 hộp dưa leo = 500g" đều được, dù cùng đơn vị "hộp".

### Bảng `zalo_units` (tham chiếu)

Bảng dùng chung danh sách đơn vị bán cho toàn hệ thống. Mỗi đơn vị có một `system_unit_type` mặc định:

| code | label | system_unit_type |
|---|---|---|
| `bo` | bó | g |
| `kg` | kg | g |
| `g` | g | g |
| `hop` | hộp | g |
| `goi` | gói | g |
| `chai` | chai | ml |
| `lit` | lít | ml |
| `ml` | ml | ml |
| `cai` | cái | piece |

> Khi cần thêm đơn vị mới (vd: "thùng", "vỉ"), chèn thêm dòng vào bảng `zalo_units` qua tinker hoặc seeder; **không sửa `code` của đơn vị đã dùng** (sẽ vỡ FK trong báo cáo cũ).

---

## 2. Cấu hình đơn vị khi tạo / sửa sản phẩm

Vào **Quản trị → Zalo Products → Tạo mới / Sửa**:

1. Chọn **Đơn vị bán** từ dropdown — `system_unit` sẽ được tự động điền theo loại (g/ml/piece).
2. Có thể đổi `system_unit` thủ công nếu cần (vd: bán "hộp nước" tính theo `ml` thay vì `g`); hệ thống sẽ chặn nếu đơn vị bán và `system_unit` không tương thích (vd: chọn đơn vị "chai" nhưng đặt system_unit = "g").
3. Nhập **Hệ số quy đổi** (`conversion_factor`):
   - 1 bó rau muống = 100g → nhập `100`
   - 1 hộp cà chua bi = 200g → nhập `200`
   - 1 chai nước ép = 500ml → nhập `500`
   - 1 cái bí ngô (bán theo trái) → đơn vị bán = `cái`, system_unit = `piece`, hệ số = `1`

### Sản phẩm "đếm cái thuần"

Nếu sản phẩm bán theo cái (vd: "1 cái bánh"), chọn:
- Đơn vị bán: `cái`
- System unit: `piece`
- Hệ số: `1`

Khi đó UI sẽ hiển thị `x3` thay vì `3 cái (3 cái)` để gọn hơn.

---

## 3. Trường hợp sản phẩm legacy (chưa có đơn vị)

Sản phẩm tồn tại trước khi triển khai unit có:
- `unit_id = NULL`
- `system_unit = 'piece'` (mặc định backfill)
- `conversion_factor = 1`

Hành vi:
- Frontend hiển thị `x{quantity}` (như cũ).
- Báo cáo tồn kho gom vào nhóm `Đếm cái`.
- Không gây lỗi trong checkout.

> **Khuyến nghị**: Vào danh sách sản phẩm, lọc các sản phẩm chưa cấu hình đơn vị, cập nhật dần thành dữ liệu chính xác để báo cáo có ý nghĩa hơn.

---

## 4. Đọc báo cáo tồn kho theo đơn vị hệ thống

Vào **Quản lý Tồn kho → Báo cáo** (`/inventory/report`):

### Khối "Tổng theo đơn vị hệ thống"

Cộng dồn nhập/xuất/điều chỉnh quy đổi qua `conversion_factor`, gom theo `system_unit`:

```
Khối lượng (g/kg)   +35,5 kg   -12,2 kg   +0     +23,3 kg
Thể tích (ml/l)     +20 l      -5 l       +0     +15 l
Đếm cái             +200 cái   -45 cái    +0     +155 cái
```

Hiển thị tự động chuyển `g → kg` và `ml → l` khi giá trị ≥ 1000.

### Bảng chi tiết per-product

Cột mới:
- **Đơn vị**: hiển thị đơn vị bán + hệ số (vd: `bó ×100 g`).
- **Quy đổi (hệ thống)**: tổng biến động ròng đã quy đổi sang đơn vị hệ thống của sản phẩm (vd: `+5 kg`, `-300 g`).

Dùng cột này để so sánh sản phẩm bán theo đơn vị khác nhau (vd: rau muống bán theo `bó`, cà chua bán theo `hộp` — đều quy về g để biết tổng "thực" đã xuất bao nhiêu).

---

## 5. Snapshot trên đơn hàng — vì sao quan trọng

Khi khách đặt hàng, mỗi `zalo_order_items` lưu **snapshot** đơn vị tại thời điểm đặt:

- `unit_label` — tên đơn vị bán (vd: "bó")
- `system_unit` — `g`/`ml`/`piece`
- `conversion_factor` — hệ số tại thời điểm đặt
- `system_total` — tổng hệ thống = `quantity × conversion_factor`

**Lý do**: nếu sau này admin đổi đơn vị/hệ số của sản phẩm (vd: bó từ 100g → 120g), các đơn cũ vẫn giữ đúng giá trị quy đổi — không bị sai lệch lịch sử. Báo cáo doanh thu/tồn kho theo đơn hàng dựa vào snapshot, không dựa vào giá trị hiện tại của sản phẩm.

> **Cảnh báo bảo mật**: Backend luôn lookup đơn vị từ DB khi tạo order item — không tin payload từ client. Đã có test `test_checkout_does_not_trust_client_unit_payload` đảm bảo điều này.

---

## 6. Quy trình đổi đơn vị / hệ số đang chạy

Nếu phải đổi `conversion_factor` (vd: nhà cung cấp đổi quy cách bó 100g → 150g):

1. Vào sửa sản phẩm → nhập hệ số mới → lưu.
2. **Sản phẩm**: tồn kho `stock` không tự đổi (vẫn là số lượng bó). Quy đổi hiển thị trên trang tồn kho sẽ dùng hệ số mới.
3. **Đơn hàng cũ**: vẫn giữ snapshot cũ — báo cáo lịch sử không thay đổi.
4. **Đơn hàng mới**: dùng hệ số mới từ DB.

> Nếu muốn báo cáo "đồng bộ" toàn bộ về quy cách mới, cần script migration cập nhật `system_total` & `conversion_factor` trên `zalo_order_items`. Hiện tại mặc định **không làm** vì sẽ mất tính chính xác lịch sử.

---

## 7. Câu hỏi thường gặp

**Q: Tại sao tổng "khối lượng" báo cáo lại nhỏ hơn tổng "đếm" sản phẩm trên trang tồn kho?**
A: Trang tồn kho gộp thô số lượng bó/hộp; báo cáo quy đổi nhân với `conversion_factor` rồi gom theo `system_unit` — chỉ cộng các sản phẩm cùng nhóm khối lượng. Sản phẩm bán theo cái (`piece`) không được cộng vào kg.

**Q: Một sản phẩm có thể có nhiều đơn vị bán (vd: vừa bán theo bó vừa theo kg) không?**
A: Hiện tại 1 sản phẩm = 1 đơn vị bán. Nếu cần, nên tạo 2 sản phẩm riêng ("Rau muống bó", "Rau muống kg") cùng category, khác đơn vị/hệ số.

**Q: Customer thấy gì khi đặt hàng?**
A: Trên trang chi tiết sản phẩm có ô +/− chọn số lượng và dòng phụ "1 bó ≈ 100g". Trong giỏ hàng và đơn hàng hiển thị `3 bó (300g)`. Sản phẩm legacy hiện `x3`.

**Q: Đơn vị nào thì hiện ở dạng quy đổi (kg/l)?**
A: Khi tổng quy đổi ≥ 1000 g sẽ thành kg, ≥ 1000 ml thành l. Dưới ngưỡng vẫn hiện g/ml.
