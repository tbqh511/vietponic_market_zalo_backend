@extends('layouts.main')

@section('content')

@if(session('success'))
    <div class="alert alert-success alert-dismissible fade show" role="alert">
        {{ session('success') }}
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
@endif
@if(session('error'))
    <div class="alert alert-danger alert-dismissible fade show" role="alert">
        {{ session('error') }}
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
@endif

{{-- Card 1: Thông tin cơ bản --}}
<div class="card mb-3">
    <div class="card-header d-flex justify-content-between align-items-center flex-wrap gap-2">
        <h4 class="mb-0">Khách hàng #{{ $customer->id }}</h4>
        <div class="d-flex gap-2 flex-wrap">
            <a href="{{ route('zalo-customers.edit', $customer->id) }}" class="btn btn-secondary btn-sm">
                <i class="bi bi-pencil"></i> Sửa thông tin
            </a>
            <a href="{{ route('zalo-customers.index') }}" class="btn btn-outline-secondary btn-sm">
                <i class="bi bi-arrow-left"></i> Quay lại
            </a>
        </div>
    </div>
    <div class="card-body">
        <div class="row g-4">
            {{-- Thông tin cá nhân --}}
            <div class="col-md-6">
                <h6 class="text-muted text-uppercase small mb-3">Thông tin cá nhân</h6>
                <table class="table table-sm">
                    <tr>
                        <td class="text-muted" style="width:40%">Họ tên</td>
                        <td><strong>{{ $customer->name ?: '—' }}</strong></td>
                    </tr>
                    <tr>
                        <td class="text-muted">Số điện thoại</td>
                        <td>{{ $customer->mobile ?: '—' }}</td>
                    </tr>
                    <tr>
                        <td class="text-muted">Email</td>
                        <td>{{ $customer->email ?: '—' }}</td>
                    </tr>
                    <tr>
                        <td class="text-muted">Địa chỉ</td>
                        <td>{{ $customer->address ?: '—' }}</td>
                    </tr>
                    <tr>
                        <td class="text-muted">Loại đăng nhập</td>
                        <td><span class="badge bg-secondary">{{ $customer->logintype ?: '—' }}</span></td>
                    </tr>
                    <tr>
                        <td class="text-muted">Firebase ID</td>
                        <td class="small text-muted">{{ $customer->firebase_id ?: '—' }}</td>
                    </tr>
                    <tr>
                        <td class="text-muted">Ngày tham gia</td>
                        <td>{{ $customer->created_at?->format('d/m/Y H:i') }}</td>
                    </tr>
                </table>
            </div>

            {{-- Thống kê & trạng thái --}}
            <div class="col-md-6">
                <h6 class="text-muted text-uppercase small mb-3">Thống kê & Trạng thái</h6>
                <table class="table table-sm">
                    <tr>
                        <td class="text-muted" style="width:50%">Trạng thái tài khoản</td>
                        <td>
                            @if($customer->isActive)
                                <span class="badge bg-success">Hoạt động</span>
                            @else
                                <span class="badge bg-danger">Đã tắt</span>
                            @endif
                        </td>
                    </tr>
                    <tr>
                        <td class="text-muted">Tổng đơn hàng</td>
                        <td><strong>{{ $ordersCount }}</strong> đơn</td>
                    </tr>
                    <tr>
                        <td class="text-muted">Tổng giá trị đơn</td>
                        <td><strong>{{ number_format($ordersTotal) }}đ</strong></td>
                    </tr>
                    <tr>
                        <td class="text-muted">Giới thiệu bởi</td>
                        <td>
                            @if($customer->referrer)
                                <a href="{{ route('zalo-customers.show', $customer->referrer->id) }}">
                                    {{ $customer->referrer->name ?: '#' . $customer->referrer->id }}
                                </a>
                            @else
                                <span class="text-muted">—</span>
                            @endif
                        </td>
                    </tr>
                    <tr>
                        <td class="text-muted">Số người giới thiệu</td>
                        <td>{{ $referralsCount }} người</td>
                    </tr>
                </table>

                {{-- Toggle Active --}}
                <div class="mt-3">
                    <form action="{{ route('zalo-customers.toggle-active', $customer->id) }}"
                          method="POST" style="display:inline-block"
                          onsubmit="return confirm('Bạn có chắc muốn {{ $customer->isActive ? 'vô hiệu hoá' : 'kích hoạt' }} tài khoản này?')">
                        @csrf
                        @method('PATCH')
                        <button class="btn btn-sm {{ $customer->isActive ? 'btn-warning' : 'btn-success' }}">
                            <i class="bi bi-{{ $customer->isActive ? 'pause-circle' : 'play-circle' }}"></i>
                            {{ $customer->isActive ? 'Vô hiệu hoá' : 'Kích hoạt' }}
                        </button>
                    </form>

                    <form action="{{ route('zalo-customers.destroy', $customer->id) }}"
                          method="POST" style="display:inline-block"
                          onsubmit="return confirm('XOÁ khách hàng #{{ $customer->id }}? Hành động không thể hoàn tác. Chú ý: sẽ bị từ chối nếu có đơn hàng.')">
                        @csrf
                        @method('DELETE')
                        <button class="btn btn-sm btn-outline-danger ms-1">
                            <i class="bi bi-trash"></i> Xoá
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

