@extends('layouts.main')

@section('content')
    <div class="card">
        <div class="card-header d-flex justify-content-between align-items-center">
            <h4>Trạm lấy hàng</h4>
            <a href="{{ route('zalo-stations.create') }}" class="btn btn-primary">Thêm trạm</a>
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
                        <th>Địa chỉ</th>
                        <th>Vĩ độ</th>
                        <th>Kinh độ</th>
                        <th>VTP</th>
                        <th>Thao tác</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($stations as $station)
                        <tr>
                            <td>{{ $station->id }}</td>
                            <td>{{ $station->name }}</td>
                            <td>
                                @if($station->image)
                                    <img src="{{ asset($station->image) }}" alt="" style="height:40px" />
                                @endif
                            </td>
                            <td>{{ $station->address }}</td>
                            <td>{{ $station->lat }}</td>
                            <td>{{ $station->lng }}</td>
                            <td>
                                @if($station->vtp_province_id && $station->vtp_district_id)
                                    <span class="badge bg-success">Đã cấu hình</span>
                                @else
                                    <span class="badge bg-danger">Thiếu cấu hình</span>
                                @endif
                            </td>
                            <td>
                                <a href="{{ route('zalo-stations.edit', $station->id) }}" class="btn btn-sm btn-secondary">Sửa</a>
                                <form action="{{ route('zalo-stations.destroy', $station->id) }}" method="POST" style="display:inline-block"
                                      onsubmit="return confirm('Xoá trạm này?')">
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