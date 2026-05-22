@extends('layouts.main')

@section('content')
    @if(session('success'))
        <div class="alert alert-success alert-dismissible fade show">
            {{ session('success') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    @endif
    @if(session('error'))
        <div class="alert alert-danger alert-dismissible fade show">
            {{ session('error') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    @endif
    @if($errors->any())
        <div class="alert alert-danger">
            <ul class="mb-0">@foreach($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul>
        </div>
    @endif

    @php $owner = $payout->farm?->owner; @endphp

    <div class="card mb-3">
        <div class="card-header d-flex justify-content-between align-items-center">
            <h4 class="mb-0">
                Chi tiết đối soát #{{ $payout->id }}
                @switch($payout->status)
                    @case('pending')   <span class="badge bg-warning text-dark ms-2">Chưa GD</span> @break
                    @case('paid')      <span class="badge bg-success ms-2">Đã chi</span> @break
                    @case('cancelled') <span class="badge bg-secondary ms-2">Đã huỷ</span> @break
                    @case('draft')     <span class="badge bg-light text-dark ms-2">Draft</span> @break
                @endswitch
            </h4>
            <a href="{{ route('farm-payouts.index') }}" class="btn btn-light btn-sm">Quay lại danh sách</a>
        </div>
        <div class="card-body">
            <div class="row g-4">
                <div class="col-md-6">
                    <h6 class="text-muted">Thông tin Farm</h6>
                    <table class="table table-sm">
                        <tr><td style="width:40%" class="text-muted">Farm</td>
                            <td>
                                <a href="{{ route('farms.show', $payout->farm_id) }}">{{ $payout->farm->name ?? '—' }}</a>
                                <span class="text-muted">— <code>{{ $payout->farm->code ?? '' }}</code></span>
                            </td></tr>
                        <tr><td class="text-muted">Kỳ đối soát</td>
                            <td>{{ $payout->period_start?->format('d/m/Y') }} → {{ $payout->period_end?->format('d/m/Y') }}</td></tr>
                        <tr><td class="text-muted">Tỷ lệ hoa hồng</td>
                            <td>{{ number_format(($payout->farm->commission_rate ?? 0) * 100, 2) }}%</td></tr>
                        <tr><td class="text-muted">SL bán</td>
                            <td>{{ number_format($payout->total_sold, 2) }}</td></tr>
                    </table>

                    <h6 class="text-muted mt-3">Số liệu thanh toán</h6>
                    <table class="table table-sm">
                        <tr><td style="width:40%" class="text-muted">Tổng doanh thu</td>
                            <td>{{ number_format($payout->gross_revenue, 0, ',', '.') }} đ</td></tr>
                        <tr><td class="text-muted">Điều chỉnh</td>
                            <td>{{ number_format($payout->adjustment, 0, ',', '.') }} đ</td></tr>
                        <tr><td class="text-muted">Thực nhận</td>
                            <td><strong class="text-success">{{ number_format($payout->net_payout, 0, ',', '.') }} đ</strong></td></tr>
                    </table>
                </div>

                <div class="col-md-6">
                    <h6 class="text-muted">Thông tin nhận tiền (từ Customer owner)</h6>
                    @if($owner)
                        <table class="table table-sm">
                            <tr><td style="width:40%" class="text-muted">Customer</td>
                                <td>
                                    <a href="{{ route('zalo-customers.show', $owner->id) }}">{{ $owner->name }}</a>
                                </td></tr>
                            <tr><td class="text-muted">SĐT</td><td>{{ $owner->mobile ?: '—' }}</td></tr>
                            <tr><td class="text-muted">Ngân hàng</td><td>{{ $owner->farm_bank_name ?: '—' }}</td></tr>
                            <tr><td class="text-muted">Số tài khoản</td>
                                <td>
                                    @if($owner->farm_bank_account)
                                        <code>{{ $owner->farm_bank_account }}</code>
                                    @else — @endif
                                </td></tr>
                            <tr><td class="text-muted">Chủ TK</td><td>{{ $owner->farm_bank_holder ?: '—' }}</td></tr>
                        </table>
                        @if(empty($owner->farm_bank_account))
                            <div class="alert alert-warning py-2 small">
                                Customer chưa cập nhật TK. Liên hệ farm trước khi thực hiện chuyển khoản.
                            </div>
                        @endif
                    @else
                        <div class="alert alert-warning">Farm chưa có owner.</div>
                    @endif

                    @if($payout->note)
                        <h6 class="text-muted mt-3">Ghi chú</h6>
                        <pre class="bg-light p-2 rounded small mb-0" style="white-space: pre-wrap;">{{ $payout->note }}</pre>
                    @endif
                </div>
            </div>
        </div>
    </div>

    {{-- Khu vực action tuỳ theo trạng thái --}}
    @if($payout->status === 'pending')
        <div class="card mb-3">
            <div class="card-header"><h5 class="mb-0">Xác nhận đã thanh toán</h5></div>
            <div class="card-body">
                <form action="{{ route('farm-payouts.mark-paid', $payout->id) }}" method="POST" enctype="multipart/form-data" class="row g-3">
                    @csrf
                    <div class="col-md-4">
                        <label class="form-label">Phương thức</label>
                        <select name="payment_method" class="form-select">
                            <option value="bank_transfer" selected>Chuyển khoản ngân hàng</option>
                            <option value="cash">Tiền mặt</option>
                            <option value="other">Khác</option>
                        </select>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Mã giao dịch</label>
                        <input type="text" name="transaction_ref" class="form-control" maxlength="120" placeholder="Vd: FT26050912345">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Ảnh UNC <span class="text-danger">*</span></label>
                        <input type="file" name="proof_image" accept="image/*" class="form-control" required>
                        <small class="text-muted">Bắt buộc. Tối đa 5MB, định dạng ảnh.</small>
                    </div>
                    <div class="col-12 d-flex gap-2">
                        <button type="submit" class="btn btn-success">Xác nhận Đã thanh toán</button>
                    </div>
                </form>
            </div>
        </div>

        <div class="card">
            <div class="card-header"><h5 class="mb-0 text-danger">Huỷ lệnh chi</h5></div>
            <div class="card-body">
                <form action="{{ route('farm-payouts.cancel', $payout->id) }}" method="POST"
                      onsubmit="return confirm('Huỷ lệnh chi #{{ $payout->id }}? Số liệu doanh thu vẫn được giữ.');">
                    @csrf
                    <div class="row g-2">
                        <div class="col-md-9">
                            <input type="text" name="reason" maxlength="500" class="form-control" placeholder="Lý do huỷ (sẽ append vào ghi chú)">
                        </div>
                        <div class="col-md-3">
                            <button type="submit" class="btn btn-danger w-100">Huỷ lệnh chi</button>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    @elseif($payout->status === 'paid')
        <div class="card mb-3">
            <div class="card-header"><h5 class="mb-0">Đã thanh toán</h5></div>
            <div class="card-body">
                <div class="row g-3">
                    <div class="col-md-6">
                        <table class="table table-sm">
                            <tr><td style="width:40%" class="text-muted">Thời điểm chi</td>
                                <td>{{ $payout->paid_at?->format('d/m/Y H:i') }}</td></tr>
                            <tr><td class="text-muted">Phương thức</td>
                                <td>{{ $payout->payment_method ?: '—' }}</td></tr>
                            <tr><td class="text-muted">Mã GD</td>
                                <td>{{ $payout->transaction_ref ? '#' . $payout->transaction_ref : '—' }}</td></tr>
                        </table>
                    </div>
                    <div class="col-md-6">
                        <h6 class="text-muted">Ảnh UNC</h6>
                        @if($payout->proof_image_path)
                            <a href="{{ asset('storage/' . $payout->proof_image_path) }}" target="_blank">
                                <img src="{{ asset('storage/' . $payout->proof_image_path) }}"
                                     alt="UNC" class="img-fluid rounded border" style="max-height:280px;">
                            </a>
                            <div class="text-muted small mt-1">Click để mở full size.</div>
                        @else
                            <span class="text-muted">— không có file —</span>
                        @endif
                    </div>
                </div>
            </div>
        </div>
    @endif
@endsection
