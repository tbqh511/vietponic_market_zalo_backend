@extends('layouts.main')

@section('content')
    <div class="card">
        <div class="card-header">
            <h4>Create Zalo Category</h4>
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
            <form action="{{ route('zalo-categories.store') }}" method="POST" enctype="multipart/form-data">
                @csrf
                <div class="mb-3">
                    <label class="form-label">Name</label>
                    <input type="text" name="name" class="form-control" value="{{ old('name') }}" required>
                </div>
                <div class="mb-3">
                    <label class="form-label">Image file (or paste image URL)</label>
                    <input type="file" name="image_file" id="image_file" class="form-control" accept="image/*">
                    <small class="form-text text-muted">You may also paste an image URL below instead of uploading.</small>
                    <input type="text" name="image" id="image_url" class="form-control mt-2" value="{{ old('image') }}" placeholder="https://...">
                    <div id="preview" class="mt-2">
                        <img id="previewImg" src="" style="height:40px; display:none" />
                        <div id="meta" style="font-size:0.9em; margin-top:6px; display:none"></div>
                    </div>
                </div>
                <button class="btn btn-primary">Create</button>
                <a href="{{ route('zalo-categories.index') }}" class="btn btn-secondary">Cancel</a>
            </form>
        </div>
    </div>
@endsection
