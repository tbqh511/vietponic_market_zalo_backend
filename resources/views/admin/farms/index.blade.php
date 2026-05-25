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
            <h4 class="mb-0">Danh sách Farm</h4>
            <form method="GET" class="d-flex gap-2 flex-wrap">
                <input type="text" name="q" value="{{ request('q') }}" placeholder="Tìm mã / tên Farm"
                       class="form-control form-control-sm" style="max-width:220px;">
                <select name="status" class="form-select form-select-sm" style="max-width:160px;">
                    <option value="">Tất cả trạng thái</option>
                    <option value="active"    {{ request('status') === 'active' ? 'selected' : '' }}>Đang hoạt động</option>
                    <option value="suspended" {{ request('status') === 'suspended' ? 'selected' : '' }}>Tạm khoá</option>
                </select>
                <button class="btn btn-secondary btn-sm">Lọc</button>
                @if(request()->hasAny(['q','status']))
                    <a href="{{ route('farms.index') }}" class="btn btn-light btn-sm">Xoá lọc</a>
                @endif
            </form>
        </div>
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-hover align-middle">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Mã Farm</th>
                            <th>Tên Farm</th>
                            <th>Chủ sở hữu</th>
                            <th class="text-center">SL Sản phẩm</th>
                            <th>Chu kỳ</th>
                            <th>Trạng thái</th>
                            <th class="text-end">Hành động</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($farms as $f)
                            <tr>
                                <td>#{{ $f->id }}</td>
                                <td><code>{{ $f->code }}</code></td>
                                <td>{{ $f->name }}</td>
                                <td>
                                    @if($f->owner)
                                        {{ $f->owner->name ?: '—' }}
                                        <div class="text-muted small">{{ $f->owner->mobile }}</div>
                                    @else
                                        <span class="text-muted">— chưa có owner —</span>
                                    @endif
                                </td>
                                <td class="text-center">{{ $f->products_count }}</td>
                                <td>{{ $f->payment_cycle }}</td>
                                <td>
                                    @if($f->is_active && $f->approved_at)
                                        <span class="badge bg-success">Active</span>
                                    @elseif(!$f->is_active)
                                        <span class="badge bg-danger">Tạm khoá</span>
                                    @else
                                        <span class="badge bg-secondary">Chưa duyệt</span>
                                    @endif
                                </td>
                                <td class="text-end">
                                    @if($f->is_active && $f->owner_customer_id)
                                        <a href="{{ route('farms.show', $f->id) }}?action=transfer"
                                           class="btn btn-outline-warning btn-sm"
                                           title="Chuyển chủ farm">
                                            <i class="bi bi-arrow-left-right"></i>
                                        </a>
                                    @elseif($f->is_active && !$f->owner_customer_id)
                                        <a href="{{ route('farms.show', $f->id) }}?action=transfer"
                                           class="btn btn-outline-primary btn-sm"
                                           title="Gán chủ farm">
                                            <i class="bi bi-person-plus"></i>
                                        </a>
                                    @endif
                                    <a href="{{ route('farms.show', $f->id) }}" class="btn btn-primary btn-sm">Chi tiết</a>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="8" class="text-center text-muted py-4">Chưa có Farm nào. Duyệt yêu cầu đăng ký để tạo Farm đầu tiên.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            <div class="d-flex justify-content-between align-items-center">
                <small class="text-muted">Tổng: {{ $farms->total() }} Farm</small>
                {{ $farms->links() }}
            </div>
        </div>
    </div>
@endsection
