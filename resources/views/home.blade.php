@php
    $lang = Session::get('language');

    // Build 30-day date range for revenue chart (filled with zeros for missing days)
    $chartDates   = [];
    $chartRevenue = [];
    $chartOrders  = [];
    $revenueByDate = $revenueRows->keyBy('date');
    for ($i = 29; $i >= 0; $i--) {
        $d = now()->timezone('Asia/Ho_Chi_Minh')->subDays($i);
        $key = $d->toDateString();
        $chartDates[]   = $d->format('d/m');
        $row = $revenueByDate->get($key);
        $chartRevenue[] = $row ? (float) $row->revenue : 0;
        $chartOrders[]  = $row ? (int) $row->cnt : 0;
    }

    // Status display maps
    $statusLabel = [
        'pending'   => 'Chờ xác nhận',
        'confirmed' => 'Đã xác nhận',
        'packing'   => 'Đóng gói',
        'shipping'  => 'Đang giao',
        'delivered' => 'Đã giao',
        'cancelled' => 'Đã hủy',
    ];
    $statusBadge = [
        'pending'   => 'warning',
        'confirmed' => 'primary',
        'packing'   => 'secondary',
        'shipping'  => 'info',
        'delivered' => 'success',
        'cancelled' => 'danger',
    ];
    $statusColor = [
        'pending'   => '#ffc107',
        'confirmed' => '#0d6efd',
        'packing'   => '#6f42c1',
        'shipping'  => '#0dcaf0',
        'delivered' => '#198754',
        'cancelled' => '#dc3545',
    ];

    // Build pie data
    $pieData = [];
    foreach ($orderStatusBreakdown as $row) {
        $pieData[] = [
            'name'  => $statusLabel[$row->status] ?? $row->status,
            'y'     => (int) $row->count,
            'color' => $statusColor[$row->status] ?? '#adb5bd',
        ];
    }
@endphp

@extends('layouts.main')

@section('title')
    Dashboard
@endsection

