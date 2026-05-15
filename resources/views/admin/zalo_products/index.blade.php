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

            <form method="GET" class="mb-3">
                <div class="row">
                    <div class="col-sm-4">
                        <select name="category_id" class="form-select" onchange="this.form.submit()">
                            <option value="">-- Tất cả danh mục --</option>
                            @foreach($categories as $cat)
                                <option value="{{ $cat->id }}" {{ request('category_id') == $cat->id ? 'selected' : '' }}>{{ $cat->name }}</option>
                            @endforeach
                        </select>
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
                    @foreach($products as $p)
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
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>
@endsection
