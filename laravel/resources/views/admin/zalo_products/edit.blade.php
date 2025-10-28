@extends('layouts.main')

@section('content')
    <div class="card">
        <div class="card-header">
            <h4>Edit Zalo Product</h4>
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
            <form action="{{ route('zalo-products.update', $product->id) }}" method="POST" enctype="multipart/form-data">
                @csrf
                @method('PUT')
                <div class="mb-3">
                    <label class="form-label">Category</label>
                    <select name="category_id" class="form-select">
                        <option value="">-- none --</option>
                        @foreach($categories as $cat)
                            <option value="{{ $cat->id }}" {{ $product->category_id == $cat->id ? 'selected' : '' }}>{{ $cat->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="mb-3">
                    <label class="form-label">Name</label>
                    <input type="text" name="name" class="form-control" value="{{ old('name', $product->name) }}" required>
                </div>
                <div class="mb-3">
                    <label class="form-label">Price</label>
                    <input type="number" name="price" class="form-control" value="{{ old('price', $product->price) }}" step="0.01">
                </div>
                <div class="mb-3">
                    <label class="form-label">Original Price</label>
                    <input type="number" name="original_price" class="form-control" value="{{ old('original_price', $product->original_price) }}" step="0.01">
                </div>
                <div class="mb-3">
                    <label class="form-label">Product Image</label>
                    @if($product->image)
                        <div class="mb-2">
                            <strong>Current Image:</strong><br>
                            <img src="{{ $product->image_url }}" alt="Current Image" style="max-width: 200px; max-height: 200px; border: 1px solid #ddd; padding: 5px;">
                        </div>
                    @endif
                    <input type="file" name="image" class="form-control" accept="image/*" onchange="previewImage(this)">
                    <div class="form-text">
                        Accepted formats: JPEG, PNG, JPG, GIF. Maximum size: 2MB. Image will be resized to 560x560px.
                        @if($product->image)
                            Leave empty to keep current image.
                        @endif
                    </div>
                    <div id="image-preview" class="mt-2" style="display: none;">
                        <strong>New Image Preview:</strong><br>
                        <img id="preview-img" src="" alt="Image Preview" style="max-width: 200px; max-height: 200px; border: 1px solid #ddd; padding: 5px;">
                    </div>
                </div>
                <div class="mb-3">
                    <label class="form-label">Detail</label>
                    <textarea name="detail" class="form-control" rows="4">{{ old('detail', $product->detail) }}</textarea>
                </div>
                <button class="btn btn-primary">Save</button>
                <a href="{{ route('zalo-products.index') }}" class="btn btn-secondary">Cancel</a>
            </form>
        </div>
    </div>
@endsection

@section('scripts')
<script>
function previewImage(input) {
    if (input.files && input.files[0]) {
        const file = input.files[0];
        
        // Validate file type
        const allowedTypes = ['image/jpeg', 'image/png', 'image/jpg', 'image/gif'];
        if (!allowedTypes.includes(file.type)) {
            alert('Please select a valid image file (JPEG, PNG, JPG, GIF)');
            input.value = '';
            return;
        }
        
        // Validate file size (2MB)
        if (file.size > 2 * 1024 * 1024) {
            alert('File size must be less than 2MB');
            input.value = '';
            return;
        }
        
        const reader = new FileReader();
        reader.onload = function(e) {
            document.getElementById('preview-img').src = e.target.result;
            document.getElementById('image-preview').style.display = 'block';
        };
        reader.readAsDataURL(file);
    } else {
        // Hide preview if no file selected
        document.getElementById('image-preview').style.display = 'none';
    }
}
</script>
@endsection
