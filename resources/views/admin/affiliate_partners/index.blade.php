@extends('layouts.main')

@section('content')
    <div class="card mb-3">
        <div class="card-header d-flex justify-content-between align-items-center">
            <h4 class="mb-0">Cấu hình Affiliate</h4>
        </div>
        <div class="card-body">
            @if(session('success'))
                <div class="alert alert-success">{{ session('success') }}</div>
            @endif
            <div class="row g-3">
                <div class="col-md-4">
                    <form action="{{ route('affiliate-settings.commission-rate') }}" method="POST" class="d-flex gap-2">
                        @csrf
                        @method('PATCH')
                        <label class="me-2 align-self-center">Tỷ lệ hoa hồng (%):</label>
                        <input type="number" step="0.01" min="0" max="100" name="rate" value="{{ $commissionRate }}" class="form-control" style="max-width:140px;">
                        <button type="submit" class="btn btn-primary">Lưu</button>
                    </form>
                </div>
                <div class="col-md-4">
                    <form action="{{ route('affiliate-settings.auto-approve') }}" method="POST" class="d-flex gap-2">
                        @csrf
                        @method('PATCH')
                        <label class="me-2 align-self-center">Tự duyệt:</label>
                        <select name="enabled" class="form-select" style="max-width:160px;">
                            <option value="1" {{ $autoApprove ? 'selected' : '' }}>Bật</option>
                            <option value="0" {{ !$autoApprove ? 'selected' : '' }}>Tắt (manual)</option>
                        </select>
                        <button type="submit" class="btn btn-primary">Lưu</button>
                    </form>
                </div>
                <div class="col-md-4">
                    <form action="{{ route('affiliate-settings.enabled') }}" method="POST" class="d-flex gap-2">
                        @csrf
                        @method('PATCH')
                        <label class="me-2 align-self-center">Module:</label>
                        <select name="enabled" class="form-select" style="max-width:160px;">
                            <option value="1" {{ $enabled ? 'selected' : '' }}>Đang bật</option>
                            <option value="0" {{ !$enabled ? 'selected' : '' }}>Tạm tắt</option>
                        </select>
                        <button type="submit" class="btn btn-primary">Lưu</button>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <div class="card">
        <div class="card-header d-flex justify-content-between align-items-center flex-wrap gap-2">
            <h4 class="mb-0">Danh sách Cộng tác viên
                <span class="badge bg-secondary ms-1">{{ $partners->total() }}</span>
            </h4>
            <form id="filterForm" method="GET" class="d-flex gap-2 flex-wrap">
                <input type="text" id="searchInput" name="q" value="{{ request('q') }}"
                       placeholder="Tìm mã/tên/SĐT" class="form-control" style="max-width:220px;" autocomplete="off">
                <select id="statusFilter" name="status" class="form-select" style="max-width:160px;">
                    <option value="">Tất cả trạng thái</option>
                    <option value="pending"   {{ request('status') === 'pending'   ? 'selected' : '' }}>Chờ duyệt</option>
                    <option value="approved"  {{ request('status') === 'approved'  ? 'selected' : '' }}>Đã duyệt</option>
                    <option value="suspended" {{ request('status') === 'suspended' ? 'selected' : '' }}>Tạm khoá</option>
                    <option value="rejected"  {{ request('status') === 'rejected'  ? 'selected' : '' }}>Từ chối</option>
                </select>
                <button class="btn btn-secondary">Lọc</button>
            </form>
        </div>
        <div class="card-body">
            <table class="table align-middle">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Mã GT</th>
                        <th>Họ tên</th>
                        <th>SĐT</th>
                        <th>Trạng thái</th>
                        <th>Ngày duyệt</th>
                        <th>Thao tác</th>
                    </tr>
                </thead>
                <tbody>
                @forelse($partners as $p)
                    <tr id="row-{{ $p->id }}">
                        <td>{{ $p->id }}</td>
                        <td><code>{{ $p->affiliate_code }}</code></td>
                        <td>{{ $p->name }}</td>
                        <td>{{ $p->mobile }}</td>
                        <td>
                            <select class="form-select form-select-sm status-select status-{{ $p->affiliate_status }}"
                                    data-id="{{ $p->id }}"
                                    data-original="{{ $p->affiliate_status }}"
                                    style="min-width:120px;">
                                <option value="pending"   {{ $p->affiliate_status === 'pending'   ? 'selected' : '' }}>Chờ duyệt</option>
                                <option value="approved"  {{ $p->affiliate_status === 'approved'  ? 'selected' : '' }}>Đã duyệt</option>
                                <option value="suspended" {{ $p->affiliate_status === 'suspended' ? 'selected' : '' }}>Tạm khoá</option>
                                <option value="rejected"  {{ $p->affiliate_status === 'rejected'  ? 'selected' : '' }}>Từ chối</option>
                            </select>
                        </td>
                        <td class="approved-at">{{ $p->affiliate_approved_at }}</td>
                        <td>
                            <a href="{{ route('affiliate-partners.show', $p->id) }}" class="btn btn-sm btn-primary">Xem</a>
                            <a href="{{ route('affiliate-partners.edit', $p->id) }}" class="btn btn-sm btn-secondary">Sửa</a>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="7" class="text-center text-muted">Chưa có cộng tác viên nào</td></tr>
                @endforelse
                </tbody>
            </table>

            <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mt-2">
                <div class="text-muted small">
                    Hiển thị {{ $partners->firstItem() ?? 0 }}–{{ $partners->lastItem() ?? 0 }}
                    / {{ $partners->total() }} cộng tác viên
                </div>
                {{ $partners->links() }}
            </div>
        </div>
    </div>
