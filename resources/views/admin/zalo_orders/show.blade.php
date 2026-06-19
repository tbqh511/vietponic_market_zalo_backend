@extends('layouts.main')

@section('content')
    @if(session('success'))
        <div class="alert alert-success">{{ session('success') }}</div>
    @endif
    @if($errors->any())
        <div class="alert alert-danger py-2">
            <ul class="mb-0 ps-3">
                @foreach($errors->all() as $e)<li>{{ $e }}</li>@endforeach
            </ul>
        </div>
    @endif
    @if(session('error'))
        <div class="alert alert-danger">{{ session('error') }}</div>
    @endif

    @php
        $statusOptions = [
            'pending'    => 'Chờ xác nhận',
            'confirmed'  => 'Đã xác nhận',
            'preparing'  => 'Đang chuẩn bị',
            'delivering' => 'Đang giao',
            'delivered'  => 'Đã giao',
            'cancelled'  => 'Đã huỷ',
        ];
        $paymentOptions = [
            'pending'  => 'Chờ thanh toán',
            'cod'      => 'COD',
            'success'  => 'Đã thanh toán',
            'failed'   => 'Thất bại',
            'refunded' => 'Đã hoàn tiền',
        ];
    @endphp

    {{-- ── Cập nhật nhanh ──────────────────────────────────────────────────── --}}
    <div class="card mb-3">
        <div class="card-body py-2">
            <form action="{{ route('zalo-orders.update', $order->id) }}" method="POST"
                  class="row g-2 align-items-end flex-wrap">
                @csrf
                @method('PUT')
                <div class="col-auto">
                    <label class="form-label mb-1 small fw-semibold">Trạng thái</label>
                    <select name="status" class="form-select form-select-sm">
                        @foreach($statusOptions as $val => $label)
                            <option value="{{ $val }}" @selected($order->status === $val)>{{ $label }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-auto">
                    <label class="form-label mb-1 small fw-semibold">Thanh toán</label>
                    <select name="payment_status" class="form-select form-select-sm">
                        @foreach($paymentOptions as $val => $label)
                            <option value="{{ $val }}" @selected($order->payment_status === $val)>{{ $label }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-auto">
                    <label class="form-label mb-1 small fw-semibold">Ghi chú</label>
                    <input type="text" name="note" class="form-control form-control-sm"
                        value="{{ $order->note }}" placeholder="Ghi chú nhanh..." style="width:200px">
                </div>
                <div class="col-auto">
                    <button type="submit" class="btn btn-primary btn-sm">Cập nhật</button>
                    <a href="{{ route('zalo-orders.edit', $order->id) }}" class="btn btn-outline-secondary btn-sm ms-1">Sửa đầy đủ</a>
                </div>
            </form>
        </div>
    </div>

    <div class="card">
        <div class="card-header d-flex justify-content-between align-items-center">
            <h4 class="mb-0">Đơn hàng #{{ $order->id }}</h4>
            <span class="text-muted small">{{ $order->created_at?->format('d/m/Y H:i') }}</span>
        </div>
        <div class="card-body">
            <h5>Giao hàng</h5>
            @if($order->delivery)
                <p>{{ $order->delivery->name }} - {{ $order->delivery->phone }}</p>
                <p>{{ collect([$order->delivery->address, $order->delivery->ward_name, $order->delivery->province_name])->filter()->join(', ') }}</p>
            @else
                <p>Không có thông tin giao hàng</p>
            @endif

            <h5>Sản phẩm</h5>
            <table class="table">
                <thead>
                    <tr>
                        <th>Tên sản phẩm</th>
                        <th>Đơn giá</th>
                        <th>Số lượng</th>
                        <th>Quy đổi (hệ thống)</th>
                        <th>Thành tiền</th>
                        <th>Thao tác</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($order->items as $it)
                        <tr>
                            <td>{{ $it->name }}</td>
                            <td>{{ number_format($it->price) }}</td>
                            <td>
                                @if($it->unit_label)
                                    {{ $it->quantity }} {{ $it->unit_label }}
                                @else
                                    x{{ $it->quantity }}
                                @endif
                            </td>
                            <td class="small text-muted">
                                @if($it->system_total !== null && $it->system_unit && (float)$it->conversion_factor !== 1.0)
                                    {{ \App\Models\ZaloUnit::formatSystemTotal((float)$it->system_total, $it->system_unit) }}
                                @else
                                    —
                                @endif
                            </td>
                            <td>{{ number_format($it->price * $it->quantity) }}</td>
                            <td>
                                <a href="{{ route('zalo-order-items.edit', $it->id) }}" class="btn btn-sm btn-secondary">Sửa</a>
                                <form action="{{ route('zalo-order-items.destroy', $it->id) }}" method="POST" style="display:inline-block" onsubmit="return confirm('Xoá sản phẩm này?')">
                                    @csrf
                                    @method('DELETE')
                                    <button class="btn btn-sm btn-danger">Xoá</button>
                                </form>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>

            <h5>Tóm tắt đơn hàng</h5>
            @php
                $statusBadge = match($order->status) {
                    'delivered'  => 'success',
                    'delivering' => 'primary',
                    'preparing'  => 'info',
                    'confirmed'  => 'warning',
                    'cancelled'  => 'danger',
                    default      => 'secondary',
                };
                $paymentBadge = match($order->payment_status) {
                    'success'  => 'success',
                    'failed'   => 'danger',
                    'refunded' => 'warning',
                    'cod'      => 'secondary',
                    default    => 'light text-dark border',
                };
            @endphp
            <div class="mb-2">
                <span class="badge bg-{{ $statusBadge }}">{{ $statusOptions[$order->status] ?? $order->status }}</span>
                <span class="badge bg-{{ $paymentBadge }} ms-1">{{ $paymentOptions[$order->payment_status] ?? $order->payment_status }}</span>
            </div>
            @if($order->subtotal && $order->subtotal != $order->total)
                <p class="mb-1 text-muted small">Tạm tính: {{ number_format($order->subtotal) }}đ</p>
            @endif
            @if($order->shipping_fee)
                <p class="mb-1 text-muted small">Phí vận chuyển: {{ number_format($order->shipping_fee) }}đ</p>
            @endif
            @if($order->discount_amount)
                <p class="mb-1 text-success small">Giảm giá@if($order->voucher_code) ({{ $order->voucher_code }})@endif: -{{ number_format($order->discount_amount) }}đ</p>
            @endif
            <p class="fw-bold">Tổng tiền: {{ number_format($order->total) }}đ</p>
            @if($order->cancellation_reason)
                <p class="text-danger small mb-0">Lý do huỷ: {{ $order->cancellation_reason }}</p>
            @endif
        </div>
    </div>

    {{-- ─── Phân công đóng gói (per-farm) ──────────────────────────────────────── --}}
    @if($order->assignments->isNotEmpty())
        <div class="card mt-3">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h5 class="mb-0">Đóng gói theo farm</h5>
                <a href="{{ route('order-packing.index', ['q' => $order->id]) }}" class="btn btn-sm btn-secondary">Phân công</a>
            </div>
            <div class="card-body">
                <table class="table mb-0 align-middle">
                    <thead>
                        <tr>
                            <th>Farm</th>
                            <th>Trạng thái</th>
                            <th>Nhân viên phụ trách</th>
                            <th>Mốc thời gian</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($order->assignments as $a)
                            @php
                                $badge = match($a->status) {
                                    'packed'   => 'success',
                                    'packing'  => 'primary',
                                    'assigned' => 'info',
                                    default    => 'secondary',
                                };
                                $statusLabels = [
                                    'unassigned' => 'Chưa gán',
                                    'assigned'   => 'Đã gán',
                                    'packing'    => 'Đang đóng gói',
                                    'packed'     => 'Đã đóng gói',
                                ];
                            @endphp
                            <tr>
                                <td>{{ optional($a->farm)->name ?? ('#'.$a->farm_id) }}</td>
                                <td><span class="badge bg-{{ $badge }}">{{ $statusLabels[$a->status] ?? $a->status }}</span></td>
                                <td>
                                    @if($a->assignedCustomer)
                                        {{ $a->assignedCustomer->name }}
                                        <span class="small text-muted">({{ $a->assignedCustomer->farm_role === 'owner' ? 'chủ farm' : 'nhân viên' }})</span>
                                    @else
                                        <span class="text-muted">—</span>
                                    @endif
                                </td>
                                <td class="small text-muted">
                                    @if($a->packed_at)
                                        Xong: {{ $a->packed_at->format('d/m H:i') }}
                                    @elseif($a->packing_started_at)
                                        Bắt đầu: {{ $a->packing_started_at->format('d/m H:i') }}
                                    @elseif($a->assigned_at)
                                        Gán: {{ $a->assigned_at->format('d/m H:i') }}
                                    @else
                                        —
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    @endif

    {{-- ─── Nhật ký đóng gói (audit trail truy vết sự cố) ──────────────────────── --}}
    @if($order->packingLogs->isNotEmpty())
        <div class="card mt-3">
            <div class="card-header">
                <h5 class="mb-0">Nhật ký đóng gói ({{ $order->packingLogs->count() }})</h5>
            </div>
            <div class="card-body">
                <ul class="list-unstyled mb-0" style="border-left: 2px solid #dee2e6; padding-left: 20px;">
                    @php
                        $actionLabels = [
                            'assigned'        => 'Phân công',
                            'reassigned'      => 'Đổi người phụ trách',
                            'unassigned'      => 'Gỡ phân công',
                            'packing_started' => 'Bắt đầu đóng gói',
                            'packed'          => 'Hoàn tất đóng gói',
                            'status_changed'  => 'Đổi trạng thái đơn',
                        ];
                    @endphp
                    @foreach($order->packingLogs as $log)
                        <li class="mb-3" style="position: relative;">
                            <span style="position:absolute;left:-27px;top:4px;width:12px;height:12px;background:#0d6efd;border-radius:50%;border:2px solid white;box-shadow:0 0 0 2px #dee2e6;"></span>
                            <div>
                                <strong>{{ optional($log->created_at)->format('d/m/Y H:i') }}</strong>
                                — {{ $actionLabels[$log->action] ?? $log->action }}
                                @if($log->from_status || $log->to_status)
                                    <span class="badge bg-light text-dark">{{ $log->from_status }} → {{ $log->to_status }}</span>
                                @endif
                            </div>
                            <div class="text-muted small">👤 {{ $log->actor_label ?? 'Hệ thống' }}</div>
                            @if(!empty($log->meta['packer_name']))
                                <div class="text-muted small">→ {{ $log->meta['packer_name'] }}</div>
                            @endif
                        </li>
                    @endforeach
                </ul>
            </div>
        </div>
    @endif

    {{-- ─── ViettelPost tracking section ───────────────────────────────────────── --}}
    @if($order->delivery && $order->delivery->type === 'shipping')
        <div class="card mt-3">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h5 class="mb-0">Hành trình ViettelPost</h5>
                @if($order->delivery->vtp_order_number)
                    <span class="badge bg-info text-white" style="font-size: 14px;">{{ $order->delivery->vtp_order_number }}</span>
                @endif
            </div>
            <div class="card-body">
                @if($order->delivery->vtp_order_number)
                    <div class="row mb-3">
                        <div class="col-md-4">
                            <strong>Trạng thái:</strong><br>
                            <span class="text-primary">{{ $order->delivery->vtp_status_name ?: '—' }}</span>
                            @if($order->delivery->vtp_status_code)
                                <span class="text-muted">(TT {{ $order->delivery->vtp_status_code }})</span>
                            @endif
                        </div>
                        <div class="col-md-4">
                            <strong>Vị trí hiện tại:</strong><br>
                            <span>{{ $order->delivery->vtp_location ?: '—' }}</span>
                        </div>
                        <div class="col-md-4">
                            <strong>Cập nhật:</strong><br>
                            <span>{{ $order->delivery->vtp_status_at ? $order->delivery->vtp_status_at->format('d/m/Y H:i:s') : '—' }}</span>
                            @if($order->delivery->vtp_is_returning)
                                <br><span class="badge bg-warning text-dark">Đang chuyển hoàn</span>
                            @endif
                        </div>
                    </div>

                    @if($order->trackingEvents->isNotEmpty())
                        <hr>
                        <h6>Lịch sử hành trình ({{ $order->trackingEvents->count() }} sự kiện)</h6>
                        <ul class="list-unstyled mt-3" style="border-left: 2px solid #dee2e6; padding-left: 20px;">
                            @foreach($order->trackingEvents as $event)
                                <li class="mb-3 pb-2" style="position: relative;">
                                    <span style="position: absolute; left: -27px; top: 4px; width: 12px; height: 12px; background: #0d6efd; border-radius: 50%; border: 2px solid white; box-shadow: 0 0 0 2px #dee2e6;"></span>
                                    <div>
                                        <strong>{{ $event->status_at?->format('d/m/Y H:i') }}</strong>
                                        — {{ $event->status_name }}
                                        <span class="badge bg-light text-dark">{{ $event->status_code }}</span>
                                    </div>
                                    @if($event->location)
                                        <div class="text-muted small">📍 {{ $event->location }}</div>
                                    @endif
                                    @if($event->note)
                                        <div class="text-muted small">💬 {{ $event->note }}</div>
                                    @endif
                                    @if($event->employee_name)
                                        <div class="text-muted small">👤 {{ $event->employee_name }} @if($event->employee_phone) — {{ $event->employee_phone }}@endif</div>
                                    @endif
                                    @if($event->reason_code)
                                        <div class="text-warning small">⚠ Mã lý do: {{ $event->reason_code }}</div>
                                    @endif
                                </li>
                            @endforeach
                        </ul>
                    @else
                        <p class="text-muted small mb-0">Chưa có sự kiện hành trình nào từ VTP.</p>
                    @endif
                @else
                    <div class="alert alert-warning mb-2">
                        Đơn shipping này <strong>chưa có mã VTP</strong>. Có thể do lỗi tạo lúc checkout.
                    </div>
                    <form action="{{ route('zalo-orders.vtp-retry', $order->id) }}" method="POST" onsubmit="return confirm('Tạo lại đơn VTP?')">
                        @csrf
                        <button type="submit" class="btn btn-primary btn-sm">Tạo lại đơn VTP</button>
                    </form>
                @endif
            </div>
        </div>
    @endif
@endsection
