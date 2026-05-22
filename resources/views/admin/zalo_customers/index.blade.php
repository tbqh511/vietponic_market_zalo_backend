@extends('layouts.main')

@section('content')

@if(session('success'))
    <div class="alert alert-success alert-dismissible fade show" role="alert">
        {{ session('success') }}
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
@endif
@if(session('error'))
    <div class="alert alert-danger alert-dismissible fade show" role="alert">
        {{ session('error') }}
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
@endif

<div class="card">
    <div class="card-header">
        <div class="d-flex justify-content-between align-items-start flex-wrap gap-2">
            <h4 class="mb-0">Quản lý Khách hàng</h4>
            <form method="GET" action="{{ route('zalo-customers.index') }}" class="d-flex flex-wrap gap-2 align-items-center">
                <input type="text" name="q" value="{{ request('q') }}"
                       placeholder="Tìm tên / SĐT / email" class="form-control" style="max-width:220px;">
                <select name="is_active" class="form-select" style="max-width:160px;" onchange="this.form.submit()">
                    <option value="">Tất cả trạng thái</option>
                    <option value="1" {{ request('is_active') === '1' ? 'selected' : '' }}>Đang hoạt động</option>
                    <option value="0" {{ request('is_active') === '0' ? 'selected' : '' }}>Đã tắt</option>
                </select>
                <select name="farm_status" class="form-select" style="max-width:190px;" onchange="this.form.submit()">
                    <option value="">Tất cả trạng thái Farm</option>
                    <option value="approved"  {{ request('farm_status') === 'approved'  ? 'selected' : '' }}>Farm đã duyệt</option>
                    <option value="requested" {{ request('farm_status') === 'requested' ? 'selected' : '' }}>Đang xin duyệt</option>
                    <option value="none"      {{ request('farm_status') === 'none'      ? 'selected' : '' }}>Chưa là Farm</option>
                </select>
                <input type="date" name="date_from" value="{{ request('date_from') }}"
                       class="form-control" style="max-width:150px;" title="Từ ngày">
                <input type="date" name="date_to" value="{{ request('date_to') }}"
                       class="form-control" style="max-width:150px;" title="Đến ngày">
                <button type="submit" class="btn btn-secondary">Lọc</button>
                @if(request()->hasAny(['q','is_active','farm_status','date_from','date_to']))
                    <a href="{{ route('zalo-customers.index') }}" class="btn btn-outline-secondary">Xoá lọc</a>
                @endif
            </form>
        </div>
    </div>

    <div class="card-body">
        <div class="table-responsive">
            <table class="table table-hover align-middle">
                <thead class="table-light">
                    <tr>
                        <th>ID</th>
                        <th>Họ tên</th>
                        <th>SĐT</th>
                        <th>Email</th>
                        <th class="text-center">Trạng thái</th>
                        <th class="text-center">Farm Partner</th>
                        <th>Ngày tham gia</th>
                        <th style="min-width:220px;">Thao tác</th>
                    </tr>
                </thead>
                <tbody>
                @forelse($customers as $c)
                    <tr>
                        <td class="text-muted small">{{ $c->id }}</td>
                        <td>
                            <a href="{{ route('zalo-customers.show', $c->id) }}" class="fw-semibold text-decoration-none">
                                {{ $c->name ?: '—' }}
                            </a>
                            @if($c->logintype)
                                <br><span class="badge bg-secondary" style="font-size:0.65rem;">{{ $c->logintype }}</span>
                            @endif
                        </td>
                        <td class="small">{{ $c->mobile ?: '—' }}</td>
                        <td class="small text-muted">{{ $c->email ?: '—' }}</td>

                        {{-- Toggle Active --}}
                        <td class="text-center">
                            <form action="{{ route('zalo-customers.toggle-active', $c->id) }}" method="POST" class="d-inline"
                                  onsubmit="return confirm('{{ $c->isActive ? 'Vô hiệu hoá' : 'Kích hoạt' }} tài khoản {{ addslashes($c->name ?: '#'.$c->id) }}?')">
                                @csrf
                                @method('PATCH')
                                <button type="submit" class="btn btn-sm {{ $c->isActive ? 'btn-success' : 'btn-outline-danger' }}"
                                        title="{{ $c->isActive ? 'Đang hoạt động — nhấn để vô hiệu hoá' : 'Đã tắt — nhấn để kích hoạt' }}"
                                        style="min-width:90px;">
                                    @if($c->isActive)
                                        <i class="bi bi-check-circle-fill"></i> Hoạt động
                                    @else
                                        <i class="bi bi-slash-circle"></i> Đã tắt
                                    @endif
                                </button>
                            </form>
                        </td>

                        {{-- Trạng thái Farm Partner + thao tác inline --}}
                        <td class="text-center">
                            @php $fps = $c->farm_partner_status ?: 'none'; @endphp
                            <div class="d-flex flex-column align-items-center gap-1">
                                @if($fps === 'approved' && $c->isFarmOwner())
                                    <span class="badge bg-primary"><i class="bi bi-tree-fill"></i> Chủ farm</span>
                                    @if($c->farm)
                                        <div class="small text-muted" title="Mã: {{ $c->farm->code }}">
                                            {{ $c->farm->name }}
                                        </div>
                                    @endif
                                    <form action="{{ route('zalo-customers.suspend-farm', $c->id) }}" method="POST" class="d-inline"
                                          onsubmit="return confirm('Tạm dừng vai trò Farm Partner của {{ addslashes($c->name ?: '#'.$c->id) }}?\nFarm sẽ bị ẩn khỏi Hub.')">
                                        @csrf
                                        @method('PATCH')
                                        <button type="submit" class="btn btn-sm btn-outline-warning" title="Tạm dừng vai trò Farm Partner">
                                            <i class="bi bi-pause-circle"></i> Tạm dừng
                                        </button>
                                    </form>
                                @elseif($fps === 'approved' && $c->isFarmStaff())
                                    <span class="badge bg-info text-dark"><i class="bi bi-person-badge"></i> Nhân viên</span>
                                    @if($c->farm)
                                        <div class="small text-muted" title="Mã: {{ $c->farm->code }}">
                                            {{ $c->farm->name }}
                                        </div>
                                        <a href="{{ route('farms.show', $c->farm->id) }}#tab-staff"
                                           class="btn btn-sm btn-outline-secondary" title="Quản lý tại trang Farm">
                                            <i class="bi bi-box-arrow-up-right"></i> Vào farm
                                        </a>
                                    @endif
                                @elseif($fps === 'approved')
                                    {{-- Approved nhưng chưa có farm_id/farm_role — data lệch, hiển thị cảnh báo --}}
                                    <span class="badge bg-warning text-dark"><i class="bi bi-exclamation-triangle"></i> Approved chưa gán farm</span>
                                @elseif($fps === 'requested')
                                    <span class="badge bg-warning text-dark"><i class="bi bi-hourglass-split"></i> Đang xin</span>
                                    <button type="button" class="btn btn-sm btn-outline-success"
                                            data-bs-toggle="modal" data-bs-target="#promoteFarmModal"
                                            data-customer-id="{{ $c->id }}" data-customer-name="{{ $c->name ?: '#'.$c->id }}"
                                            title="Duyệt và gán Farm">
                                        <i class="bi bi-check2-circle"></i> Duyệt
                                    </button>
                                @elseif($fps === 'suspended')
                                    <span class="badge bg-secondary"><i class="bi bi-slash-circle"></i> Tạm dừng</span>
                                    <div class="small text-muted">Kích hoạt lại trong /farms</div>
                                @else
                                    <span class="text-muted small">—</span>
                                    <div class="d-flex gap-1 flex-wrap justify-content-center">
                                        <button type="button" class="btn btn-sm btn-outline-success"
                                                data-bs-toggle="modal" data-bs-target="#promoteFarmModal"
                                                data-customer-id="{{ $c->id }}" data-customer-name="{{ $c->name ?: '#'.$c->id }}"
                                                title="Chỉ định làm chủ farm">
                                            <i class="bi bi-tree"></i> Chủ farm
                                        </button>
                                        <button type="button" class="btn btn-sm btn-outline-info"
                                                data-bs-toggle="modal" data-bs-target="#assignStaffModal"
                                                data-customer-id="{{ $c->id }}" data-customer-name="{{ $c->name ?: '#'.$c->id }}"
                                                title="Gán làm nhân viên farm có sẵn"
                                                @if($farmsWithOwner->isEmpty()) disabled @endif>
                                            <i class="bi bi-person-plus"></i> Staff
                                        </button>
                                    </div>
                                @endif
                            </div>
                        </td>

                        <td class="small text-muted">{{ $c->created_at?->format('d/m/Y') }}</td>

                        <td>
                            <div class="d-flex gap-1 flex-wrap">
                                <a href="{{ route('zalo-customers.show', $c->id) }}"
                                   class="btn btn-sm btn-outline-primary" title="Xem chi tiết">
                                    <i class="bi bi-eye"></i>
                                </a>
                                <a href="{{ route('zalo-customers.edit', $c->id) }}"
                                   class="btn btn-sm btn-outline-secondary" title="Sửa thông tin">
                                    <i class="bi bi-pencil"></i>
                                </a>
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="8" class="text-center text-muted py-4">Chưa có khách hàng nào</td>
                    </tr>
                @endforelse
                </tbody>
            </table>
        </div>

        <div class="d-flex justify-content-between align-items-center mt-3">
            <div class="text-muted small">
                Tổng: <strong>{{ $customers->total() }}</strong> khách hàng
            </div>
            {{ $customers->links() }}
        </div>
    </div>
