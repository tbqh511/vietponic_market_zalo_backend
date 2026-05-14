@extends('layouts.main')

@section('content')

<div class="card">
    <div class="card-header d-flex justify-content-between align-items-center">
        <h4 class="mb-0">Sửa khách hàng #{{ $customer->id }}</h4>
        <a href="{{ route('zalo-customers.show', $customer->id) }}" class="btn btn-outline-secondary btn-sm">
            <i class="bi bi-arrow-left"></i> Quay lại
        </a>
    </div>
    <div class="card-body">

        @if ($errors->any())
            <div class="alert alert-danger">
                <ul class="mb-0">
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        {{-- Read-only system info --}}
        <div class="p-3 bg-light rounded mb-4">
            <small class="text-muted d-block"><strong>Firebase ID:</strong> {{ $customer->firebase_id ?: '—' }}</small>
            <small class="text-muted d-block"><strong>Loại đăng nhập:</strong> {{ $customer->logintype ?: '—' }}</small>
            <small class="text-muted d-block"><strong>Ngày tham gia:</strong> {{ $customer->created_at?->format('d/m/Y H:i') }}</small>
        </div>

        <form action="{{ route('zalo-customers.update', $customer->id) }}" method="POST">
            @csrf
            @method('PUT')

            <div class="mb-3">
                <label class="form-label fw-semibold">Họ tên</label>
                <input type="text" name="name" class="form-control" style="max-width:400px;"
                       value="{{ old('name', $customer->name) }}" placeholder="Nhập họ tên">
            </div>

            <div class="mb-3">
                <label class="form-label fw-semibold">Email</label>
                <input type="email" name="email" class="form-control" style="max-width:400px;"
                       value="{{ old('email', $customer->email) }}" placeholder="Nhập email">
            </div>

            <div class="mb-3">
                <label class="form-label fw-semibold">Số điện thoại</label>
                <input type="text" name="mobile" class="form-control" style="max-width:250px;"
                       value="{{ old('mobile', $customer->mobile) }}" placeholder="Nhập số điện thoại">
            </div>

            <div class="mb-3">
                <label class="form-label fw-semibold">Địa chỉ</label>
                <textarea name="address" class="form-control" rows="3" style="max-width:500px;"
                          placeholder="Nhập địa chỉ">{{ old('address', $customer->address) }}</textarea>
            </div>

            <div class="mb-4">
                <label class="form-label fw-semibold">Trạng thái tài khoản</label>
                <select name="isActive" class="form-select" style="max-width:200px;">
                    <option value="1" {{ old('isActive', $customer->isActive) == 1 ? 'selected' : '' }}>Hoạt động</option>
                    <option value="0" {{ old('isActive', $customer->isActive) == 0 ? 'selected' : '' }}>Tắt</option>
                </select>
            </div>

            <hr>

            <div class="d-flex gap-2">
                <button type="submit" class="btn btn-primary">
                    <i class="bi bi-save"></i> Lưu thay đổi
                </button>
                <a href="{{ route('zalo-customers.show', $customer->id) }}" class="btn btn-secondary">Huỷ</a>
            </div>
        </form>
    </div>
</div>

@endsection
