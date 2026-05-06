@extends('layouts.main')

@section('content')
    <div class="card">
        <div class="card-header">
            <h4>Edit Order Item</h4>
        </div>
        <div class="card-body">
            <form action="{{ route('zalo-order-items.update', $item->id) }}" method="POST">
                @csrf
                @method('PUT')
                <div class="mb-3">
                    <label class="form-label">Name</label>
                    <input type="text" name="name" class="form-control" value="{{ old('name', $item->name) }}" required>
                </div>
                <div class="mb-3">
                    <label class="form-label">Price</label>
                    <input type="number" name="price" class="form-control" value="{{ old('price', $item->price) }}" required>
                </div>
                <div class="mb-3">
                    <label class="form-label">Quantity</label>
                    <input type="number" name="quantity" class="form-control" value="{{ old('quantity', $item->quantity) }}" required>
                </div>
                <button class="btn btn-primary">Save</button>
                <a href="{{ route('zalo-orders.show', $item->order_id) }}" class="btn btn-secondary">Cancel</a>
            </form>
        </div>
    </div>
@endsection
