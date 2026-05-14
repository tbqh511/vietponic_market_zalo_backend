@extends('layouts.main')

@section('content')
    <div class="card">
        <div class="card-header"><h4 class="mb-0">Sửa CTV #{{ $partner->id }} (<code>{{ $partner->affiliate_code }}</code>)</h4></div>
        <div class="card-body">
            @if ($errors->any())
                <div class="alert alert-danger">
                    <ul class="mb-0">
                        @foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach
                    </ul>
                </div>
            @endif
            <form action="{{ route('affiliate-partners.update', $partner->id) }}" method="POST">
                @csrf
                @method('PUT')
                <div class="mb-3">
                    <label>Họ tên</label>
                    <input type="text" name="name" class="form-control" value="{{ old('name', $partner->name) }}">
                </div>
                <div class="mb-3">
                    <label>SĐT</label>
                    <input type="text" name="mobile" class="form-control" value="{{ old('mobile', $partner->mobile) }}">
                </div>
                <div class="mb-3">
                    <label>Email</label>
                    <input type="email" name="email" class="form-control" value="{{ old('email', $partner->email) }}">
                </div>
                <hr>
                <h6>Thông tin ngân hàng</h6>
                <div class="mb-3">
                    <label>Tên ngân hàng</label>
                    <input type="text" name="affiliate_bank_name" class="form-control" value="{{ old('affiliate_bank_name', $partner->affiliate_bank_name) }}">
                </div>
                <div class="mb-3">
                    <label>Số tài khoản</label>
                    <input type="text" name="affiliate_bank_account" class="form-control" value="{{ old('affiliate_bank_account', $partner->affiliate_bank_account) }}">
                </div>
                <div class="mb-3">
                    <label>Chủ tài khoản</label>
                    <input type="text" name="affiliate_bank_holder" class="form-control" value="{{ old('affiliate_bank_holder', $partner->affiliate_bank_holder) }}">
                </div>
                <button class="btn btn-primary">Lưu</button>
                <a href="{{ route('affiliate-partners.show', $partner->id) }}" class="btn btn-secondary">Huỷ</a>
            </form>
        </div>
    </div>
@endsection
