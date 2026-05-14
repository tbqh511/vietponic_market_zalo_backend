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
            <div class="alert alert-success">{{ session('success') }}</div>
        @endif

        {{-- Filters --}}
        <form method="GET" class="mb-3">
            <div class="row g-2">
                <div class="col-sm-4">
                    <select name="category_id" class="form-select" onchange="this.form.submit()">
                        <option value="">-- Tất cả danh mục --</option>
                        @foreach($categories as $cat)
                            <option value="{{ $cat->id }}" {{ request('category_id') == $cat->id ? 'selected' : '' }}>
                                {{ $cat->name }}
                            </option>
                        @endforeach
                    </select>
                </div>
                <div class="col-sm-4">
                    <select name="stock_status" class="form-select" onchange="this.form.submit()">
                        <option value="">-- Tất cả trạng thái --</option>
                        <option value="out"  {{ request('stock_status') === 'out'  ? 'selected' : '' }}>Hết hàng</option>
                        <option value="low"  {{ request('stock_status') === 'low'  ? 'selected' : '' }}>Tồn kho thấp</option>
                        <option value="ok"   {{ request('stock_status') === 'ok'   ? 'selected' : '' }}>Đủ hàng</option>
                    </select>
                </div>
            </div>
        </form>

        <div class="table-responsive">
            <table class="table table-hover align-middle">
                <thead class="table-light">
                    <tr>
                        <th style="width:50px">Ảnh</th>
                        <th>Tên sản phẩm</th>
                        <th>Danh mục</th>
                        <th class="text-center">Tồn kho</th>
                        <th class="text-center">Đặt giữ</th>
                        <th class="text-center">Khả dụng</th>
                        <th class="text-center">Ngưỡng</th>
                        <th class="text-center">Trạng thái</th>
                        <th>Thao tác</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($products as $p)
                    <tr>
                        <td>
                            @if($p->image)
                                <img src="{{ $p->image_url }}" alt="{{ $p->name }}"
                                     style="height:40px;width:40px;object-fit:cover;border-radius:4px;">
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
                        <td class="text-center fw-bold">{{ number_format($p->stock) }}</td>
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
                            <a href="{{ route('inventory.import', $p->id) }}" class="btn btn-sm btn-primary">
                                <i class="fas fa-plus"></i> Nhập
                            </a>
                            <a href="{{ route('inventory.show', $p->id) }}" class="btn btn-sm btn-secondary">
                                <i class="fas fa-history"></i> Lịch sử
                            </a>
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="9" class="text-center text-muted py-4">Không có sản phẩm nào.</td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>
@endsection
