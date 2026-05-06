# Vietponics — Seed Data: Categories & Products

> File này mô tả đầy đủ 10 danh mục và toàn bộ sản phẩm để tạo Laravel Seeder cho bảng `zalo_categories` và `zalo_products`.

---

## Schema tham khảo

```
zalo_categories: id (bigint PK), name (string), image (string nullable)
zalo_products:   id (bigint PK), category_id (bigint FK), name (string),
                 price (bigint), original_price (bigint nullable),
                 image (string nullable), detail (text nullable)
```

---

## Yêu cầu khi tạo Seeder

- Class name: `ZaloCategoryProductSeeder`
- File: `database/seeders/ZaloCategoryProductSeeder.php`
- Dùng `DB::table()->insertOrIgnore()` hoặc `upsert()` để tránh lỗi trùng lặp
- Xóa dữ liệu cũ trước khi seed: `DB::table('zalo_products')->truncate()` rồi `DB::table('zalo_categories')->truncate()`
- Tắt foreign key check trước truncate, bật lại sau
- Trường `image` để `null` cho tất cả (sẽ upload ảnh thực tế sau)
- Đăng ký seeder này trong `DatabaseSeeder.php`

---

## Categories (10 danh mục)

| id | name                        |
|----|-----------------------------|
| 1  | Rau lá & rau thủy canh      |
| 2  | Trái cây ôn đới             |
| 3  | Hoa tươi & hoa chậu         |
| 4  | Cà phê & chè                |
| 5  | Rau củ quả ôn đới           |
| 6  | Nấm & rau đặc sản           |
| 7  | Trái cây nhiệt đới          |
| 8  | Dược liệu & thảo mộc        |
| 9  | Hạt & ngũ cốc               |
| 10 | Nông sản chế biến           |

---

## Products

### Category 1 — Rau lá & rau thủy canh

| id | name                    | price   | original_price | detail                                                              |
|----|-------------------------|---------|----------------|---------------------------------------------------------------------|
| 1  | Xà lách lolo xanh       | 25000   | 28000          | Xà lách lolo xanh thủy canh Đà Lạt, giòn tươi, không thuốc trừ sâu. Đóng gói 200g. |
| 2  | Xà lách lolo đỏ         | 28000   | 32000          | Xà lách lolo đỏ thủy canh, màu sắc đẹp, giàu anthocyanin. Đóng gói 200g. |
| 3  | Xà lách romaine         | 30000   | 35000          | Xà lách romaine (cos) thủy canh Đà Lạt, lá giòn dài, lý tưởng làm Caesar salad. |
| 4  | Xà lách butter          | 32000   | 36000          | Xà lách butter (bơ) mềm mịn, thủy canh sạch, vị ngọt nhẹ. Đóng gói 200g. |
| 5  | Cải ngọt thủy canh      | 18000   | 22000          | Cải ngọt thủy canh Đà Lạt, tươi mướt, ngọt tự nhiên. Đóng gói 300g. |
| 6  | Cải xanh                | 15000   | 18000          | Cải xanh thủy canh sạch, giàu vitamin K và canxi. Đóng gói 300g. |
| 7  | Cải thìa                | 20000   | 24000          | Cải thìa (bok choy) thủy canh, thân trắng giòn, lá xanh mướt. Đóng gói 300g. |
| 8  | Bó xôi (spinach)        | 35000   | 40000          | Bó xôi thủy canh Đà Lạt, giàu sắt và folate. Đóng gói 200g. |
| 9  | Rau muống thủy canh     | 15000   | 18000          | Rau muống thủy canh sạch, ngọn non giòn. Đóng gói 300g. |
| 10 | Húng quế               | 20000   | 25000          | Húng quế tươi Đà Lạt, thơm đậm đà, dùng cho pizza, pasta, salad. Đóng gói 100g. |
| 11 | Húng lủi               | 18000   | 22000          | Húng lủi (spearmint) tươi, mùi thơm dịu, dùng pha trà hoặc nấu ăn. Đóng gói 100g. |
| 12 | Ngò rí                 | 12000   | 15000          | Ngò rí (rau mùi) tươi Đà Lạt, thơm đặc trưng, dùng để garnish. Đóng gói 100g. |
| 13 | Tía tô                 | 12000   | 15000          | Tía tô tươi, lá tím xanh hai mặt, dùng trong ẩm thực Việt và Nhật. Đóng gói 100g. |
| 14 | Rau cải baby mix        | 45000   | 50000          | Hỗn hợp rau cải baby: cải ngọt, cải đỏ, mustard green. Đóng gói 150g. |
| 15 | Arugula                | 40000   | 45000          | Arugula (rocket) thủy canh Đà Lạt, vị đắng nhẹ kiểu Ý, dùng làm salad cao cấp. |

