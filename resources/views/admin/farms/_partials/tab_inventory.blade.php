<h6 class="text-muted mb-3">Lịch sử batch (50 batch gần nhất)</h6>

<div class="table-responsive">
    <table class="table table-hover align-middle">
        <thead>
            <tr>
                <th>ID</th>
                <th>Ngày nhập</th>
                <th>Sản phẩm</th>
                <th class="text-end">SL nhập</th>
                <th class="text-end">Đã bán</th>
                <th class="text-end">Còn lại</th>
                <th class="text-end">Giá vốn</th>
                <th>Hết hạn</th>
                <th>Trạng thái</th>
            </tr>
        </thead>
        <tbody>
            @forelse($batches as $b)
                <tr>
                    <td>#{{ $b->id }}</td>
                    <td>{{ $b->batch_date?->format('d/m/Y') }}</td>
                    <td>{{ $b->product?->name ?: '—' }}</td>
                    <td class="text-end">{{ number_format($b->quantity_in, 2) }}</td>
                    <td class="text-end">{{ number_format($b->quantity_sold, 2) }}</td>
                    <td class="text-end"><strong>{{ number_format($b->quantity_remaining, 2) }}</strong></td>
                    <td class="text-end">{{ number_format($b->cost_price, 0, ',', '.') }} đ</td>
                    <td>{{ $b->expire_date?->format('d/m/Y') ?: '—' }}</td>
                    <td>
                        @switch($b->status)
                            @case('active')   <span class="badge bg-success">Active</span> @break
                            @case('depleted') <span class="badge bg-secondary">Đã hết</span> @break
                            @case('expired')  <span class="badge bg-warning text-dark">Hết hạn</span> @break
                            @case('recalled') <span class="badge bg-danger">Thu hồi</span> @break
                            @default          <span class="badge bg-light text-dark">{{ $b->status }}</span>
                        @endswitch
                    </td>
                </tr>
            @empty
                <tr>
                    <td colspan="9" class="text-center text-muted py-4">Farm chưa nhập batch nào.</td>
                </tr>
            @endforelse
        </tbody>
    </table>
</div>

<p class="text-muted small mb-0">
    Quản lý nhập batch + đóng batch thực hiện qua app Zalo (Farm Hub). Trang admin chỉ xem.
</p>
