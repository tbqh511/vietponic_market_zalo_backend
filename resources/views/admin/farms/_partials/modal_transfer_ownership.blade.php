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

                        {{-- Search box (lọc client-side trên danh sách đã load). --}}
                        <input type="text" id="transferSearchInput" class="form-control mb-2"
                               placeholder="Gõ tên hoặc số điện thoại để lọc…" autocomplete="off">

                        <select name="new_owner_id" id="transferNewOwnerSelect"
                                class="form-select" size="8" required
                                style="height:auto;">
                            @forelse($transferCandidates as $c)
                                <option value="{{ $c->id }}"
                                        data-warning="{{ $c->_transfer_warning }}"
                                        data-blocked="{{ $c->_transfer_blocked ? '1' : '0' }}"
                                        data-search="{{ mb_strtolower(($c->name ?? '').' '.($c->mobile ?? '').' #'.$c->id) }}"
                                        @if($c->_transfer_blocked) disabled @endif>
                                    {{ $c->name ?: '#'.$c->id }}{{ $c->mobile ? ' — '.$c->mobile : '' }}
                                    @if($c->_transfer_warning) — {{ $c->_transfer_warning }} @endif
                                </option>
                            @empty
                                <option value="" disabled>— Không có khách hàng nào khả dụng —</option>
                            @endforelse
                        </select>
                        <small class="text-muted">
                            Hiển thị tối đa 200 khách hàng đang hoạt động. Nhân viên của farm này được xếp đầu danh sách.
                            Tổng: {{ count($transferCandidates) }} khách hàng.
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
    const searchInput = document.getElementById('transferSearchInput');
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
            if (confirmChk) confirmChk.checked = false;
        }
        submitBtn.disabled = blocked;
    }
    sel.addEventListener('change', syncWarning);

    // Client-side filter: ẩn/hiện <option> dựa vào data-search.
    if (searchInput) {
        searchInput.addEventListener('input', function () {
            const q = this.value.trim().toLowerCase();
            Array.from(sel.options).forEach(function (opt) {
                if (!opt.value) return; // bỏ qua placeholder/empty
                const hay = opt.dataset.search || opt.textContent.toLowerCase();
                opt.hidden = q && !hay.includes(q);
            });
        });
    }

    // Auto-mở modal khi URL có ?action=transfer (entry point từ /farms list +
    // trang sửa Khách hàng).
    document.addEventListener('DOMContentLoaded', function () {
        if (new URLSearchParams(window.location.search).get('action') === 'transfer') {
            const modalEl = document.getElementById('transferOwnershipModal');
            if (modalEl && window.bootstrap) {
                new bootstrap.Modal(modalEl).show();
            }
        }
    });
})();
</script>
