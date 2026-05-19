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

    <div class="card">
        <div class="card-header d-flex justify-content-between align-items-center flex-wrap gap-2">
            <h4 class="mb-0">Đối soát Farm (Payout)</h4>
            <div class="d-flex gap-2 flex-wrap">
                <form method="GET" class="d-flex gap-2 flex-wrap">
                    <select name="farm_id" class="form-select form-select-sm" style="max-width:200px;">
                        <option value="">— Tất cả farm —</option>
                        @foreach($farms as $f)
                            <option value="{{ $f->id }}" {{ (string) request('farm_id') === (string) $f->id ? 'selected' : '' }}>
                                {{ $f->name }} ({{ $f->code }})
                            </option>
                        @endforeach
                    </select>
                    <select name="status" class="form-select form-select-sm" style="max-width:160px;">
                        <option value="">— Tất cả trạng thái —</option>
                        @foreach(['draft' => 'Draft', 'pending' => 'Chưa GD', 'paid' => 'Đã chi', 'cancelled' => 'Đã huỷ'] as $val => $label)
                            <option value="{{ $val }}" {{ request('status') === $val ? 'selected' : '' }}>{{ $label }}</option>
                        @endforeach
                    </select>
                    <input type="date" name="date_from" value="{{ request('date_from') }}" class="form-control form-control-sm" style="max-width:140px;" placeholder="Từ">
                    <input type="date" name="date_to"   value="{{ request('date_to')   }}" class="form-control form-control-sm" style="max-width:140px;" placeholder="Đến">
                    <button class="btn btn-secondary btn-sm">Lọc</button>
                    @if(request()->hasAny(['farm_id','status','date_from','date_to']))
                        <a href="{{ route('farm-payouts.index') }}" class="btn btn-light btn-sm">Xoá lọc</a>
                    @endif
                </form>
                <a href="{{ route('farm-payouts.create') }}" class="btn btn-success btn-sm">+ Tạo lệnh chi</a>
            </div>
        </div>

        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-hover align-middle">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Farm</th>
                            <th>Kỳ đối soát</th>
                            <th class="text-end">Tổng SL</th>
                            <th class="text-end">Doanh thu</th>
                            <th class="text-end">Cần thanh toán</th>
                            <th>Trạng thái</th>
                            <th class="text-end">Hành động</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($payouts as $p)
                            <tr>
                                <td>#{{ $p->id }}</td>
                                <td>
                                    <strong>{{ $p->farm->name ?? '—' }}</strong>
                                    <div class="text-muted small"><code>{{ $p->farm->code ?? '' }}</code></div>
                                </td>
                                <td>
                                    {{ $p->period_start?->format('d/m/Y') }} →
                                    {{ $p->period_end?->format('d/m/Y') }}
                                </td>
                                <td class="text-end">{{ number_format($p->total_sold, 2) }}</td>
                                <td class="text-end">{{ number_format($p->gross_revenue, 0, ',', '.') }} đ</td>
                                <td class="text-end"><strong>{{ number_format($p->net_payout, 0, ',', '.') }} đ</strong></td>
                                <td>
                                    @switch($p->status)
                                        @case('draft')     <span class="badge bg-light text-dark">Draft</span> @break
                                        @case('pending')   <span class="badge bg-warning text-dark">Chưa GD</span> @break
                                        @case('paid')      <span class="badge bg-success">Đã chi</span> @break
                                        @case('cancelled') <span class="badge bg-secondary">Đã huỷ</span> @break
                                        @default           <span class="badge bg-light text-dark">{{ $p->status }}</span>
                                    @endswitch
                                </td>
                                <td class="text-end">
                                    <a href="{{ route('farm-payouts.show', $p->id) }}" class="btn btn-primary btn-sm">
                                        {{ $p->status === 'pending' ? 'Tạo lệnh chi' : 'Xem chi tiết' }}
                                    </a>
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="8" class="text-center text-muted py-4">Chưa có payout nào — bấm "Tạo lệnh chi" để bắt đầu.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            <div class="d-flex justify-content-between align-items-center">
                <small class="text-muted">Tổng: {{ $payouts->total() }} payout</small>
                {{ $payouts->links() }}
            </div>
        </div>
    </div>
@endsection
