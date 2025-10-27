@extends('layouts.main')

@section('content')
    <div class="card">
        <div class="card-header">
            <h4>Create Banner</h4>
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
            <form action="{{ route('banners.store') }}" method="POST">
                @csrf
                <div class="mb-3">
                    <label class="form-label">Image URL</label>
                    <input type="text" name="image" class="form-control" value="{{ old('image') }}" required>
                </div>
                <button class="btn btn-primary">Create</button>
                <a href="{{ route('banners.index') }}" class="btn btn-secondary">Cancel</a>
            </form>
        </div>
    </div>
@endsection
