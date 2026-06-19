@extends('layouts.main')

@section('content')
<div class="d-flex justify-content-between align-items-center mb-3">
    <h4 class="mb-0">Sửa đơn hàng #{{ $order->id }}</h4>
    <a href="{{ route('zalo-orders.show', $order->id) }}" class="btn btn-sm btn-outline-secondary">← Xem chi tiết</a>
</div>

<div class="row g-3">
    {{-- ── Cột trái: form chỉnh sửa ────────────────────────────────────── --}}
    <div class="col-lg-7">
        <div class="card">
            <div class="card-body">
                @if($errors->any())
                    <div class="alert alert-danger py-2">
                        <ul class="mb-0 ps-3">
                            @foreach($errors->all() as $e)
                                <li>{{ $e }}</li>
                            @endforeach
                        </ul>
                    </div>
                @endif

                <form action="{{ route('zalo-orders.update', $order->id) }}" method="POST">
                    @csrf
                    @method('PUT')

                    <div class="mb-3">
                        <label class="form-label fw-semibold">Trạng thái đơn hàng</label>
                        <select id="status-select" name="status" class="form-select @error('status') is-invalid @enderror">
                            @foreach($statusOptions as $val => $label)
                                <option value="{{ $val }}" @selected(old('status', $order->status) === $val)>{{ $label }}</option>
                            @endforeach
                        </select>
                        @error('status')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>

                    <div id="cancel-reason-block" class="mb-3" style="display:none">
                        <label class="form-label fw-semibold text-danger">Lý do huỷ đơn</label>
                        <textarea name="cancellation_reason" class="form-control @error('cancellation_reason') is-invalid @enderror"
                            rows="2" placeholder="Nhập lý do huỷ đơn...">{{ old('cancellation_reason', $order->cancellation_reason) }}</textarea>
                        @error('cancellation_reason')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>

                    <div class="mb-3">
                        <label class="form-label fw-semibold">Trạng thái thanh toán</label>
                        <select name="payment_status" class="form-select @error('payment_status') is-invalid @enderror">
                            @foreach($paymentOptions as $val => $label)
                                <option value="{{ $val }}" @selected(old('payment_status', $order->payment_status) === $val)>{{ $label }}</option>
                            @endforeach
                        </select>
                        @error('payment_status')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>

                    <div class="mb-3">
                        <label class="form-label fw-semibold">Ngày nhận hàng</label>
                        <input type="datetime-local" name="received_at" class="form-control @error('received_at') is-invalid @enderror"
                            value="{{ old('received_at', $order->received_at ? $order->received_at->format('Y-m-d\TH:i') : '') }}">
                        @error('received_at')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>

                    <div class="mb-4">
                        <label class="form-label fw-semibold">Ghi chú nội bộ</label>
                        <textarea name="note" class="form-control" rows="3"
                            placeholder="Ghi chú cho nhân viên xử lý...">{{ old('note', $order->note) }}</textarea>
                    </div>

                    <div class="d-flex gap-2">
                        <button type="submit" class="btn btn-primary">Lưu thay đổi</button>
                        <a href="{{ route('zalo-orders.show', $order->id) }}" class="btn btn-secondary">Huỷ</a>
                    </div>
                </form>
            </div>
        </div>
    </div>

    {{-- ── Cột phải: context panel (read-only) ─────────────────────────── --}}
    <div class="col-lg-5">
        {{-- Thông tin khách hàng & giao hàng --}}
        <div class="card mb-3">
            <div class="card-header py-2">
                <strong class="small">Thông tin giao hàng</strong>
            </div>
            <div class="card-body py-2">
                @if($order->delivery)
                    <div class="fw-semibold">{{ $order->delivery->name }}</div>
                    <div class="text-muted small">{{ $order->delivery->phone }}</div>
                    <div class="text-muted small mt-1">{{ collect([$order->delivery->address, $order->delivery->ward_name, $order->delivery->province_name])->filter()->join(', ') }}</div>
                @else
                    <span class="text-muted small">Không có thông tin giao hàng</span>
                @endif
            </div>
        </div>

        {{-- Danh sách sản phẩm --}}
        <div class="card mb-3">
            <div class="card-header py-2">
                <strong class="small">Sản phẩm ({{ $order->items->count() }})</strong>
            </div>
            <div class="card-body p-0">
                <table class="table table-sm mb-0">
                    <tbody>
                        @foreach($order->items as $it)
                            <tr>
                                <td class="ps-3">
                                    <div class="small">{{ $it->name }}</div>
                                    <div class="text-muted" style="font-size: 0.75rem">
                                        {{ number_format($it->price) }}đ ×
                                        @if($it->unit_label){{ $it->quantity }} {{ $it->unit_label }}
                                        @else{{ $it->quantity }}@endif
                                    </div>
                                </td>
                                <td class="text-end pe-3 align-middle small fw-semibold">
                                    {{ number_format($it->price * $it->quantity) }}đ
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            <div class="card-footer py-2">
                @if($order->subtotal && $order->subtotal != $order->total)
                    <div class="d-flex justify-content-between small text-muted">
                        <span>Tạm tính</span>
                        <span>{{ number_format($order->subtotal) }}đ</span>
                    </div>
                @endif
                @if($order->shipping_fee)
                    <div class="d-flex justify-content-between small text-muted">
                        <span>Phí vận chuyển</span>
                        <span>{{ number_format($order->shipping_fee) }}đ</span>
                    </div>
                @endif
                @if($order->discount_amount)
                    <div class="d-flex justify-content-between small text-success">
                        <span>Giảm giá @if($order->voucher_code)({{ $order->voucher_code }})@endif</span>
                        <span>-{{ number_format($order->discount_amount) }}đ</span>
                    </div>
                @endif
                <div class="d-flex justify-content-between fw-bold mt-1">
                    <span>Tổng cộng</span>
                    <span>{{ number_format($order->total) }}đ</span>
                </div>
            </div>
        </div>

        {{-- Trạng thái hiện tại --}}
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
                default    => 'light text-dark',
            };
            $statusLabels = [
                'pending'    => 'Chờ xác nhận',
                'confirmed'  => 'Đã xác nhận',
                'preparing'  => 'Đang chuẩn bị',
                'delivering' => 'Đang giao',
                'delivered'  => 'Đã giao',
                'cancelled'  => 'Đã huỷ',
            ];
            $paymentLabels = [
                'pending'  => 'Chờ thanh toán',
                'cod'      => 'COD',
                'success'  => 'Đã thanh toán',
                'failed'   => 'Thất bại',
                'refunded' => 'Đã hoàn tiền',
            ];
        @endphp
        <div class="card">
            <div class="card-header py-2">
                <strong class="small">Trạng thái hiện tại</strong>
            </div>
            <div class="card-body py-2 d-flex gap-2 flex-wrap">
                <span class="badge bg-{{ $statusBadge }}">{{ $statusLabels[$order->status] ?? $order->status }}</span>
                <span class="badge bg-{{ $paymentBadge }}">{{ $paymentLabels[$order->payment_status] ?? $order->payment_status }}</span>
                @if($order->cancellation_reason)
                    <div class="w-100 text-muted small mt-1">Lý do huỷ: {{ $order->cancellation_reason }}</div>
                @endif
            </div>
        </div>
    </div>
</div>

@section('script')
<script>
    const statusSel = document.getElementById('status-select');
    const cancelBlock = document.getElementById('cancel-reason-block');

    function toggleCancelReason() {
        cancelBlock.style.display = statusSel.value === 'cancelled' ? '' : 'none';
    }

    statusSel.addEventListener('change', toggleCancelReason);
    toggleCancelReason();
</script>
@endsection
@endsection
