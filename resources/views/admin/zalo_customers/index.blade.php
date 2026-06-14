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
            <form id="customerFilterForm" method="GET" action="{{ route('zalo-customers.index') }}" class="d-flex flex-wrap gap-2 align-items-center">
                <input type="text" name="q" value="{{ request('q') }}"
                       placeholder="Tìm tên / SĐT / email" class="form-control" style="max-width:220px;">
                <input type="search" name="phone" value="{{ request('phone') }}" inputmode="numeric"
                       placeholder="Lọc nhanh SĐT (vd: 3878)" class="form-control" style="max-width:200px;"
                       title="Nhập một phần số điện thoại — vd 3878 sẽ khớp 84918963878">
                <select name="is_active" class="form-select" style="max-width:160px;">
                    <option value="">Tất cả trạng thái</option>
                    <option value="1" {{ request('is_active') === '1' ? 'selected' : '' }}>Đang hoạt động</option>
                    <option value="0" {{ request('is_active') === '0' ? 'selected' : '' }}>Đã tắt</option>
                </select>
                <select name="farm_status" class="form-select" style="max-width:190px;">
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
                @if(request()->hasAny(['q','phone','is_active','farm_status','date_from','date_to']))
                    <a href="{{ route('zalo-customers.index') }}" class="btn btn-outline-secondary">Xoá lọc</a>
                @endif
            </form>
        </div>
    </div>

    <div class="card-body">
        {{-- Vùng được live filter (AJAX) thay thế — xem _table.blade.php + script cuối file. --}}
        <div id="customersTable" aria-live="polite">
            @include('admin.zalo_customers._table')
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

/* ── Live filter (AJAX) ──────────────────────────────────────────────────────
 * Gõ vào ô SĐT / ô tìm kiếm hoặc đổi select/ngày → nạp lại CHỈ phần bảng
 * (#customersTable) qua AJAX, không reload cả trang nên không mất focus.
 * Vẫn search toàn bộ DB + giữ phân trang. Nút "Lọc" / Enter là fallback khi
 * tắt JS (submit thường). */
(function () {
    const form      = document.getElementById('customerFilterForm');
    const container = document.getElementById('customersTable');
    if (!form || !container) return;

    let timer   = null;
    let inflight = null;

    function buildQuery() {
        const params = new URLSearchParams(new FormData(form));
        // Bỏ field rỗng cho URL gọn (backend dùng filled() nên không ảnh hưởng).
        for (const key of [...params.keys()]) {
            if (params.get(key) === '') params.delete(key);
        }
        return params;
    }

    async function reload(url) {
        const params  = buildQuery();
        const cleanUrl = url || (form.action + (params.toString() ? '?' + params.toString() : ''));
        // Cập nhật URL trình duyệt (không kèm partial=1) để refresh/chia sẻ giữ filter.
        window.history.replaceState(null, '', cleanUrl);

        // Thêm partial=1 cho request lấy đúng phần bảng.
        const fetchUrl = cleanUrl + (cleanUrl.includes('?') ? '&' : '?') + 'partial=1';

        if (inflight) inflight.abort();
        inflight = new AbortController();
        container.style.opacity = '0.5';
        try {
            const res = await fetch(fetchUrl, {
                headers: { 'X-Requested-With': 'XMLHttpRequest' },
                signal: inflight.signal,
            });
            if (res.ok) container.innerHTML = await res.text();
        } catch (e) {
            if (e.name !== 'AbortError') console.error('Live filter failed:', e);
        } finally {
            container.style.opacity = '';
        }
    }

    const debounced = function () {
        clearTimeout(timer);
        timer = setTimeout(() => reload(), 350);
    };

    // Gõ text/SĐT → debounce; đổi select/ngày → nạp ngay.
    form.querySelectorAll('input[type="text"], input[type="search"]')
        .forEach(el => el.addEventListener('input', debounced));
    form.querySelectorAll('select, input[type="date"]')
        .forEach(el => el.addEventListener('change', () => reload()));

    // Phân trang trong vùng bảng → nạp AJAX thay vì reload cả trang.
    container.addEventListener('click', function (e) {
        const link = e.target.closest('.pagination a');
        if (!link || !link.href) return;
        e.preventDefault();
        // Bỏ partial khỏi href hiển thị; reload() tự thêm lại.
        const u = new URL(link.href);
        u.searchParams.delete('partial');
        reload(u.toString());
    });
})();
</script>

@endsection
