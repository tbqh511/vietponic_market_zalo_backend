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

        {{-- Movement history — sau khi chuyển sang batch model, "movement" = order_items
             đã trừ vào batch FEFO. Cột "trước/sau" không còn ý nghĩa per-row (vì
             allocation song song giữa nhiều batch), nên view chỉ hiển thị qty xuất
             + batch nguồn + đơn hàng. --}}
        <div class="card">
            <div class="card-header"><h6 class="mb-0">Lịch sử xuất kho (theo đơn hàng / batch)</h6></div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-sm mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>Thời gian</th>
                                <th>Batch</th>
                                <th class="text-center">Số lượng</th>
                                <th>Đơn hàng</th>
                                <th>Trạng thái đơn</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($movements as $m)
                            <tr>
                                <td class="small text-muted text-nowrap">
                                    {{ $m->order?->created_at?->format('d/m/Y H:i') }}
                                </td>
                                <td class="small">
                                    @if($m->batch)
                                        <span class="badge bg-light text-dark border">#{{ $m->batch->id }}</span>
                                        <span class="text-muted">— {{ $m->batch->batch_date?->format('d/m/Y') }}</span>
                                    @else
                                        <span class="text-muted">backorder</span>
                                    @endif
                                </td>
                                <td class="text-center fw-bold text-danger">
                                    -{{ rtrim(rtrim(number_format((float) $m->quantity, 2, '.', ''), '0'), '.') }}
                                </td>
                                <td class="small">
                                    <a href="{{ route('zalo-orders.show', $m->order_id) }}">#{{ $m->order_id }}</a>
                                </td>
                                <td class="small">
                                    <span class="badge bg-info text-dark">{{ $m->order?->status ?? '—' }}</span>
                                </td>
                            </tr>
                            @empty
                            <tr>
                                <td colspan="5" class="text-center text-muted py-3">Chưa có lịch sử xuất kho.</td>
                            </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
                @if($movements->hasPages())
                <div class="p-3">{{ $movements->links() }}</div>
                @endif
            </div>
        </div>
    </div>
</div>
@endsection
