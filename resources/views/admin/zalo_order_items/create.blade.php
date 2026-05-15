@extends('layouts.main')

@section('content')
    <div class="card">
        <div class="card-header">
            <h4>Thêm sản phẩm vào đơn hàng</h4>
        </div>
        <div class="card-body">
            <form action="{{ route('zalo-order-items.store') }}" method="POST">
                @csrf
                <input type="hidden" name="order_id" value="{{ $order?->id }}">
                <div class="mb-3">
                    <label class="form-label">Tên sản phẩm</label>
                    <input type="text" name="name" class="form-control" required>
                </div>
                <div class="mb-3">
                    <label class="form-label">Đơn giá</label>
                    <input type="number" name="price" class="form-control" required>
                </div>
                <div class="mb-3">
                    <label class="form-label">Số lượng</label>
                    <input type="number" name="quantity" class="form-control" value="1" required>
                </div>
                <button class="btn btn-primary">Thêm</button>
                <a href="{{ route('zalo-orders.index') }}" class="btn btn-secondary">Huỷ</a>
            </form>
        </div>
    </div>
@endsection
