@extends('layouts.main')

@section('content')
    @if(session('success'))
        <div class="alert alert-success">{{ session('success') }}</div>
    @endif
    @if(session('error'))
        <div class="alert alert-danger">{{ session('error') }}</div>
    @endif

    <div class="card">
        <div class="card-header">
            <h4 class="mb-0">Phân công đóng gói</h4>
            <small class="text-muted">Gán nhân viên đóng gói cho từng đơn theo farm. Mỗi đơn có thể có nhiều phiếu nếu hàng thuộc nhiều farm.</small>
        </div>
        <div class="card-body">
            {{-- Bộ lọc --}}
            <form method="GET" class="row g-2 mb-3">
                <div class="col-md-3">
                    <input type="text" name="q" value="{{ request('q') }}" class="form-control" placeholder="Mã đơn #">
                </div>
                <div class="col-md-3">
                    <select name="farm_id" class="form-control">
                        <option value="">— Tất cả farm —</option>
                        @foreach($farms as $farm)
                            <option value="{{ $farm->id }}" @selected(request('farm_id') == $farm->id)>{{ $farm->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-3">
                    <select name="status" class="form-control">
                        <option value="">— Tất cả trạng thái —</option>
                        @foreach($statusOptions as $key => $label)
                            <option value="{{ $key }}" @selected(request('status') === $key)>{{ $label }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-3">
                    <button class="btn btn-primary">Lọc</button>
                    <a href="{{ route('order-packing.index') }}" class="btn btn-secondary">Xoá lọc</a>
                </div>
            </form>

            <table class="table align-middle">
                <thead>
                    <tr>
                        <th>Đơn</th>
                        <th>Farm</th>
                        <th>Trạng thái đóng gói</th>
                        <th>Nhân viên phụ trách</th>
                        <th>Phân công</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($assignments as $a)
                        @php
                            $badge = match($a->status) {
                                'packed'   => 'success',
                                'packing'  => 'primary',
                                'assigned' => 'info',
                                default    => 'secondary',
                            };
                            $members = $membersByFarm[$a->farm_id] ?? collect();
                        @endphp
                        <tr>
                            <td>
                                <a href="{{ route('zalo-orders.show', $a->order_id) }}">#{{ $a->order_id }}</a>
                                <div class="small text-muted">{{ optional($a->order)->status }}</div>
                            </td>
                            <td>{{ optional($a->farm)->name ?? ('#'.$a->farm_id) }}</td>
                            <td>
                                <span class="badge bg-{{ $badge }}">{{ $statusOptions[$a->status] ?? $a->status }}</span>
                            </td>
                            <td>
                                @if($a->assignedCustomer)
                                    {{ $a->assignedCustomer->name }}
                                    <span class="small text-muted">({{ $a->assignedCustomer->farm_role === 'owner' ? 'chủ farm' : 'nhân viên' }})</span>
                                @else
                                    <span class="text-muted">Chưa gán</span>
                                @endif
                            </td>
                            <td>
                                @if($a->status === 'packed')
                                    <span class="text-muted small">Đã đóng gói — khoá</span>
                                @elseif($members->isEmpty())
                                    <span class="text-muted small">Farm chưa có thành viên</span>
                                @else
                                    <form action="{{ route('order-packing.assign', $a->id) }}" method="POST" class="d-flex gap-2">
                                        @csrf
                                        <select name="packer_customer_id" class="form-control form-control-sm" required>
                                            <option value="">— Chọn người —</option>
                                            @foreach($members as $m)
                                                <option value="{{ $m->id }}" @selected($a->assigned_customer_id == $m->id)>
                                                    {{ $m->name }} {{ $m->farm_role === 'owner' ? '(chủ)' : '' }}
                                                </option>
                                            @endforeach
                                        </select>
                                        <button class="btn btn-sm btn-primary">Gán</button>
                                    </form>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5" class="text-center text-muted py-4">Không có phiếu đóng gói nào khớp bộ lọc.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>

            {{ $assignments->links() }}
        </div>
    </div>
@endsection
