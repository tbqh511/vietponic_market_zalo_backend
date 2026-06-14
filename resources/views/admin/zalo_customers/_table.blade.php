{{-- Bảng danh sách khách hàng — tách riêng để live filter (AJAX) thay thế phần này. --}}
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

                {{-- Trạng thái Farm Partner + thao tác inline --}}
                <td class="text-center">
                    @php $fps = $c->farm_partner_status ?: 'none'; @endphp
                    <div class="d-flex flex-column align-items-center gap-1">
                        @if($fps === 'approved' && $c->isFarmOwner())
                            <span class="badge bg-primary"><i class="bi bi-tree-fill"></i> Chủ farm</span>
                            @if($c->farm)
                                <div class="small text-muted" title="Mã: {{ $c->farm->code }}">
                                    {{ $c->farm->name }}
                                </div>
                            @endif
                            <form action="{{ route('zalo-customers.suspend-farm', $c->id) }}" method="POST" class="d-inline"
                                  onsubmit="return confirm('Tạm dừng vai trò Farm Partner của {{ addslashes($c->name ?: '#'.$c->id) }}?\nFarm sẽ bị ẩn khỏi Hub.')">
                                @csrf
                                @method('PATCH')
                                <button type="submit" class="btn btn-sm btn-outline-warning" title="Tạm dừng vai trò Farm Partner">
                                    <i class="bi bi-pause-circle"></i> Tạm dừng
                                </button>
                            </form>
                        @elseif($fps === 'approved' && $c->isFarmStaff())
                            <span class="badge bg-info text-dark"><i class="bi bi-person-badge"></i> Nhân viên</span>
                            @if($c->farm)
                                <div class="small text-muted" title="Mã: {{ $c->farm->code }}">
                                    {{ $c->farm->name }}
                                </div>
                                <a href="{{ route('farms.show', $c->farm->id) }}#tab-staff"
                                   class="btn btn-sm btn-outline-secondary" title="Quản lý tại trang Farm">
                                    <i class="bi bi-box-arrow-up-right"></i> Vào farm
                                </a>
                            @endif
                        @elseif($fps === 'approved')
                            {{-- Approved nhưng chưa có farm_id/farm_role — data lệch, hiển thị cảnh báo --}}
                            <span class="badge bg-warning text-dark"><i class="bi bi-exclamation-triangle"></i> Approved chưa gán farm</span>
                        @elseif($fps === 'requested')
                            <span class="badge bg-warning text-dark"><i class="bi bi-hourglass-split"></i> Đang xin</span>
                            <button type="button" class="btn btn-sm btn-outline-success"
                                    data-bs-toggle="modal" data-bs-target="#promoteFarmModal"
                                    data-customer-id="{{ $c->id }}" data-customer-name="{{ $c->name ?: '#'.$c->id }}"
                                    title="Duyệt và gán Farm">
                                <i class="bi bi-check2-circle"></i> Duyệt
                            </button>
                        @elseif($fps === 'suspended')
                            <span class="badge bg-secondary"><i class="bi bi-slash-circle"></i> Tạm dừng</span>
                            <div class="small text-muted">Kích hoạt lại trong /farms</div>
                        @else
                            <span class="text-muted small">—</span>
                            <div class="d-flex gap-1 flex-wrap justify-content-center">
                                <button type="button" class="btn btn-sm btn-outline-success"
                                        data-bs-toggle="modal" data-bs-target="#promoteFarmModal"
                                        data-customer-id="{{ $c->id }}" data-customer-name="{{ $c->name ?: '#'.$c->id }}"
                                        title="Chỉ định làm chủ farm">
                                    <i class="bi bi-tree"></i> Chủ farm
                                </button>
                                <button type="button" class="btn btn-sm btn-outline-info"
                                        data-bs-toggle="modal" data-bs-target="#assignStaffModal"
                                        data-customer-id="{{ $c->id }}" data-customer-name="{{ $c->name ?: '#'.$c->id }}"
                                        title="Gán làm nhân viên farm có sẵn"
                                        @if($farmsWithOwner->isEmpty()) disabled @endif>
                                    <i class="bi bi-person-plus"></i> Staff
                                </button>
                            </div>
                        @endif
                    </div>
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
