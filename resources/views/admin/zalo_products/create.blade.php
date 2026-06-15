@extends('layouts.main')

@section('content')
    <div class="card">
        <div class="card-header">
            <h4>Tạo sản phẩm</h4>
        </div>
        <div class="card-body">
            @if($errors->any())
                <div class="alert alert-danger">
                    <ul class="mb-0">
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

                {{-- ─── Hình ảnh sản phẩm (multi-upload) ───────────────────────────── --}}
                <div class="mb-3">
                    <label class="form-label fw-semibold">Hình ảnh sản phẩm <span class="text-danger">*</span></label>
                    <div class="form-text mb-2">
                        Chọn một hoặc nhiều ảnh. Ảnh đầu tiên sẽ là ảnh đại diện.
                        Định dạng: JPEG, PNG, JPG, GIF. Tối đa 2MB/ảnh. Sẽ resize về 560×560px.
                    </div>

                    {{-- Drop zone --}}
                    <div id="drop-zone"
                         class="border border-2 border-dashed rounded p-4 text-center text-muted"
                         style="cursor:pointer; border-color:#adb5bd !important;"
                         onclick="document.getElementById('image-input').click()">
                        <i class="fas fa-cloud-upload-alt fa-2x mb-2"></i>
                        <div>Kéo thả ảnh vào đây hoặc <strong>click để chọn</strong></div>
                    </div>
                    <input type="file" id="image-input" name="images[]" multiple accept="image/*"
                           class="d-none" onchange="handleFiles(this.files)">

                    {{-- Preview grid --}}
                    <div id="preview-grid" class="row g-2 mt-2"></div>
                    <div id="image-count-hint" class="form-text mt-1" style="display:none;">
                        <span id="image-count">0</span> ảnh đã chọn
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
                                <option value="{{ $u->id }}" data-system="{{ $u->system_unit_type }}"
                                    {{ old('unit_id') == $u->id ? 'selected' : '' }}>
                                    {{ $u->label }} ({{ $u->system_unit_type }})
                                </option>
                            @endforeach
                        </select>
                    </div>
                    <div class="mb-3 col-md-4">
                        <label class="form-label">Hệ đơn vị hệ thống</label>
                        <select name="system_unit" class="form-select" id="system-unit-select">
                            <option value="piece" {{ old('system_unit', 'piece') == 'piece' ? 'selected' : '' }}>piece (cái)</option>
                            <option value="g"     {{ old('system_unit') == 'g'     ? 'selected' : '' }}>g (gram)</option>
                            <option value="ml"    {{ old('system_unit') == 'ml'    ? 'selected' : '' }}>ml (mililít)</option>
                        </select>
                    </div>
                    <div class="mb-3 col-md-4">
                        <label class="form-label">Hệ số quy đổi</label>
                        <input type="number" name="conversion_factor" class="form-control"
                               value="{{ old('conversion_factor', 1) }}" step="0.001" min="0.001" required>
                        <div class="form-text">Ví dụ: 1 bó = 100g → nhập 100. 1 hộp cà chua = 200g → nhập 200.</div>
                    </div>
                </div>
                <button class="btn btn-primary">Tạo mới</button>
                <a href="{{ route('zalo-products.index') }}" class="btn btn-secondary">Huỷ</a>
            </form>
        </div>
    </div>
@endsection

@section('script')
<script>
// ─── Multi-image upload (create page) ───────────────────────────────────────
const dt = new DataTransfer();

function handleFiles(files) {
    const allowed = ['image/jpeg','image/png','image/jpg','image/gif'];
    const maxSize = 2 * 1024 * 1024;
    Array.from(files).forEach(file => {
        if (!allowed.includes(file.type)) {
            alert(`"${file.name}" không phải định dạng hợp lệ (JPEG, PNG, JPG, GIF).`);
            return;
        }
        if (file.size > maxSize) {
            alert(`"${file.name}" vượt quá 2MB.`);
            return;
        }
        dt.items.add(file);
    });
    syncInput();
    renderPreviews();
}

function syncInput() {
    const input = document.getElementById('image-input');
    input.files = dt.files;
    const countEl = document.getElementById('image-count');
    const hintEl  = document.getElementById('image-count-hint');
    countEl.textContent = dt.files.length;
    hintEl.style.display = dt.files.length > 0 ? '' : 'none';
}

function removeFile(index) {
    dt.items.remove(index);
    syncInput();
    renderPreviews();
}

function renderPreviews() {
    const grid = document.getElementById('preview-grid');
    grid.innerHTML = '';
    Array.from(dt.files).forEach((file, i) => {
        const reader = new FileReader();
        reader.onload = e => {
            const col = document.createElement('div');
            col.className = 'col-6 col-sm-4 col-md-3 col-lg-2';
            col.innerHTML = `
                <div class="position-relative border rounded overflow-hidden" style="aspect-ratio:1">
                    <img src="${e.target.result}" class="w-100 h-100 object-fit-cover" alt="">
                    ${i === 0 ? '<span class="position-absolute top-0 start-0 badge bg-primary m-1" style="font-size:10px">Ảnh chính</span>' : ''}
                    <button type="button" onclick="removeFile(${i})"
                        class="btn btn-sm btn-danger position-absolute top-0 end-0 m-1 p-0"
                        style="width:22px;height:22px;line-height:1;font-size:14px"
                        title="Xoá ảnh">×</button>
                </div>`;
            grid.appendChild(col);
        };
        reader.readAsDataURL(file);
    });
}

// Drop zone events
const zone = document.getElementById('drop-zone');
zone.addEventListener('dragover', e => { e.preventDefault(); zone.classList.add('bg-light'); });
zone.addEventListener('dragleave', () => zone.classList.remove('bg-light'));
zone.addEventListener('drop', e => {
    e.preventDefault();
    zone.classList.remove('bg-light');
    handleFiles(e.dataTransfer.files);
});

// Unit sync
function syncSystemUnit(selectEl) {
    const sys = selectEl.selectedOptions[0]?.dataset.system;
    if (sys) document.getElementById('system-unit-select').value = sys;
}
const unitSelect = document.getElementById('unit-select');
if (unitSelect) {
    unitSelect.addEventListener('change', function () { syncSystemUnit(this); });
    syncSystemUnit(unitSelect);
}
</script>
@endsection
