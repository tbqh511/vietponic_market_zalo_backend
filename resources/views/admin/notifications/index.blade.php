@extends('layouts.main')

@section('content')
    <div class="card mb-3">
        <div class="card-header d-flex justify-content-between align-items-center">
            <h4 class="mb-0">Thông báo Zalo (OA / ZNS)</h4>
        </div>
        <div class="card-body">
            @if(session('success'))
                <div class="alert alert-success">{{ session('success') }}</div>
            @endif

            <form action="{{ route('admin.notifications.toggle') }}" method="POST" class="d-flex gap-2 align-items-center mb-4">
                @csrf
                <label class="me-2 mb-0">Trạng thái gửi thông báo:</label>
                <select name="enabled" class="form-select" style="max-width:200px;">
                    <option value="1" {{ $enabled ? 'selected' : '' }}>Đang bật</option>
                    <option value="0" {{ !$enabled ? 'selected' : '' }}>Đã tắt</option>
                </select>
                <button type="submit" class="btn btn-primary">Lưu</button>
            </form>

            <h6>Trạng thái OA token</h6>
            <ul class="mb-4">
                @if($tokenStatus['has_token'])
                    <li>Đã có token, hết hạn lúc: <strong>{{ $tokenStatus['expires_at'] ?? '—' }}</strong></li>
                    <li>Tình trạng:
                        @if($tokenStatus['expired'])
                            <span class="badge bg-warning text-dark">Hết hạn (sẽ tự refresh)</span>
                        @else
                            <span class="badge bg-success">Còn hạn</span>
                        @endif
                    </li>
                @else
                    <li><span class="badge bg-danger">Chưa có token</span> — seed bằng lệnh
                        <code>php artisan zalo:oa-token --access=... --refresh=...</code></li>
                @endif
            </ul>

            <h6>ZNS template (cấu hình trong .env)</h6>
            <table class="table table-sm" style="max-width:520px;">
                <tbody>
                    <tr><td>Thanh toán thành công</td><td>{{ $templates['paid'] ?: '— chưa cấu hình —' }}</td></tr>
                    <tr><td>Đổi trạng thái</td><td>{{ $templates['status'] ?: '— chưa cấu hình —' }}</td></tr>
                    <tr><td>Huỷ đơn</td><td>{{ $templates['cancelled'] ?: '— chưa cấu hình —' }}</td></tr>
                    <tr><td>Vận chuyển (VTP)</td><td>{{ $templates['shipping'] ?: '— chưa cấu hình —' }}</td></tr>
                </tbody>
            </table>
            <p class="text-muted mb-0">Kênh ưu tiên: OA Message (miễn phí, khi khách đã follow OA), fallback ZNS theo SĐT.</p>
        </div>
    </div>
@endsection
