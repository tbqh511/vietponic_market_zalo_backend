@extends('layouts.main')

@section('content')
    <div class="card">
        <div class="card-header d-flex justify-content-between align-items-center">
            <h4>Danh mục sản phẩm</h4>
            <a href="{{ route('zalo-categories.create') }}" class="btn btn-primary">Thêm danh mục</a>
        </div>
        <div class="card-body">
            @if(session('success'))
                <div class="alert alert-success">{{ session('success') }}</div>
            @endif
            <table class="table">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Tên</th>
                        <th>Hình ảnh</th>
                        <th>Thao tác</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($categories as $cat)
                        <tr>
                            <td>{{ $cat->id }}</td>
                            <td>{{ $cat->name }}</td>
                            <td>
                                @if($cat->image)
                                    <img src="{{ $cat->image }}" alt="" style="height:40px" />
                                @endif
                            </td>
                            <td>
                                <a href="{{ route('zalo-categories.edit', $cat->id) }}" class="btn btn-sm btn-secondary">Sửa</a>
                                <form action="{{ route('zalo-categories.destroy', $cat->id) }}" method="POST" style="display:inline-block"
                                      onsubmit="return confirm('Xoá danh mục này?')">
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
