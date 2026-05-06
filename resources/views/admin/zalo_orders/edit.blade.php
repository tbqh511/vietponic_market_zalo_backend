@extends('layouts.main')

@section('content')
    <div class="card">
        <div class="card-header">
            <h4>Edit Order #{{ $order->id }}</h4>
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
                    <label class="form-label">Status</label>
                    <input type="text" name="status" class="form-control" value="{{ old('status', $order->status) }}">
                </div>
                <div class="mb-3">
                    <label class="form-label">Payment Status</label>
                    <input type="text" name="payment_status" class="form-control" value="{{ old('payment_status', $order->payment_status) }}">
                </div>
                <div class="mb-3">
                    <label class="form-label">Received At</label>
                    <input type="datetime-local" name="received_at" class="form-control" value="{{ old('received_at', $order->received_at) }}">
                </div>
                <div class="mb-3">
                    <label class="form-label">Note</label>
                    <textarea name="note" class="form-control">{{ old('note', $order->note) }}</textarea>
                </div>
                <button class="btn btn-primary">Save</button>
                <a href="{{ route('zalo-orders.show', $order->id) }}" class="btn btn-secondary">Cancel</a>
            </form>
        </div>
    </div>
@endsection
