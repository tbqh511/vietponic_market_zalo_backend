@extends('layouts.main')

@section('content')
<div class="card">
    <div class="card-header d-flex justify-content-between align-items-center">
        <h4 class="mb-0"><i class="fas fa-chart-bar"></i> Báo cáo tồn kho</h4>
        <a href="{{ route('inventory.index') }}" class="btn btn-secondary btn-sm">
            <i class="fas fa-arrow-left"></i> Quay lại
        </a>
    </div>
    <div class="card-body">
        {{-- Date filter --}}
        <form method="GET" class="mb-4">
            <div class="row g-2 align-items-end">
                <div class="col-sm-3">
                    <label class="form-label small">Từ ngày</label>
                    <input type="date" name="from" class="form-control" value="{{ $from }}">
                </div>
                <div class="col-sm-3">
                    <label class="form-label small">Đến ngày</label>
                    <input type="date" name="to" class="form-control" value="{{ $to }}">
                </div>
                <div class="col-sm-2">
                    <button type="submit" class="btn btn-primary w-100">Lọc</button>
                </div>
                @if($from || $to)
                <div class="col-sm-2">
                    <a href="{{ route('inventory.report') }}" class="btn btn-outline-secondary w-100">Xoá lọc</a>
                </div>
                @endif
            </div>
        </form>

        {{-- Summary cards --}}
        <div class="row g-3 mb-4">
            <div class="col-sm-4">
                <div class="card text-center border-success">
                    <div class="card-body">
                        <div class="fs-2 fw-bold text-success">{{ number_format($report['totals']['total_imports']) }}</div>
                        <div class="text-muted">Tổng nhập kho</div>
                    </div>
                </div>
            </div>
            <div class="col-sm-4">
                <div class="card text-center border-danger">
                    <div class="card-body">
                        <div class="fs-2 fw-bold text-danger">{{ number_format($report['totals']['total_exports']) }}</div>
                        <div class="text-muted">Tổng xuất kho</div>
                    </div>
                </div>
            </div>
            <div class="col-sm-4">
                <div class="card text-center border-info">
                    <div class="card-body">
                        <div class="fs-2 fw-bold text-info">{{ number_format($report['totals']['total_movements']) }}</div>
                        <div class="text-muted">Tổng giao dịch</div>
                    </div>
                </div>
            </div>
        </div>

        {{-- Per-product table --}}
        @if($report['products']->isEmpty())
            <p class="text-muted text-center py-4">Không có dữ liệu trong khoảng thời gian này.</p>
        @else
            <div class="table-responsive">
                <table class="table table-hover">
                    <thead class="table-light">
                        <tr>
                            <th>Sản phẩm</th>
                            <th class="text-center text-success">Nhập</th>
                            <th class="text-center text-danger">Xuất</th>
                            <th class="text-center text-warning">Điều chỉnh</th>
                            <th class="text-center">Biến động ròng</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($report['products'] as $row)
                        <tr>
                            <td class="fw-semibold">{{ $row['product_name'] }}</td>
                            <td class="text-center text-success">+{{ number_format($row['imports']) }}</td>
                            <td class="text-center text-danger">-{{ number_format($row['exports']) }}</td>
                            <td class="text-center text-warning">{{ $row['adjustments'] >= 0 ? '+' : '' }}{{ number_format($row['adjustments']) }}</td>
                            <td class="text-center fw-bold {{ $row['net_change'] >= 0 ? 'text-success' : 'text-danger' }}">
                                {{ $row['net_change'] >= 0 ? '+' : '' }}{{ number_format($row['net_change']) }}
                            </td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </div>
</div>
@endsection
