@extends('layouts.main')

@section('content')
    <div class="card mb-3">
        <div class="card-header d-flex justify-content-between align-items-center">
            <h4 class="mb-0">Cấu hình Affiliate</h4>
        </div>
        <div class="card-body">
            @if(session('success'))
                <div class="alert alert-success">{{ session('success') }}</div>
            @endif
            <div class="row g-3">
                <div class="col-md-4">
                    <form action="{{ route('affiliate-settings.commission-rate') }}" method="POST" class="d-flex gap-2">
                        @csrf
                        @method('PATCH')
                        <label class="me-2 align-self-center">Tỷ lệ hoa hồng (%):</label>
                        <input type="number" step="0.01" min="0" max="100" name="rate" value="{{ $commissionRate }}" class="form-control" style="max-width:140px;">
                        <button type="submit" class="btn btn-primary">Lưu</button>
                    </form>
                </div>
                <div class="col-md-4">
                    <form action="{{ route('affiliate-settings.auto-approve') }}" method="POST" class="d-flex gap-2">
                        @csrf
                        @method('PATCH')
                        <label class="me-2 align-self-center">Tự duyệt:</label>
                        <select name="enabled" class="form-select" style="max-width:160px;">
                            <option value="1" {{ $autoApprove ? 'selected' : '' }}>Bật</option>
                            <option value="0" {{ !$autoApprove ? 'selected' : '' }}>Tắt (manual)</option>
                        </select>
                        <button type="submit" class="btn btn-primary">Lưu</button>
                    </form>
                </div>
                <div class="col-md-4">
                    <form action="{{ route('affiliate-settings.enabled') }}" method="POST" class="d-flex gap-2">
                        @csrf
                        @method('PATCH')
                        <label class="me-2 align-self-center">Module:</label>
                        <select name="enabled" class="form-select" style="max-width:160px;">
                            <option value="1" {{ $enabled ? 'selected' : '' }}>Đang bật</option>
                            <option value="0" {{ !$enabled ? 'selected' : '' }}>Tạm tắt</option>
                        </select>
                        <button type="submit" class="btn btn-primary">Lưu</button>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <div class="card">
        <div class="card-header d-flex justify-content-between align-items-center">
            <h4 class="mb-0">Danh sách Cộng tác viên</h4>
            <form method="GET" class="d-flex gap-2">
                <input type="text" name="q" value="{{ request('q') }}" placeholder="Tìm mã/tên/SĐT" class="form-control" style="max-width:220px;">
                <select name="status" class="form-select" style="max-width:160px;">
                    <option value="">Tất cả trạng thái</option>
                    <option value="pending" {{ request('status') === 'pending' ? 'selected' : '' }}>Chờ duyệt</option>
                    <option value="approved" {{ request('status') === 'approved' ? 'selected' : '' }}>Đã duyệt</option>
                    <option value="suspended" {{ request('status') === 'suspended' ? 'selected' : '' }}>Tạm khoá</option>
                    <option value="rejected" {{ request('status') === 'rejected' ? 'selected' : '' }}>Từ chối</option>
                </select>
                <button class="btn btn-secondary">Lọc</button>
            </form>
        </div>
        <div class="card-body">
            <table class="table">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Mã GT</th>
                        <th>Họ tên</th>
                        <th>SĐT</th>
                        <th>Trạng thái</th>
                        <th>Ngày duyệt</th>
                        <th>Thao tác</th>
                    </tr>
                </thead>
                <tbody>
                @forelse($partners as $p)
                    <tr>
                        <td>{{ $p->id }}</td>
                        <td><code>{{ $p->affiliate_code }}</code></td>
                        <td>{{ $p->name }}</td>
                        <td>{{ $p->mobile }}</td>
                        <td>{{ $p->affiliate_status }}</td>
                        <td>{{ $p->affiliate_approved_at }}</td>
                        <td>
                            <a href="{{ route('affiliate-partners.show', $p->id) }}" class="btn btn-sm btn-primary">Xem</a>
                            <a href="{{ route('affiliate-partners.edit', $p->id) }}" class="btn btn-sm btn-secondary">Sửa</a>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="7" class="text-center text-muted">Chưa có cộng tác viên nào</td></tr>
                @endforelse
                </tbody>
            </table>
            {{ $partners->links() }}
        </div>
    </div>
@endsection
