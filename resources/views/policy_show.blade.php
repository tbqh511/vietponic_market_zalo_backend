@extends('frontends.vietponics_master')

@section('title', $policy->title . ' - Vietponics')
@section('description', 'Vietponics - ' . $policy->title)

@push('styles')
<style>
  .policy-wrap {
    max-width: 880px;
    margin: 0 auto;
    padding: clamp(2rem, 5vw, 4rem) clamp(1rem, 4vw, 2rem) clamp(3rem, 6vw, 5rem);
  }
  .policy-breadcrumb {
    font-size: 13px;
    color: var(--text-light);
    margin-bottom: 14px;
  }
  .policy-breadcrumb a { color: var(--text-muted); text-decoration: none; }
  .policy-breadcrumb a:hover { color: var(--primary-dark); }
  .policy-title {
    font-family: 'Lora', Georgia, serif;
    font-size: clamp(26px, 4.5vw, 38px);
    font-weight: 700;
    color: var(--dark);
    line-height: 1.25;
    margin-bottom: 8px;
  }
  .policy-meta {
    font-size: 13px;
    color: var(--text-light);
    margin-bottom: 28px;
    padding-bottom: 22px;
    border-bottom: 1px solid var(--border);
  }
  .policy-body {
    font-size: 15.5px;
    line-height: 1.85;
    color: var(--text);
  }
  .policy-body h1, .policy-body h2, .policy-body h3, .policy-body h4 {
    font-family: 'Lora', Georgia, serif;
    color: var(--dark);
    margin: 1.6em 0 .6em;
    line-height: 1.35;
  }
  .policy-body h2 { font-size: 22px; }
  .policy-body h3 { font-size: 18px; }
  .policy-body p { margin: 0 0 1.1em; }
  .policy-body ul, .policy-body ol { margin: 0 0 1.1em; padding-left: 1.5em; }
  .policy-body li { margin-bottom: .4em; }
  .policy-body a { color: var(--primary-dark); text-decoration: underline; }
  .policy-body img { max-width: 100%; height: auto; border-radius: var(--r); }
  .policy-body table { width: 100%; border-collapse: collapse; margin: 0 0 1.2em; }
  .policy-body th, .policy-body td { border: 1px solid var(--border); padding: 8px 12px; text-align: left; }
  .policy-body th { background: var(--primary-pale); }
  .policy-empty { color: var(--text-light); font-style: italic; }
</style>
@endpush

@section('content')
<div class="policy-wrap">
  <div class="policy-breadcrumb">
    <a href="{{ url('/') }}">Trang chủ</a> &nbsp;·&nbsp; {{ $policy->title }}
  </div>
  <h1 class="policy-title">{{ $policy->title }}</h1>
  <div class="policy-meta">Cập nhật lần cuối: {{ $policy->updated_at->format('d/m/Y') }}</div>
  <div class="policy-body">
    @if(filled($policy->content))
      {!! $policy->content !!}
    @else
      <p class="policy-empty">Nội dung đang được cập nhật.</p>
    @endif
  </div>
</div>
@endsection
