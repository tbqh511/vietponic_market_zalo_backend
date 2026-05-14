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
                    <option value="">Tất cả Farm Status</option>
                    <option value="active"   {{ request('farm_status') === 'active'   ? 'selected' : '' }}>Farm Active</option>
                    <option value="inactive" {{ request('farm_status') === 'inactive' ? 'selected' : '' }}>Farm Inactive</option>
                    <option value="none"     {{ request('farm_status') === 'none'     ? 'selected' : '' }}>Chưa là Farm</option>
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
                        <th>Đăng nhập</th>
                        <th class="text-center">Trạng thái</th>
                        <th class="text-center">Farm Partner</th>
                        <th>Ngày tham gia</th>
                        <th>Thao tác</th>
                    </tr>
                </thead>
                <tbody>
                @forelse($customers as $c)
                    <tr>
                        <td class="text-muted small">{{ $c->id }}</td>
                        <td>
                            @if($c->name)
                                {{ $c->name }}
                            @else
                                <span class="text-muted">—</span>
                            @endif
                        </td>
                        <td>{{ $c->mobile ?: '—' }}</td>
                        <td class="small text-muted">{{ $c->email ?: '—' }}</td>
                        <td><span class="badge bg-secondary">{{ $c->logintype ?: '—' }}</span></td>
                        <td class="text-center">
                            @if($c->isActive)
                                <span class="badge bg-success">Hoạt động</span>
                            @else
                                <span class="badge bg-danger">Tắt</span>
                            @endif
                        </td>
                        <td class="text-center">
                            @if($c->farmPartner)
                                @if($c->farmPartner->status === 'active')
                                    <span class="badge bg-primary">Farm Active</span>
                                @else
                                    <span class="badge bg-warning text-dark">Farm Inactive</span>
                                @endif
                            @else
                                <span class="text-muted small">—</span>
                            @endif
                        </td>
                        <td class="small text-muted">{{ $c->created_at?->format('d/m/Y') }}</td>
                        <td>
                            <a href="{{ route('zalo-customers.show', $c->id) }}"
                               class="btn btn-sm btn-primary">Xem</a>
                            <a href="{{ route('zalo-customers.edit', $c->id) }}"
                               class="btn btn-sm btn-secondary">Sửa</a>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="9" class="text-center text-muted py-4">Chưa có khách hàng nào</td>
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
