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
                    <tr><th>Name</th><th>Price</th><th>Qty</th><th>Subtotal</th><th>Actions</th></tr>
                </thead>
                <tbody>
                    @foreach($order->items as $it)
                        <tr>
                            <td>{{ $it->name }}</td>
                            <td>{{ number_format($it->price) }}</td>
                            <td>{{ $it->quantity }}</td>
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
