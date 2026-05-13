<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('title', 'Vietponics - Rau sạch thủy canh Đà Lạt')</title>
    <meta name="description" content="@yield('description', 'Rau sạch thủy canh trồng tại Đà Lạt, giao tận nhà')">
    <link rel="shortcut icon" href="{{ asset('images/favicon.ico') }}">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Be+Vietnam+Pro:ital,wght@0,300;0,400;0,500;0,600;0,700;0,800;1,400;1,500&family=Lora:ital,wght@0,600;0,700;1,600;1,700&display=swap" rel="stylesheet">
    @stack('styles')
</head>
<body>
    @include('frontends.vietponics_header')
    @yield('content')
    @include('frontends.vietponics_footer')
    @stack('scripts')
</body>
</html>
