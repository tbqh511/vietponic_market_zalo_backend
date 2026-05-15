@extends('layouts.main')

@section('content')
    <div class="card">
        <div class="card-header d-flex justify-content-between align-items-center">
            <h4>Order #{{ $order->id }}</h4>
            <a href="{{ route('zalo-orders.edit', $order->id) }}" class="btn btn-secondary">Edit</a>
        </div>
        <div class="card-body">
            <h5>Delivery</h5>
            @if($order->delivery)
                <p>{{ $order->delivery->name }} - {{ $order->delivery->phone }}</p>
                <p>{{ $order->delivery->address }}</p>
            @else
                <p>No delivery info</p>
            @endif

            <h5>Items</h5>
            <table class="table">
                <thead>
                    <tr>
                        <th>Tên sản phẩm</th>
                        <th>Đơn giá</th>
                        <th>Số lượng</th>
                        <th>Quy đổi (hệ thống)</th>
                        <th>Thành tiền</th>
                        <th>Thao tác</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($order->items as $it)
                        <tr>
                            <td>{{ $it->name }}</td>
                            <td>{{ number_format($it->price) }}</td>
                            <td>
                                @if($it->unit_label)
                                    {{ $it->quantity }} {{ $it->unit_label }}
                                @else
                                    x{{ $it->quantity }}
                                @endif
                            </td>
                            <td class="small text-muted">
                                @if($it->system_total !== null && $it->system_unit && (float)$it->conversion_factor !== 1.0)
                                    {{ \App\Models\ZaloUnit::formatSystemTotal((float)$it->system_total, $it->system_unit) }}
                                @else
                                    —
                                @endif
                            </td>
                            <td>{{ number_format($it->price * $it->quantity) }}</td>
                            <td>
                                <a href="{{ route('zalo-order-items.edit', $it->id) }}" class="btn btn-sm btn-secondary">Edit</a>
                                <form action="{{ route('zalo-order-items.destroy', $it->id) }}" method="POST" style="display:inline-block" onsubmit="return confirm('Delete item?')">
                                    @csrf
                                    @method('DELETE')
                                    <button class="btn btn-sm btn-danger">Delete</button>
                                </form>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>

            <h5>Order summary</h5>
            <p>Total: {{ number_format($order->total) }}</p>
            <p>Status: {{ $order->status }} | Payment: {{ $order->payment_status }}</p>
        </div>
    </div>
@endsection
