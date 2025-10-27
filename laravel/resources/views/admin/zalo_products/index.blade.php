@extends('layouts.main')

@section('content')
    <div class="card">
        <div class="card-header d-flex justify-content-between align-items-center">
            <h4>Zalo Products</h4>
            <a href="{{ route('zalo-products.create') }}" class="btn btn-primary">New Product</a>
        </div>
        <div class="card-body">
            @if(session('success'))
                <div class="alert alert-success">{{ session('success') }}</div>
            @endif

            <form method="GET" class="mb-3">
                <div class="row">
                    <div class="col-sm-4">
                        <select name="category_id" class="form-select" onchange="this.form.submit()">
                            <option value="">-- All categories --</option>
                            @foreach($categories as $cat)
                                <option value="{{ $cat->id }}" {{ request('category_id') == $cat->id ? 'selected' : '' }}>{{ $cat->name }}</option>
                            @endforeach
                        </select>
                    </div>
                </div>
            </form>

            <table class="table">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Name</th>
                        <th>Category</th>
                        <th>Price</th>
                        <th>Image</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($products as $p)
                        <tr>
                            <td>{{ $p->id }}</td>
                            <td>{{ $p->name }}</td>
                            <td>{{ $p->category?->name }}</td>
                            <td>{{ number_format($p->price) }}</td>
                            <td>@if($p->image)<img src="{{ $p->image }}" alt="" style="height:40px">@endif</td>
                            <td>
                                <a href="{{ route('zalo-products.edit', $p->id) }}" class="btn btn-sm btn-secondary">Edit</a>
                                <form action="{{ route('zalo-products.destroy', $p->id) }}" method="POST" style="display:inline-block" onsubmit="return confirm('Delete this product?')">
                                    @csrf
                                    @method('DELETE')
                                    <button class="btn btn-sm btn-danger">Delete</button>
                                </form>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>
@endsection
