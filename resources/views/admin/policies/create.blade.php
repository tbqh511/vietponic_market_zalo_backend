@extends('layouts.main')

@section('title')
    {{ __('Thêm chính sách') }}
@endsection

@section('content')
    <section class="section">
        <div class="card">
            <div class="card-header">
                <h4 class="mb-0">Thêm chính sách</h4>
            </div>
            <div class="card-body">
                <form action="{{ route('policies.store') }}" method="POST">
                    @csrf
                    @include('admin.policies._form')
                    <button class="btn btn-primary">Tạo mới</button>
                    <a href="{{ route('policies.index') }}" class="btn btn-secondary">Huỷ</a>
                </form>
            </div>
        </div>
    </section>
@endsection
