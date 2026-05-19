<div class="d-flex justify-content-between align-items-center mb-3">
    <div>
        <h6 class="text-muted mb-1">Nhân viên / Người vận hành ({{ $farm->staff->count() }})</h6>
        <div class="small text-muted">
            Chủ farm: <strong>{{ $farm->owner?->name ?? '— chưa có —' }}</strong>.
            Nhân viên có full quyền trong Farm Hub (nhập/xuất kho, xem doanh thu)
            nhưng không nhận payout — tiền vẫn về chủ farm.
        </div>
    </div>
    @if($farm->owner_customer_id)
        <button class="btn btn-success btn-sm" data-bs-toggle="modal" data-bs-target="#attachStaffModal">
            + Thêm nhân viên
        </button>
    @else
        <span class="text-muted small">Cần gán chủ farm trước khi thêm nhân viên.</span>
    @endif
</div>

<div class="table-responsive">
    <table class="table table-hover align-middle">
        <thead>
            <tr>
                <th>ID</th>
                <th>Tên</th>
                <th>SĐT</th>
                <th>Email</th>
                <th>Ngày thêm</th>
                <th class="text-end">Hành động</th>
            </tr>
        </thead>
        <tbody>
            @forelse($farm->staff as $s)
                <tr>
                    <td class="text-muted small">#{{ $s->id }}</td>
                    <td>
                        <a href="{{ route('zalo-customers.show', $s->id) }}" class="fw-semibold text-decoration-none">
                            {{ $s->name ?: '—' }}
                        </a>
                    </td>
                    <td class="small">{{ $s->mobile ?: '—' }}</td>
                    <td class="small text-muted">{{ $s->email ?: '—' }}</td>
                    <td class="small text-muted">{{ $s->updated_at?->format('d/m/Y') }}</td>
                    <td class="text-end">
                        <form action="{{ route('farms.staff.detach', [$farm->id, $s->id]) }}" method="POST" class="d-inline"
                              onsubmit="return confirm('Gỡ {{ addslashes($s->name ?: '#'.$s->id) }} khỏi farm?\nCustomer sẽ mất quyền truy cập Farm Hub.');">
                            @csrf
                            @method('DELETE')
                            <button class="btn btn-danger btn-sm">Gỡ</button>
                        </form>
                    </td>
                </tr>
            @empty
                <tr>
                    <td colspan="6" class="text-center text-muted py-4">
                        Chưa có nhân viên nào. Bấm "Thêm nhân viên" để bắt đầu.
                    </td>
                </tr>
            @endforelse
        </tbody>
    </table>
</div>

{{-- Modal thêm nhân viên: chỉ list customer active chưa thuộc farm nào (controller đã lọc). --}}
<div class="modal fade" id="attachStaffModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <form action="{{ route('farms.staff.attach', $farm->id) }}" method="POST">
            @csrf
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Thêm nhân viên vào Farm</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    @if($availableStaffCandidates->isEmpty())
                        <div class="alert alert-info mb-0">
                            Không có khách hàng nào thoả điều kiện (đang hoạt động + chưa thuộc farm).
                            Vào <a href="{{ route('zalo-customers.index') }}">Quản lý Khách hàng</a> để kiểm tra.
                        </div>
                    @else
                        <div class="mb-3">
                            <label class="form-label">Khách hàng <span class="text-danger">*</span></label>
                            <select name="customer_id" class="form-select" required>
                                <option value="">— Chọn khách hàng —</option>
                                @foreach($availableStaffCandidates as $c)
                                    <option value="{{ $c->id }}">
                                        {{ $c->name ?: '#'.$c->id }}{{ $c->mobile ? ' — '.$c->mobile : '' }}
                                    </option>
                                @endforeach
                            </select>
                            <small class="text-muted">
                                Chỉ hiển thị 50 khách đầu (đang active + chưa thuộc farm nào). Cần thêm khách
                                ngoài danh sách thì vào <a href="{{ route('zalo-customers.index') }}">Quản lý Khách hàng</a>
                                và dùng "Gán làm Staff".
                            </small>
                        </div>
                    @endif
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Huỷ</button>
                    @if($availableStaffCandidates->isNotEmpty())
                        <button type="submit" class="btn btn-success">Thêm</button>
                    @endif
                </div>
            </div>
        </form>
    </div>
</div>