</div>

{{-- Modal: Chỉ định Farm Partner --}}
<div class="modal fade" id="promoteFarmModal" tabindex="-1" aria-labelledby="promoteFarmModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <form id="promoteFarmForm" method="POST" action="">
            @csrf
            @method('PATCH')
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="promoteFarmModalLabel">
                        <i class="bi bi-tree-fill"></i> Chỉ định Farm Partner
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Đóng"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label small text-muted mb-1">Khách hàng</label>
                        <div class="form-control bg-light" id="promoteFarmCustomerName">—</div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label fw-semibold">Phương án</label>
                        <div class="form-check">
                            <input class="form-check-input" type="radio" name="mode" id="modeNew" value="new" checked>
                            <label class="form-check-label" for="modeNew">
                                <i class="bi bi-plus-circle"></i> Tạo Farm mới
                            </label>
                        </div>
                        <div class="form-check">
                            <input class="form-check-input" type="radio" name="mode" id="modeExisting" value="existing"
                                {{ $availableFarms->isEmpty() ? 'disabled' : '' }}>
                            <label class="form-check-label" for="modeExisting">
                                <i class="bi bi-link-45deg"></i> Gán vào Farm có sẵn (chưa có chủ)
                                @if($availableFarms->isEmpty())
                                    <span class="small text-muted">— không có farm nào trống</span>
                                @endif
                            </label>
                        </div>
                    </div>

                    {{-- Block: Tạo farm mới --}}
                    <div id="newFarmBlock">
                        <div class="mb-3">
                            <label for="farm_name" class="form-label">Tên Farm <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" id="farm_name" name="farm_name" maxlength="150"
                                   placeholder="VD: Farm Đà Lạt — Cầu Đất">
                            <div class="form-text">Mã farm sẽ được tự động sinh từ tên (vd: FARM-FARMDALAT-001).</div>
                        </div>
                        <div class="mb-3">
                            <label for="farm_address" class="form-label">Địa chỉ</label>
                            <input type="text" class="form-control" id="farm_address" name="farm_address" maxlength="255"
                                   placeholder="Tuỳ chọn">
                        </div>
                        <div class="mb-3">
                            <label for="farm_description" class="form-label">Mô tả ngắn</label>
                            <textarea class="form-control" id="farm_description" name="farm_description" rows="2"
                                      maxlength="1000" placeholder="Tuỳ chọn"></textarea>
                        </div>
                    </div>

                    {{-- Block: Chọn farm có sẵn --}}
                    <div id="existingFarmBlock" class="d-none">
                        <div class="mb-3">
                            <label for="farm_id" class="form-label">Chọn Farm <span class="text-danger">*</span></label>
                            <select class="form-select" id="farm_id" name="farm_id" disabled>
                                <option value="">— Chọn farm —</option>
                                @foreach($availableFarms as $f)
                                    <option value="{{ $f->id }}">{{ $f->name }} ({{ $f->code }})</option>
                                @endforeach
                            </select>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Huỷ</button>
                    <button type="submit" class="btn btn-success">
                        <i class="bi bi-check2-circle"></i> Xác nhận
                    </button>
                </div>
            </div>
        </form>
    </div>
