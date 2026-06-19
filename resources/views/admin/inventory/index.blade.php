@extends('layouts.main')

@section('content')
<div class="card">
    <div class="card-header d-flex justify-content-between align-items-center flex-wrap gap-2">
        <h4 class="mb-0">Quản lý Tồn kho</h4>
        <div class="d-flex gap-2">
            <a href="{{ route('inventory.low-stock') }}" class="btn btn-warning btn-sm">
                <i class="fas fa-exclamation-triangle"></i> Tồn kho thấp
            </a>
            <a href="{{ route('inventory.report') }}" class="btn btn-info btn-sm">
                <i class="fas fa-chart-bar"></i> Báo cáo
            </a>
        </div>
    </div>
    <div class="card-body">
        @if(session('success'))
            <div class="alert alert-success alert-dismissible fade show" role="alert">
                {{ session('success') }}
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        @endif

        {{-- Filters --}}
        <form method="GET" class="mb-3" id="filterForm">
            <div class="row g-2">
                <div class="col-sm-4">
                    <div class="input-group">
                        <span class="input-group-text"><i class="fas fa-search"></i></span>
                        <input type="text" name="search" class="form-control"
                               placeholder="Tìm tên sản phẩm..."
                               value="{{ request('search') }}">
                    </div>
                </div>
                <div class="col-sm-3">
                    <select name="category_id" class="form-select" onchange="this.form.submit()">
                        <option value="">-- Tất cả danh mục --</option>
                        @foreach($categories as $cat)
                            <option value="{{ $cat->id }}" {{ request('category_id') == $cat->id ? 'selected' : '' }}>
                                {{ $cat->name }}
                            </option>
                        @endforeach
                    </select>
                </div>
                <div class="col-sm-3">
                    <select name="stock_status" class="form-select" onchange="this.form.submit()">
                        <option value="">-- Tất cả trạng thái --</option>
                        <option value="out"  {{ request('stock_status') === 'out'  ? 'selected' : '' }}>Hết hàng</option>
                        <option value="low"  {{ request('stock_status') === 'low'  ? 'selected' : '' }}>Tồn kho thấp</option>
                        <option value="ok"   {{ request('stock_status') === 'ok'   ? 'selected' : '' }}>Đủ hàng</option>
                    </select>
                </div>
                <div class="col-sm-2 d-flex gap-1">
                    <button type="submit" class="btn btn-primary flex-fill">
                        <i class="fas fa-search"></i>
                    </button>
                    @if(request()->hasAny(['search','category_id','stock_status']))
                        <a href="{{ route('inventory.index') }}" class="btn btn-outline-secondary flex-fill">
                            <i class="fas fa-times"></i>
                        </a>
                    @endif
                </div>
            </div>
        </form>

        <div class="d-flex justify-content-between align-items-center mb-2 text-muted small">
            <span>
                Hiển thị {{ $products->firstItem() }}–{{ $products->lastItem() }}
                trong tổng số {{ $products->total() }} sản phẩm
            </span>
        </div>

        <div class="table-responsive">
            <table class="table table-hover align-middle">
                <thead class="table-light">
                    <tr>
                        <th style="width:50px">Ảnh</th>
                        <th>Tên sản phẩm</th>
                        <th>Danh mục</th>
                        <th>Đơn vị</th>
                        <th class="text-center">Tồn kho</th>
                        <th class="text-center">Đặt giữ</th>
                        <th class="text-center">Khả dụng</th>
                        <th class="text-center">Ngưỡng</th>
                        <th class="text-center">Trạng thái</th>
                        <th style="min-width:260px">Thao tác</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($products as $p)
                    <tr>
                        <td>
                            @if($p->image)
                                <img src="{{ $p->image_url }}"
                                     alt="{{ $p->name }}"
                                     loading="lazy"
                                     width="40" height="40"
                                     style="object-fit:cover;border-radius:4px;">
                            @else
                                <span class="text-muted">—</span>
                            @endif
                        </td>
                        <td>
                            <a href="{{ route('inventory.show', $p->id) }}" class="fw-semibold text-decoration-none">
                                {{ $p->name }}
                            </a>
                        </td>
                        <td class="text-muted small">{{ $p->category?->name }}</td>
                        <td class="text-muted small">
                            @if($p->unit)
                                {{ $p->unit->label }}
                                @if((float)$p->conversion_factor !== 1.0)
                                    <span class="d-block">×{{ rtrim(rtrim(number_format((float)$p->conversion_factor, 3, '.', ''), '0'), '.') }} {{ $p->system_unit }}</span>
                                @endif
                            @else
                                <span class="text-muted">—</span>
                            @endif
                        </td>
                        <td class="text-center fw-bold">
                            {{ number_format($p->stock) }}
                            @if($p->unit && (float)$p->conversion_factor !== 1.0)
                                <div class="small text-muted fw-normal">
                                    ≈ {{ \App\Models\ZaloUnit::formatSystemTotal($p->stock * (float)$p->conversion_factor, $p->system_unit ?? 'piece') }}
                                </div>
                            @endif
                        </td>
                        <td class="text-center text-warning">{{ number_format($p->stock_reserved) }}</td>
                        <td class="text-center fw-bold">{{ number_format($p->stock_available) }}</td>
                        <td class="text-center text-muted">{{ number_format($p->reorder_point) }}</td>
                        <td class="text-center">
                            @if($p->stock <= 0)
                                <span class="badge bg-danger">Hết hàng</span>
                            @elseif($p->stock <= $p->reorder_point)
                                <span class="badge bg-warning text-dark">Tồn kho thấp</span>
                            @else
                                <span class="badge bg-success">Đủ hàng</span>
                            @endif
                        </td>
                        <td>
                            <div class="d-flex gap-1 align-items-center flex-wrap">
                                {{-- Nhập kho nhanh --}}
                                <button type="button"
                                        class="btn btn-sm btn-primary"
                                        data-bs-toggle="modal"
                                        data-bs-target="#importModal"
                                        data-id="{{ $p->id }}"
                                        data-name="{{ $p->name }}"
                                        data-stock="{{ $p->stock }}">
                                    <i class="fas fa-plus"></i> Nhập
                                </button>
                                {{-- Xuất kho nhanh --}}
                                <button type="button"
                                        class="btn btn-sm btn-danger"
                                        @if($p->stock <= 0) disabled @endif
                                        data-bs-toggle="modal"
                                        data-bs-target="#exportModal"
                                        data-id="{{ $p->id }}"
                                        data-name="{{ $p->name }}"
                                        data-stock="{{ $p->stock }}">
                                    <i class="fas fa-minus"></i> Xuất
                                </button>
                                {{-- Lịch sử --}}
                                <a href="{{ route('inventory.show', $p->id) }}" class="btn btn-sm btn-secondary">
                                    <i class="fas fa-history"></i>
                                </a>
                            </div>
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="10" class="text-center text-muted py-4">Không có sản phẩm nào.</td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        {{-- Pagination --}}
        <div class="d-flex justify-content-center mt-3">
            {{ $products->links() }}
        </div>
    </div>
