@extends('layouts.main')

@section('content')
    <div class="card">
        <div class="card-header d-flex justify-content-between align-items-center">
            <h4>Zalo Stations</h4>
            <a href="{{ route('zalo-stations.create') }}" class="btn btn-primary">New Station</a>
        </div>
        <div class="card-body">
            @if(session('success'))
                <div class="alert alert-success">{{ session('success') }}</div>
            @endif
            <table class="table">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Name</th>
                        <th>Image</th>
                        <th>Address</th>
                        <th>Lat</th>
                        <th>Lng</th>
                        <th>Actions</th>
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
                                <a href="{{ route('zalo-stations.edit', $station->id) }}" class="btn btn-sm btn-secondary">Edit</a>
                                <form action="{{ route('zalo-stations.destroy', $station->id) }}" method="POST" style="display:inline-block"
                                      onsubmit="return confirm('Delete this station?')">
                                    @csrf
                                    @method('DELETE')
                                    <button class="btn btn-sm btn-danger">Delete</button>
                                </form>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>
@endsection