---

### Category 2 — Trái cây ôn đới

| id | name                  | price   | original_price | detail                                                              |
|----|-----------------------|---------|----------------|---------------------------------------------------------------------|
| 16 | Dâu tây Đà Lạt        | 85000   | 95000          | Dâu tây Đà Lạt tươi, trồng theo tiêu chuẩn VietGAP. Hộp 500g. |
| 17 | Hồng giòn             | 60000   | 70000          | Hồng giòn Đà Lạt, vỏ mỏng, ruột giòn ngọt, không chát. 500g. |
| 18 | Hồng mềm              | 55000   | 65000          | Hồng mềm chín cây Đà Lạt, ngọt thanh, giàu vitamin C. 500g. |
| 19 | Kiwi berry            | 120000  | 135000         | Kiwi berry mini Đà Lạt, ăn cả vỏ, ngọt như kiwi thu nhỏ. Hộp 250g. |
| 20 | Mận hậu               | 50000   | 60000          | Mận hậu tươi, vị chua ngọt, trái to đều. 500g. |
| 21 | Đào tiên              | 75000   | 85000          | Đào tiên Đà Lạt, thơm mùi đặc trưng, thịt vàng giòn. 500g. |
| 22 | Lê Việt Nam           | 65000   | 75000          | Lê Việt Nam trồng tại Đà Lạt, giòn mọng nước, vị ngọt thanh. 500g. |
| 23 | Táo Anna              | 70000   | 80000          | Táo Anna Đà Lạt, vỏ đỏ hồng, thịt trắng giòn, vị ngọt nhẹ chua. 500g. |
| 24 | Việt quất             | 150000  | 170000         | Việt quất (blueberry) Đà Lạt tươi, giàu antioxidant. Hộp 250g. |
| 25 | Cherry Đà Lạt         | 180000  | 200000         | Cherry Đà Lạt chín đỏ, ngọt đậm, hạt nhỏ. Hộp 250g. |
| 26 | Dâu tằm               | 45000   | 55000          | Dâu tằm tươi Đà Lạt, màu tím đẹp, vị chua ngọt. Hộp 300g. |

---

### Category 3 — Hoa tươi & hoa chậu

