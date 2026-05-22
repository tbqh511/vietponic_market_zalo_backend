@extends('layouts.main')

@section('content')
    <div class="card">
        <div class="card-header d-flex justify-content-between align-items-center flex-wrap gap-2">
            <div>
                <h4 class="mb-1">Hoàn tiền chờ xử lý</h4>
                <small class="text-muted">
                    Đơn BANK / MOMO / ZaloPay-fallback đã huỷ — kế toán chuyển tiền tay rồi xác nhận tại đây.
                </small>
            </div>
            <div class="text-end">
                <div class="badge bg-warning text-dark fs-6">
                    Tổng cần xử lý: <strong>{{ number_format($totalCount) }}</strong> đơn
                </div>
                <div class="badge bg-danger fs-6 ms-1">
                    Tổng tiền: <strong>{{ number_format((int) $totalAmount) }}đ</strong>
                </div>
            </div>
        </div>

        <div class="card-body">
            {{-- Flash messages --}}
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

            {{-- Filter form --}}
            <form method="GET" action="{{ route('refunds.pending') }}" class="row g-2 mb-3">
                <div class="col-md-3">
                    <label class="form-label small mb-0">Phương thức</label>
                    <select name="method" class="form-select form-select-sm">
                        <option value="">-- Tất cả --</option>
                        <option value="BANK" @selected($filterMethod === 'BANK')>BANK</option>
                        <option value="MOMO" @selected($filterMethod === 'MOMO')>MOMO</option>
                        <option value="ZALOPAY" @selected($filterMethod === 'ZALOPAY')>ZALOPAY (fallback)</option>
                    </select>
                </div>
                <div class="col-md-3">
                    <label class="form-label small mb-0">Huỷ từ ngày</label>
                    <input type="date" name="from" value="{{ $filterFrom }}" class="form-control form-control-sm">
                </div>
                <div class="col-md-3">
                    <label class="form-label small mb-0">Đến ngày</label>
                    <input type="date" name="to" value="{{ $filterTo }}" class="form-control form-control-sm">
                </div>
                <div class="col-md-3 d-flex align-items-end gap-2">
                    <button type="submit" class="btn btn-sm btn-primary">Lọc</button>
                    <a href="{{ route('refunds.pending') }}" class="btn btn-sm btn-secondary">Reset</a>
                </div>
            </form>

            @if($orders->isEmpty())
                <div class="alert alert-success">
                    <i class="fa fa-check-circle"></i>
                    Không có đơn nào chờ hoàn tiền với bộ lọc hiện tại.
                </div>
            @else
                <div class="table-responsive">
                    <table class="table table-hover table-sm align-middle">
                        <thead class="table-light">
                            <tr>
                                <th>ID</th>
                                <th>Khách hàng</th>
                                <th>SĐT</th>
                                <th>Phương thức</th>
                                <th class="text-end">Số tiền hoàn</th>
                                <th>Huỷ lúc</th>
                                <th>Người huỷ</th>
                                <th>Lý do</th>
                                <th class="text-center" style="min-width:140px;">Thao tác</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($orders as $o)
                                <tr>
                                    <td>
                                        <a href="{{ route('zalo-orders.show', $o->id) }}" target="_blank">
                                            #{{ $o->id }}
                                        </a>
                                    </td>
                                    <td>{{ optional($o->customer)->name ?? '—' }}</td>
                                    <td>{{ optional($o->customer)->mobile ?? '—' }}</td>
                                    <td>
                                        <span class="badge bg-secondary">{{ $o->payment_method }}</span>
                                    </td>
                                    <td class="text-end fw-bold text-danger">
                                        {{ number_format((int) ($o->refund_amount ?? $o->total)) }}đ
                                    </td>
                                    <td>
                                        <small>{{ $o->cancelled_at ? \Carbon\Carbon::parse($o->cancelled_at)->format('d/m/Y H:i') : '—' }}</small>
                                    </td>
                                    <td>
                                        <span class="badge {{ $o->cancelled_by === 'customer' ? 'bg-info' : ($o->cancelled_by === 'admin' ? 'bg-warning text-dark' : 'bg-dark') }}">
                                            {{ $o->cancelled_by ?? '—' }}
                                        </span>
                                    </td>
                                    <td>
                                        <small class="text-muted" title="{{ $o->cancellation_reason }}">
                                            {{ \Illuminate\Support\Str::limit($o->cancellation_reason, 60) }}
                                        </small>
                                    </td>
                                    <td class="text-center">
                                        <button type="button" class="btn btn-sm btn-success"
                                            data-bs-toggle="modal"
                                            data-bs-target="#confirmModal{{ $o->id }}">
                                            <i class="fa fa-check"></i> Đã hoàn tiền
                                        </button>
                                    </td>
                                </tr>

                                {{-- Confirm modal --}}
                                <div class="modal fade" id="confirmModal{{ $o->id }}" tabindex="-1">
                                    <div class="modal-dialog">
                                        <form method="POST" action="{{ route('refunds.confirm', $o->id) }}">
                                            @csrf
                                            <div class="modal-content">
                                                <div class="modal-header">
                                                    <h5 class="modal-title">
                                                        Xác nhận hoàn tiền — Đơn #{{ $o->id }}
                                                    </h5>
                                                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                                </div>
                                                <div class="modal-body">
                                                    <p>
                                                        Bạn đã chuyển <strong class="text-danger">{{ number_format((int) ($o->refund_amount ?? $o->total)) }}đ</strong>
                                                        cho khách <strong>{{ optional($o->customer)->name ?? '—' }}</strong>
                                                        ({{ optional($o->customer)->mobile ?? '—' }})
                                                        qua <strong>{{ $o->payment_method }}</strong>?
                                                    </p>
                                                    <div class="alert alert-warning small">
                                                        <i class="fa fa-exclamation-triangle"></i>
                                                        Sau khi xác nhận, trạng thái <code>refund_status</code> sẽ chuyển từ
                                                        <code>pending_manual</code> sang <code>refunded</code> và không thể undo.
                                                    </div>
                                                    <div class="mb-2">
                                                        <label class="form-label">Ghi chú (mã giao dịch, ngày chuyển...)</label>
                                                        <textarea name="note" class="form-control" rows="3"
                                                            placeholder="VD: Đã chuyển qua Vietcombank 22/05/2026, TXN: 123456789"></textarea>
                                                    </div>
                                                </div>
                                                <div class="modal-footer">
                                                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Huỷ</button>
                                                    <button type="submit" class="btn btn-success">
                                                        <i class="fa fa-check"></i> Xác nhận hoàn tiền
                                                    </button>
                                                </div>
                                            </div>
                                        </form>
                                    </div>
                                </div>
                            @endforeach
                        </tbody>
                    </table>
                </div>

                {{-- Pagination --}}
                <div class="mt-3">
                    {{ $orders->links() }}
                </div>
            @endif
        </div>
    </div>
@endsection
