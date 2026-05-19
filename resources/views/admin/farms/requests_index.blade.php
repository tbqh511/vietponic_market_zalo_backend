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
            <h4 class="mb-0">Yêu cầu đăng ký Farm Partner</h4>
            <form method="GET" class="d-flex gap-2 flex-wrap">
                <input type="text" name="q" value="{{ request('q') }}" placeholder="Tìm theo Tên / SĐT / Email"
                       class="form-control form-control-sm" style="max-width:240px;">
                <input type="date" name="date_from" value="{{ request('date_from') }}" class="form-control form-control-sm" style="max-width:160px;">
                <input type="date" name="date_to" value="{{ request('date_to') }}" class="form-control form-control-sm" style="max-width:160px;">
                <button class="btn btn-secondary btn-sm">Lọc</button>
                @if(request()->hasAny(['q','date_from','date_to']))
                    <a href="{{ route('farm-requests.index') }}" class="btn btn-light btn-sm">Xoá lọc</a>
                @endif
            </form>
        </div>
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-hover align-middle">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Tên Customer</th>
                            <th>SĐT</th>
                            <th>Email</th>
                            <th>Ngày yêu cầu</th>
                            <th>Trạng thái</th>
                            <th class="text-end">Hành động</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($requests as $c)
                            <tr>
                                <td>#{{ $c->id }}</td>
                                <td>{{ $c->name ?: '—' }}</td>
                                <td>{{ $c->mobile ?: '—' }}</td>
                                <td>{{ $c->email ?: '—' }}</td>
                                <td>{{ $c->updated_at?->format('d/m/Y H:i') }}</td>
                                <td><span class="badge bg-warning text-dark">Đang chờ</span></td>
                                <td class="text-end">
                                    <button type="button"
                                            class="btn btn-success btn-sm btn-approve"
                                            data-bs-toggle="modal"
                                            data-bs-target="#approveModal"
                                            data-customer-id="{{ $c->id }}"
                                            data-customer-name="{{ $c->name }}"
                                            data-customer-mobile="{{ $c->mobile }}">
                                        Duyệt
                                    </button>
                                    <form action="{{ route('farm-requests.reject', $c->id) }}" method="POST" class="d-inline"
                                          onsubmit="return confirm('Từ chối yêu cầu của {{ addslashes($c->name) }}?');">
                                        @csrf
                                        <button class="btn btn-danger btn-sm">Từ chối</button>
                                    </form>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="7" class="text-center text-muted py-4">Không có yêu cầu nào đang chờ duyệt.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            <div class="d-flex justify-content-between align-items-center">
                <small class="text-muted">Tổng: {{ $requests->total() }} yêu cầu</small>
                {{ $requests->links() }}
            </div>
        </div>
    </div>

    {{-- Modal xác nhận duyệt + tạo Farm. JS bên dưới populate fields từ data attrs. --}}
    <div class="modal fade" id="approveModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog">
            <form id="approveForm" method="POST" action="">
                @csrf
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title">Xác nhận duyệt & tạo Farm</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <div class="alert alert-info py-2 mb-3">
                            <strong>Customer:</strong>
                            <span id="modalCustomerName">—</span>
                            (<span id="modalCustomerMobile">—</span>)
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Tên Farm <span class="text-danger">*</span></label>
                            <input type="text" name="name" id="farmName" class="form-control" required maxlength="150">
                            <small class="text-muted">Auto-fill từ tên customer, có thể sửa.</small>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Mã Farm <span class="text-danger">*</span></label>
                            <input type="text" name="code" id="farmCode" class="form-control" required maxlength="50" style="font-family:monospace;">
                            <small class="text-muted">Định dạng gợi ý: FARM-{SLUG}-001. Phải unique trên hệ thống.</small>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Ghi chú nội bộ</label>
                            <textarea name="note" id="farmNote" class="form-control" rows="2" maxlength="1000"></textarea>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-light" data-bs-dismiss="modal">Huỷ bỏ</button>
                        <button type="submit" class="btn btn-success">Xác nhận Duyệt</button>
                    </div>
                </div>
            </form>
        </div>
    </div>

    <script>
        // Slugify đơn giản: bỏ dấu tiếng Việt + ký tự đặc biệt, uppercase, max 10 ký tự.
        function slugifyFarmCode(name) {
            const vn = 'àáảãạăắằẳẵặâấầẩẫậèéẻẽẹêếềểễệìíỉĩịòóỏõọôốồổỗộơớờởỡợùúủũụưứừửữựỳýỷỹỵđ';
            const en = 'aaaaaaaaaaaaaaaaaeeeeeeeeeeeiiiiiooooooooooooooooouuuuuuuuuuuyyyyyd';
            let s = (name || '').toLowerCase();
            let out = '';
            for (const ch of s) {
                const idx = vn.indexOf(ch);
                out += idx >= 0 ? en[idx] : ch;
            }
            out = out.replace(/[^a-z0-9]/g, '').toUpperCase().substring(0, 10);
            return out || 'F';
        }

        document.querySelectorAll('.btn-approve').forEach(btn => {
            btn.addEventListener('click', () => {
                const id     = btn.dataset.customerId;
                const name   = btn.dataset.customerName || '';
                const mobile = btn.dataset.customerMobile || '';

                document.getElementById('modalCustomerName').textContent   = name || '—';
                document.getElementById('modalCustomerMobile').textContent = mobile || '—';
                document.getElementById('farmName').value = 'Farm ' + name;
                document.getElementById('farmCode').value = 'FARM-' + slugifyFarmCode(name) + '-001';
                document.getElementById('farmNote').value = '';
                document.getElementById('approveForm').action = '{{ url('farm-requests') }}/' + id + '/approve';
            });
        });
    </script>
@endsection