{{-- Card 2: Farm Partner (read-only) — duyệt/suspend chuyển sang admin Farm Hub --}}
<div class="card mb-3">
    <div class="card-header">
        <h5 class="mb-0"><i class="bi bi-tree"></i> Farm Partner</h5>
    </div>
    <div class="card-body">
        @php($fps = $customer->farm_partner_status ?: 'none')
        <table class="table table-sm mb-0" style="max-width:520px;">
            <tr>
                <td class="text-muted" style="width:40%">Trạng thái</td>
                <td>
                    @if($fps === 'approved')
                        <span class="badge bg-primary">Đã duyệt</span>
                    @elseif($fps === 'requested')
                        <span class="badge bg-warning text-dark">Đang xin duyệt</span>
                    @elseif($fps === 'suspended')
                        <span class="badge bg-secondary">Tạm dừng</span>
                    @else
                        <span class="text-muted">Chưa là Farm Partner</span>
                    @endif
                </td>
            </tr>
            <tr>
                <td class="text-muted">Vai trò (role)</td>
                <td><code>{{ $customer->role ?: 'customer' }}</code></td>
            </tr>
            @if($customer->farm)
                <tr>
                    <td class="text-muted">Farm sở hữu</td>
                    <td>
                        <strong>{{ $customer->farm->name }}</strong>
                        <span class="text-muted small">— mã: {{ $customer->farm->code }}</span>
                    </td>
                </tr>
                <tr>
                    <td class="text-muted">Chu kỳ thanh toán</td>
                    <td>{{ $customer->farm->payment_cycle }}</td>
                </tr>
                <tr>
                    <td class="text-muted">Ngày duyệt</td>
                    <td>{{ $customer->farm->approved_at?->format('d/m/Y H:i') ?: '—' }}</td>
                </tr>
            @endif
        </table>
        <p class="text-muted small mt-3 mb-0">
            <i class="bi bi-info-circle"></i>
            Thao tác duyệt / tạm dừng Farm Partner đã chuyển sang trang quản trị Farm Hub
            (sẽ cập nhật ở task tiếp theo).
        </p>
    </div>
</div>

{{-- Card 4: Đơn hàng gần đây --}}
<div class="card mb-3">
    <div class="card-header d-flex justify-content-between align-items-center">
        <h5 class="mb-0"><i class="bi bi-bag"></i> Đơn hàng gần đây</h5>
        <span class="badge bg-secondary">{{ $ordersCount }} đơn</span>
    </div>
    <div class="card-body">
        <div class="table-responsive">
            <table class="table table-sm align-middle">
                <thead class="table-light">
                    <tr>
                        <th>ID</th>
                        <th>Trạng thái</th>
                        <th>Tổng tiền</th>
                        <th>Ngày đặt</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                @forelse($recentOrders as $order)
                    <tr>
                        <td class="small text-muted">#{{ $order->id }}</td>
                        <td><span class="badge bg-info text-dark">{{ $order->status ?? '—' }}</span></td>
                        <td>{{ number_format((float)($order->total ?? 0)) }}đ</td>
                        <td class="small text-muted">{{ $order->created_at?->format('d/m/Y H:i') }}</td>
                        <td>
                            <a href="{{ route('zalo-orders.show', $order->id) }}" class="btn btn-sm btn-outline-primary">Xem</a>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="5" class="text-center text-muted py-3">Chưa có đơn hàng</td>
                    </tr>
                @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>

{{-- Card 5: Affiliate (chỉ hiện khi có) --}}
@if($customer->affiliate_code)
<div class="card mb-3">
    <div class="card-header">
        <h5 class="mb-0"><i class="bi bi-people"></i> Affiliate</h5>
    </div>
    <div class="card-body">
        <table class="table table-sm">
            <tr>
                <td class="text-muted" style="width:30%">Mã giới thiệu</td>
                <td><code>{{ $customer->affiliate_code }}</code></td>
            </tr>
            <tr>
                <td class="text-muted">Trạng thái</td>
                <td>
                    @php
                        $statusColors = [
                            'pending'   => 'warning',
                            'approved'  => 'success',
                            'suspended' => 'danger',
                            'rejected'  => 'secondary',
                        ];
                    @endphp
                    <span class="badge bg-{{ $statusColors[$customer->affiliate_status] ?? 'secondary' }}">
                        {{ $customer->affiliate_status ?? '—' }}
                    </span>
                </td>
            </tr>
            @if($customer->affiliate_approved_at)
            <tr>
                <td class="text-muted">Ngày duyệt</td>
                <td>{{ $customer->affiliate_approved_at->format('d/m/Y H:i') }}</td>
            </tr>
            @endif
        </table>
        <a href="{{ route('affiliate-partners.show', $customer->id) }}" class="btn btn-sm btn-outline-primary">
            Xem chi tiết Affiliate
        </a>
    </div>
</div>
@endif

@endsection