@section('css')
<link rel="stylesheet" href="{{ url('assets/extensions/apexcharts/apexcharts.css') }}">
<style>
    .kpi-sub { font-size: 12px; color: #6c757d; margin-top: 2px; }
    .alert-card { border-radius: 8px; padding: 20px 24px; background: #fff; display: flex; align-items: center; gap: 16px; box-shadow: 0 1px 4px rgba(0,0,0,.06); }
    .alert-card .ac-icon { width: 48px; height: 48px; border-radius: 8px; display: flex; align-items: center; justify-content: center; font-size: 20px; flex-shrink: 0; }
    .alert-card .ac-label { font-size: 13px; color: #6c757d; font-weight: 500; }
    .alert-card .ac-value { font-size: 26px; font-weight: 700; line-height: 1.2; }
    .section-card { background: #fff; border-radius: 8px; box-shadow: 0 1px 4px rgba(0,0,0,.06); padding: 20px 24px; }
    .section-card-title { font-size: 15px; font-weight: 700; color: #333; margin-bottom: 14px; display: flex; align-items: center; gap: 8px; }
    .expire-urgent td { background: #fff5f5 !important; }
    .status-dot { width: 8px; height: 8px; border-radius: 50%; display: inline-block; }
</style>
@endsection

@section('content')
<section class="section">

    {{-- ══════════════════════════════════════════════════════════════
         Row 1: KPI Summary Cards
    ══════════════════════════════════════════════════════════════ --}}
    <div class="row g-3 mb-3">
        {{-- Doanh thu tháng --}}
        <div class="col-6 col-md-3">
            <a href="{{ url('zalo-orders') }}" class="text-decoration-none">
                <div class="das-card mb-0">
                    <div class="des_icon bg4">
                        <i class="fa fa-chart-line" style="font-size:26px;color:#fff;"></i>
                    </div>
                    <div class="des_info">
                        <span class="title-text">Doanh thu tháng</span>
                        <span class="data text4">{{ number_format($stats['month_revenue'], 0, ',', '.') }}đ</span>
                        <div class="kpi-sub">Đơn đã giao trong tháng</div>
                    </div>
                </div>
            </a>
        </div>

        {{-- Đơn hàng hôm nay --}}
        <div class="col-6 col-md-3">
            <a href="{{ url('zalo-orders') }}" class="text-decoration-none">
                <div class="das-card mb-0">
                    <div class="des_icon bg3">
                        <i class="fa fa-shopping-cart" style="font-size:26px;color:#fff;"></i>
                    </div>
                    <div class="des_info">
                        <span class="title-text">Đơn hàng hôm nay</span>
                        <span class="data text3">{{ $stats['today_orders'] }}</span>
                        <div class="kpi-sub">Tổng đơn tạo trong ngày</div>
                    </div>
                </div>
            </a>
        </div>

        {{-- Tổng khách hàng --}}
        <div class="col-6 col-md-3">
            <a href="{{ url('zalo-customers') }}" class="text-decoration-none">
                <div class="das-card mb-0">
                    <div class="des_icon bg1">
                        <i class="fa fa-users" style="font-size:26px;color:#fff;"></i>
                    </div>
                    <div class="des_info">
                        <span class="title-text">Tổng khách hàng</span>
                        <span class="data text1">{{ $stats['total_customers'] }}</span>
                        <div class="kpi-sub">+{{ $stats['new_customers_month'] }} tháng này</div>
                    </div>
                </div>
            </a>
        </div>

        {{-- Nông trại hoạt động --}}
        <div class="col-6 col-md-3">
            <a href="{{ url('farms') }}" class="text-decoration-none">
                <div class="das-card mb-0">
                    <div class="des_icon bg2">
                        <i class="fa fa-seedling" style="font-size:26px;color:#fff;"></i>
                    </div>
                    <div class="des_info">
                        <span class="title-text">Nông trại hoạt động</span>
                        <span class="data text2">{{ $stats['active_farms'] }}</span>
                        <div class="kpi-sub">Đối tác đang cung ứng</div>
                    </div>
                </div>
            </a>
        </div>
    </div>

    {{-- ══════════════════════════════════════════════════════════════
         Row 2: Operational Alert Cards
    ══════════════════════════════════════════════════════════════ --}}
    <div class="row g-3 mb-4">
        {{-- Đơn chờ xử lý --}}
        <div class="col-6 col-md-3">
            <a href="{{ url('zalo-orders') }}?status=pending" class="text-decoration-none">
                <div class="alert-card">
                    <div class="ac-icon" style="background:#fff8e1;">
                        <i class="fa fa-clock" style="color:#f59e0b;"></i>
                    </div>
                    <div>
                        <div class="ac-label">Đơn chờ xử lý</div>
                        <div class="ac-value" style="color:{{ $stats['pending_orders'] > 0 ? '#f59e0b' : '#333' }};">
                            {{ $stats['pending_orders'] }}
                        </div>
                    </div>
                </div>
            </a>
        </div>

        {{-- Yêu cầu hợp tác nông trại --}}
        <div class="col-6 col-md-3">
            <a href="{{ url('farm-requests') }}" class="text-decoration-none">
                <div class="alert-card">
                    <div class="ac-icon" style="background:#e8f5e9;">
                        <i class="fa fa-handshake" style="color:#16a34a;"></i>
                    </div>
                    <div>
                        <div class="ac-label">Yêu cầu hợp tác</div>
                        <div class="ac-value" style="color:{{ $stats['pending_farm_requests'] > 0 ? '#16a34a' : '#333' }};">
                            {{ $stats['pending_farm_requests'] }}
                        </div>
                    </div>
                </div>
            </a>
        </div>

        {{-- Lô hàng sắp hết hạn --}}
        <div class="col-6 col-md-3">
            <a href="{{ url('inventory') }}?filter=expiring" class="text-decoration-none">
                <div class="alert-card">
                    <div class="ac-icon" style="background:#fff0f0;">
                        <i class="fa fa-exclamation-triangle" style="color:#dc2626;"></i>
                    </div>
                    <div>
                        <div class="ac-label">Lô hàng sắp hết hạn <span class="badge bg-secondary" style="font-size:10px;">≤7 ngày</span></div>
                        <div class="ac-value" style="color:{{ $stats['expiring_soon'] > 0 ? '#dc2626' : '#333' }};">
                            {{ $stats['expiring_soon'] }}
                        </div>
                    </div>
                </div>
            </a>
        </div>

        {{-- Khách hàng mới tháng này --}}
        <div class="col-6 col-md-3">
            <a href="{{ url('zalo-customers') }}" class="text-decoration-none">
                <div class="alert-card">
                    <div class="ac-icon" style="background:#ede9fe;">
                        <i class="fa fa-user-plus" style="color:#7c3aed;"></i>
                    </div>
                    <div>
                        <div class="ac-label">Khách hàng mới tháng</div>
                        <div class="ac-value" style="color:#7c3aed;">{{ $stats['new_customers_month'] }}</div>
                    </div>
                </div>
            </a>
        </div>
    </div>

    {{-- ══════════════════════════════════════════════════════════════
         Row 3: Charts
    ══════════════════════════════════════════════════════════════ --}}
    <div class="row g-3 mb-4">
        {{-- Revenue + Orders 30-day combo chart --}}
        <div class="col-md-8">
            <div class="section-card" style="min-height:320px;">
                <div class="section-card-title">
                    <i class="fa fa-chart-bar text-success"></i>
                    Doanh thu & Đơn hàng — 30 ngày qua
                </div>
                <div id="chart-revenue" style="min-height:260px;"></div>
            </div>
        </div>

        {{-- Order status pie --}}
        <div class="col-md-4">
            <div class="section-card" style="min-height:320px;">
                <div class="section-card-title">
                    <i class="fa fa-chart-pie text-primary"></i>
                    Trạng thái đơn hàng
                </div>
                <div id="chart-status" style="min-height:260px;"></div>
                @if($orderStatusBreakdown->isEmpty())
                    <p class="text-center text-muted mt-3" style="font-size:13px;">Chưa có dữ liệu</p>
                @endif
            </div>
        </div>
    </div>

    {{-- ══════════════════════════════════════════════════════════════
         Row 4: Tables
    ══════════════════════════════════════════════════════════════ --}}
    <div class="row g-3">
        {{-- Recent orders --}}
        <div class="col-md-7">
            <div class="section-card">
                <div class="section-card-title">
                    <i class="fa fa-list-alt text-primary"></i>
                    Đơn hàng gần đây
                    <a href="{{ url('zalo-orders') }}" class="ms-auto text-decoration-none" style="font-size:12px;font-weight:500;color:#0d6efd;">Xem tất cả →</a>
                </div>
                <div class="table-responsive">
                    <table class="table table-sm table-hover align-middle mb-0" style="font-size:13px;">
                        <thead class="table-light">
                            <tr>
                                <th>#ID</th>
                                <th>Khách hàng</th>
                                <th class="text-end">Tổng tiền</th>
                                <th>Trạng thái</th>
                                <th>Thời gian</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($recentOrders as $order)
                            <tr>
                                <td>
                                    <a href="{{ url('zalo-orders/'.$order->id) }}" class="fw-semibold text-decoration-none">
                                        #{{ $order->id }}
                                    </a>
                                </td>
                                <td>
                                    @if($order->customer)
                                        <span>{{ $order->customer->name ?? 'N/A' }}</span>
                                    @else
                                        <span class="text-muted">—</span>
                                    @endif
                                </td>
                                <td class="text-end fw-semibold">
                                    {{ number_format($order->total, 0, ',', '.') }}đ
                                </td>
                                <td>
                                    @php $badge = $statusBadge[$order->status] ?? 'secondary'; @endphp
                                    <span class="badge bg-{{ $badge }} bg-opacity-15 text-{{ $badge }} border border-{{ $badge }} border-opacity-25" style="font-size:11px;">
                                        {{ $statusLabel[$order->status] ?? $order->status }}
                                    </span>
                                </td>
                                <td class="text-muted">
                                    {{ $order->created_at ? $order->created_at->timezone('Asia/Ho_Chi_Minh')->format('d/m H:i') : '—' }}
                                </td>
                            </tr>
                            @empty
                            <tr>
                                <td colspan="5" class="text-center text-muted py-3">Chưa có đơn hàng nào</td>
                            </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        {{-- Expiring batches --}}
        <div class="col-md-5">
            <div class="section-card">
                <div class="section-card-title">
                    <i class="fa fa-exclamation-circle text-danger"></i>
                    Lô hàng sắp hết hạn
                    <a href="{{ url('inventory') }}" class="ms-auto text-decoration-none" style="font-size:12px;font-weight:500;color:#0d6efd;">Xem tồn kho →</a>
                </div>
                <div class="table-responsive">
                    <table class="table table-sm table-hover align-middle mb-0" style="font-size:13px;">
                        <thead class="table-light">
                            <tr>
                                <th>Sản phẩm</th>
                                <th>Nông trại</th>
                                <th class="text-center">Còn lại</th>
                                <th>Hết hạn</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($expiringBatches as $batch)
                            @php
                                $daysLeft = $batch->expire_date ? now()->diffInDays($batch->expire_date, false) : 999;
                                $urgent = $daysLeft <= 3;
                            @endphp
                            <tr class="{{ $urgent ? 'expire-urgent' : '' }}">
                                <td>
                                    <span class="fw-semibold">{{ $batch->product->name ?? '—' }}</span>
                                </td>
                                <td class="text-muted" style="max-width:90px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;">
                                    {{ $batch->farm->name ?? '—' }}
                                </td>
                                <td class="text-center">
                                    <span class="badge bg-{{ $batch->quantity_remaining <= 5 ? 'danger' : 'secondary' }}">
                                        {{ $batch->quantity_remaining }}
                                    </span>
                                </td>
                                <td>
                                    @if($batch->expire_date)
                                        <span class="{{ $urgent ? 'text-danger fw-bold' : 'text-warning fw-semibold' }}">
                                            {{ $batch->expire_date->format('d/m/Y') }}
                                        </span>
                                        <br>
                                        <small class="text-muted">
                                            {{ $daysLeft <= 0 ? 'Đã hết hạn' : "còn {$daysLeft} ngày" }}
                                        </small>
                                    @else
                                        <span class="text-muted">—</span>
                                    @endif
                                </td>
                            </tr>
                            @empty
                            <tr>
                                <td colspan="4" class="text-center text-muted py-3">
                                    <i class="fa fa-check-circle text-success me-1"></i>
                                    Không có lô hàng sắp hết hạn
                                </td>
                            </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

</section>
@endsection

@section('script')
<script src="{{ url('assets/extensions/apexcharts/apexcharts.min.js') }}"></script>
<script>
(function () {
    // ── Revenue + Orders combo chart ──────────────────────────────
    var revenueChart = new ApexCharts(document.querySelector('#chart-revenue'), {
        series: [
            {
                name: 'Doanh thu (đ)',
                type: 'bar',
                data: @json($chartRevenue)
            },
            {
                name: 'Số đơn',
                type: 'line',
                data: @json($chartOrders)
            }
        ],
        chart: {
            type: 'line',
            height: 260,
            toolbar: { show: false },
            fontFamily: 'inherit',
        },
        stroke: { width: [0, 3], curve: 'smooth' },
        plotOptions: { bar: { borderRadius: 4, columnWidth: '55%' } },
        colors: ['#A0D8B3', '#9797F4'],
        xaxis: {
            categories: @json($chartDates),
            labels: { rotate: -45, style: { fontSize: '10px' } },
            tickAmount: 10,
        },
        yaxis: [
            {
                title: { text: 'Doanh thu (đ)', style: { fontSize: '11px' } },
                labels: {
                    formatter: function (val) {
                        if (val >= 1000000) return (val / 1000000).toFixed(1) + 'M';
                        if (val >= 1000) return (val / 1000).toFixed(0) + 'K';
                        return val;
                    }
                }
            },
            {
                opposite: true,
                title: { text: 'Số đơn', style: { fontSize: '11px' } },
                labels: { formatter: function (val) { return Math.round(val); } }
            }
        ],
        tooltip: {
            y: [
                {
                    formatter: function (val) {
                        return new Intl.NumberFormat('vi-VN').format(val) + 'đ';
                    }
                },
                { formatter: function (val) { return val + ' đơn'; } }
            ]
        },
        grid: { borderColor: '#f3f4f6' },
        legend: { position: 'top' },
    });
    revenueChart.render();

    // ── Order status pie chart ────────────────────────────────────
    @if(!$orderStatusBreakdown->isEmpty())
    var pieData = @json($pieData);
    var statusChart = new ApexCharts(document.querySelector('#chart-status'), {
        series: pieData.map(function (d) { return d.y; }),
        labels: pieData.map(function (d) { return d.name; }),
        colors: pieData.map(function (d) { return d.color; }),
        chart: {
            type: 'donut',
            height: 260,
            fontFamily: 'inherit',
            toolbar: { show: false },
        },
        plotOptions: {
            pie: {
                donut: { size: '60%' }
            }
        },
        legend: { position: 'bottom', fontSize: '12px' },
        tooltip: {
            y: { formatter: function (val) { return val + ' đơn'; } }
        },
        dataLabels: { enabled: true, formatter: function (val) { return Math.round(val) + '%'; } },
    });
    statusChart.render();
    @endif
})();
</script>
@endsection
