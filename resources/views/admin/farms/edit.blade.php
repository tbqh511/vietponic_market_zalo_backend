@extends('layouts.main')

@section('content')
    <div class="card">
        <div class="card-header d-flex justify-content-between align-items-center">
            <h4 class="mb-0">Sửa Farm: <code>{{ $farm->code }}</code></h4>
            <a href="{{ route('farms.show', $farm->id) }}" class="btn btn-light btn-sm">Quay lại</a>
        </div>
        <div class="card-body">
            @if($errors->any())
                <div class="alert alert-danger">
                    <ul class="mb-0">
                        @foreach($errors->all() as $e)
                            <li>{{ $e }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif

            <form action="{{ route('farms.update', $farm->id) }}" method="POST">
                @csrf
                @method('PUT')

                <div class="mb-3">
                    <label class="form-label">Tên Farm <span class="text-danger">*</span></label>
                    <input type="text" name="name" class="form-control" value="{{ old('name', $farm->name) }}" required maxlength="150">
                </div>
                <div class="mb-3">
                    <label class="form-label">Mô tả</label>
                    <textarea name="description" class="form-control" rows="3" maxlength="1000">{{ old('description', $farm->description) }}</textarea>
                </div>
                <div class="mb-3">
                    <label class="form-label">Địa chỉ</label>
                    <input type="text" name="address" class="form-control" value="{{ old('address', $farm->address) }}" maxlength="255">
                </div>

                <div class="row g-3">
                    <div class="col-md-4">
                        <label class="form-label">Tỷ lệ hoa hồng (0..1) <span class="text-danger">*</span></label>
                        <input type="number" step="0.0001" min="0" max="1" name="commission_rate"
                               class="form-control" value="{{ old('commission_rate', $farm->commission_rate) }}" required>
                        <small class="text-muted">Vd: 0.85 = farm nhận 85% doanh thu</small>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Chu kỳ thanh toán <span class="text-danger">*</span></label>
                        <select name="payment_cycle" class="form-select" required>
                            @foreach(['weekly' => 'Hàng tuần', 'biweekly' => '2 tuần/lần', 'monthly' => 'Hàng tháng'] as $val => $label)
                                <option value="{{ $val }}" {{ old('payment_cycle', $farm->payment_cycle) === $val ? 'selected' : '' }}>{{ $label }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Trạng thái <span class="text-danger">*</span></label>
                        <select name="is_active" class="form-select" required>
                            <option value="1" {{ old('is_active', $farm->is_active) ? 'selected' : '' }}>Đang hoạt động</option>
                            <option value="0" {{ !old('is_active', $farm->is_active) ? 'selected' : '' }}>Tạm khoá</option>
                        </select>
                    </div>
                </div>

                <hr>
                <p class="text-muted small">
                    <strong>Không cho sửa:</strong> Mã Farm (<code>{{ $farm->code }}</code>) và Owner
                    ({{ $farm->owner->name ?? '—' }} #{{ $farm->owner_customer_id ?? '—' }}) —
                    nếu cần đổi, làm action chuyển chủ riêng.
                </p>

                <div class="d-flex gap-2 mt-3">
                    <button type="submit" class="btn btn-primary">Lưu thay đổi</button>
                    <a href="{{ route('farms.show', $farm->id) }}" class="btn btn-light">Huỷ</a>
                </div>
            </form>
        </div>
    </div>
@endsection
