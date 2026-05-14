@extends('layouts.main')

@section('content')
    @if(session('success'))
        <div class="alert alert-success">{{ session('success') }}</div>
    @endif

    <div class="card mb-3">
        <div class="card-header d-flex justify-content-between align-items-center">
            <h4 class="mb-0">CTV #{{ $partner->id }} — <code>{{ $partner->affiliate_code }}</code></h4>
            <div>
                <a href="{{ route('affiliate-partners.edit', $partner->id) }}" class="btn btn-secondary btn-sm">Sửa thông tin</a>
                <a href="{{ route('affiliate-partners.index') }}" class="btn btn-light btn-sm">Quay lại</a>
            </div>
        </div>
        <div class="card-body">
            <div class="row">
                <div class="col-md-6">
                    <p><strong>Họ tên:</strong> {{ $partner->name }}</p>
                    <p><strong>SĐT:</strong> {{ $partner->mobile }}</p>
                    <p><strong>Email:</strong> {{ $partner->email }}</p>
                    <p><strong>Đã giới thiệu:</strong> {{ $referralsCount }} khách hàng</p>
                </div>
                <div class="col-md-6">
                    <p><strong>Trạng thái:</strong> {{ $partner->affiliate_status }}</p>
                    <p><strong>Ngày duyệt:</strong> {{ $partner->affiliate_approved_at }}</p>
                    <p><strong>NH:</strong> {{ $partner->affiliate_bank_name }} — {{ $partner->affiliate_bank_account }} ({{ $partner->affiliate_bank_holder }})</p>
                    <form action="{{ route('affiliate-partners.status', $partner->id) }}" method="POST" class="d-flex gap-2 mt-2">
                        @csrf
                        @method('PATCH')
                        <select name="affiliate_status" class="form-select" style="max-width:200px;">
                            <option value="pending" {{ $partner->affiliate_status === 'pending' ? 'selected' : '' }}>Chờ duyệt</option>
                            <option value="approved" {{ $partner->affiliate_status === 'approved' ? 'selected' : '' }}>Đã duyệt</option>
                            <option value="suspended" {{ $partner->affiliate_status === 'suspended' ? 'selected' : '' }}>Tạm khoá</option>
                            <option value="rejected" {{ $partner->affiliate_status === 'rejected' ? 'selected' : '' }}>Từ chối</option>
                        </select>
                        <button class="btn btn-primary btn-sm">Đổi trạng thái</button>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <div class="card mb-3">
        <div class="card-header"><h5 class="mb-0">Tổng hoa hồng</h5></div>
        <div class="card-body">
            <div class="row text-center">
                <div class="col">
                    <div class="text-muted">Chờ</div>
                    <div class="fs-4">{{ number_format(($stats['pending']->total ?? 0)) }}</div>
                </div>
                <div class="col">
                    <div class="text-muted">Đã xác nhận</div>
                    <div class="fs-4 text-success">{{ number_format(($stats['confirmed']->total ?? 0)) }}</div>
                </div>
                <div class="col">
                    <div class="text-muted">Đã chi trả</div>
                    <div class="fs-4">{{ number_format(($stats['paid']->total ?? 0)) }}</div>
                </div>
                <div class="col">
                    <div class="text-muted">Đã huỷ</div>
                    <div class="fs-4 text-danger">{{ number_format(($stats['cancelled']->total ?? 0)) }}</div>
                </div>
            </div>
        </div>
    </div>

    <div class="card mb-3">
        <div class="card-header"><h5 class="mb-0">Ghi nhận chi trả</h5></div>
        <div class="card-body">
            <form action="{{ route('affiliate-partners.payouts.create', $partner->id) }}" method="POST" class="row g-2">
                @csrf
                <div class="col-md-3">
                    <input type="number" min="1" name="amount" class="form-control" placeholder="Số tiền (VND)" required>
                </div>
                <div class="col-md-3">
                    <input type="text" name="reference" class="form-control" placeholder="Mã giao dịch (tuỳ chọn)">
                </div>
                <div class="col-md-4">
                    <input type="text" name="notes" class="form-control" placeholder="Ghi chú">
                </div>
                <div class="col-md-2">
                    <button class="btn btn-success w-100">Ghi nhận</button>
                </div>
            </form>

            @if($payouts->count())
                <table class="table mt-3">
                    <thead><tr><th>ID</th><th>Số tiền</th><th>Mã GD</th><th>Trạng thái</th><th>Ngày trả</th><th>Ghi chú</th></tr></thead>
                    <tbody>
                    @foreach($payouts as $p)
                        <tr>
                            <td>{{ $p->id }}</td>
                            <td>{{ number_format($p->amount) }}</td>
                            <td>{{ $p->reference }}</td>
                            <td>{{ $p->status }}</td>
                            <td>{{ $p->paid_at }}</td>
                            <td>{{ $p->notes }}</td>
                        </tr>
                    @endforeach
                    </tbody>
                </table>
            @endif
        </div>
    </div>

    <div class="card mb-3">
        <div class="card-header d-flex justify-content-between align-items-center">
            <h5 class="mb-0">Khách hàng đã giới thiệu</h5>
            <span class="badge bg-info">{{ $referralsCount }} khách</span>
        </div>
        <div class="card-body">
            @if($referrals->count())
                <div class="table-responsive">
                    <table class="table align-middle">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Họ tên</th>
                                <th>SĐT</th>
                                <th>Email</th>
                                <th class="text-end">Số đơn</th>
                                <th class="text-end">Tổng chi tiêu</th>
                                <th class="text-end">Hoa hồng đã sinh</th>
                                <th>Ngày tham gia</th>
                                <th></th>
                            </tr>
                        </thead>
                        <tbody>
                        @foreach($referrals as $r)
                            <tr>
                                <td>{{ $r->id }}</td>
                                <td>{{ $r->name ?: '—' }}</td>
                                <td>{{ $r->mobile ?: '—' }}</td>
                                <td>{{ $r->email ?: '—' }}</td>
                                <td class="text-end">{{ (int) $r->orders_count }}</td>
                                <td class="text-end">{{ number_format((float) $r->orders_total) }}</td>
                                <td class="text-end text-success">{{ number_format((int) $r->commission_total) }}</td>
                                <td>{{ $r->created_at }}</td>
                                <td>
                                    <a href="{{ route('zalo-orders.index', ['customer_id' => $r->id]) }}" class="btn btn-sm btn-outline-primary">Xem đơn</a>
                                </td>
                            </tr>
                        @endforeach
                        </tbody>
                    </table>
                </div>
                {{ $referrals->links() }}
            @else
                <p class="text-center text-muted mb-0">Chưa có khách hàng được giới thiệu</p>
            @endif
        </div>
    </div>

    <div class="card">
        <div class="card-header"><h5 class="mb-0">Lịch sử hoa hồng</h5></div>
        <div class="card-body">
            <table class="table">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Đơn</th>
                        <th>Khách</th>
                        <th>Tổng đơn</th>
                        <th>Tỷ lệ</th>
                        <th>Hoa hồng</th>
                        <th>Trạng thái</th>
                        <th>Ngày</th>
                    </tr>
                </thead>
                <tbody>
                @forelse($commissions as $c)
                    <tr>
                        <td>{{ $c->id }}</td>
                        <td>{{ $c->order_id }}</td>
                        <td>{{ $c->referred_customer_id }}</td>
                        <td>{{ number_format($c->order_total) }}</td>
                        <td>{{ $c->commission_rate }}%</td>
                        <td>{{ number_format($c->commission_amount) }}</td>
                        <td>{{ $c->status }}</td>
                        <td>{{ $c->created_at }}</td>
                    </tr>
                @empty
                    <tr><td colspan="8" class="text-center text-muted">Chưa có hoa hồng</td></tr>
                @endforelse
                </tbody>
            </table>
            {{ $commissions->links() }}
        </div>
    </div>
@endsection
