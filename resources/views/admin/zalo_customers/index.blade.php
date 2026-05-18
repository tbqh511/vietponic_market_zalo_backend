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

<div class="card">
    <div class="card-header">
        <div class="d-flex justify-content-between align-items-start flex-wrap gap-2">
            <h4 class="mb-0">Quản lý Khách hàng</h4>
            <form method="GET" action="{{ route('zalo-customers.index') }}" class="d-flex flex-wrap gap-2 align-items-center">
                <input type="text" name="q" value="{{ request('q') }}"
                       placeholder="Tìm tên / SĐT / email" class="form-control" style="max-width:220px;">
                <select name="is_active" class="form-select" style="max-width:160px;" onchange="this.form.submit()">
                    <option value="">Tất cả trạng thái</option>
                    <option value="1" {{ request('is_active') === '1' ? 'selected' : '' }}>Đang hoạt động</option>
                    <option value="0" {{ request('is_active') === '0' ? 'selected' : '' }}>Đã tắt</option>
                </select>
                <select name="farm_status" class="form-select" style="max-width:190px;" onchange="this.form.submit()">
                    <option value="">Tất cả trạng thái Farm</option>
                    <option value="approved"  {{ request('farm_status') === 'approved'  ? 'selected' : '' }}>Farm đã duyệt</option>
                    <option value="requested" {{ request('farm_status') === 'requested' ? 'selected' : '' }}>Đang xin duyệt</option>
                    <option value="none"      {{ request('farm_status') === 'none'      ? 'selected' : '' }}>Chưa là Farm</option>
                </select>
                <input type="date" name="date_from" value="{{ request('date_from') }}"
                       class="form-control" style="max-width:150px;" title="Từ ngày">
                <input type="date" name="date_to" value="{{ request('date_to') }}"
                       class="form-control" style="max-width:150px;" title="Đến ngày">
                <button type="submit" class="btn btn-secondary">Lọc</button>
                @if(request()->hasAny(['q','is_active','farm_status','date_from','date_to']))
                    <a href="{{ route('zalo-customers.index') }}" class="btn btn-outline-secondary">Xoá lọc</a>
                @endif
            </form>
        </div>
    </div>

    <div class="card-body">
        <div class="table-responsive">
            <table class="table table-hover align-middle">
                <thead class="table-light">
                    <tr>
                        <th>ID</th>
                        <th>Họ tên</th>
                        <th>SĐT</th>
                        <th>Email</th>
                        <th class="text-center">Trạng thái</th>
                        <th class="text-center">Farm Partner</th>
                        <th>Ngày tham gia</th>
                        <th style="min-width:220px;">Thao tác</th>
                    </tr>
                </thead>
                <tbody>
                @forelse($customers as $c)
                    <tr>
                        <td class="text-muted small">{{ $c->id }}</td>
                        <td>
                            <a href="{{ route('zalo-customers.show', $c->id) }}" class="fw-semibold text-decoration-none">
                                {{ $c->name ?: '—' }}
                            </a>
                            @if($c->logintype)
                                <br><span class="badge bg-secondary" style="font-size:0.65rem;">{{ $c->logintype }}</span>
                            @endif
                        </td>
                        <td class="small">{{ $c->mobile ?: '—' }}</td>
                        <td class="small text-muted">{{ $c->email ?: '—' }}</td>

                        {{-- Toggle Active --}}
                        <td class="text-center">
                            <form action="{{ route('zalo-customers.toggle-active', $c->id) }}" method="POST" class="d-inline"
                                  onsubmit="return confirm('{{ $c->isActive ? 'Vô hiệu hoá' : 'Kích hoạt' }} tài khoản {{ addslashes($c->name ?: '#'.$c->id) }}?')">
                                @csrf
                                @method('PATCH')
                                <button type="submit" class="btn btn-sm {{ $c->isActive ? 'btn-success' : 'btn-outline-danger' }}"
                                        title="{{ $c->isActive ? 'Đang hoạt động — nhấn để vô hiệu hoá' : 'Đã tắt — nhấn để kích hoạt' }}"
                                        style="min-width:90px;">
                                    @if($c->isActive)
                                        <i class="bi bi-check-circle-fill"></i> Hoạt động
                                    @else
                                        <i class="bi bi-slash-circle"></i> Đã tắt
                                    @endif
                                </button>
                            </form>
                        </td>

                        {{-- Trạng thái Farm Partner (read-only; thao tác duyệt chuyển sang admin Farm Hub) --}}
                        <td class="text-center">
                            @php($fps = $c->farm_partner_status ?: 'none')
                            @if($fps === 'approved')
                                <span class="badge bg-primary"><i class="bi bi-tree-fill"></i> Đã duyệt</span>
                            @elseif($fps === 'requested')
                                <span class="badge bg-warning text-dark"><i class="bi bi-hourglass-split"></i> Đang xin</span>
                            @elseif($fps === 'suspended')
                                <span class="badge bg-secondary"><i class="bi bi-slash-circle"></i> Tạm dừng</span>
                            @else
                                <span class="text-muted small">—</span>
                            @endif
                        </td>

                        <td class="small text-muted">{{ $c->created_at?->format('d/m/Y') }}</td>

                        <td>
                            <div class="d-flex gap-1 flex-wrap">
                                <a href="{{ route('zalo-customers.show', $c->id) }}"
                                   class="btn btn-sm btn-outline-primary" title="Xem chi tiết">
                                    <i class="bi bi-eye"></i>
                                </a>
                                <a href="{{ route('zalo-customers.edit', $c->id) }}"
                                   class="btn btn-sm btn-outline-secondary" title="Sửa thông tin">
                                    <i class="bi bi-pencil"></i>
                                </a>
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="8" class="text-center text-muted py-4">Chưa có khách hàng nào</td>
                    </tr>
                @endforelse
                </tbody>
            </table>
        </div>

        <div class="d-flex justify-content-between align-items-center mt-3">
            <div class="text-muted small">
                Tổng: <strong>{{ $customers->total() }}</strong> khách hàng
            </div>
            {{ $customers->links() }}
        </div>
    </div>
</div>

@endsection
