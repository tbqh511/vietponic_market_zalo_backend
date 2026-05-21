@extends('layouts.main')

@section('content')
    <div class="card">
        <div class="card-header d-flex justify-content-between align-items-center">
            <h4 class="mb-0">Mã giảm giá</h4>
            <div class="d-flex gap-2">
                <form method="GET" class="d-flex gap-2">
                    <input type="text" name="q" value="{{ request('q') }}" placeholder="Tìm mã/tên" class="form-control" style="max-width:200px;">
                    <select name="active" class="form-select" style="max-width:140px;">
                        <option value="">Tất cả</option>
                        <option value="1" {{ request('active') === '1' ? 'selected' : '' }}>Đang bật</option>
                        <option value="0" {{ request('active') === '0' ? 'selected' : '' }}>Đã tắt</option>
                    </select>
                    <button class="btn btn-secondary">Lọc</button>
                </form>
                <a href="{{ route('vouchers.create') }}" class="btn btn-primary">+ Thêm mã</a>
            </div>
        </div>
        <div class="card-body">
            @if(session('success'))
                <div class="alert alert-success">{{ session('success') }}</div>
            @endif
            <table class="table">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Mã</th>
                        <th>Tên</th>
                        <th>Loại</th>
                        <th>Giá trị</th>
                        <th>Lượt dùng</th>
                        <th>Hiệu lực</th>
                        <th>Trạng thái</th>
                        <th>Thao tác</th>
                    </tr>
                </thead>
                <tbody>
                @forelse($vouchers as $v)
                    <tr>
                        <td>{{ $v->id }}</td>
                        <td><code>{{ $v->code }}</code></td>
                        <td>{{ $v->name }}</td>
                        <td>
                            @switch($v->discount_type)
                                @case('percent') Phần trăm @break
                                @case('fixed') Số tiền @break
                                @case('free_shipping') Miễn ship @break
                            @endswitch
                        </td>
                        <td>
                            @if($v->discount_type === 'percent')
                                {{ rtrim(rtrim(number_format($v->discount_value, 2), '0'), '.') }}%
                                @if($v->max_discount_amount)
                                    <small class="text-muted">tối đa {{ number_format($v->max_discount_amount) }}đ</small>
                                @endif
                            @elseif($v->discount_type === 'fixed')
                                {{ number_format((float) $v->discount_value) }}đ
                            @else
                                {{ $v->discount_value > 0 ? 'tối đa ' . number_format((float) $v->discount_value) . 'đ' : 'toàn bộ' }}
                            @endif
                        </td>
                        <td>{{ $v->used_count }}/{{ $v->max_uses ?? '∞' }}</td>
                        <td>
                            @if($v->valid_from || $v->valid_to)
                                <small>
                                    {{ optional($v->valid_from)->format('d/m/Y') ?: '∞' }} →
                                    {{ optional($v->valid_to)->format('d/m/Y') ?: '∞' }}
                                </small>
                            @else
                                <small class="text-muted">không giới hạn</small>
                            @endif
                        </td>
                        <td>
                            <span class="badge bg-{{ $v->is_active ? 'success' : 'secondary' }}">
                                {{ $v->is_active ? 'Bật' : 'Tắt' }}
                            </span>
                            @if(!$v->is_public)
                                <span class="badge bg-info">Private</span>
                            @endif
                        </td>
                        <td>
                            <a href="{{ route('vouchers.show', $v->id) }}" class="btn btn-sm btn-primary">Xem</a>
                            <a href="{{ route('vouchers.edit', $v->id) }}" class="btn btn-sm btn-secondary">Sửa</a>
                            <form action="{{ route('vouchers.toggle-active', $v->id) }}" method="POST" class="d-inline">
                                @csrf
                                @method('PATCH')
                                <button class="btn btn-sm btn-warning">{{ $v->is_active ? 'Tắt' : 'Bật' }}</button>
                            </form>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="9" class="text-center text-muted">Chưa có mã giảm giá nào</td></tr>
                @endforelse
                </tbody>
            </table>
            {{ $vouchers->links() }}
        </div>
    </div>
@endsection
