@extends('frontends.master')

@section('content')
<div class="content">
    <!--  section  -->
    @include('frontends.components.home_slider',['locationsStreets' => $locationsStreets, 'locationsWards' => $locationsWards, 'categories' => $categories])
    <!--  section  end-->
    <!-- breadcrumbs-->
    @include('frontends.components.home_breadcrumb',['title'=>"Trang chủ"])
    <!-- breadcrumbs end -->
    <!-- section -->
    @include('frontends.components.home_products_grid', ['newestProducts'=> $newestProducts])
    <!-- section end-->
    <!-- section -->
    @include('frontends.components.home_about_wrap')
    <!-- section end-->
    <!-- section  -->
    @include('frontends.components.home_explore_place',['locationsWards' => $locationsWards])
    <!--section end-->
    <!-- section -->
    @include('frontends.components.home_agent_list',['agents' => $agents])
    <!-- section end-->
    <!-- section -->
    @include('frontends.components.home_report_info',['infos' => $infos])
    <!-- section end-->
    <!-- section -->
    @include('frontends.components.home_client_say')
    <!-- section end-->
</div>
@endsection
