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
                    <input type="number" name="price" class="form-control" value="{{ old('price', $product->price) }}">
                </div>
                <div class="mb-3">
                    <label class="form-label">Original Price</label>
                    <input type="number" name="original_price" class="form-control" value="{{ old('original_price', $product->original_price) }}">
                </div>
                <div class="mb-3">
                    <label class="form-label">Current Image</label>
                    @if($product->image)
                        <div class="mb-2">
                            <img src="{{ asset($product->image) }}" style="height:100px; width:100px; object-fit:cover;" class="rounded">
                        </div>
                    @endif
                    <label class="form-label">Image file (or paste image URL)</label>
                    <input type="file" name="image_file" id="image_file" class="form-control" accept="image/*">
                    <small class="form-text text-muted">You may also paste an image URL below instead of uploading.</small>
                    <input type="text" name="image" id="image_url" class="form-control mt-2" value="{{ old('image', $product->image) }}" placeholder="https://...">
                    <div id="preview" class="mt-2">
                        <img id="previewImg" src="" style="height:40px; display:none" />
                        <div id="meta" style="font-size:0.9em; margin-top:6px; display:none"></div>
                    </div>
                </div>
                <div class="mb-3">
                    <label class="form-label">Detail</label>
                    <textarea name="detail" class="form-control">{{ old('detail', $product->detail) }}</textarea>
                </div>
                <button class="btn btn-primary">Save</button>
                <a href="{{ route('zalo-products.index') }}" class="btn btn-secondary">Cancel</a>
            </form>
        </div>
    </div>
@endsection

@section('script')
    <script>
        const fileInput = document.getElementById('image_file');
        const urlInput = document.getElementById('image_url');
        const previewImg = document.getElementById('previewImg');
        const metaDiv = document.getElementById('meta');

        function showMeta(intrinsicW, intrinsicH, renderedH = 40) {
            const renderedW = Math.round(intrinsicW * (renderedH / intrinsicH));
            function gcd(a, b) { return b == 0 ? a : gcd(b, a % b); }
            const r = gcd(renderedW, renderedH);
            const i = gcd(intrinsicW, intrinsicH);
            metaDiv.style.display = 'block';
            metaDiv.innerHTML = `Rendered size:\t${renderedW} × ${renderedH} px<br>Rendered aspect ratio:\t${renderedW / r}:${renderedH / r}<br>Intrinsic size:\t${intrinsicW} × ${intrinsicH} px<br>Intrinsic aspect ratio:\t${intrinsicW / i}:${intrinsicH / i}`;
        }

        fileInput && fileInput.addEventListener('change', function (e) {
            const file = e.target.files[0];
            if (!file) return;
            const url = URL.createObjectURL(file);
            previewImg.src = url;
            previewImg.style.display = 'inline-block';
            const img = new Image();
            img.onload = function () {
                showMeta(img.naturalWidth, img.naturalHeight, 40);
                URL.revokeObjectURL(url);
            };
            img.src = url;
            // clear the URL input when a file is chosen
            if (urlInput) urlInput.value = '';
        });

        // if user pastes an image URL, show preview and try to fetch intrinsic size
        urlInput && urlInput.addEventListener('change', function (e) {
            const v = e.target.value.trim();
            if (!v) return;
            previewImg.src = v;
            previewImg.style.display = 'inline-block';
            const img = new Image();
            img.crossOrigin = 'anonymous';
            img.onload = function () {
                showMeta(img.naturalWidth, img.naturalHeight, 40);
            };
            img.onerror = function () {
                metaDiv.style.display = 'block';
                metaDiv.innerHTML = 'Could not load image to compute metadata.';
            };
            img.src = v;
            // clear file input when URL provided
            if (fileInput) fileInput.value = null;
        });
    </script>
@endsection
