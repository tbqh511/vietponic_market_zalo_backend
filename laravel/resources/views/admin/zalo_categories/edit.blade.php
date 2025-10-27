@extends('layouts.main')

@section('content')
    <div class="card">
        <div class="card-header">
            <h4>Edit Zalo Category</h4>
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
            <form action="{{ route('zalo-categories.update', $category->id) }}" method="POST">
                @csrf
                @method('PUT')
                <div class="mb-3">
                    <label class="form-label">Name</label>
                    <input type="text" name="name" class="form-control" value="{{ old('name', $category->name) }}" required>
                </div>
                <div class="mb-3">
                    <label class="form-label">Image URL</label>
                    <input type="text" name="image" class="form-control" value="{{ old('image', $category->image) }}">
                </div>
                <button class="btn btn-primary">Save</button>
                <a href="{{ route('zalo-categories.index') }}" class="btn btn-secondary">Cancel</a>
            </form>
        </div>
    </div>
@endsection
