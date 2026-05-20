{{-- Modal "Chuyển chủ farm" — dùng chung cho nút ở header + nút trong tab thông tin.
     Auto-mở khi URL có ?action=transfer (từ link ở danh sách Farm hoặc trang
     sửa Khách hàng). Một modal duy nhất cho toàn page → include 1 lần ở
     show.blade.php (KHÔNG include lại trong tab_info để tránh duplicate DOM).
--}}
<div class="modal fade" id="transferOwnershipModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <form action="{{ route('farms.transfer-ownership', $farm->id) }}" method="POST">
            @csrf
            @method('PATCH')
            <input type="hidden" name="current_owner_id" value="{{ $farm->owner_customer_id ?? '' }}">

            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Chuyển chủ farm — {{ $farm->name }}</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="alert alert-info small">
                        <strong>Lưu ý:</strong> Chủ cũ
                        (<strong>{{ $farm->owner?->name ?? '— chưa có —' }}</strong>) sẽ tự động trở thành
                        <strong>nhân viên</strong> của farm này (giữ quyền truy cập Hub
                        nhưng không nhận payout). Để gỡ hẳn khỏi farm, vào Tab Nhân viên sau khi chuyển.
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Chủ mới <span class="text-danger">*</span></label>
                        <select name="new_owner_id" id="transferNewOwnerSelect" class="form-select" required>
                            <option value="">— Chọn customer —</option>
                            @foreach($transferCandidates as $c)
                                <option value="{{ $c->id }}"
                                        data-warning="{{ $c->_transfer_warning }}"
                                        data-blocked="{{ $c->_transfer_blocked ? '1' : '0' }}"
                                        @if($c->_transfer_blocked) disabled @endif>
                                    {{ $c->name ?: '#'.$c->id }}{{ $c->mobile ? ' — '.$c->mobile : '' }}
                                    @if($c->_transfer_warning) — {{ $c->_transfer_warning }} @endif
                                </option>
                            @endforeach
                        </select>
                        <small class="text-muted">
                            Hiển thị tối đa 200 customer active. Staff của farm này được xếp đầu danh sách.
                            Có thể gõ tên / SĐT để tìm nhanh.
                        </small>
                    </div>

                    {{-- Checkbox confirm chỉ hiện khi chọn ứng viên có cảnh báo (staff farm khác). --}}
                    <div class="mb-3 d-none" id="transferWarningBox">
                        <div class="alert alert-warning py-2 small mb-2" id="transferWarningText"></div>
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" name="confirm_warnings"
                                   value="1" id="transferConfirmCheck">
                            <label class="form-check-label small" for="transferConfirmCheck">
                                Tôi đã hiểu — vẫn tiếp tục chuyển chủ farm.
                            </label>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Huỷ</button>
                    <button type="submit" class="btn btn-warning" id="transferSubmitBtn">Chuyển chủ farm</button>
                </div>
            </div>
        </form>
    </div>
</div>

<script>
(function () {
    const sel = document.getElementById('transferNewOwnerSelect');
    if (!sel) return;
    const warnBox  = document.getElementById('transferWarningBox');
    const warnText = document.getElementById('transferWarningText');
    const confirmChk = document.getElementById('transferConfirmCheck');
    const submitBtn  = document.getElementById('transferSubmitBtn');

    function syncWarning() {
        const opt = sel.options[sel.selectedIndex];
        const warning = opt ? (opt.dataset.warning || '') : '';
        const blocked = opt ? opt.dataset.blocked === '1' : false;
        if (warning && !blocked) {
            warnText.textContent = warning;
            warnBox.classList.remove('d-none');
        } else {
            warnBox.classList.add('d-none');
            confirmChk.checked = false;
        }
        submitBtn.disabled = blocked;
    }
    sel.addEventListener('change', syncWarning);

    // Auto-mở modal khi URL có ?action=transfer (entry point từ /farms list +
    // trang sửa Khách hàng). Phải đợi DOM + Bootstrap sẵn sàng.
    document.addEventListener('DOMContentLoaded', function () {
        if (new URLSearchParams(window.location.search).get('action') === 'transfer') {
            const modalEl = document.getElementById('transferOwnershipModal');
            if (modalEl && window.bootstrap) {
                new bootstrap.Modal(modalEl).show();
            }
        }

        // Init Select2 nếu có (codebase đã load global ở layouts/include +
        // footer_script). Phải init SAU khi jQuery load — DOMContentLoaded
        // chưa đủ vì jQuery có thể load sau, nên thử + retry.
        function tryInitSelect2() {
            if (window.jQuery && jQuery.fn.select2) {
                jQuery(sel).select2({
                    dropdownParent: jQuery('#transferOwnershipModal'),
                    theme: 'bootstrap-5',
                    width: '100%',
                }).on('change', syncWarning);
                return true;
            }
            return false;
        }
        if (!tryInitSelect2()) {
            // jQuery có thể load ở cuối body — retry 1 lần sau khi window load.
            window.addEventListener('load', tryInitSelect2);
        }
    });
})();
</script>
