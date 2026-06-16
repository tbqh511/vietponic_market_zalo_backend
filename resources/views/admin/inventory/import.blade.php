@extends('layouts.main')

@section('content')
<div class="row justify-content-center">
    <div class="col-lg-6">
        <div class="card">
            <div class="card-header">
                <h5 class="mb-0">Nhập kho — {{ $inventory->name }}</h5>
            </div>
            <div class="card-body">
                @if($errors->any())
                    <div class="alert alert-danger">{{ $errors->first() }}</div>
                @endif

                {{-- Current stock preview --}}
                <div class="row text-center g-2 mb-4">
                    <div class="col-4">
                        <div class="border rounded p-2 bg-light">
                            <div class="fs-5 fw-bold">{{ $inventory->stock }}</div>
                            <div class="small text-muted">Tồn kho hiện tại</div>
                        </div>
                    </div>
                    <div class="col-4 d-flex align-items-center justify-content-center">
                        <i class="fas fa-arrow-right text-muted fs-4"></i>
                    </div>
                    <div class="col-4">
                        <div class="border rounded p-2 bg-success bg-opacity-10" id="preview-box">
                            <div class="fs-5 fw-bold text-success" id="preview-qty">{{ $inventory->stock }}</div>
                            <div class="small text-muted">Sau khi nhập</div>
                        </div>
                    </div>
                </div>

                <form action="{{ route('inventory.import.store', $inventory->id) }}" method="POST">
                    @csrf
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Số lượng nhập <span class="text-danger">*</span></label>
                        <input type="number" name="quantity" id="import-qty" class="form-control form-control-lg"
                               min="1" required placeholder="VD: 50"
                               value="{{ old('quantity') }}">
                        <div class="form-text">Số lượng thực tế nhập vào kho.</div>
                    </div>

                    <div class="row g-3 mb-3">
                        <div class="col-6">
                            <label class="form-label fw-semibold">Ngày nhập lô</label>
                            <input type="date" name="batch_date" class="form-control"
                                   value="{{ old('batch_date', date('Y-m-d')) }}">
                            <div class="form-text">Ngày nhận hàng thực tế.</div>
                        </div>
                        <div class="col-6">
                            <label class="form-label fw-semibold">Hạn sử dụng <span class="text-muted fw-normal">(tuỳ chọn)</span></label>
                            <input type="date" name="expire_date" id="expire-date" class="form-control"
                                   min="{{ date('Y-m-d') }}"
                                   value="{{ old('expire_date', date('Y-m-d', strtotime('+5 days'))) }}">
                            <div class="form-text" id="expire-hint"></div>
                        </div>
                    </div>

                    <div class="mb-4">
                        <label class="form-label fw-semibold">Ghi chú</label>
                        <textarea name="note" class="form-control" rows="3"
                                  placeholder="VD: Nhập từ nhà cung cấp ABC, ngày 14/05/2026">{{ old('note') }}</textarea>
                    </div>

                    <div class="d-flex gap-2">
                        <button type="submit" class="btn btn-primary flex-fill">
                            <i class="fas fa-plus"></i> Xác nhận nhập kho
                        </button>
                        <a href="{{ route('inventory.show', $inventory->id) }}" class="btn btn-secondary">
                            Huỷ
                        </a>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<script>
(function () {
    var currentStock = {{ (int) $inventory->stock }};
    var input = document.getElementById('import-qty');
    var preview = document.getElementById('preview-qty');
    if (input && preview) {
        input.addEventListener('input', function () {
            var qty = parseInt(input.value, 10) || 0;
            preview.textContent = currentStock + qty;
        });
    }

    var expireInput = document.getElementById('expire-date');
    var expireHint  = document.getElementById('expire-hint');
    function updateExpireHint() {
        if (!expireInput || !expireHint) return;
        var val = expireInput.value;
        if (!val) { expireHint.textContent = 'Để trống nếu không có HSD.'; return; }
        var today = new Date(); today.setHours(0,0,0,0);
        var exp   = new Date(val); exp.setHours(0,0,0,0);
        var diff  = Math.round((exp - today) / 86400000);
        if (diff < 0)      expireHint.innerHTML = '<span class="text-danger">Ngày đã qua!</span>';
        else if (diff === 0) expireHint.innerHTML = '<span class="text-warning">Hết hạn hôm nay.</span>';
        else               expireHint.textContent = diff + ' ngày kể từ hôm nay.';
    }
    if (expireInput) {
        expireInput.addEventListener('change', updateExpireHint);
        updateExpireHint();
    }
})();
</script>
@endsection
