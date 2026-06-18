@extends('layouts.main')

@php
    // Map trạng thái đơn → màu badge + nhãn tiếng Việt.
    $statusMeta = [
        'pending'    => ['Chờ xác nhận', 'bg-secondary'],
        'confirmed'  => ['Đã xác nhận',  'bg-info text-dark'],
        'preparing'  => ['Đang chuẩn bị','bg-primary'],
        'delivering' => ['Đang giao',    'bg-warning text-dark'],
        'delivered'  => ['Đã giao',      'bg-success'],
        'cancelled'  => ['Đã huỷ',       'bg-danger'],
    ];
    // Map trạng thái thanh toán → màu + nhãn.
    $payMeta = [
        'success' => ['Đã thanh toán', 'bg-success'],
        'cod'     => ['COD',           'bg-info text-dark'],
        'pending' => ['Chờ thanh toán','bg-warning text-dark'],
        'failed'  => ['Thất bại',      'bg-danger'],
        'refunded'=> ['Đã hoàn tiền',  'bg-dark'],
    ];
@endphp

@section('content')
    <div class="card shadow-sm border-0">
        <div class="card-header bg-white d-flex justify-content-between align-items-center flex-wrap gap-2 py-3">
            <div>
                <h4 class="mb-1">Đơn hàng</h4>
                <small class="text-muted">Quản lý &amp; theo dõi đơn hàng từ Zalo Mini App</small>
            </div>
        </div>

        {{-- KPI cards --}}
        <div class="card-body pb-0">
            @if(session('success'))
                <div class="alert alert-success alert-dismissible fade show">
                    {{ session('success') }}
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            @endif

            <div class="row g-3 mb-3">
                <div class="col-6 col-lg-3">
                    <div class="border rounded-3 p-3 h-100 bg-light">
                        <div class="text-muted small">Tổng đơn (đã lọc)</div>
                        <div class="fs-4 fw-bold">{{ number_format($totalOrders) }}</div>
                    </div>
                </div>
                <div class="col-6 col-lg-3">
                    <div class="border rounded-3 p-3 h-100 bg-light">
                        <div class="text-muted small">Doanh thu (trừ huỷ)</div>
                        <div class="fs-4 fw-bold text-success">{{ number_format((int) $totalRevenue) }}đ</div>
                    </div>
                </div>
                <div class="col-6 col-lg-3">
                    <div class="border rounded-3 p-3 h-100 bg-light">
                        <div class="text-muted small">Chờ xác nhận</div>
                        <div class="fs-4 fw-bold text-secondary">{{ number_format($pendingCount) }}</div>
                    </div>
                </div>
                <div class="col-6 col-lg-3">
                    <div class="border rounded-3 p-3 h-100 bg-light">
                        <div class="text-muted small">Chờ thanh toán</div>
                        <div class="fs-4 fw-bold text-warning">{{ number_format($unpaidCount) }}</div>
                    </div>
                </div>
            </div>

            {{-- Filters: all server-side, auto-submit --}}
            <form method="GET" action="{{ route('zalo-orders.index') }}" id="filterForm"
                  class="d-flex flex-wrap gap-2 align-items-end mb-3">
                <div style="min-width: 220px; flex: 1 1 220px;">
                    <label class="form-label small mb-0"><i class="fa fa-search me-1"></i>Tìm kiếm</label>
                    <input type="text" name="q" id="searchInput" class="form-control form-control-sm"
                           placeholder="Tên, mã đơn, SĐT..." value="{{ request('q') }}"
                           autocomplete="off">
                </div>
                <div>
                    <label class="form-label small mb-0">Trạng thái</label>
                    <select name="status" class="form-select form-select-sm" style="min-width:140px"
                            onchange="this.form.submit()">
                        <option value="">-- Tất cả --</option>
                        @foreach($statusOptions as $val => $label)
                            <option value="{{ $val }}" @selected(request('status') === $val)>{{ $label }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="form-label small mb-0">Thanh toán</label>
                    <select name="payment_status" class="form-select form-select-sm" style="min-width:140px"
                            onchange="this.form.submit()">
                        <option value="">-- Tất cả --</option>
                        <option value="success"  @selected(request('payment_status') === 'success')>Đã thanh toán</option>
                        <option value="cod"      @selected(request('payment_status') === 'cod')>COD</option>
                        <option value="pending"  @selected(request('payment_status') === 'pending')>Chờ thanh toán</option>
                        <option value="refunded" @selected(request('payment_status') === 'refunded')>Đã hoàn tiền</option>
                    </select>
                </div>
                <div>
                    <label class="form-label small mb-0">Từ ngày</label>
                    <input type="date" name="from" value="{{ request('from') }}"
                           class="form-control form-control-sm" onchange="this.form.submit()">
                </div>
                <div>
                    <label class="form-label small mb-0">Đến ngày</label>
                    <input type="date" name="to" value="{{ request('to') }}"
                           class="form-control form-control-sm" onchange="this.form.submit()">
                </div>
                <a href="{{ route('zalo-orders.index') }}" class="btn btn-sm btn-outline-secondary"
                   title="Xoá bộ lọc">×</a>
            </form>
        </div>

        <div class="card-body pt-0">
            @if($orders->isEmpty())
                <div class="alert alert-info">Không có đơn hàng nào khớp bộ lọc.</div>
            @else
                <div id="orderList" class="d-flex flex-column gap-2">
                    @foreach($orders as $o)
                        @php
                            [$stLabel, $stClass] = $statusMeta[$o->status] ?? [$o->status, 'bg-secondary'];
                            [$pyLabel, $pyClass] = $payMeta[$o->payment_status] ?? [$o->payment_status, 'bg-light text-dark'];
                            $custName = optional($o->customer)->name ?? ('KH #' . $o->customer_id);
                            $custPhone = optional($o->customer)->mobile;
                            $dest = $o->delivery
                                ? collect([$o->delivery->ward_name, $o->delivery->district_name, $o->delivery->province_name])
                                    ->filter()->implode(', ')
                                : null;
                        @endphp
                        <div class="order-row border rounded-3 p-3">
                            <div class="row align-items-center g-2">
                                {{-- Mã đơn + ngày --}}
                                <div class="col-md-2">
                                    <div class="fw-bold">
                                        <a href="{{ route('zalo-orders.show', $o->id) }}" class="text-decoration-none">#{{ $o->id }}</a>
                                    </div>
                                    <small class="text-muted">
                                        {{ $o->created_at ? \Carbon\Carbon::parse($o->created_at)->format('d/m/Y H:i') : '—' }}
                                    </small>
                                </div>

                                {{-- Khách hàng --}}
                                <div class="col-md-3">
                                    <div class="fw-semibold">{{ $custName }}</div>
                                    <small class="text-muted">
                                        <i class="fa fa-phone"></i> {{ $custPhone ?? '—' }}
                                    </small>
                                    @if($dest)
                                        <div class="small text-muted text-truncate" title="{{ $dest }}">
                                            <i class="fa fa-map-marker-alt"></i> {{ $dest }}
                                        </div>
                                    @endif
                                </div>

                                {{-- Trạng thái + thanh toán --}}
                                <div class="col-md-3">
                                    <span class="badge {{ $stClass }}">{{ $stLabel }}</span>
                                    <span class="badge {{ $pyClass }}">{{ $pyLabel }}</span>
                                    <div class="small text-muted mt-1">
                                        {{ $o->items_count }} sản phẩm
                                        @if($o->payment_method)
                                            · {{ $o->payment_method }}
                                        @endif
                                        @if(optional($o->delivery)->vtp_order_number)
                                            · VTP {{ $o->delivery->vtp_order_number }}
                                        @endif
                                    </div>
                                </div>

                                {{-- Tổng tiền --}}
                                <div class="col-md-2 text-md-end">
                                    <div class="fw-bold fs-5">{{ number_format((int) $o->total) }}đ</div>
                                    @if($o->shipping_fee)
                                        <small class="text-muted">Ship {{ number_format((int) $o->shipping_fee) }}đ</small>
                                    @endif
                                </div>

                                {{-- Thao tác --}}
                                <div class="col-md-2 text-md-end d-flex gap-1 justify-content-md-end flex-wrap">
                                    <a href="{{ route('zalo-orders.show', $o->id) }}" class="btn btn-sm btn-primary">Xem</a>

                                    {{-- Quick status actions --}}
                                    @if($o->status === 'pending')
                                        <form action="{{ route('zalo-orders.update', $o->id) }}" method="POST">
                                            @csrf @method('PATCH')
                                            <input type="hidden" name="status" value="confirmed">
                                            <input type="hidden" name="_back_to_list" value="1">
                                            <button class="btn btn-sm btn-success" title="Xác nhận đơn hàng">Xác nhận</button>
                                        </form>
                                    @elseif($o->status === 'confirmed')
                                        <form action="{{ route('zalo-orders.update', $o->id) }}" method="POST">
                                            @csrf @method('PATCH')
                                            <input type="hidden" name="status" value="preparing">
                                            <input type="hidden" name="_back_to_list" value="1">
                                            <button class="btn btn-sm btn-info text-white" title="Chuyển sang đang chuẩn bị">Chuẩn bị</button>
                                        </form>
                                    @elseif($o->status === 'preparing')
                                        <form action="{{ route('zalo-orders.update', $o->id) }}" method="POST">
                                            @csrf @method('PATCH')
                                            <input type="hidden" name="status" value="delivering">
                                            <input type="hidden" name="_back_to_list" value="1">
                                            <button class="btn btn-sm btn-warning" title="Chuyển sang đang giao">Giao hàng</button>
                                        </form>
                                    @elseif($o->status === 'delivering')
                                        <form action="{{ route('zalo-orders.update', $o->id) }}" method="POST">
                                            @csrf @method('PATCH')
                                            <input type="hidden" name="status" value="delivered">
                                            <input type="hidden" name="_back_to_list" value="1">
                                            <button class="btn btn-sm btn-success" title="Đánh dấu đã giao">Đã giao</button>
                                        </form>
                                    @endif

                                    {{-- Dropdown thêm --}}
                                    <div class="dropdown">
                                        <button class="btn btn-sm btn-outline-secondary dropdown-toggle" type="button"
                                                data-bs-toggle="dropdown">...</button>
                                        <ul class="dropdown-menu dropdown-menu-end">
                                            <li><a class="dropdown-item" href="{{ route('zalo-orders.edit', $o->id) }}">Sửa đơn</a></li>
                                            @if(!in_array($o->status, ['cancelled', 'delivered']))
                                                <li>
                                                    <form action="{{ route('zalo-orders.update', $o->id) }}" method="POST"
                                                          onsubmit="return confirm('Hủy đơn hàng #{{ $o->id }}?')">
                                                        @csrf @method('PATCH')
                                                        <input type="hidden" name="status" value="cancelled">
                                                        <input type="hidden" name="cancellation_reason" value="Admin huỷ nhanh từ danh sách">
                                                        <input type="hidden" name="_back_to_list" value="1">
                                                        <button type="submit" class="dropdown-item text-danger">Hủy đơn</button>
                                                    </form>
                                                </li>
                                            @endif
                                            <li><hr class="dropdown-divider"></li>
                                            <li>
                                                <form action="{{ route('zalo-orders.destroy', $o->id) }}" method="POST"
                                                      onsubmit="return confirm('Xoá vĩnh viễn đơn hàng #{{ $o->id }}?')">
                                                    @csrf @method('DELETE')
                                                    <button type="submit" class="dropdown-item text-danger">Xoá đơn</button>
                                                </form>
                                            </li>
                                        </ul>
                                    </div>
                                </div>
                            </div>
                        </div>
                    @endforeach
                </div>

                <div class="mt-3">
                    {{ $orders->links() }}
                </div>
            @endif
        </div>
    </div>

    <style>
        .order-row { transition: box-shadow .15s ease, border-color .15s ease; }
        .order-row:hover { box-shadow: 0 2px 8px rgba(0,0,0,.08); border-color: #adb5bd !important; }
    </style>

    <script>
        (function () {
            const input = document.getElementById('searchInput');
            const form  = document.getElementById('filterForm');
            if (!input || !form) return;
            let timer;
            input.addEventListener('input', function () {
                clearTimeout(timer);
                timer = setTimeout(function () { form.submit(); }, 400);
            });
        })();
    </script>
@endsection
