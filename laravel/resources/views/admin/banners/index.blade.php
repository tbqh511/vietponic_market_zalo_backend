@extends('layouts.main')

@section('content')
    <div class="card">
        <div class="card-header d-flex justify-content-between align-items-center">
            <h4>Banners</h4>
            <a href="{{ route('banners.create') }}" class="btn btn-primary">New Banner</a>
        </div>
        <div class="card-body">
            @if(session('success'))
                <div class="alert alert-success">{{ session('success') }}</div>
            @endif
            <table class="table">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Image</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($banners as $b)
                        <tr>
                            <td>{{ $b->id }}</td>
                            <td>@if($b->image)<img src="{{ $b->image }}" style="height:40px" />@endif</td>
                            <td>
                                <a href="{{ route('banners.edit', $b->id) }}" class="btn btn-sm btn-secondary">Edit</a>
                                <form action="{{ route('banners.destroy', $b->id) }}" method="POST" style="display:inline-block" onsubmit="return confirm('Delete this banner?')">
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
