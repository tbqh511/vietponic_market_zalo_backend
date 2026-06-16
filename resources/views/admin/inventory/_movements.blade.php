{{-- Bảng lịch sử nhập/xuất kho — tái dùng cho load đầu trang và AJAX real-time. --}}
<div class="d-flex justify-content-between align-items-center px-3 pt-3 pb-2 text-muted small">
    <span>
        @if($movements->total() > 0)
            Hiển thị {{ $movements->firstItem() }}–{{ $movements->lastItem() }}
            trong tổng số {{ $movements->total() }} lượt
        @else
            Không có lượt nhập/xuất nào
        @endif
    </span>
</div>
<div class="table-responsive">
    <table class="table table-sm align-middle mb-0">
        <thead class="table-light">
            <tr>
                <th class="text-nowrap">Thời gian</th>
                <th>Loại</th>
                <th>Nguồn</th>
                <th>Batch</th>
                <th class="text-center">Số lượng</th>
                <th>Đơn hàng</th>
                <th>Ghi chú</th>
            </tr>
        </thead>
        <tbody>
            @forelse($movements as $m)
            <tr>
                <td class="small text-muted text-nowrap">
                    {{ optional($m->created_at)->format('d/m/Y H:i') }}
                </td>
                <td>
                    @if($m->type === 'in')
                        <span class="badge bg-success">Nhập</span>
                    @else
                        <span class="badge bg-danger">Xuất</span>
                    @endif
                </td>
                <td class="small text-nowrap">
                    <span class="badge bg-secondary">{{ \App\Models\StockMovement::sourceLabel($m->source) }}</span>
                </td>
                <td class="small text-nowrap">
                    @if($m->batch_id)
                        <span class="badge bg-light border" style="color:#212529;">#{{ $m->batch_id }}</span>
                        @if($m->batch)
                            <span class="text-muted">— {{ optional($m->batch->batch_date)->format('d/m/Y') }}</span>
                        @endif
                    @else
                        <span class="text-muted">—</span>
                    @endif
                </td>
                <td class="text-center fw-bold {{ $m->quantity >= 0 ? 'text-success' : 'text-danger' }}">
                    {{ $m->quantity >= 0 ? '+' : '-' }}{{ rtrim(rtrim(number_format(abs((float) $m->quantity), 2, '.', ''), '0'), '.') }}
                </td>
                <td class="small">
                    @if($m->order_id)
                        <a href="{{ route('zalo-orders.show', $m->order_id) }}">#{{ $m->order_id }}</a>
                    @else
                        <span class="text-muted">—</span>
                    @endif
                </td>
                <td class="small text-muted">{{ $m->note ?: '—' }}</td>
            </tr>
            @empty
            <tr>
                <td colspan="7" class="text-center text-muted py-3">Chưa có lịch sử nhập/xuất kho.</td>
            </tr>
            @endforelse
        </tbody>
    </table>
</div>
@if($movements->hasPages())
<div class="p-3">{{ $movements->links() }}</div>
@endif
