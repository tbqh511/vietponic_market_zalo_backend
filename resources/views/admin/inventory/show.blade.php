@extends('layouts.main')

@section('content')
<div class="row">
    {{-- Product info + quick actions --}}
    <div class="col-lg-4 mb-4">
        <div class="card h-100">
            <div class="card-header"><h5 class="mb-0">Thông tin sản phẩm</h5></div>
            <div class="card-body">
                @if($inventory->image)
                    <img src="{{ $inventory->image_url }}" alt="{{ $inventory->name }}"
                         class="img-fluid rounded mb-3" style="max-height:160px;object-fit:cover;">
                @endif
                <h6 class="fw-bold">{{ $inventory->name }}</h6>
                <p class="text-muted small mb-1">Danh mục: {{ $inventory->category?->name ?? '—' }}</p>
                <p class="text-muted small mb-1">Giá: {{ number_format($inventory->price) }} ₫</p>
                <p class="text-muted small mb-3">
                    Đơn vị bán:
                    @if($inventory->unit)
                        <strong>{{ $inventory->unit->label }}</strong>
                        @if((float)$inventory->conversion_factor !== 1.0)
                            <span class="d-block">1 {{ $inventory->unit->label }} ≈ {{ \App\Models\ZaloUnit::formatSystemTotal((float)$inventory->conversion_factor, $inventory->system_unit ?? 'piece') }}</span>
                            <span class="d-block">Tồn quy đổi: <strong>{{ \App\Models\ZaloUnit::formatSystemTotal($inventory->stock * (float)$inventory->conversion_factor, $inventory->system_unit ?? 'piece') }}</strong></span>
                        @endif
                    @else
                        <span class="text-muted">— (chưa cấu hình)</span>
                    @endif
                </p>

                <div class="row text-center g-2 mb-3">
                    <div class="col-4">
                        <div class="border rounded p-2">
                            <div class="fs-4 fw-bold">{{ $inventory->stock }}</div>
                            <div class="small text-muted">Tồn kho</div>
                        </div>
                    </div>
                    <div class="col-4">
                        <div class="border rounded p-2">
                            <div class="fs-4 fw-bold text-warning">{{ $inventory->stock_reserved }}</div>
                            <div class="small text-muted">Đặt giữ</div>
                        </div>
                    </div>
                    <div class="col-4">
                        <div class="border rounded p-2">
                            <div class="fs-4 fw-bold text-success">{{ $inventory->stock_available }}</div>
                            <div class="small text-muted">Khả dụng</div>
                        </div>
                    </div>
                </div>

                <div class="mb-3">
                    @if($inventory->stock <= 0)
                        <span class="badge bg-danger fs-6">Hết hàng</span>
                    @elseif($inventory->stock <= $inventory->reorder_point)
                        <span class="badge bg-warning text-dark fs-6">Tồn kho thấp</span>
                    @else
                        <span class="badge bg-success fs-6">Đủ hàng</span>
                    @endif
                </div>

                <a href="{{ route('inventory.import', $inventory->id) }}" class="btn btn-primary w-100 mb-2">
                    <i class="fas fa-plus"></i> Nhập kho
                </a>
                <a href="{{ route('inventory.index') }}" class="btn btn-secondary w-100">
                    <i class="fas fa-arrow-left"></i> Quay lại
                </a>
            </div>
        </div>
    </div>

    {{-- Adjust & reorder point forms --}}
    <div class="col-lg-8 mb-4">
        @if(session('success'))
            <div class="alert alert-success">{{ session('success') }}</div>
        @endif
        @if($errors->any())
            <div class="alert alert-danger">{{ $errors->first() }}</div>
        @endif

        <div class="row g-3 mb-3">
            {{-- Adjust stock --}}
            <div class="col-md-6">
                <div class="card">
                    <div class="card-header"><h6 class="mb-0">Điều chỉnh tồn kho</h6></div>
                    <div class="card-body">
                        <form action="{{ route('inventory.adjust', $inventory->id) }}" method="POST">
                            @csrf
                            <div class="mb-2">
                                <label class="form-label small">Số lượng mới</label>
                                <input type="number" name="quantity" class="form-control"
                                       value="{{ $inventory->stock }}" min="0" required>
                            </div>
                            <div class="mb-2">
                                <label class="form-label small">Lý do điều chỉnh</label>
                                <input type="text" name="note" class="form-control"
                                       placeholder="VD: Kiểm kê kho tháng 5" required>
                            </div>
                            <button type="submit" class="btn btn-warning w-100">Điều chỉnh</button>
                        </form>
                    </div>
                </div>
            </div>

            {{-- Reorder point --}}
            <div class="col-md-6">
                <div class="card">
                    <div class="card-header"><h6 class="mb-0">Ngưỡng cảnh báo</h6></div>
                    <div class="card-body">
                        <form action="{{ route('inventory.reorder-point', $inventory->id) }}" method="POST">
                            @csrf
                            <div class="mb-2">
                                <label class="form-label small">Ngưỡng tối thiểu</label>
                                <input type="number" name="reorder_point" class="form-control"
                                       value="{{ $inventory->reorder_point }}" min="0" required>
                                <div class="form-text">Cảnh báo khi tồn kho ≤ ngưỡng này.</div>
                            </div>
                            <button type="submit" class="btn btn-outline-secondary w-100 mt-3">Lưu ngưỡng</button>
                        </form>
                    </div>
                </div>
            </div>
        </div>

        {{-- Lịch sử nhập/xuất kho — đọc từ ledger stock_movements: gồm tất cả lượt
             nhập (admin/farm) và xuất (đơn hàng, huỷ đơn, điều chỉnh, farm export,
             đóng lô). Có search/filter real-time + paging qua AJAX. --}}
        <div class="card">
            <div class="card-header"><h6 class="mb-0">Lịch sử nhập / xuất kho</h6></div>
            <div class="card-body">
                <form id="movementFilter" onsubmit="return false;">
                    <div class="row g-2 mb-3">
                        <div class="col-md-4">
                            <div class="input-group input-group-sm">
                                <span class="input-group-text"><i class="fas fa-search"></i></span>
                                <input type="text" id="mvSearch" name="q" class="form-control"
                                       placeholder="Tìm theo ghi chú / #đơn / #batch..."
                                       value="{{ $filters['q'] ?? '' }}">
                            </div>
                        </div>
                        <div class="col-6 col-md-2">
                            <select id="mvType" name="type" class="form-select form-select-sm">
                                <option value="">-- Loại --</option>
                                <option value="in"  {{ ($filters['type'] ?? '') === 'in'  ? 'selected' : '' }}>Nhập</option>
                                <option value="out" {{ ($filters['type'] ?? '') === 'out' ? 'selected' : '' }}>Xuất</option>
                            </select>
                        </div>
                        <div class="col-6 col-md-3">
                            <select id="mvSource" name="source" class="form-select form-select-sm">
                                <option value="">-- Tất cả nguồn --</option>
                                @foreach(\App\Models\StockMovement::SOURCE_LABELS as $key => $label)
                                    <option value="{{ $key }}" {{ ($filters['source'] ?? '') === $key ? 'selected' : '' }}>{{ $label }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-6 col-md-3">
                            <div class="input-group input-group-sm">
                                <input type="date" id="mvFrom" name="from" class="form-control" value="{{ $filters['from'] ?? '' }}" title="Từ ngày">
                                <input type="date" id="mvTo" name="to" class="form-control" value="{{ $filters['to'] ?? '' }}" title="Đến ngày">
                            </div>
                        </div>
                    </div>
                </form>

                <div id="movementsContainer" class="border rounded position-relative">
                    @include('admin.inventory._movements')
                </div>
            </div>
        </div>
    </div>
</div>
@endsection

@section('script')
<script>
(function () {
    var baseUrl   = "{{ route('inventory.movements', $inventory->id) }}";
    var container = document.getElementById('movementsContainer');
    var form      = document.getElementById('movementFilter');
    if (!container || !form) return;

    var debounceTimer = null;

    function currentParams() {
        return {
            q:      (document.getElementById('mvSearch').value || '').trim(),
            type:   document.getElementById('mvType').value,
            source: document.getElementById('mvSource').value,
            from:   document.getElementById('mvFrom').value,
            to:     document.getElementById('mvTo').value
        };
    }

    function load(url, params) {
        container.classList.add('opacity-50');
        $.get(url, params || {})
            .done(function (html) { container.innerHTML = html; })
            .fail(function () {
                if (window.Toastify) {
                    Toastify({ text: 'Không tải được lịch sử kho.', duration: 4000, backgroundColor: '#dc3545' }).showToast();
                }
            })
            .always(function () { container.classList.remove('opacity-50'); });
    }

    function reload() { load(baseUrl, currentParams()); }

    // Search: debounce 300ms.
    document.getElementById('mvSearch').addEventListener('input', function () {
        clearTimeout(debounceTimer);
        debounceTimer = setTimeout(reload, 300);
    });

    // Select / date: reload ngay.
    ['mvType', 'mvSource', 'mvFrom', 'mvTo'].forEach(function (id) {
        document.getElementById(id).addEventListener('change', reload);
    });

    // Phân trang AJAX — link đã kèm query string nhờ withQueryString().
    container.addEventListener('click', function (e) {
        var a = e.target.closest('.pagination a');
        if (!a) return;
        e.preventDefault();
        load(a.getAttribute('href'));
    });
})();
</script>
@endsection
