@extends('layouts.main')

@section('content')
    <div class="card">
        <div class="card-header">
            <h4>Create Zalo Product</h4>
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
            <form action="{{ route('zalo-products.store') }}" method="POST">
                @csrf
                <div class="mb-3">
                    <label class="form-label">Category</label>
                    <select name="category_id" class="form-select">
                        <option value="">-- none --</option>
                        @foreach($categories as $cat)
                            <option value="{{ $cat->id }}">{{ $cat->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="mb-3">
                    <label class="form-label">Name</label>
                    <input type="text" name="name" class="form-control" value="{{ old('name') }}" required>
                </div>
                <div class="mb-3">
                    <label class="form-label">Price</label>
                    <input type="number" name="price" class="form-control" value="{{ old('price', 0) }}">
                </div>
                <div class="mb-3">
                    <label class="form-label">Original Price</label>
                    <input type="number" name="original_price" class="form-control" value="{{ old('original_price') }}">
                </div>
                <div class="mb-3">
                    <label class="form-label">Image URL</label>
                    <input type="text" name="image" class="form-control" value="{{ old('image') }}">
                </div>
                <div class="mb-3">
                    <label class="form-label">Detail</label>
                    <textarea name="detail" class="form-control">{{ old('detail') }}</textarea>
                </div>
                <button class="btn btn-primary">Create</button>
                <a href="{{ route('zalo-products.index') }}" class="btn btn-secondary">Cancel</a>
            </form>
        </div>
    </div>
@endsection
