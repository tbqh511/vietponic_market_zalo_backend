@extends('layouts.main')

@section('content')
    @if(session('error'))
        <div class="alert alert-danger">{{ session('error') }}</div>
    @endif
    @if($errors->any())
        <div class="alert alert-danger">
            <ul class="mb-0">@foreach($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul>
        </div>
    @endif

    <div class="card mb-3">
        <div class="card-header d-flex justify-content-between align-items-center">
            <h4 class="mb-0">Tạo lệnh chi cho Farm</h4>
            <a href="{{ route('farm-payouts.index') }}" class="btn btn-light btn-sm">Quay lại</a>
        </div>
        <div class="card-body">
            {{-- Form preview (GET): cho phép admin xem revenue trước khi commit. --}}
            <form method="GET" class="row g-3 mb-3 border-bottom pb-3">
                <div class="col-md-4">
                    <label class="form-label">Farm <span class="text-danger">*</span></label>
                    <select name="farm_id" class="form-select" required>
                        <option value="">— Chọn farm —</option>
                        @foreach($farms as $f)
                            <option value="{{ $f->id }}" {{ (string) request('farm_id') === (string) $f->id ? 'selected' : '' }}>
                                {{ $f->name }} ({{ $f->code }})
                                @if($f->owner) — {{ $f->owner->name }} @endif
                            </option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-3">
                    <label class="form-label">Từ ngày <span class="text-danger">*</span></label>
                    <input type="date" name="period_start" value="{{ request('period_start') }}" class="form-control" required>
                </div>
                <div class="col-md-3">
                    <label class="form-label">Đến ngày <span class="text-danger">*</span></label>
                    <input type="date" name="period_end" value="{{ request('period_end') }}" class="form-control" required>
                </div>
                <div class="col-md-2 d-flex align-items-end">
                    <button class="btn btn-secondary w-100">Preview</button>
                </div>
            </form>

            @if($preview)
                <div class="alert alert-info">
                    <h5 class="mb-2">Xem trước lệnh chi</h5>
                    <div class="row g-2">
                        <div class="col-md-3"><strong>Farm:</strong> {{ $preview['farm']->name }}</div>
                        <div class="col-md-3"><strong>Số đơn:</strong> {{ $preview['orders_count'] }}</div>
                        <div class="col-md-3"><strong>SL bán:</strong> {{ number_format($preview['items_sold'], 2) }}</div>
                        <div class="col-md-3"><strong>Tỷ lệ:</strong> {{ number_format($preview['farm']->commission_rate * 100, 2) }}%</div>
                    </div>
                    <hr class="my-2">
                    <div class="row g-2">
                        <div class="col-md-4"><strong>Doanh thu (gross):</strong> {{ number_format($preview['gross_revenue'], 0, ',', '.') }} đ</div>
                        <div class="col-md-4"><strong>Cần thanh toán (net):</strong>
                            <span class="text-success">{{ number_format($preview['net_payout'], 0, ',', '.') }} đ</span>
                        </div>
                    </div>
                </div>

                <form action="{{ route('farm-payouts.store') }}" method="POST" class="row g-3">
                    @csrf
                    <input type="hidden" name="farm_id"      value="{{ request('farm_id') }}">
                    <input type="hidden" name="period_start" value="{{ request('period_start') }}">
                    <input type="hidden" name="period_end"   value="{{ request('period_end') }}">

                    <div class="col-md-3">
                        <label class="form-label">Điều chỉnh (+/- VND)</label>
                        <input type="number" step="1000" name="adjustment" value="0" class="form-control">
                        <small class="text-muted">Cộng thêm hoặc trừ vào net. Có thể âm.</small>
                    </div>
                    <div class="col-md-9">
                        <label class="form-label">Ghi chú nội bộ</label>
                        <input type="text" name="note" maxlength="1000" class="form-control" placeholder="Vd: Lệnh chi tháng 5/2026">
                    </div>
                    <div class="col-12">
                        <button type="submit" class="btn btn-success">Tạo lệnh chi (status = pending)</button>
                        <span class="text-muted small ms-2">Sau khi tạo, vào màn chi tiết để upload UNC khi đã chuyển khoản.</span>
                    </div>
                </form>
            @elseif(request()->hasAny(['farm_id', 'period_start', 'period_end']))
                <div class="alert alert-warning">Chưa có dữ liệu preview. Hãy điền đủ Farm + Từ ngày + Đến ngày rồi bấm Preview.</div>
            @endif
        </div>
    </div>
@endsection
