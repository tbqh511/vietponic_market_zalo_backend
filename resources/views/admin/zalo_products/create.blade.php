@extends('layouts.main')

@section('content')
    <div class="card">
        <div class="card-header">
            <h4>Tạo sản phẩm</h4>
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
            <form action="{{ route('zalo-products.store') }}" method="POST" enctype="multipart/form-data">
                @csrf
                <div class="mb-3">
                    <label class="form-label">Danh mục</label>
                    <select name="category_id" class="form-select">
                        <option value="">-- Không có --</option>
                        @foreach($categories as $cat)
                            <option value="{{ $cat->id }}">{{ $cat->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="mb-3">
                    <label class="form-label">Tên sản phẩm</label>
                    <input type="text" name="name" class="form-control" value="{{ old('name') }}" required>
                </div>
                <div class="mb-3">
                    <label class="form-label">Giá bán</label>
                    <input type="number" name="price" class="form-control" value="{{ old('price', 0) }}" step="0.01">
                </div>
                <div class="mb-3">
                    <label class="form-label">Giá gốc</label>
                    <input type="number" name="original_price" class="form-control" value="{{ old('original_price') }}" step="0.01">
                </div>
                <div class="mb-3">
                    <label class="form-label">Hình ảnh sản phẩm</label>
                    <input type="file" name="image" class="form-control" accept="image/*" onchange="previewImage(this)">
                    <div class="form-text">
                        Định dạng chấp nhận: JPEG, PNG, JPG, GIF. Dung lượng tối đa: 2MB. Hình sẽ được thu nhỏ về 560x560px.
                    </div>
                    <div id="image-preview" class="mt-2" style="display: none;">
                        <img id="preview-img" src="" alt="Image Preview" style="max-width: 200px; max-height: 200px; border: 1px solid #ddd; padding: 5px;">
                    </div>
                </div>
                <div class="mb-3">
                    <label class="form-label">Mô tả chi tiết</label>
                    <textarea name="detail" class="form-control" rows="4">{{ old('detail') }}</textarea>
                </div>
                <hr>
                <h6>Đơn vị sản phẩm</h6>
                <div class="row">
                    <div class="mb-3 col-md-4">
                        <label class="form-label">Đơn vị hiển thị</label>
                        <select name="unit_id" class="form-select" id="unit-select">
                            <option value="">-- không gắn đơn vị --</option>
                            @foreach($units as $u)
                                <option value="{{ $u->id }}" data-system="{{ $u->system_unit_type }}" {{ old('unit_id') == $u->id ? 'selected' : '' }}>{{ $u->label }} ({{ $u->system_unit_type }})</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="mb-3 col-md-4">
                        <label class="form-label">Hệ đơn vị hệ thống</label>
                        <select name="system_unit" class="form-select" id="system-unit-select">
                            <option value="piece" {{ old('system_unit', 'piece') == 'piece' ? 'selected' : '' }}>piece (cái)</option>
                            <option value="g" {{ old('system_unit') == 'g' ? 'selected' : '' }}>g (gram)</option>
                            <option value="ml" {{ old('system_unit') == 'ml' ? 'selected' : '' }}>ml (mililít)</option>
                        </select>
                    </div>
                    <div class="mb-3 col-md-4">
                        <label class="form-label">Hệ số quy đổi</label>
                        <input type="number" name="conversion_factor" class="form-control" value="{{ old('conversion_factor', 1) }}" step="0.001" min="0.001" required>
                        <div class="form-text">Ví dụ: 1 bó = 100g → nhập 100. 1 hộp cà chua = 200g → nhập 200.</div>
                    </div>
                </div>
                <button class="btn btn-primary">Tạo mới</button>
                <a href="{{ route('zalo-products.index') }}" class="btn btn-secondary">Huỷ</a>
            </form>
        </div>
    </div>
@endsection

@section('scripts')
<script>
document.getElementById('unit-select')?.addEventListener('change', function () {
    const sys = this.selectedOptions[0]?.dataset.system;
    if (sys) document.getElementById('system-unit-select').value = sys;
});
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
    }
}
</script>
@endsection
