@extends('layouts.main')

@section('content')
    <div class="card">
        <div class="card-header">
            <h4>Sửa sản phẩm</h4>
        </div>
        <div class="card-body">
            @if(session('success'))
                <div class="alert alert-success alert-dismissible fade show">
                    {{ session('success') }}
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            @endif
            @if($errors->any())
                <div class="alert alert-danger">
                    <ul class="mb-0">
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
                            <option value="{{ $cat->id }}" {{ $product->category_id == $cat->id ? 'selected' : '' }}>
                                {{ $cat->name }}
                            </option>
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

                {{-- ─── Hình ảnh hiện tại ───────────────────────────────────────────── --}}
                <div class="mb-3">
                    <label class="form-label fw-semibold">Hình ảnh sản phẩm</label>

                    @if($product->productImages->count() > 0)
                        <p class="form-text mb-2">
                            Kéo thả để sắp xếp lại. Ảnh đầu tiên là ảnh đại diện.
                            Nhấn <strong>×</strong> để xóa từng ảnh.
                        </p>
                        <div id="existing-images" class="row g-2 mb-3">
                            @foreach($product->productImages as $idx => $img)
                                <div class="col-6 col-sm-4 col-md-3 col-lg-2 sortable-item" data-id="{{ $img->id }}">
                                    <div class="position-relative border rounded overflow-hidden bg-light" style="aspect-ratio:1;cursor:grab;">
                                        <img src="{{ $img->image_url }}"
                                             class="w-100 h-100"
                                             style="object-fit:cover"
                                             alt="Ảnh {{ $idx + 1 }}">
                                        @if($idx === 0)
                                            <span class="position-absolute top-0 start-0 badge bg-primary m-1"
                                                  style="font-size:10px">Ảnh chính</span>
                                        @endif
                                        <button type="button"
                                                onclick="deleteExistingImage({{ $img->id }}, this)"
                                                class="btn btn-sm btn-danger position-absolute top-0 end-0 m-1 p-0"
                                                style="width:22px;height:22px;line-height:1;font-size:14px"
                                                title="Xoá ảnh này">×</button>
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    @else
                        <p class="text-muted small mb-2">Chưa có ảnh nào. Hãy tải lên ảnh mới bên dưới.</p>
                    @endif

                    {{-- Upload thêm ảnh mới --}}
                    <label class="form-label">Thêm ảnh mới</label>
                    <div id="drop-zone"
                         class="border border-2 border-dashed rounded p-3 text-center text-muted"
                         style="cursor:pointer;border-color:#adb5bd !important;"
                         onclick="document.getElementById('new-image-input').click()">
                        <i class="fas fa-cloud-upload-alt fa-xl mb-1"></i>
                        <div class="small">Kéo thả hoặc <strong>click</strong> để thêm ảnh</div>
                    </div>
                    <input type="file" id="new-image-input" name="new_images[]" multiple accept="image/*"
                           class="d-none" onchange="handleNewFiles(this.files)">

                    <div id="new-preview-grid" class="row g-2 mt-2"></div>
                    <div id="new-count-hint" class="form-text mt-1" style="display:none;">
                        <span id="new-count">0</span> ảnh mới đã chọn
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
                                <option value="{{ $u->id }}" data-system="{{ $u->system_unit_type }}"
                                    {{ old('unit_id', $product->unit_id) == $u->id ? 'selected' : '' }}>
                                    {{ $u->label }} ({{ $u->system_unit_type }})
                                </option>
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
                        <input type="number" name="conversion_factor" class="form-control"
                               value="{{ old('conversion_factor', $product->conversion_factor) }}"
                               step="0.001" min="0.001" required>
                        <div class="form-text">Ví dụ: 1 bó = 100g → nhập 100. 1 hộp cà chua = 200g → nhập 200.</div>
                    </div>
                </div>
                <hr>
                <h6>Vận chuyển <small class="text-muted">(dùng để tính phí ViettelPost)</small></h6>
                <div class="row">
                    <div class="mb-3 col-md-3">
                        <label class="form-label">Trọng lượng (gam)</label>
                        <input type="number" name="weight" class="form-control"
                               value="{{ old('weight', $product->weight ?? 500) }}"
                               min="1" max="50000" required>
                        <div class="form-text">Ví dụ: 300 (rau lá), 700 (củ quả)</div>
                    </div>
                    <div class="mb-3 col-md-3">
                        <label class="form-label">Dài (cm)</label>
                        <input type="number" name="length" class="form-control"
                               value="{{ old('length', $product->length ?? 20) }}"
                               min="1" max="200" required>
                    </div>
                    <div class="mb-3 col-md-3">
                        <label class="form-label">Rộng (cm)</label>
                        <input type="number" name="width" class="form-control"
                               value="{{ old('width', $product->width ?? 15) }}"
                               min="1" max="200" required>
                    </div>
                    <div class="mb-3 col-md-3">
                        <label class="form-label">Cao (cm)</label>
                        <input type="number" name="height" class="form-control"
                               value="{{ old('height', $product->height ?? 10) }}"
                               min="1" max="200" required>
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

