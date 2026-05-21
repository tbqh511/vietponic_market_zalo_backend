@extends('layouts.main')

@section('content')
    <div class="card">
        <div class="card-header d-flex justify-content-between align-items-center">
            <h4 class="mb-0">Sửa mã giảm giá: <code>{{ $voucher->code }}</code></h4>
            <a href="{{ route('vouchers.show', $voucher->id) }}" class="btn btn-sm btn-secondary">Xem chi tiết</a>
        </div>
        <div class="card-body">
            <form action="{{ route('vouchers.update', $voucher->id) }}" method="POST">
                @method('PUT')
                @include('admin.vouchers._form')
                <div class="mt-4 d-flex gap-2">
                    <button type="submit" class="btn btn-primary">Lưu thay đổi</button>
                    <a href="{{ route('vouchers.index') }}" class="btn btn-secondary">Huỷ</a>
                    <form action="{{ route('vouchers.destroy', $voucher->id) }}" method="POST" class="ms-auto"
                          onsubmit="return confirm('Xoá mã này? Lịch sử đơn đã dùng vẫn được giữ.');">
                        @csrf
                        @method('DELETE')
                        <button type="submit" class="btn btn-danger">Xoá</button>
                    </form>
                </div>
            </form>
        </div>
    </div>
@endsection
