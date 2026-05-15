@extends('layouts.main')

@section('content')
    <div class="card">
        <div class="card-header">
            <h4>Sửa đơn hàng #{{ $order->id }}</h4>
        </div>
        <div class="card-body">
            @if($errors->any())
                <div class="alert alert-danger">
                    <ul>
                        @foreach($errors->all() as $e)
                            <li>{{ $e }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif
            <form action="{{ route('zalo-orders.update', $order->id) }}" method="POST">
                @csrf
                @method('PUT')
                <div class="mb-3">
                    <label class="form-label">Trạng thái</label>
                    <input type="text" name="status" class="form-control" value="{{ old('status', $order->status) }}">
                </div>
                <div class="mb-3">
                    <label class="form-label">Trạng thái thanh toán</label>
                    <input type="text" name="payment_status" class="form-control" value="{{ old('payment_status', $order->payment_status) }}">
                </div>
                <div class="mb-3">
                    <label class="form-label">Ngày nhận hàng</label>
                    <input type="datetime-local" name="received_at" class="form-control" value="{{ old('received_at', $order->received_at) }}">
                </div>
                <div class="mb-3">
                    <label class="form-label">Ghi chú</label>
                    <textarea name="note" class="form-control">{{ old('note', $order->note) }}</textarea>
                </div>
                <button class="btn btn-primary">Lưu</button>
                <a href="{{ route('zalo-orders.show', $order->id) }}" class="btn btn-secondary">Huỷ</a>
            </form>
        </div>
    </div>
@endsection
