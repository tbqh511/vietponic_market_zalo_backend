@php
$allMembers = collect();
if ($farm->owner) {
    $allMembers->push($farm->owner);
}
$allMembers = $allMembers->merge($farm->staff->sortBy(fn($m) => match($m->farm_role) {
    'admin' => 0, 'packer' => 1, 'shipper' => 2, default => 3
}));

$roleBadge = [
    'owner'   => ['label' => 'Chủ farm',          'class' => 'bg-primary'],
    'admin'   => ['label' => 'Quản lý',            'class' => 'bg-warning text-dark'],
    'packer'  => ['label' => 'Nhân viên đóng gói', 'class' => 'bg-info text-dark'],
    'shipper' => ['label' => 'Nhân viên giao hàng','class' => 'bg-success'],
];
@endphp

<div class="d-flex justify-content-between align-items-center mb-3">
    <div>
        <h6 class="text-muted mb-1">Thành viên Farm ({{ $allMembers->count() }})</h6>
        <div class="small text-muted">
            Chủ farm nhận payout. Quản lý/Đóng gói/Giao hàng hoạt động trong Hub nhưng không nhận tiền.
        </div>
    </div>
    @if($farm->owner_customer_id)
        <button class="btn btn-success btn-sm" data-bs-toggle="modal" data-bs-target="#attachStaffModal">
            + Thêm thành viên
        </button>
    @else
        <span class="text-muted small">Cần gán chủ farm trước khi thêm thành viên.</span>
    @endif
</div>

<div class="table-responsive">
    <table class="table table-hover align-middle">
        <thead>
            <tr>
                <th>ID</th>
                <th>Tên</th>
                <th>SĐT</th>
                <th>Vai trò</th>
                <th>Ngày cập nhật</th>
                <th class="text-end">Hành động</th>
            </tr>
        </thead>
        <tbody>
            @forelse($allMembers as $m)
                @php $badge = $roleBadge[$m->farm_role] ?? ['label' => $m->farm_role, 'class' => 'bg-secondary']; @endphp
                <tr>
                    <td class="text-muted small">#{{ $m->id }}</td>
                    <td>
                        <a href="{{ route('zalo-customers.show', $m->id) }}" class="fw-semibold text-decoration-none">
                            {{ $m->name ?: '—' }}
                        </a>
                    </td>
                    <td class="small">{{ $m->mobile ?: '—' }}</td>
                    <td>
                        <span class="badge {{ $badge['class'] }}">{{ $badge['label'] }}</span>
                    </td>
                    <td class="small text-muted">{{ $m->updated_at?->format('d/m/Y') }}</td>
                    <td class="text-end">
                        @if(! $m->isFarmOwner())
                            <button class="btn btn-outline-secondary btn-sm me-1"
                                    data-bs-toggle="modal"
                                    data-bs-target="#changeRoleModal{{ $m->id }}">
                                Đổi vai trò
                            </button>
                            <form action="{{ route('farms.staff.detach', [$farm->id, $m->id]) }}" method="POST" class="d-inline"
                                  onsubmit="return confirm('Gỡ {{ addslashes($m->name ?: '#'.$m->id) }} khỏi farm?\nThành viên sẽ mất quyền truy cập Farm Hub.');">
                                @csrf
                                @method('DELETE')
                                <button class="btn btn-danger btn-sm">Gỡ</button>
                            </form>
                        @else
                            <span class="text-muted small">—</span>
                        @endif
                    </td>
                </tr>
            @empty
                <tr>
                    <td colspan="6" class="text-center text-muted py-4">
                        Chưa có thành viên nào.
                    </td>
                </tr>
            @endforelse
        </tbody>
    </table>
</div>

{{-- Modal đổi vai trò: 1 modal/thành viên non-owner --}}
@foreach($farm->staff as $m)
<div class="modal fade" id="changeRoleModal{{ $m->id }}" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-sm">
        <form action="{{ route('farms.staff.change-role', [$farm->id, $m->id]) }}" method="POST">
            @csrf
            @method('PATCH')
            <div class="modal-content">
                <div class="modal-header py-2">
                    <h6 class="modal-title">Đổi vai trò — {{ $m->name ?: '#'.$m->id }}</h6>
                    <button type="button" class="btn-close btn-sm" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body py-2">
                    <select name="role" class="form-select form-select-sm" required>
                        <option value="admin"   @selected($m->farm_role === 'admin')>Quản lý</option>
                        <option value="packer"  @selected($m->farm_role === 'packer')>Nhân viên đóng gói</option>
                        <option value="shipper" @selected($m->farm_role === 'shipper')>Nhân viên giao hàng</option>
                    </select>
                </div>
                <div class="modal-footer py-2">
                    <button type="button" class="btn btn-sm btn-outline-secondary" data-bs-dismiss="modal">Huỷ</button>
                    <button type="submit" class="btn btn-sm btn-primary">Lưu</button>
                </div>
            </div>
        </form>
    </div>
</div>
@endforeach

{{-- Modal thêm thành viên --}}
<div class="modal fade" id="attachStaffModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <form action="{{ route('farms.staff.attach', $farm->id) }}" method="POST">
            @csrf
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Thêm thành viên vào Farm</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    @if(!$hasStaffCandidates)
                        <div class="alert alert-info mb-0">
                            Không có khách hàng nào thoả điều kiện (đang hoạt động + chưa thuộc farm).
                            Vào <a href="{{ route('zalo-customers.index') }}">Quản lý Khách hàng</a> để kiểm tra.
                        </div>
                    @else
                        <div class="mb-3">
                            <label class="form-label">Khách hàng <span class="text-danger">*</span></label>
                            <select name="customer_id" id="selectStaffCandidate" class="form-select" required></select>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Vai trò <span class="text-danger">*</span></label>
                            <select name="role" class="form-select" required>
                                <option value="admin">Quản lý — quyền vận hành đầy đủ (như chủ, trừ payout)</option>
                                <option value="packer" selected>Nhân viên đóng gói</option>
                                <option value="shipper">Nhân viên giao hàng nội bộ</option>
                            </select>
                        </div>
                    @endif
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Huỷ</button>
                    @if($hasStaffCandidates)
                        <button type="submit" class="btn btn-success">Thêm</button>
                    @endif
                </div>
            </div>
        </form>
    </div>
</div>
