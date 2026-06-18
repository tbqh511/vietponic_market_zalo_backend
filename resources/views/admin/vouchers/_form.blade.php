@csrf
<div class="row g-3">
    <div class="col-md-4">
        <label class="form-label">Mã <span class="text-danger">*</span></label>
        <input type="text" name="code" class="form-control text-uppercase" value="{{ old('code', $voucher->code) }}" required maxlength="50">
    </div>
    <div class="col-md-8">
        <label class="form-label">Tên hiển thị <span class="text-danger">*</span></label>
        <input type="text" name="name" class="form-control" value="{{ old('name', $voucher->name) }}" required maxlength="255">
    </div>
    <div class="col-12">
        <label class="form-label">Mô tả</label>
        <textarea name="description" class="form-control" rows="2" maxlength="1000">{{ old('description', $voucher->description) }}</textarea>
    </div>

    <div class="col-md-4">
        <label class="form-label">Loại giảm <span class="text-danger">*</span></label>
        <select name="discount_type" class="form-select" id="discount_type" required>
            @foreach([
                'percent'       => 'Phần trăm (%)',
                'fixed'         => 'Số tiền cố định',
                'free_shipping' => 'Miễn phí vận chuyển',
            ] as $val => $label)
                <option value="{{ $val }}" @selected(old('discount_type', $voucher->discount_type) === $val)>{{ $label }}</option>
            @endforeach
        </select>
    </div>
    <div class="col-md-4">
        <label class="form-label">Giá trị <span class="text-danger">*</span></label>
        <input type="number" step="0.01" min="0" name="discount_value" class="form-control" value="{{ old('discount_value', $voucher->discount_value ?? 0) }}" required>
        <small class="text-muted">% với loại percent, VND với fixed/free_shipping (0 = free toàn bộ ship)</small>
    </div>
    <div class="col-md-4">
        <label class="form-label">Giảm tối đa</label>
        <input type="number" min="0" name="max_discount_amount" class="form-control" value="{{ old('max_discount_amount', $voucher->max_discount_amount) }}">
        <small class="text-muted">VND. Bỏ trống = không cap. Áp dụng cho percent và free_shipping (khi giá trị = 0)</small>
    </div>

    <div class="col-md-4">
        <label class="form-label">Đơn tối thiểu</label>
        <input type="number" min="0" name="min_order_amount" class="form-control" value="{{ old('min_order_amount', $voucher->min_order_amount ?? 0) }}">
    </div>
    <div class="col-md-4">
        <label class="form-label">Tổng lượt dùng</label>
        <input type="number" min="0" name="max_uses" class="form-control" value="{{ old('max_uses', $voucher->max_uses) }}">
        <small class="text-muted">Bỏ trống = không giới hạn</small>
    </div>
    <div class="col-md-4">
        <label class="form-label">Lượt mỗi khách <span class="text-danger">*</span></label>
        <input type="number" min="0" name="max_uses_per_customer" class="form-control" value="{{ old('max_uses_per_customer', $voucher->max_uses_per_customer ?? 1) }}" required>
        <small class="text-muted">0 = không giới hạn</small>
    </div>

    <div class="col-md-6">
        <label class="form-label">Hiệu lực từ</label>
        <input type="date" name="valid_from" class="form-control"
               value="{{ old('valid_from', optional($voucher->valid_from)->format('Y-m-d')) }}">
        <small class="text-muted">Voucher sẽ có hiệu lực từ 00:00 ngày được chọn</small>
    </div>
    <div class="col-md-6">
        <label class="form-label">Hiệu lực đến</label>
        <input type="date" name="valid_to" class="form-control"
               value="{{ old('valid_to', optional($voucher->valid_to)->format('Y-m-d')) }}">
        <small class="text-muted">Voucher hết hiệu lực sau 23:59 ngày được chọn</small>
    </div>

    <div class="col-md-6">
        <div class="form-check form-switch">
            <input type="hidden" name="is_active" value="0">
            <input type="checkbox" name="is_active" value="1" class="form-check-input"
                   id="is_active" {{ old('is_active', $voucher->is_active ?? true) ? 'checked' : '' }}>
            <label for="is_active" class="form-check-label">Đang bật</label>
        </div>
    </div>
    <div class="col-md-6">
        <div class="form-check form-switch">
            <input type="hidden" name="is_public" value="0">
            <input type="checkbox" name="is_public" value="1" class="form-check-input"
                   id="is_public" {{ old('is_public', $voucher->is_public ?? true) ? 'checked' : '' }}>
            <label for="is_public" class="form-check-label">Hiển thị trong danh sách voucher khả dụng</label>
        </div>
    </div>
</div>

@if($errors->any())
    <div class="alert alert-danger mt-3">
        <ul class="mb-0">
            @foreach($errors->all() as $err)
                <li>{{ $err }}</li>
            @endforeach
        </ul>
    </div>
@endif
