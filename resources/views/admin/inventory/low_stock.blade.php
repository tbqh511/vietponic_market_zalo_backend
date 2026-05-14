@extends('layouts.main')

@section('content')
<div class="card">
    <div class="card-header d-flex justify-content-between align-items-center">
        <h4 class="mb-0">
            <i class="fas fa-exclamation-triangle text-warning"></i>
            Sản phẩm tồn kho thấp
        </h4>
        <a href="{{ route('inventory.index') }}" class="btn btn-secondary btn-sm">
            <i class="fas fa-arrow-left"></i> Quay lại
        </a>
    </div>
    <div class="card-body">
        @if(session('success'))
            <div class="alert alert-success">{{ session('success') }}</div>
        @endif

        @if($products->isEmpty())
            <div class="text-center py-5 text-muted">
                <i class="fas fa-check-circle fs-1 text-success mb-2"></i>
                <p>Tất cả sản phẩm đều có tồn kho đầy đủ.</p>
            </div>
        @else
            <p class="text-muted mb-3">
                Có <strong>{{ $products->count() }}</strong> sản phẩm có tồn kho ≤ ngưỡng cảnh báo.
            </p>
            <div class="table-responsive">
                <table class="table table-hover align-middle">
                    <thead class="table-light">
                        <tr>
                            <th style="width:50px">Ảnh</th>
                            <th>Tên sản phẩm</th>
                            <th>Danh mục</th>
                            <th class="text-center">Tồn kho</th>
                            <th class="text-center">Khả dụng</th>
                            <th class="text-center">Ngưỡng</th>
                            <th class="text-center">Trạng thái</th>
                            <th>Thao tác</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($products as $p)
                        <tr class="{{ $p->stock <= 0 ? 'table-danger' : 'table-warning' }}">
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
                            <td class="small text-muted">{{ $p->category?->name }}</td>
                            <td class="text-center fw-bold">{{ $p->stock }}</td>
                            <td class="text-center fw-bold">{{ $p->stock_available }}</td>
                            <td class="text-center text-muted">{{ $p->reorder_point }}</td>
                            <td class="text-center">
                                @if($p->stock <= 0)
                                    <span class="badge bg-danger">Hết hàng</span>
                                @else
                                    <span class="badge bg-warning text-dark">Tồn kho thấp</span>
                                @endif
                            </td>
                            <td>
                                <a href="{{ route('inventory.import', $p->id) }}" class="btn btn-sm btn-primary">
                                    <i class="fas fa-plus"></i> Nhập kho
                                </a>
                            </td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </div>
</div>
@endsection
