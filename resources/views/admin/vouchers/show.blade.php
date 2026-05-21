@extends('layouts.main')

@section('content')
    <div class="card mb-3">
        <div class="card-header d-flex justify-content-between align-items-center">
            <h4 class="mb-0">Mã: <code>{{ $voucher->code }}</code> — {{ $voucher->name }}</h4>
            <div class="d-flex gap-2">
                <a href="{{ route('vouchers.edit', $voucher->id) }}" class="btn btn-secondary">Sửa</a>
                <a href="{{ route('vouchers.index') }}" class="btn btn-light">← Quay lại</a>
            </div>
        </div>
        <div class="card-body">
            @if(session('success'))
                <div class="alert alert-success">{{ session('success') }}</div>
            @endif
            <dl class="row">
                <dt class="col-sm-3">Trạng thái</dt>
                <dd class="col-sm-9">
                    <span class="badge bg-{{ $voucher->is_active ? 'success' : 'secondary' }}">
                        {{ $voucher->is_active ? 'Đang bật' : 'Đã tắt' }}
                    </span>
                    @if(!$voucher->is_public)
                        <span class="badge bg-info">Private</span>
                    @endif
                </dd>
                <dt class="col-sm-3">Loại</dt>
                <dd class="col-sm-9">
                    @switch($voucher->discount_type)
                        @case('percent') Phần trăm @break
                        @case('fixed') Số tiền cố định @break
                        @case('free_shipping') Miễn phí vận chuyển @break
                    @endswitch
                </dd>
                <dt class="col-sm-3">Giá trị</dt>
                <dd class="col-sm-9">
                    {{ rtrim(rtrim(number_format($voucher->discount_value, 2), '0'), '.') }}
                    @if($voucher->max_discount_amount)
                        <small class="text-muted">(tối đa {{ number_format($voucher->max_discount_amount) }}đ)</small>
                    @endif
                </dd>
                <dt class="col-sm-3">Đơn tối thiểu</dt>
                <dd class="col-sm-9">{{ number_format($voucher->min_order_amount) }}đ</dd>
                <dt class="col-sm-3">Lượt dùng</dt>
                <dd class="col-sm-9">{{ $voucher->used_count }} / {{ $voucher->max_uses ?? '∞' }}
                    (mỗi khách: {{ $voucher->max_uses_per_customer ?: '∞' }})
                </dd>
                <dt class="col-sm-3">Hiệu lực</dt>
                <dd class="col-sm-9">
                    {{ optional($voucher->valid_from)->format('d/m/Y H:i') ?: '∞' }}
                    →
                    {{ optional($voucher->valid_to)->format('d/m/Y H:i') ?: '∞' }}
                </dd>
                @if($voucher->description)
                    <dt class="col-sm-3">Mô tả</dt>
                    <dd class="col-sm-9">{{ $voucher->description }}</dd>
                @endif
            </dl>
        </div>
    </div>

    <div class="card">
        <div class="card-header"><h4 class="mb-0">Lịch sử áp dụng ({{ $voucher->redemptions->count() }})</h4></div>
        <div class="card-body">
            <table class="table">
                <thead>
                    <tr>
                        <th>Order ID</th>
                        <th>Customer ID</th>
                        <th>Giảm</th>
                        <th>Tổng đơn</th>
                        <th>Lúc</th>
                    </tr>
                </thead>
                <tbody>
                @forelse($voucher->redemptions as $r)
                    <tr>
                        <td>{{ $r->order_id }}</td>
                        <td>{{ $r->customer_id }}</td>
                        <td>{{ number_format($r->discount_amount) }}đ</td>
                        <td>{{ $r->order ? number_format((float) $r->order->total) . 'đ' : '—' }}</td>
                        <td>{{ optional($r->redeemed_at)->format('d/m/Y H:i') }}</td>
                    </tr>
                @empty
                    <tr><td colspan="5" class="text-center text-muted">Chưa có ai dùng</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
    </div>
@endsection
