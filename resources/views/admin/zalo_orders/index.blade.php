@extends('layouts.main')

@section('content')
    <div class="card">
        <div class="card-header d-flex justify-content-between align-items-center">
            <h4>Đơn hàng</h4>
        </div>
        <div class="card-body">
            @if(session('success'))
                <div class="alert alert-success">{{ session('success') }}</div>
            @endif
            <table class="table">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Mã khách</th>
                        <th>Trạng thái</th>
                        <th>Thanh toán</th>
                        <th>Tổng tiền</th>
                        <th>Ngày đặt</th>
                        <th>Thao tác</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($orders as $o)
                        <tr>
                            <td>{{ $o->id }}</td>
                            <td>{{ $o->customer_id }}</td>
                            <td>{{ $o->status }}</td>
                            <td>{{ $o->payment_status }}</td>
                            <td>{{ number_format($o->total) }}</td>
                            <td>{{ $o->created_at }}</td>
                            <td>
                                <a href="{{ route('zalo-orders.show', $o->id) }}" class="btn btn-sm btn-primary">Xem</a>
                                <a href="{{ route('zalo-orders.edit', $o->id) }}" class="btn btn-sm btn-secondary">Sửa</a>
                                <form action="{{ route('zalo-orders.destroy', $o->id) }}" method="POST" style="display:inline-block" onsubmit="return confirm('Xoá đơn hàng này?')">
                                    @csrf
                                    @method('DELETE')
                                    <button class="btn btn-sm btn-danger">Xoá</button>
                                </form>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>
@endsection