@section('script')
<script src="https://cdn.jsdelivr.net/npm/sortablejs@1.15.2/Sortable.min.js"></script>
<script>
// ─── Drag-to-reorder existing images ──────────────────────────────────────
const existingGrid = document.getElementById('existing-images');
if (existingGrid) {
    const sortable = Sortable.create(existingGrid, {
        animation: 150,
        ghostClass: 'opacity-50',
        onEnd() {
            updatePrimaryBadge();
            saveNewOrder();
        }
    });
}

function updatePrimaryBadge() {
    if (!existingGrid) return;
    existingGrid.querySelectorAll('.sortable-item').forEach((item, idx) => {
        const badge = item.querySelector('.badge');
        if (idx === 0) {
            if (!badge) {
                const img = item.querySelector('img');
                const b = document.createElement('span');
                b.className = 'position-absolute top-0 start-0 badge bg-primary m-1';
                b.style.fontSize = '10px';
                b.textContent = 'Ảnh chính';
                img.parentElement.appendChild(b);
            }
        } else {
            badge?.remove();
        }
    });
}

function saveNewOrder() {
    if (!existingGrid) return;
    const order = Array.from(existingGrid.querySelectorAll('.sortable-item'))
        .map(el => parseInt(el.dataset.id));
    fetch('{{ route("zalo-products.images.reorder", $product->id) }}', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]')?.content
                         ?? '{{ csrf_token() }}'
        },
        body: JSON.stringify({ order })
    });
}

// ─── Delete existing image ────────────────────────────────────────────────
function deleteExistingImage(imageId, btn) {
    if (!confirm('Xoá ảnh này?')) return;
    btn.disabled = true;
    fetch(`{{ url('zalo-products/' . $product->id . '/images') }}/${imageId}`, {
        method: 'DELETE',
        headers: { 'X-CSRF-TOKEN': '{{ csrf_token() }}' }
    })
    .then(r => r.json())
    .then(data => {
        if (data.ok) {
            const col = btn.closest('.sortable-item');
            col.remove();
            updatePrimaryBadge();
        }
    })
    .catch(() => { btn.disabled = false; alert('Xoá thất bại, thử lại.'); });
}

// ─── Add new images preview ───────────────────────────────────────────────
const dt = new DataTransfer();

function handleNewFiles(files) {
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
    syncNewInput();
    renderNewPreviews();
}

function syncNewInput() {
    const input = document.getElementById('new-image-input');
    input.files = dt.files;
    const countEl = document.getElementById('new-count');
    const hintEl  = document.getElementById('new-count-hint');
    countEl.textContent = dt.files.length;
    hintEl.style.display = dt.files.length > 0 ? '' : 'none';
}

function removeNewFile(index) {
    dt.items.remove(index);
    syncNewInput();
    renderNewPreviews();
}

function renderNewPreviews() {
    const grid = document.getElementById('new-preview-grid');
    grid.innerHTML = '';
    Array.from(dt.files).forEach((file, i) => {
        const reader = new FileReader();
        reader.onload = e => {
            const col = document.createElement('div');
            col.className = 'col-6 col-sm-4 col-md-3 col-lg-2';
            col.innerHTML = `
                <div class="position-relative border rounded overflow-hidden bg-light" style="aspect-ratio:1">
                    <img src="${e.target.result}" class="w-100 h-100" style="object-fit:cover" alt="">
                    <span class="position-absolute top-0 start-0 badge bg-success m-1" style="font-size:10px">Mới</span>
                    <button type="button" onclick="removeNewFile(${i})"
                        class="btn btn-sm btn-danger position-absolute top-0 end-0 m-1 p-0"
                        style="width:22px;height:22px;line-height:1;font-size:14px"
                        title="Bỏ ảnh này">×</button>
                </div>`;
            grid.appendChild(col);
        };
        reader.readAsDataURL(file);
    });
}

const zone = document.getElementById('drop-zone');
zone.addEventListener('dragover', e => { e.preventDefault(); zone.classList.add('bg-light'); });
zone.addEventListener('dragleave', () => zone.classList.remove('bg-light'));
zone.addEventListener('drop', e => {
    e.preventDefault();
    zone.classList.remove('bg-light');
    handleNewFiles(e.dataTransfer.files);
});

// ─── Unit sync ────────────────────────────────────────────────────────────
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