@endsection

@section('script')
<style>
    .status-select.status-pending   { border-color: #fd7e14; color: #fd7e14; }
    .status-select.status-approved  { border-color: #28a745; color: #28a745; }
    .status-select.status-suspended { border-color: #6c757d; color: #6c757d; }
    .status-select.status-rejected  { border-color: #dc3545; color: #dc3545; }
</style>
<script>
(function () {
    var CSRF = '{{ csrf_token() }}';

    // ── Real-time filter ──────────────────────────────────────────────────────
    var searchTimer;
    $('#searchInput').on('input', function () {
        clearTimeout(searchTimer);
        searchTimer = setTimeout(function () {
            $('#filterForm').submit();
        }, 400);
    });

    $('#statusFilter').on('change', function () {
        $('#filterForm').submit();
    });

    // ── Inline status change ──────────────────────────────────────────────────
    $(document).on('change', '.status-select', function () {
        var $sel = $(this);
        var id   = $sel.data('id');
        var prev = $sel.data('original');
        var next = $sel.val();

        $sel.prop('disabled', true);

        $.ajax({
            url: '/affiliate-partners/' + id + '/status',
            method: 'PATCH',
            headers: { 'X-CSRF-TOKEN': CSRF, 'Accept': 'application/json' },
            data: { affiliate_status: next },
            success: function (res) {
                $sel.data('original', next);
                $sel.removeClass('status-pending status-approved status-suspended status-rejected')
                    .addClass('status-' + next);

                if (res.affiliate_approved_at) {
                    $sel.closest('tr').find('.approved-at').text(res.affiliate_approved_at);
                }

                Toastify({
                    text: 'Đã cập nhật trạng thái',
                    duration: 2500,
                    gravity: 'bottom',
                    position: 'right',
                    style: { background: '#28a745' }
                }).showToast();
            },
            error: function () {
                $sel.val(prev);
                Toastify({
                    text: 'Lỗi: không thể cập nhật trạng thái',
                    duration: 3000,
                    gravity: 'bottom',
                    position: 'right',
                    style: { background: '#dc3545' }
                }).showToast();
            },
            complete: function () {
                $sel.prop('disabled', false);
            }
        });
    });
})();
</script>
@endsection
