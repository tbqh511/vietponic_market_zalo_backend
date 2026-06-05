@extends('frontends.vietponics_master')

@section('title', 'Không tìm thấy trang - Vietponics')
@section('description', 'Trang bạn đang tìm kiếm không tồn tại trên Vietponics.')

@push('styles')
<style>
  .err-wrap {
    max-width: 720px;
    margin: 0 auto;
    padding: clamp(3rem, 8vw, 6rem) clamp(1rem, 4vw, 2rem) clamp(4rem, 9vw, 7rem);
    text-align: center;
  }
  .err-code {
    font-family: 'Lora', Georgia, serif;
    font-size: clamp(96px, 22vw, 180px);
    font-weight: 700;
    line-height: 1;
    color: var(--primary);
    letter-spacing: -4px;
  }
  .err-title {
    font-family: 'Lora', Georgia, serif;
    font-size: clamp(22px, 4vw, 30px);
    font-weight: 700;
    color: var(--dark);
    margin: 8px 0 12px;
  }
  .err-desc {
    font-size: 15.5px;
    color: var(--text-muted);
    line-height: 1.8;
    margin-bottom: 28px;
  }
  .err-search {
    display: flex;
    gap: 8px;
    max-width: 480px;
    margin: 0 auto 22px;
  }
  .err-search input {
    flex: 1;
    background: var(--white);
    border: 1px solid var(--border);
    border-radius: var(--r);
    padding: 12px 16px;
    font-size: 14px;
    color: var(--text);
    font-family: inherit;
    outline: none;
  }
  .err-search input::placeholder { color: var(--text-light); }
  .err-search input:focus { border-color: var(--primary); }
  .err-search button {
    background: var(--primary);
    color: #fff;
    border: none;
    border-radius: var(--r);
    padding: 12px 18px;
    font-size: 14px;
    font-weight: 600;
    cursor: pointer;
    font-family: inherit;
    transition: background .2s;
  }
  .err-search button:hover { background: var(--primary-dark); }
  .err-home {
    display: inline-block;
    background: var(--primary);
    color: #fff;
    text-decoration: none;
    border-radius: var(--r-pill);
    padding: 12px 28px;
    font-size: 14px;
    font-weight: 600;
    transition: background .2s;
  }
  .err-home:hover { background: var(--primary-dark); }
  .err-or { font-size: 13px; color: var(--text-light); margin-bottom: 18px; }
</style>
@endpush

@section('content')
<div class="err-wrap">
  <div class="err-code">404</div>
  <h1 class="err-title">Không tìm thấy trang</h1>
  <p class="err-desc">Xin lỗi, trang bạn đang tìm kiếm không tồn tại hoặc đã được di chuyển.</p>
  <form class="err-search" action="{{ url('/') }}" method="GET">
    <input name="se" type="text" placeholder="Tìm rau, củ, quả…" autocomplete="off">
    <button type="submit">Tìm kiếm</button>
  </form>
  <p class="err-or">Hoặc</p>
  <a href="{{ url('/') }}" class="err-home">Quay về trang chủ</a>
</div>
@endsection