</div>

{{-- Modal: Gán làm Nhân viên (Staff) — chọn farm đã có chủ + active --}}
<div class="modal fade" id="assignStaffModal" tabindex="-1" aria-labelledby="assignStaffModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <form id="assignStaffForm" method="POST" action="">
            @csrf
            @method('PATCH')
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="assignStaffModalLabel">
                        <i class="bi bi-person-plus-fill"></i> Gán làm Nhân viên Farm
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Đóng"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label small text-muted mb-1">Khách hàng</label>
                        <div class="form-control bg-light" id="assignStaffCustomerName">—</div>
                    </div>

                    <div class="mb-3">
                        <label for="assign_farm_id" class="form-label">Farm <span class="text-danger">*</span></label>
                        <select class="form-select" id="assign_farm_id" name="farm_id" required>
                            <option value="">— Chọn farm —</option>
                            @foreach($farmsWithOwner as $f)
                                <option value="{{ $f->id }}">{{ $f->name }} ({{ $f->code }})</option>
                            @endforeach
                        </select>
                        <div class="form-text">
                            Chỉ liệt kê farm <strong>đã có chủ</strong> và đang active. Nhân viên có full quyền
                            trong Farm Hub nhưng <strong>không nhận payout</strong> — tiền vẫn về chủ farm.
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Huỷ</button>
                    <button type="submit" class="btn btn-info">
                        <i class="bi bi-check2-circle"></i> Xác nhận
                    </button>
                </div>
            </div>
        </form>
    </div>
