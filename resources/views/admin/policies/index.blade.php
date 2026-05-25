@extends('layouts.main')

@section('title')
    {{ __('Quản lý chính sách') }}
@endsection

@section('content')
    <section class="section">
        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h4 class="mb-0">Chính sách & điều khoản</h4>
                <a href="{{ route('policies.create') }}" class="btn btn-primary">Thêm chính sách</a>
            </div>
            <div class="card-body">
                @if(session('success'))
                    <div class="alert alert-success">{{ session('success') }}</div>
                @endif
                <div class="table-responsive">
                    <table class="table table-hover">
                        <thead>
                            <tr>
                                <th>#</th>
                                <th>Tiêu đề</th>
                                <th>Slug</th>
                                <th class="text-center">Thứ tự</th>
                                <th class="text-center">Trạng thái</th>
                                <th>Cập nhật</th>
                                <th class="text-end">Thao tác</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($policies as $policy)
                                <tr>
                                    <td>{{ $policy->id }}</td>
                                    <td>{{ $policy->title }}</td>
                                    <td><code>{{ $policy->slug }}</code></td>
                                    <td class="text-center">{{ $policy->sort_order }}</td>
                                    <td class="text-center">
                                        @if($policy->is_active)
                                            <span class="badge bg-success">Hiển thị</span>
                                        @else
                                            <span class="badge bg-secondary">Ẩn</span>
                                        @endif
                                    </td>
                                    <td>{{ optional($policy->updated_at)->format('d/m/Y H:i') }}</td>
                                    <td class="text-end">
                                        <a href="{{ route('policies.edit', $policy->id) }}" class="btn btn-sm btn-secondary">Sửa</a>
                                        <form action="{{ route('policies.destroy', $policy->id) }}" method="POST" style="display:inline-block"
                                              onsubmit="return confirm('Xoá chính sách “{{ $policy->title }}”?')">
                                            @csrf
                                            @method('DELETE')
                                            <button class="btn btn-sm btn-danger">Xoá</button>
                                        </form>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="7" class="text-center text-muted py-4">Chưa có chính sách nào. Bấm “Thêm chính sách” để tạo mới.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </section>
@endsection
