@extends('layouts.main')

@section('title')
    {{ __('Sửa chính sách') }}
@endsection

@section('content')
    <section class="section">
        <div class="card">
            <div class="card-header">
                <h4 class="mb-0">Sửa chính sách: {{ $policy->title }}</h4>
            </div>
            <div class="card-body">
                <form action="{{ route('policies.update', $policy->id) }}" method="POST">
                    @csrf
                    @method('PUT')
                    @include('admin.policies._form')
                    <button class="btn btn-primary">Lưu thay đổi</button>
                    <a href="{{ route('policies.index') }}" class="btn btn-secondary">Huỷ</a>
                </form>
            </div>
        </div>
    </section>
@endsection
