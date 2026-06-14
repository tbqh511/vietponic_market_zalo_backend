@extends('layouts.main')

@section('content')
    <div class="card">
        <div class="card-header d-flex justify-content-between align-items-center">
            <h4>Sản phẩm</h4>
            <a href="{{ route('zalo-products.create') }}" class="btn btn-primary">Thêm sản phẩm</a>
        </div>
        <div class="card-body">
            @if(session('success'))
                <div class="alert alert-success">{{ session('success') }}</div>
            @endif

            <form method="GET" id="filterForm" class="mb-3">
                <div class="row g-2 align-items-end">
                    <div class="col-sm-3">
                        <label class="form-label small text-muted mb-1">Tìm kiếm</label>
                        <input
                            type="text"
                            name="search"
                            id="searchInput"
                            class="form-control"
                            placeholder="Tên, ID, danh mục..."
                            value="{{ request('search') }}"
                            autocomplete="off"
                        >
                    </div>
                    <div class="col-sm-3">
                        <label class="form-label small text-muted mb-1">Danh mục</label>
                        <select name="category_id" class="form-select" onchange="this.form.submit()">
                            <option value="">-- Tất cả danh mục --</option>
                            @foreach($categories as $cat)
                                <option value="{{ $cat->id }}" {{ request('category_id') == $cat->id ? 'selected' : '' }}>{{ $cat->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-sm-2">
                        <label class="form-label small text-muted mb-1">Giá từ (đ)</label>
                        <input
                            type="number"
                            name="price_min"
                            class="form-control"
                            placeholder="0"
                            value="{{ request('price_min') }}"
                            min="0"
                            step="1000"
                        >
                    </div>
                    <div class="col-sm-2">
                        <label class="form-label small text-muted mb-1">Giá đến (đ)</label>
                        <input
                            type="number"
                            name="price_max"
                            class="form-control"
                            placeholder="Không giới hạn"
                            value="{{ request('price_max') }}"
                            min="0"
                            step="1000"
                        >
                    </div>
                    <div class="col-sm-2">
                        <label class="form-label small text-muted mb-1">Hiển thị</label>
                        <select name="per_page" class="form-select" onchange="this.form.submit()">
                            <option value="10"  {{ request('per_page', 15) == 10  ? 'selected' : '' }}>10 / trang</option>
                            <option value="15"  {{ request('per_page', 15) == 15  ? 'selected' : '' }}>15 / trang</option>
                            <option value="25"  {{ request('per_page', 15) == 25  ? 'selected' : '' }}>25 / trang</option>
                            <option value="50"  {{ request('per_page', 15) == 50  ? 'selected' : '' }}>50 / trang</option>
                        </select>
                    </div>
                </div>
                <div class="row mt-2">
                    <div class="col-auto">
                        <button type="submit" class="btn btn-sm btn-primary">Lọc</button>
                        <a href="{{ route('zalo-products.index') }}" class="btn btn-sm btn-outline-secondary ms-1">Xoá bộ lọc</a>
                    </div>
                    <div class="col-auto ms-auto text-muted small d-flex align-items-center">
                        Tổng: <strong class="ms-1">{{ $products->total() }}</strong>&nbsp;sản phẩm
                    </div>
                </div>
            </form>

            <table class="table">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Tên</th>
                        <th>Danh mục</th>
                        <th>Giá</th>
                        <th>Hình ảnh</th>
                        <th>Thao tác</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($products as $p)
                        <tr>
                            <td>{{ $p->id }}</td>
                            <td>{{ $p->name }}</td>
                            <td>{{ $p->category?->name }}</td>
                            <td>{{ number_format($p->price) }}</td>
                            <td>@if($p->image)<img src="{{ $p->image_url }}" alt="{{ $p->name }}" style="height:40px; width:40px; object-fit:cover; border:1px solid #ddd;">@else<span class="text-muted">Chưa có ảnh</span>@endif</td>
                            <td>
                                <a href="{{ route('zalo-products.edit', $p->id) }}" class="btn btn-sm btn-secondary">Sửa</a>
                                <form action="{{ route('zalo-products.destroy', $p->id) }}" method="POST" style="display:inline-block" onsubmit="return confirm('Xoá sản phẩm này?')">
                                    @csrf
                                    @method('DELETE')
                                    <button class="btn btn-sm btn-danger">Xoá</button>
                                </form>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="text-center text-muted py-4">Không tìm thấy sản phẩm nào.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>

            <div class="d-flex justify-content-between align-items-center mt-3">
                <div class="text-muted small">
                    Trang {{ $products->currentPage() }} / {{ $products->lastPage() }}
                    &nbsp;({{ $products->firstItem() }}–{{ $products->lastItem() }} trong {{ $products->total() }})
                </div>
                <div>
                    {{ $products->links() }}
                </div>
            </div>
        </div>
    </div>
@endsection

@section('script')
<script>
(function () {
    const input = document.getElementById('searchInput');
    const form  = document.getElementById('filterForm');
    if (!input || !form) return;

    let timer;
    input.addEventListener('input', function () {
        clearTimeout(timer);
        timer = setTimeout(function () {
            form.submit();
        }, 400);
    });
})();
</script>
@endsection
