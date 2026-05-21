<div class="d-flex justify-content-between align-items-center mb-3">
    <h6 class="text-muted mb-0">Sản phẩm farm đang cung cấp ({{ $farm->products->count() }})</h6>
    <button class="btn btn-success btn-sm" data-bs-toggle="modal" data-bs-target="#attachProductModal">
        + Gắn thêm sản phẩm
    </button>
</div>

<div class="table-responsive">
    <table class="table table-hover align-middle">
        <thead>
            <tr>
                <th style="width:90px">Mã SP</th>
                <th style="width:72px">Ảnh</th>
                <th>Tên Sản phẩm</th>
                <th class="text-end">Giá vốn (pivot)</th>
                <th class="text-center">Primary?</th>
                <th class="text-end">Hành động</th>
            </tr>
        </thead>
        <tbody>
            @forelse($farm->products as $p)
                <tr>
                    <td><code>#{{ $p->id }}</code></td>
                    <td>
                        <img src="{{ $p->image_url }}" alt=""
                             style="width:48px;height:48px;object-fit:cover;border-radius:6px;background:#f3f4f6"
                             onerror="this.style.visibility='hidden'">
                    </td>
                    <td>{{ $p->name }}</td>
                    <td class="text-end">{{ number_format($p->pivot->cost_price, 0, ',', '.') }} đ</td>
                    <td class="text-center">
                        @if($p->pivot->is_primary)
                            <span class="badge bg-primary">Primary</span>
                        @else
                            <span class="text-muted">—</span>
                        @endif
                    </td>
                    <td class="text-end">
                        <form action="{{ route('farms.products.detach', [$farm->id, $p->id]) }}" method="POST" class="d-inline"
                              onsubmit="return confirm('Gỡ sản phẩm {{ addslashes($p->name) }} khỏi farm?');">
                            @csrf
                            @method('DELETE')
                            <button class="btn btn-danger btn-sm">Gỡ bỏ</button>
                        </form>
                    </td>
                </tr>
            @empty
                <tr>
                    <td colspan="6" class="text-center text-muted py-4">Chưa gắn sản phẩm nào. Bấm "Gắn thêm sản phẩm" để bắt đầu.</td>
                </tr>
            @endforelse
        </tbody>
    </table>
</div>

{{-- Modal gắn sản phẩm: dropdown chỉ liệt kê SP chưa gắn (controller đã lọc). --}}
<div class="modal fade" id="attachProductModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <form action="{{ route('farms.products.attach', $farm->id) }}" method="POST">
            @csrf
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Gắn sản phẩm vào Farm</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    @if($availableProducts->isEmpty())
                        <div class="alert alert-info mb-0">
                            Tất cả sản phẩm đã được gắn vào farm này. Cần tạo sản phẩm mới ở Quản lý Sản phẩm trước.
                        </div>
                    @else
                        <div class="mb-3">
                            <label class="form-label">Sản phẩm <span class="text-danger">*</span></label>
                            <select name="product_id" class="form-select" required>
                                <option value="">— Chọn sản phẩm —</option>
                                @foreach($availableProducts as $p)
                                    <option value="{{ $p->id }}">{{ $p->name }} (#{{ $p->id }})</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Giá vốn <span class="text-danger">*</span></label>
                            <input type="number" name="cost_price" step="100" min="0" class="form-control" required placeholder="Vd: 30000">
                            <small class="text-muted">Đơn vị: VND. Có thể override bằng batch riêng khi nhập kho.</small>
                        </div>
                        <div class="form-check">
                            <input type="checkbox" name="is_primary" value="1" id="isPrimaryCheck" class="form-check-input">
                            <label for="isPrimaryCheck" class="form-check-label">Đánh dấu Primary (farm chính cho SKU này)</label>
                        </div>
                    @endif
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-light" data-bs-dismiss="modal">Huỷ</button>
                    @if($availableProducts->isNotEmpty())
                        <button type="submit" class="btn btn-primary">Gắn sản phẩm</button>
                    @endif
                </div>
            </div>
        </form>
    </div>
</div>
