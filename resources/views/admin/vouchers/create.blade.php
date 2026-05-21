@extends('layouts.main')

@section('content')
    <div class="card">
        <div class="card-header"><h4 class="mb-0">Thêm mã giảm giá</h4></div>
        <div class="card-body">
            <form action="{{ route('vouchers.store') }}" method="POST">
                @include('admin.vouchers._form')
                <div class="mt-4 d-flex gap-2">
                    <button type="submit" class="btn btn-primary">Tạo mã</button>
                    <a href="{{ route('vouchers.index') }}" class="btn btn-secondary">Huỷ</a>
                </div>
            </form>
        </div>
    </div>
@endsection