</div>

<script>
(function () {
    const modal       = document.getElementById('promoteFarmModal');
    const form        = document.getElementById('promoteFarmForm');
    const nameDisplay = document.getElementById('promoteFarmCustomerName');
    const modeNew     = document.getElementById('modeNew');
    const modeExist   = document.getElementById('modeExisting');
    const blockNew    = document.getElementById('newFarmBlock');
    const blockExist  = document.getElementById('existingFarmBlock');
    const inputName   = document.getElementById('farm_name');
    const inputAddr   = document.getElementById('farm_address');
    const inputDesc   = document.getElementById('farm_description');
    const selectFarm  = document.getElementById('farm_id');
    const baseUrl     = @json(url('zalo-customers'));

    function applyMode() {
        const isNew = modeNew.checked;
        blockNew.classList.toggle('d-none',  !isNew);
        blockExist.classList.toggle('d-none', isNew);
        // Toggle disabled để required-by-attribute không kích hoạt block ẩn.
        inputName.required = isNew;
        selectFarm.disabled = isNew;
        selectFarm.required = !isNew;
    }

    modeNew.addEventListener('change',   applyMode);
    modeExist.addEventListener('change', applyMode);

    modal.addEventListener('show.bs.modal', function (event) {
        const trigger = event.relatedTarget;
        if (!trigger) return;
        const id   = trigger.getAttribute('data-customer-id');
        const name = trigger.getAttribute('data-customer-name') || ('#' + id);
        form.action = baseUrl + '/' + id + '/promote-farm';
        nameDisplay.textContent = name;
    });

    modal.addEventListener('hidden.bs.modal', function () {
        form.reset();
        modeNew.checked = true;
        applyMode();
    });

    applyMode();
})();

(function () {
    const modal       = document.getElementById('assignStaffModal');
    const form        = document.getElementById('assignStaffForm');
    const nameDisplay = document.getElementById('assignStaffCustomerName');
    const selectFarm  = document.getElementById('assign_farm_id');
    const baseUrl     = @json(url('zalo-customers'));

    if (!modal) return;

    modal.addEventListener('show.bs.modal', function (event) {
        const trigger = event.relatedTarget;
        if (!trigger) return;
        const id   = trigger.getAttribute('data-customer-id');
        const name = trigger.getAttribute('data-customer-name') || ('#' + id);
        form.action = baseUrl + '/' + id + '/assign-staff';
        nameDisplay.textContent = name;
    });

    modal.addEventListener('hidden.bs.modal', function () {
        form.reset();
        selectFarm.value = '';
    });
})();
</script>

@endsection
