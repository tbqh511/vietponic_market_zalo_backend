@extends('layouts.main')

@section('content')
    @if(session('success'))
        <div class="alert alert-success">{{ session('success') }}</div>
    @endif
    @if(session('error'))
        <div class="alert alert-danger">{{ session('error') }}</div>
    @endif

    <div class="card">
        <div class="card-header d-flex justify-content-between align-items-center">
            <h4>Order #{{ $order->id }}</h4>
            <a href="{{ route('zalo-orders.edit', $order->id) }}" class="btn btn-secondary">Sửa</a>
        </div>
        <div class="card-body">
            <h5>Giao hàng</h5>
            @if($order->delivery)
                <p>{{ $order->delivery->name }} - {{ $order->delivery->phone }}</p>
                <p>{{ $order->delivery->address }}</p>
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
            <p>Tổng tiền: {{ number_format($order->total) }}</p>
            <p>Trạng thái: {{ $order->status }} | Thanh toán: {{ $order->payment_status }}</p>
        </div>
    </div>

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
