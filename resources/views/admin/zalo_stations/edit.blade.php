@extends('layouts.main')

@section('content')
    <div class="card">
        <div class="card-header">
            <h4>Sửa trạm lấy hàng</h4>
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
            <form action="{{ route('zalo-stations.update', $station->id) }}" method="POST" enctype="multipart/form-data">
                @csrf
                @method('PUT')
                <div class="mb-3">
                    <label class="form-label">Tên trạm</label>
                    <input type="text" name="name" class="form-control" value="{{ old('name', $station->name) }}" required>
                </div>
                <div class="mb-3">
                    <label class="form-label">Hình ảnh hiện tại</label>
                    @if($station->image)
                        <div class="mb-2">
                            <img src="{{ asset($station->image) }}" style="height:100px; width:100px; object-fit:cover;" class="rounded">
                        </div>
                    @endif
                    <label class="form-label">Tệp hình ảnh (hoặc dán URL hình ảnh)</label>
                    <input type="file" name="image_file" id="image_file" class="form-control" accept="image/*">
                    <small class="form-text text-muted">Bạn cũng có thể dán URL hình ảnh bên dưới thay vì tải lên.</small>
                    <input type="text" name="image" id="image_url" class="form-control mt-2" value="{{ old('image', $station->image) }}" placeholder="https://...">
                    <div id="preview" class="mt-2">
                        <img id="previewImg" src="" style="height:40px; display:none" />
                        <div id="meta" style="font-size:0.9em; margin-top:6px; display:none"></div>
                    </div>
                </div>
                <div class="mb-3">
                    <label class="form-label">Địa chỉ</label>
                    <textarea name="address" class="form-control" rows="3">{{ old('address', $station->address) }}</textarea>
                </div>
                <div class="row">
                    <div class="col-md-6">
                        <div class="mb-3">
                            <label class="form-label">Vĩ độ</label>
                            <input type="number" step="any" name="lat" class="form-control" value="{{ old('lat', $station->lat) }}">
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="mb-3">
                            <label class="form-label">Kinh độ</label>
                            <input type="number" step="any" name="lng" class="form-control" value="{{ old('lng', $station->lng) }}">
                        </div>
                    </div>
                </div>
                <button class="btn btn-primary">Lưu</button>
                <a href="{{ route('zalo-stations.index') }}" class="btn btn-secondary">Huỷ</a>
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
            metaDiv.innerHTML = `Kích thước hiển thị:\t${renderedW} × ${renderedH} px<br>Tỷ lệ hiển thị:\t${renderedW / r}:${renderedH / r}<br>Kích thước gốc:\t${intrinsicW} × ${intrinsicH} px<br>Tỷ lệ gốc:\t${intrinsicW / i}:${intrinsicH / i}`;
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
                metaDiv.innerHTML = 'Không thể tải hình ảnh để đọc thông tin.';
            };
            img.src = v;
            // clear file input when URL provided
            if (fileInput) fileInput.value = null;
        });
    </script>
@endsection