</div>

{{-- Modal Nhập kho nhanh --}}
<div class="modal fade" id="importModal" tabindex="-1">
    <div class="modal-dialog modal-sm">
        <div class="modal-content">
            <form id="importForm" method="POST">
                @csrf
                <div class="modal-header">
                    <h5 class="modal-title"><i class="fas fa-plus text-primary me-1"></i> Nhập kho</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <p class="mb-2 fw-semibold" id="importProductName"></p>
                    <p class="text-muted small mb-3">Tồn kho hiện tại: <strong id="importCurrentStock"></strong></p>
                    <div class="mb-3">
                        <label class="form-label">Số lượng nhập <span class="text-danger">*</span></label>
                        <input type="number" name="quantity" class="form-control" min="1" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Ngày hết hạn</label>
                        <input type="date" name="expire_date" id="importExpireDate" class="form-control">
                        <div class="form-text text-muted">Để trống nếu lô không có hạn sử dụng</div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Ngày nhập lô</label>
                        <input type="date" name="batch_date" id="importBatchDate" class="form-control">
                    </div>
                    <div class="mb-2">
                        <label class="form-label">Ghi chú</label>
                        <input type="text" name="note" class="form-control" placeholder="Lý do nhập kho...">
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Hủy</button>
                    <button type="submit" class="btn btn-primary btn-sm">Xác nhận nhập</button>
                </div>
            </form>
        </div>
    </div>
</div>

{{-- Modal Xuất kho nhanh --}}
<div class="modal fade" id="exportModal" tabindex="-1">
    <div class="modal-dialog modal-sm">
        <div class="modal-content">
            <form id="exportForm" method="POST">
                @csrf
                <div class="modal-header">
                    <h5 class="modal-title"><i class="fas fa-minus text-danger me-1"></i> Xuất kho</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <p class="mb-2 fw-semibold" id="exportProductName"></p>
                    <p class="text-muted small mb-3">Tồn kho hiện tại: <strong id="exportCurrentStock"></strong></p>
                    <div class="mb-3">
                        <label class="form-label">Số lượng xuất <span class="text-danger">*</span></label>
                        <input type="number" name="quantity" id="exportQtyInput" class="form-control" min="1" required>
                    </div>
                    <div class="mb-2">
                        <label class="form-label">Ghi chú</label>
                        <input type="text" name="note" class="form-control" placeholder="Lý do xuất kho...">
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Hủy</button>
                    <button type="submit" class="btn btn-danger btn-sm">Xác nhận xuất</button>
                </div>
            </form>
        </div>
    </div>
</div>

@section('script')
<script>
// Nhập kho modal
document.getElementById('importModal').addEventListener('show.bs.modal', function (e) {
    const btn = e.relatedTarget;
    const id    = btn.dataset.id;
    const name  = btn.dataset.name;
    const stock = btn.dataset.stock;
    document.getElementById('importProductName').textContent = name;
    document.getElementById('importCurrentStock').textContent = stock;
    document.getElementById('importForm').action = '/inventory/' + id + '/import';
    document.querySelector('#importForm input[name=quantity]').value = '';
    const today = new Date();
    const expire = new Date(today); expire.setDate(expire.getDate() + 3);
    document.getElementById('importExpireDate').value = expire.toISOString().slice(0, 10);
    document.getElementById('importBatchDate').value = today.toISOString().slice(0, 10);
    document.querySelector('#importForm input[name=note]').value = '';
});

// Xuất kho modal
document.getElementById('exportModal').addEventListener('show.bs.modal', function (e) {
    const btn = e.relatedTarget;
    const id    = btn.dataset.id;
    const name  = btn.dataset.name;
    const stock = parseInt(btn.dataset.stock);
    document.getElementById('exportProductName').textContent = name;
    document.getElementById('exportCurrentStock').textContent = stock;
    document.getElementById('exportForm').action = '/inventory/' + id + '/quick-export';
    const qtyInput = document.getElementById('exportQtyInput');
    qtyInput.max   = stock;
    qtyInput.value = '';
    document.querySelector('#exportForm input[name=note]').value = '';
});
</script>
@endsection
@endsection
