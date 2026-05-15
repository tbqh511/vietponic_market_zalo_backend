@extends('layouts.main')

@section('content')
    <div class="card">
        <div class="card-header">
            <h4>Sửa Banner</h4>
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
            <form action="{{ route('banners.update', $banner->id) }}" method="POST" enctype="multipart/form-data">
                @csrf
                @method('PUT')
                <div class="mb-3">
                    <label class="form-label">URL hình ảnh</label>
                    <input type="file" name="image_file" id="image_file" class="form-control" accept="image/*">
                    <small class="form-text text-muted">Để trống để giữ hình ảnh hiện tại; bạn cũng có thể dán URL hình ảnh bên dưới.</small>
                    <input type="text" name="image" id="image_url" class="form-control mt-2" value="{{ old('image', $banner->image) }}" placeholder="https://...">
                    <div id="preview" class="mt-2">
                        @if($banner->image)
                            <img id="previewImg" src="{{ asset($banner->image) }}" style="height:40px" />
                        @else
                            <img id="previewImg" src="" style="height:40px; display:none" />
                        @endif
                        <div id="meta" style="font-size:0.9em; margin-top:6px;">
                            @if(isset($metadata))
                                <div>Kích thước hiển thị:	{{ $metadata['rendered_width'] }} × {{ $metadata['rendered_height'] }} px</div>
                                <div>Tỷ lệ hiển thị:	{{ $metadata['rendered_ratio'] }}</div>
                                <div>Kích thước gốc:	{{ $metadata['intrinsic_width'] }} × {{ $metadata['intrinsic_height'] }} px</div>
                                <div>Tỷ lệ gốc:	{{ $metadata['intrinsic_ratio'] }}</div>
                            @else
                                <div>Không có thông tin hình ảnh hiện tại.</div>
                            @endif
                        </div>
                    </div>
                </div>
                <button class="btn btn-primary">Lưu</button>
                <a href="{{ route('banners.index') }}" class="btn btn-secondary">Huỷ</a>
            </form>
            
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
                        if (urlInput) urlInput.value = '';
                    });

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
                        if (fileInput) fileInput.value = null;
                    });
                </script>
            @endsection
        </div>
    </div>
@endsection
