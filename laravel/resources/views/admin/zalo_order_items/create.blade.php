@extends('layouts.main')

@section('content')
    <div class="card">
        <div class="card-header">
            <h4>Add Order Item</h4>
        </div>
        <div class="card-body">
            <form action="{{ route('zalo-order-items.store') }}" method="POST">
                @csrf
                <input type="hidden" name="order_id" value="{{ $order?->id }}">
                <div class="mb-3">
                    <label class="form-label">Name</label>
                    <input type="text" name="name" class="form-control" required>
                </div>
                <div class="mb-3">
                    <label class="form-label">Price</label>
                    <input type="number" name="price" class="form-control" required>
                </div>
                <div class="mb-3">
                    <label class="form-label">Quantity</label>
                    <input type="number" name="quantity" class="form-control" value="1" required>
                </div>
                <button class="btn btn-primary">Add</button>
                <a href="{{ route('zalo-orders.index') }}" class="btn btn-secondary">Cancel</a>
            </form>
        </div>
    </div>
@endsection