| id | name                  | price   | original_price | detail                                                              |
|----|-----------------------|---------|----------------|---------------------------------------------------------------------|
| 27 | Hoa hồng cắt cành     | 35000   | 40000          | Hoa hồng Đà Lạt cắt cành tươi, bó 10 cành, nhiều màu tùy chọn. |
| 28 | Hoa cúc vàng          | 25000   | 30000          | Hoa cúc vàng Đà Lạt, bó 10 cành, tươi lâu 7-10 ngày. |
| 29 | Hoa cúc tím           | 28000   | 33000          | Hoa cúc tím cắt cành Đà Lạt, sang trọng, bó 10 cành. |
| 30 | Hoa lily              | 55000   | 65000          | Hoa lily Đà Lạt, cành dài nụ to, thơm nhẹ. Bó 3 cành. |
| 31 | Hoa cẩm chướng        | 30000   | 35000          | Hoa cẩm chướng Đà Lạt, màu đa dạng (đỏ, trắng, hồng). Bó 10 cành. |
| 32 | Hoa cát tường         | 45000   | 55000          | Hoa cát tường (eustoma) Đà Lạt, cánh mỏng sang trọng. Bó 5 cành. |
| 33 | Hoa lan hồ điệp       | 180000  | 220000         | Lan hồ điệp Đà Lạt, chậu 2 cành, màu trắng hoặc tím. |
| 34 | Hoa đồng tiền         | 25000   | 30000          | Hoa đồng tiền Đà Lạt, bó 10 cành, nhiều màu. Tươi lâu. |
| 35 | Hoa baby              | 20000   | 25000          | Hoa baby (baby's breath) trắng tinh, dùng làm nền bó hoa. Bó nhỏ. |
| 36 | Chậu hoa hồng mini    | 120000  | 150000         | Hoa hồng mini trồng chậu, nhiều nụ, màu đỏ/hồng/vàng. Chậu 15cm. |
| 37 | Chậu lavender         | 95000   | 120000         | Lavender Đà Lạt trồng chậu, thơm dịu, trang trí không gian. |
| 38 | Chậu xương rồng       | 65000   | 80000          | Xương rồng mini Đà Lạt, nhiều hình dạng, dễ chăm sóc. Chậu nhỏ. |

---

### Category 4 — Cà phê & chè

| id | name                      | price   | original_price | detail                                                              |
|----|---------------------------|---------|----------------|---------------------------------------------------------------------|
| 39 | Cà phê Arabica Đà Lạt     | 180000  | 200000         | Cà phê Arabica nguyên chất Cầu Đất - Đà Lạt, rang mộc, hương hoa quả. Túi 250g. |
| 40 | Cà phê Robusta Tây Nguyên | 120000  | 140000         | Cà phê Robusta Tây Nguyên rang mộc, vị đắng mạnh, hậu vị lâu. Túi 250g. |
| 41 | Cà phê chồn               | 850000  | 1000000        | Cà phê chồn nguyên chất, hương thơm phức tạp, vị nhẹ thanh tao. Túi 100g. |
| 42 | Chè ô long Đà Lạt         | 250000  | 280000         | Chè ô long Cầu Đất Đà Lạt, lên men nhẹ, hương hoa quả đặc trưng. Hộp 100g. |
| 43 | Chè xanh Cầu Đất          | 150000  | 175000         | Chè xanh hái tay Cầu Đất, vị chát thanh, giàu catechin. Túi 100g. |
| 44 | Trà hoa cúc               | 80000   | 95000          | Hoa cúc Đà Lạt sấy khô, pha trà thơm dịu, giúp thư giãn. Hộp 50g. |
| 45 | Trà atiso Đà Lạt          | 75000   | 90000          | Atiso Đà Lạt sấy khô, mát gan, giải nhiệt. Túi 100g. |
| 46 | Chè đen                   | 120000  | 140000         | Chè đen Lâm Đồng, lên men hoàn toàn, pha với sữa rất hợp. Túi 100g. |
| 47 | Cà phê hạt rang           | 160000  | 185000         | Cà phê hạt rang nguyên chất Đà Lạt, pha máy hoặc phin. Túi 250g. |
| 48 | Cà phê bột                | 140000  | 165000         | Cà phê bột xay sẵn Đà Lạt, thơm nồng, dùng ngay với phin. Túi 250g. |

---

### Category 5 — Rau củ quả ôn đới

| id | name                       | price  | original_price | detail                                                              |
|----|----------------------------|--------|----------------|---------------------------------------------------------------------|
| 49 | Cà chua bi                 | 30000  | 35000          | Cà chua bi Đà Lạt, vỏ bóng căng, vị ngọt chua cân bằng. 500g. |
| 50 | Cà chua beef               | 25000  | 30000          | Cà chua beef Đà Lạt, trái to đỏ đều, ít hạt, nhiều thịt. 500g. |
| 51 | Ớt chuông đỏ               | 40000  | 48000          | Ớt chuông đỏ Đà Lạt, ngọt, giòn, giàu vitamin C. 500g. |
| 52 | Ớt chuông vàng             | 42000  | 50000          | Ớt chuông vàng Đà Lạt, vị ngọt hơn ớt đỏ, màu vàng đẹp. 500g. |
| 53 | Ớt chuông xanh             | 35000  | 42000          | Ớt chuông xanh Đà Lạt, vị đắng nhẹ, giòn, thích hợp xào. 500g. |
| 54 | Khoai tây Đà Lạt           | 22000  | 28000          | Khoai tây Đà Lạt trồng sạch, ruột vàng, hàm lượng tinh bột cao. 1kg. |
| 55 | Atiso                      | 45000  | 55000          | Atiso Đà Lạt tươi, bông to, luộc hoặc nấu canh đều ngon. 3 bông. |
| 56 | Bí đỏ                      | 20000  | 25000          | Bí đỏ Đà Lạt, ruột vàng đậm, vị ngọt béo. 1kg. |
| 57 | Súp lơ trắng               | 30000  | 36000          | Súp lơ trắng Đà Lạt, bông dày trắng tinh, giàu chất xơ. 1 bông ~500g. |
| 58 | Súp lơ xanh (broccoli)     | 35000  | 42000          | Broccoli Đà Lạt, xanh tươi, hàm lượng dinh dưỡng cao. 1 bông ~400g. |
| 59 | Cà rốt Đà Lạt              | 18000  | 22000          | Cà rốt Đà Lạt, củ thẳng đỏ đẹp, ngọt, giàu beta-carotene. 500g. |
| 60 | Củ cải trắng               | 15000  | 18000          | Củ cải trắng Đà Lạt, giòn tươi, dùng nấu canh hoặc muối chua. 500g. |
| 61 | Hành tây                   | 20000  | 25000          | Hành tây Đà Lạt, củ to tròn, ít cay, thơm. 500g. |
| 62 | Tỏi tươi                   | 30000  | 35000          | Tỏi tươi Lý Sơn hoặc Phan Rang, nhánh to, thơm nồng. 200g. |
| 63 | Bắp cải xanh               | 18000  | 22000          | Bắp cải xanh Đà Lạt, cuộn chặt, lá giòn tươi. 1 bắp ~800g. |
| 64 | Bắp cải tím                | 25000  | 30000          | Bắp cải tím Đà Lạt, giàu anthocyanin, đẹp mắt. 1 bắp ~700g. |

---

### Category 6 — Nấm & rau đặc sản

| id | name                      | price  | original_price | detail                                                              |
|----|---------------------------|--------|----------------|---------------------------------------------------------------------|
| 65 | Nấm đông cô tươi          | 65000  | 75000          | Nấm đông cô tươi, nụ dày, mũ nứt vân, thơm đặc trưng. 200g. |
| 66 | Nấm đông cô khô           | 120000 | 140000         | Nấm đông cô khô tự nhiên, hương thơm cô đặc, dùng nấu lẩu, kho. 100g. |
| 67 | Nấm linh chi              | 180000 | 220000         | Nấm linh chi đỏ sấy khô, tốt cho gan và miễn dịch. 100g. |
| 68 | Nấm kim châm              | 30000  | 35000          | Nấm kim châm trắng tươi, giòn ngon, dùng lẩu hoặc xào. Gói 200g. |
| 69 | Nấm bào ngư xám           | 40000  | 48000          | Nấm bào ngư xám tươi, thịt dày, vị ngọt umami. 300g. |
| 70 | Nấm bào ngư trắng         | 42000  | 50000          | Nấm bào ngư trắng tươi, mềm mịn, phù hợp xào, canh. 300g. |
| 71 | Nấm rơm                   | 35000  | 42000          | Nấm rơm tươi nụ tròn căng, chưa nở, thơm ngọt. 300g. |
| 72 | Nấm truffle               | 950000 | 1100000        | Nấm truffle đen Đà Lạt, thơm nồng đặc trưng, dùng cho ẩm thực cao cấp. 50g. |
| 73 | Rau sam                   | 20000  | 25000          | Rau sam Đà Lạt, vị chua nhẹ, giàu omega-3. Dùng xào tỏi. 200g. |
| 74 | Cải mầm                   | 35000  | 40000          | Cải mầm (microgreens) hỗn hợp, giàu enzyme và vitamin. Hộp 100g. |
| 75 | Giá đỗ                    | 12000  | 15000          | Giá đỗ trắng sạch, giòn, ươm trong điều kiện kiểm soát. 300g. |
| 76 | Rau mầm hướng dương       | 40000  | 48000          | Rau mầm hướng dương Đà Lạt, giòn ngọt, giàu protein. Hộp 100g. |

---

### Category 7 — Trái cây nhiệt đới

| id | name                      | price   | original_price | detail                                                              |
|----|---------------------------|---------|----------------|---------------------------------------------------------------------|
| 77 | Bơ booth Đà Lạt           | 55000   | 65000          | Bơ booth Đà Lạt, hạt nhỏ thịt dày, béo ngậy. 500g (~2 trái). |
| 78 | Bơ sáp                    | 45000   | 55000          | Bơ sáp Đắk Lắk, dẻo mịn, béo ngậy, sắc vàng đặc trưng. 500g. |
| 79 | Sầu riêng Ri6             | 180000  | 210000         | Sầu riêng Ri6 Tiền Giang, cơm vàng dày, hạt lép, thơm đậm. 1kg cơm. |
| 80 | Sầu riêng Monthong        | 220000  | 250000         | Sầu riêng Monthong Thái (trồng Việt Nam), cơm dai khô, vị ngọt dịu. 1kg cơm. |
| 81 | Xoài cát Hòa Lộc          | 70000   | 85000          | Xoài cát Hòa Lộc Tiền Giang, sợi nhỏ, thơm đậm, ngọt lịm. 1kg. |
| 82 | Thanh long ruột đỏ        | 35000   | 42000          | Thanh long ruột đỏ Bình Thuận, ngọt mát, giàu lycopene. 1kg. |
| 83 | Thanh long ruột trắng     | 28000   | 35000          | Thanh long ruột trắng Bình Thuận, mướt ngọt, giải nhiệt. 1kg. |
| 84 | Chuối tiêu                | 25000   | 30000          | Chuối tiêu Khánh Hòa, nải nhỏ trái thơm, ngọt đậm. 1 nải ~1kg. |
| 85 | Dứa MD2                   | 45000   | 55000          | Dứa MD2 (siêu ngọt) không mắt, vị ngọt thuần, không chua. 1 trái. |
| 86 | Ổi lê Đài Loan            | 30000   | 38000          | Ổi lê Đài Loan giòn ngọt, ít hạt, trái to tròn. 500g. |
| 87 | Mãng cầu                  | 65000   | 80000          | Mãng cầu dai (custard apple) Tây Ninh, thịt trắng dẻo ngọt. 500g. |
| 88 | Nhãn lồng                 | 50000   | 60000          | Nhãn lồng Hưng Yên, cùi dày trong, ngọt thơm. 500g. |

---

### Category 8 — Dược liệu & thảo mộc

| id | name                  | price   | original_price | detail                                                              |
|----|-----------------------|---------|----------------|---------------------------------------------------------------------|
| 89 | Atiso tươi            | 45000   | 55000          | Atiso Đà Lạt tươi, dùng nấu nước uống hoặc canh giải nhiệt. 3 bông. |
| 90 | Đương quy             | 120000  | 145000         | Đương quy khô Đà Lạt, bồi huyết, tốt cho phụ nữ. 100g thái lát. |
| 91 | Sâm Ngọc Linh         | 2500000 | 3000000        | Sâm Ngọc Linh tươi Kon Tum, loại thượng phẩm, chứng nhận nguồn gốc. 10g. |
| 92 | Đinh lăng             | 85000   | 100000         | Rễ đinh lăng khô, tăng cường thể lực, giảm stress. 100g. |
| 93 | Nghệ vàng             | 40000   | 50000          | Nghệ vàng tươi Đà Lạt, củ to mập, curcumin cao. 500g. |
| 94 | Gừng tươi             | 25000   | 30000          | Gừng tươi Đà Lạt, củ già nhánh nhỏ, cay nồng. 500g. |
| 95 | Sả tươi               | 15000   | 18000          | Sả tươi Đà Lạt, thơm tinh dầu, dùng nấu ăn và xông hơi. 5 cây. |
| 96 | Lá stevia             | 50000   | 60000          | Lá stevia tươi Đà Lạt, ngọt tự nhiên, không calo. 50g. |
| 97 | Bạc hà tươi           | 18000   | 22000          | Bạc hà tươi Đà Lạt, mùi thơm mát, dùng pha trà hoặc garnish. 100g. |
| 98 | Lavender khô          | 75000   | 90000          | Lavender Đà Lạt sấy khô, thơm dịu, dùng trang trí và pha trà. 30g. |
| 99 | Mắc ca               | 180000  | 220000         | Hạt mắc ca Lâm Đồng rang bơ nhạt, béo giòn bùi. 200g. |
| 100| Hoa cúc khô           | 55000   | 65000          | Hoa cúc vàng sấy khô Đà Lạt, pha trà giải nhiệt sáng mắt. 50g. |

---

### Category 9 — Hạt & ngũ cốc

| id  | name                  | price  | original_price | detail                                                              |
|-----|-----------------------|--------|----------------|---------------------------------------------------------------------|
| 101 | Hạt điều rang muối    | 150000 | 175000         | Hạt điều Bình Phước rang muối nhạt, giòn bùi. Túi 200g. |
| 102 | Hạt điều trắng        | 160000 | 185000         | Hạt điều Bình Phước sấy bơ trắng, vị béo nguyên chất. 200g. |
| 103 | Hạt tiêu đen          | 55000  | 65000          | Hạt tiêu đen Phú Quốc, cay nồng thơm đặc trưng. 100g. |
| 104 | Hạt tiêu đỏ           | 80000  | 95000          | Hạt tiêu đỏ Phú Quốc chín hoàn toàn, cay nhẹ hơn tiêu đen. 50g. |
| 105 | Hạt mắc ca            | 180000 | 220000         | Hạt mắc ca Lâm Đồng nguyên vỏ, béo giòn, giàu omega-7. 200g. |
| 106 | Gạo ST25              | 75000  | 85000          | Gạo ST25 Sóc Trăng, giải nhất thế giới, thơm dẻo đặc biệt. 2kg. |
| 107 | Gạo nếp cẩm           | 45000  | 55000          | Gạo nếp cẩm Điện Biên, màu tím đen tự nhiên, giàu anthocyanin. 1kg. |
| 108 | Đậu xanh              | 30000  | 36000          | Đậu xanh cà vỏ sạch, dùng nấu chè, xôi, bánh. 500g. |
| 109 | Đậu đỏ                | 32000  | 38000          | Đậu đỏ hữu cơ, dùng nấu chè hoặc làm nhân bánh. 500g. |
| 110 | Hạt chia              | 85000  | 100000         | Hạt chia Mexico nhập khẩu, giàu omega-3 và chất xơ. 200g. |
| 111 | Yến mạch              | 60000  | 72000          | Yến mạch cán dẹp Úc nhập khẩu, dùng ăn sáng healthy. 500g. |

---

### Category 10 — Nông sản chế biến

| id  | name                      | price   | original_price | detail                                                              |
|-----|---------------------------|---------|----------------|---------------------------------------------------------------------|
| 112 | Hồng treo gió             | 180000  | 220000         | Hồng treo gió Đà Lạt theo công nghệ Nhật Bản, ngọt dẻo không chất bảo quản. Hộp 500g. |
| 113 | Mứt dâu tây               | 85000   | 100000         | Mứt dâu tây Đà Lạt nguyên trái, ít đường, không phẩm màu. Hũ 250g. |
| 114 | Dâu tây sấy dẻo           | 95000   | 115000         | Dâu tây sấy dẻo Đà Lạt, giữ nguyên vị, không chất bảo quản. Túi 100g. |
| 115 | Cà phê hòa tan            | 120000  | 140000         | Cà phê hòa tan Arabica Đà Lạt, 3in1 ít đường. Hộp 20 gói. |
| 116 | Chè atiso đóng gói        | 65000   | 80000          | Atiso Đà Lạt sấy sạch, đóng túi lọc tiện lợi, uống mát gan. Hộp 20 túi. |
| 117 | Rau sấy lạnh              | 75000   | 90000          | Hỗn hợp rau Đà Lạt sấy lạnh, giữ dinh dưỡng 95%. Túi 100g. |
| 118 | Khoai tây sấy             | 45000   | 55000          | Khoai tây Đà Lạt sấy giòn vị muối, không dầu chiên. Túi 80g. |
| 119 | Mứt hoa hồng              | 95000   | 115000         | Mứt cánh hoa hồng Đà Lạt, thơm ngọt dịu, dùng với bánh mì. Hũ 200g. |
| 120 | Trà hoa cúc đóng gói      | 70000   | 85000          | Hoa cúc Đà Lạt sấy đóng túi lọc, thơm tự nhiên. Hộp 20 túi. |
| 121 | Bơ đông lạnh              | 80000   | 95000          | Bơ Đà Lạt xay nhuyễn đông lạnh, dùng làm sinh tố, bánh mì. Túi 300g. |
| 122 | Nước ép dâu               | 55000   | 65000          | Nước ép dâu tây Đà Lạt nguyên chất, không đường, không chất bảo quản. Chai 300ml. |
| 123 | Rượu vang dâu             | 250000  | 300000         | Rượu vang dâu tây Đà Lạt, lên men tự nhiên, độ cồn 12%. Chai 750ml. |
| 124 | Mật ong Đà Lạt            | 180000  | 220000         | Mật ong hoa cà phê Đà Lạt, nguyên chất, không pha trộn. Hũ 500g. |
| 125 | Nấm đông cô sấy           | 130000  | 155000         | Nấm đông cô sấy khô nguyên nụ Đà Lạt, hương thơm đậm đặc. Túi 100g. |

---

## Tổng kết

| Category | Số sản phẩm |
|----------|-------------|
| 1. Rau lá & rau thủy canh | 15 |
| 2. Trái cây ôn đới | 11 |
| 3. Hoa tươi & hoa chậu | 12 |
| 4. Cà phê & chè | 10 |
| 5. Rau củ quả ôn đới | 16 |
| 6. Nấm & rau đặc sản | 12 |
| 7. Trái cây nhiệt đới | 12 |
| 8. Dược liệu & thảo mộc | 12 |
| 9. Hạt & ngũ cốc | 11 |
| 10. Nông sản chế biến | 14 |
| **Tổng** | **125 sản phẩm** |

---

## Prompt gợi ý cho Claude Code trong VS Code

Paste prompt sau vào Claude Code:

```
Tạo Laravel Seeder tên ZaloCategoryProductSeeder dựa trên file vietponics_seed_data.md này.

Yêu cầu:
1. Class: ZaloCategoryProductSeeder trong database/seeders/
2. Dùng DB::statement để tắt foreign key check trước khi truncate
3. Truncate zalo_products trước, sau đó zalo_categories
4. Bật lại foreign key check sau truncate
5. Insert tất cả 10 categories với đúng id và name, image = null
6. Insert tất cả 125 products với đúng id, category_id, name, price, original_price, image = null, detail
7. Dùng DB::table()->insert() với array chunk để tối ưu performance
8. Đăng ký seeder trong DatabaseSeeder.php
9. Thêm comment section header cho từng category trong seeder để dễ đọc
```
