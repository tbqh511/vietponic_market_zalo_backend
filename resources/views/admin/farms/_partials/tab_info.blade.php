<div class="row g-4">
    <div class="col-md-6">
        <h6 class="text-muted mb-3">Thông tin Farm</h6>
        <table class="table table-sm">
            <tr><td style="width:40%" class="text-muted">Mã</td><td><code>{{ $farm->code }}</code></td></tr>
            <tr><td class="text-muted">Tên</td><td>{{ $farm->name }}</td></tr>
            <tr><td class="text-muted">Mô tả</td><td>{{ $farm->description ?: '—' }}</td></tr>
            <tr><td class="text-muted">Địa chỉ</td><td>{{ $farm->address ?: '—' }}</td></tr>
            <tr><td class="text-muted">Tỷ lệ hoa hồng</td>
                <td>{{ number_format($farm->commission_rate * 100, 2) }}%</td></tr>
            <tr><td class="text-muted">Chu kỳ thanh toán</td><td>{{ $farm->payment_cycle }}</td></tr>
            <tr><td class="text-muted">Ngày duyệt</td>
                <td>{{ $farm->approved_at?->format('d/m/Y H:i') ?: '—' }}</td></tr>
            <tr><td class="text-muted">Người duyệt</td>
                <td>{{ $farm->approver?->name ?: '—' }}</td></tr>
        </table>
        <a href="{{ route('farms.edit', $farm->id) }}" class="btn btn-secondary btn-sm">Sửa thông tin</a>
    </div>

    <div class="col-md-6">
        <h6 class="text-muted mb-3">Chủ Farm (Owner)</h6>
        @if($farm->owner)
            <table class="table table-sm">
                <tr><td style="width:40%" class="text-muted">Customer ID</td>
                    <td>
                        <a href="{{ route('zalo-customers.show', $farm->owner->id) }}">#{{ $farm->owner->id }}</a>
                    </td></tr>
                <tr><td class="text-muted">Họ tên</td><td>{{ $farm->owner->name ?: '—' }}</td></tr>
                <tr><td class="text-muted">SĐT</td><td>{{ $farm->owner->mobile ?: '—' }}</td></tr>
                <tr><td class="text-muted">Email</td><td>{{ $farm->owner->email ?: '—' }}</td></tr>
                <tr><td class="text-muted">farm_partner_status</td>
                    <td><code>{{ $farm->owner->farm_partner_status ?: 'none' }}</code></td></tr>
            </table>

            @if($farm->is_active)
                <button type="button" class="btn btn-warning btn-sm"
                        data-bs-toggle="modal" data-bs-target="#transferOwnershipModal">
                    Chuyển chủ farm cho người khác
                </button>
            @endif

            <h6 class="text-muted mt-4 mb-2">Tài khoản nhận tiền</h6>
            <table class="table table-sm">
                <tr><td style="width:40%" class="text-muted">Ngân hàng</td>
                    <td>{{ $farm->owner->farm_bank_name ?: '—' }}</td></tr>
                <tr><td class="text-muted">Số tài khoản</td>
                    <td>{{ $farm->owner->farm_bank_account ?: '—' }}</td></tr>
                <tr><td class="text-muted">Chủ tài khoản</td>
                    <td>{{ $farm->owner->farm_bank_holder ?: '—' }}</td></tr>
            </table>
            @if(empty($farm->owner->farm_bank_account))
                <div class="alert alert-warning py-2 small mb-0">
                    Chưa có thông tin TK nhận tiền — cần liên hệ farm để cập nhật trước khi tạo lệnh chi.
                </div>
            @endif
        @else
            <div class="alert alert-warning">Farm này chưa có Owner Customer.</div>
        @endif
    </div>
</div>
