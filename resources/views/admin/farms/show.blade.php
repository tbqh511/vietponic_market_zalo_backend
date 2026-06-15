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

    <div class="card mb-3">
        <div class="card-header d-flex justify-content-between align-items-center flex-wrap gap-2">
            <div>
                <a href="{{ route('farms.index') }}" class="btn btn-light btn-sm me-2">‹ Quay lại</a>
                <strong>Hồ sơ Farm:</strong>
                <code>{{ $farm->code }}</code>
                — {{ $farm->name }}
                @if($farm->is_active && $farm->approved_at)
                    <span class="badge bg-success ms-2">Active</span>
                @elseif(!$farm->is_active)
                    <span class="badge bg-danger ms-2">Tạm khoá</span>
                @else
                    <span class="badge bg-secondary ms-2">Chưa duyệt</span>
                @endif
            </div>
            <div class="d-flex gap-2">
                @if($farm->is_active)
                    <form action="{{ route('farms.suspend', $farm->id) }}" method="POST"
                          onsubmit="return confirm('Tạm khoá Farm này? Customer sẽ không vào được Hub.');">
                        @csrf
                        @method('PATCH')
                        <button class="btn btn-warning btn-sm">Tạm khoá</button>
                    </form>
                @else
                    <form action="{{ route('farms.reactivate', $farm->id) }}" method="POST"
                          onsubmit="return confirm('Kích hoạt lại Farm này?');">
                        @csrf
                        @method('PATCH')
                        <button class="btn btn-success btn-sm">Kích hoạt lại</button>
                    </form>
                @endif
                @if($farm->is_active)
                    <button type="button"
                            class="btn btn-sm {{ $farm->owner_customer_id ? 'btn-info' : 'btn-primary' }}"
                            data-bs-toggle="modal" data-bs-target="#transferOwnershipModal">
                        {{ $farm->owner_customer_id ? 'Chuyển chủ' : 'Gán chủ' }}
                    </button>
                @endif
                <form action="{{ route('farms.destroy', $farm->id) }}" method="POST"
                      onsubmit="return confirm('Xoá Farm? Chỉ thành công nếu chưa có batch/payout/đơn hàng.');">
                    @csrf
                    @method('DELETE')
                    <button class="btn btn-danger btn-sm">Xoá</button>
                </form>
            </div>
        </div>

        <ul class="nav nav-tabs px-3 pt-2" id="farmTabs" role="tablist">
            <li class="nav-item">
                <button class="nav-link active" data-bs-toggle="tab" data-bs-target="#tab-info" type="button">Thông tin chung</button>
            </li>
            <li class="nav-item">
                <button class="nav-link" data-bs-toggle="tab" data-bs-target="#tab-products" type="button">
                    Cấu hình Sản phẩm
                    <span class="badge bg-secondary">{{ $farm->products->count() }}</span>
                </button>
            </li>
            <li class="nav-item">
                <button class="nav-link" data-bs-toggle="tab" data-bs-target="#tab-staff" type="button">
                    Nhân viên
                    <span class="badge bg-secondary">{{ $farm->staff->count() }}</span>
                </button>
            </li>
            <li class="nav-item">
                <button class="nav-link" data-bs-toggle="tab" data-bs-target="#tab-inventory" type="button">
                    Kho
                    <span class="badge bg-secondary">{{ count($batches) }}</span>
                </button>
            </li>
        </ul>

        <div class="tab-content p-3">
            <div class="tab-pane fade show active" id="tab-info" role="tabpanel">
                @include('admin.farms._partials.tab_info', ['farm' => $farm])
            </div>
            <div class="tab-pane fade" id="tab-products" role="tabpanel">
                @include('admin.farms._partials.tab_products', [
                    'farm'              => $farm,
                    'farmProducts'      => $farmProducts,
                    'availableProducts' => $availableProducts,
                ])
            </div>
            <div class="tab-pane fade" id="tab-staff" role="tabpanel">
                @include('admin.farms._partials.tab_staff', [
                    'farm' => $farm,
                    'availableStaffCandidates' => $availableStaffCandidates,
                ])
            </div>
            <div class="tab-pane fade" id="tab-inventory" role="tabpanel">
                @include('admin.farms._partials.tab_inventory', ['batches' => $batches])
            </div>
        </div>
    </div>

    {{-- Modal "Chuyển chủ farm" — include 1 lần ở đây, shared cho cả nút trong
         header và nút trong tab Thông tin chung. --}}
    @if($farm->is_active)
        @include('admin.farms._partials.modal_transfer_ownership', [
            'farm' => $farm,
            'transferCandidates' => $transferCandidates,
        ])
    @endif
@endsection

@section('js')
<script>
    // Kích hoạt đúng tab từ URL param ?tab=
    (function () {
        const tab = new URLSearchParams(location.search).get('tab');
        if (tab) {
            const btn = document.querySelector('[data-bs-target="#tab-' + tab + '"]');
            if (btn) new bootstrap.Tab(btn).show();
        }
    })();

    // Select2 cho modal gắn sản phẩm
    $('#attachProductModal').on('shown.bs.modal', function () {
        if (!$('#selectProduct').data('select2')) {
            $('#selectProduct').select2({
                dropdownParent: $('#attachProductModal'),
                placeholder: '— Tìm hoặc chọn sản phẩm —',
                allowClear: true,
                width: '100%',
                language: {
                    noResults: function() { return 'Không tìm thấy sản phẩm'; },
                    searching: function() { return 'Đang tìm...'; }
                }
            });
        }
        $('#selectProduct').val(null).trigger('change');
    });

    // Populate modal sửa sản phẩm
    document.querySelectorAll('.btn-edit-product').forEach(function (btn) {
        btn.addEventListener('click', function () {
            var modal = document.getElementById('editProductModal');
            modal.querySelector('form').action = this.dataset.action;
            modal.querySelector('#editCostPrice').value = this.dataset.cost;
            modal.querySelector('#editIsPrimary').checked = this.dataset.primary === '1';
            modal.querySelector('#editProductName').textContent = this.dataset.name;
            new bootstrap.Modal(modal).show();
        });
    });
</script>
@endsection
