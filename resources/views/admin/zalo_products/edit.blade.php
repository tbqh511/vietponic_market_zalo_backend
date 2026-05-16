@extends('layouts.main')

@section('content')
    <div class="card">
        <div class="card-header">
            <h4>Sửa sản phẩm</h4>
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
                    <label class="form-label">Danh mục</label>
                    <select name="category_id" class="form-select">
                        <option value="">-- Không có --</option>
                        @foreach($categories as $cat)
                            <option value="{{ $cat->id }}" {{ $product->category_id == $cat->id ? 'selected' : '' }}>{{ $cat->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="mb-3">
                    <label class="form-label">Tên sản phẩm</label>
                    <input type="text" name="name" class="form-control" value="{{ old('name', $product->name) }}" required>
                </div>
                <div class="mb-3">
                    <label class="form-label">Giá bán</label>
                    <input type="number" name="price" class="form-control" value="{{ old('price', $product->price) }}" step="0.01">
                </div>
                <div class="mb-3">
                    <label class="form-label">Giá gốc</label>
                    <input type="number" name="original_price" class="form-control" value="{{ old('original_price', $product->original_price) }}" step="0.01">
                </div>
                <div class="mb-3">
                    <label class="form-label">Hình ảnh sản phẩm</label>
                    @if($product->image)
                        <div class="mb-2">
                            <strong>Hình ảnh hiện tại:</strong><br>
                            <img src="{{ $product->image_url }}" alt="Hình ảnh hiện tại" style="max-width: 200px; max-height: 200px; border: 1px solid #ddd; padding: 5px;">
                        </div>
                    @endif
                    <input type="file" name="image" class="form-control" accept="image/*" onchange="previewImage(this)">
                    <div class="form-text">
                        Định dạng chấp nhận: JPEG, PNG, JPG, GIF. Dung lượng tối đa: 2MB. Hình sẽ được thu nhỏ về 560x560px.
                        @if($product->image)
                            Để trống để giữ hình ảnh hiện tại.
                        @endif
                    </div>
                    <div id="image-preview" class="mt-2" style="display: none;">
                        <strong>Xem trước hình ảnh mới:</strong><br>
                        <img id="preview-img" src="" alt="Image Preview" style="max-width: 200px; max-height: 200px; border: 1px solid #ddd; padding: 5px;">
                    </div>
                </div>
                <div class="mb-3">
                    <label class="form-label">Mô tả chi tiết</label>
                    <textarea name="detail" class="form-control" rows="4">{{ old('detail', $product->detail) }}</textarea>
                </div>
                <hr>
                <h6>Đơn vị sản phẩm</h6>
                <div class="row">
                    <div class="mb-3 col-md-4">
                        <label class="form-label">Đơn vị hiển thị</label>
                        <select name="unit_id" class="form-select" id="unit-select">
                            <option value="">-- không gắn đơn vị --</option>
                            @foreach($units as $u)
                                <option value="{{ $u->id }}" data-system="{{ $u->system_unit_type }}" {{ old('unit_id', $product->unit_id) == $u->id ? 'selected' : '' }}>{{ $u->label }} ({{ $u->system_unit_type }})</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="mb-3 col-md-4">
                        <label class="form-label">Hệ đơn vị hệ thống</label>
                        <select name="system_unit" class="form-select" id="system-unit-select">
                            @foreach(['piece' => 'piece (cái)', 'g' => 'g (gram)', 'ml' => 'ml (mililít)'] as $k => $v)
                                <option value="{{ $k }}" {{ old('system_unit', $product->system_unit) == $k ? 'selected' : '' }}>{{ $v }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="mb-3 col-md-4">
                        <label class="form-label">Hệ số quy đổi</label>
                        <input type="number" name="conversion_factor" class="form-control" value="{{ old('conversion_factor', $product->conversion_factor) }}" step="0.001" min="0.001" required>
                        <div class="form-text">Ví dụ: 1 bó = 100g → nhập 100. 1 hộp cà chua = 200g → nhập 200.</div>
                    </div>
                </div>
                <button class="btn btn-primary">Lưu</button>
                <a href="{{ route('zalo-products.index') }}" class="btn btn-secondary">Huỷ</a>
                <a href="{{ route('inventory.show', $product->id) }}" class="btn btn-outline-info ms-2">
                    <i class="fas fa-boxes"></i> Quản lý tồn kho
                </a>
            </form>
        </div>
    </div>
@endsection

@section('scripts')
<script>
function syncSystemUnit(selectEl) {
    const sys = selectEl.selectedOptions[0]?.dataset.system;
    if (sys) document.getElementById('system-unit-select').value = sys;
}
const unitSelect = document.getElementById('unit-select');
if (unitSelect) {
    unitSelect.addEventListener('change', function () { syncSystemUnit(this); });
    syncSystemUnit(unitSelect);
}
function previewImage(input) {
    if (input.files && input.files[0]) {
        const file = input.files[0];
        
        // Validate file type
        const allowedTypes = ['image/jpeg', 'image/png', 'image/jpg', 'image/gif'];
        if (!allowedTypes.includes(file.type)) {
            alert('Vui lòng chọn tệp hình ảnh hợp lệ (JPEG, PNG, JPG, GIF)');
            input.value = '';
            return;
        }
        
        // Validate file size (2MB)
        if (file.size > 2 * 1024 * 1024) {
            alert('Dung lượng tệp phải nhỏ hơn 2MB');
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
