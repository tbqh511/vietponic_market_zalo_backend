@extends('frontends.master')
@section('content')
<!-- content -->
<div class="content">
    <!--  section  -->
    {{-- <section class="hidden-section single-par2  " data-scrollax-parent="true">
        <div class="bg-wrap bg-parallax-wrap-gradien">
            <div class="bg par-elem " data-bg="images/bg/1.jpg" data-scrollax="properties: { translateY: '30%' }"></div>
        </div>
        <div class="container">
            <div class="section-title center-align big-title">
                <h2><span>Listings Without Map</span></h2>
                <h4>Lorem ipsum dolor sit amet, consectetur adipiscing elit. Ut nec tincidunt arcu, sit amet fermentum
                    sem.</h4>
            </div>
            <div class="scroll-down-wrap">
                <div class="mousey">
                    <div class="scroller"></div>
                </div>
                <span>Scroll Down To Discover</span>
            </div>
        </div>
    </section> --}}
    <!--  section  end-->
    <!-- breadcrumbs-->
    {{-- <div class="breadcrumbs fw-breadcrumbs sp-brd fl-wrap">
        <div class="container">
            <div class="breadcrumbs-list">
                <a href="#">Home</a><a href="#">Listings</a> <span>New York</span>
            </div>
            <div class="share-holder hid-share">
                <a href="#" class="share-btn showshare sfcs"> <i class="fas fa-share-alt"></i> Share </a>
                <div class="share-container  isShare"></div>
            </div>
        </div>
    </div> --}}
    <!-- breadcrumbs end -->
    <!-- col-list-wrap -->
    <section class="gray-bg small-padding ">
        <div class="container">
            <div class="mob-nav-content-btn  color-bg show-list-wrap-search ntm fl-wrap">Tìm kiếm thêm</div>
            <!-- list-searh-input-wrap-->
            <div class="list-searh-input-wrap box_list-searh-input-wrap lws_mobile fl-wrap">
                <div class="list-searh-input-wrap-title fl-wrap"><i class="far fa-sliders-h"></i><span>Bộ Lọc Tìm
                        Kiếm</span></div>
                <div class="custom-form fl-wrap">
                    <form id="searchForm" action="{{ route('properties.index') }}" method="GET">
                        @csrf
                        <div class="row">
                            <!-- Ô nhập liệu tìm kiếm -->
                            <div class="col-sm-3">
                                <div class="listsearch-input-item">
                                    <input name="text" type="text" placeholder="Tìm BDS" value="{{ request()->input('text') }}" />
                                </div>
                            </div>
                            <!-- Kết thúc ô nhập liệu tìm kiếm -->
                            <!-- Ô lựa chọn trạng thái -->
                            <div class="col-sm-3">
                                <div class="listsearch-input-item">
                                    <select name="propery_type" data-placeholder="Tình trạng"
                                        class="chosen-select on-radius no-search-select">
                                        <option value="">Cho thuê & Bán</option>
                                        <option value="0" {{ request()->input('propery_type') == '0' ? 'selected' :
                                            ''}}>Bán</option>
                                        <option value="1" {{ request()->input('propery_type') == '1' ? 'selected' :
                                            ''}}>Cho Thuê</option>
                                    </select>
                                </div>
                                {{-- <div class="main-search-input-item">
                                    <select name="propery_type" data-placeholder="Cho thuê & Bán"
                                        class="chosen-select no-search-select">
                                        <option value="">Cho thuê & Bán</option>
                                        <option value="1">Cho Thuê</option>
                                        <option value="0">Bán</option>
                                    </select>
                                </div> --}}
                            </div>
                            <!-- Kết thúc ô lựa chọn trạng thái -->
                            <!-- Ô lựa chọn thành phố -->
                            <div class="col-sm-3">
                                <div class="listsearch-input-item">
                                    <select name="ward" data-placeholder="Phường Xã"
                                        class="chosen-select on-radius no-search-select">
                                        <option value="">Phường Xã</option>
                                        @foreach ($locationsWards as $locationsWard)
                                        <option value="{{$locationsWard->code}}" {{ request()->input('ward') ==
                                            $locationsWard->code ? 'selected' : '' }}>
                                            {{$locationsWard->full_name}}
                                        </option>
                                        @endforeach
                                    </select>
                                </div>
                            </div>

                            <!-- Kết thúc ô lựa chọn thành phố -->
                            <!-- Ô lựa chọn Duong -->
                            <div class="col-sm-3">
                                <div class="listsearch-input-item">
                                    <select name="street" data-placeholder="All Categories" class="chosen-select">
                                        <option value="">Đường</option>
                                        @foreach ($locationsStreets as $locationsStreet)
                                        <option value="{{$locationsStreet->code}}" {{ request()->input('street') ==
                                            $locationsStreet->code ? 'selected' : '' }}>
                                            {{$locationsStreet->street_name}}
                                        </option>
                                        @endforeach
                                    </select>
                                </div>
                            </div>

                            <!-- Kết thúc ô lựa chọn thành phố -->
                            {{-- <ul class="list">
                                <li data-value="" class="option selected focus">Phường Xã</li>
                                <li data-value="24784" class="option">Phường 1</li>
                                <li data-value="24781" class="option">Phường 2</li>
                                <li data-value="24802" class="option">Phường 3</li>
                                <li data-value="24793" class="option">Phường 4</li>
                                <li data-value="24790" class="option">Phường 5</li>
                                <li data-value="24787" class="option">Phường 6</li>
                                <li data-value="24769" class="option">Phường 7</li>
                                <li data-value="24772" class="option">Phường 8</li>
                                <li data-value="24778" class="option">Phường 9</li>
                                <li data-value="24796" class="option">Phường 10</li>
                                <li data-value="24799" class="option">Phường 11</li>
                                <li data-value="24775" class="option">Phường 12</li>
                                <li data-value="24805" class="option">Xã Xuân Thọ</li>
                                <li data-value="24808" class="option">Xã Tà Nung</li>
                                <li data-value="24810" class="option">Xã Trạm Hành</li>
                                <li data-value="24811" class="option">Xã Xuân Trường</li>
                            </ul> --}}
                            <div class="clearfix"></div>
                            <!-- Ô lựa chọn danh mục -->
                            <div class="col-sm-3">
                                <div class="listsearch-input-item">
                                    <select name="category" data-placeholder="Loại BDS"
                                        class="chosen-select on-radius no-search-select">
                                        <option value="">Loại BDS</option>
                                        @foreach ($categories as $categorie)
                                        <option value="{{ $categorie->category }}" {{ request()->input('category') ==
                                            $categorie->category ? 'selected' : '' }}>
                                            {{ $categorie->category }}
                                        </option>
                                        @endforeach
                                    </select>
                                </div>
                            </div>
                            <!-- Kết thúc ô lựa chọn danh mục -->
                            <!-- Ô lựa chọn giá -->
                            <div class="col-sm-6">
                                <div class="listsearch-input-item">
                                    <div class="price-range-item fl-wrap">
                                        <span class="pr_title">Giá:</span>
                                        <input type="text" class="price-range-double" data-min="100000000"
                                            data-max="{{config('global.max_price')}}" name="price-range2"
                                            data-step="100000000" value="{{ request()->input('price-range2') }}"
                                            max_postfix="+">
                                    </div>
                                </div>
                            </div>
                            <!-- Kết thúc ô lựa chọn giá -->
                            <!-- Nút tìm kiếm -->
                            <div class="col-sm-3">
                                <div class="listsearch-input-item">
                                    <button type="submit" class="btn color-bg fw-btn float-btn small-btn">Tìm
                                        kiếm</button>
                                </div>
                            </div>
                            <!-- Kết thúc nút tìm kiếm -->
                        </div>
                        <div class="clearfix"></div>
                        <div class="hidden-listing-filter fl-wrap">
                            <div class="row">
                                <!-- listsearch-input-item -->
                                <div class="col-sm-3">
                                    <div class="listsearch-input-item">
                                        <label for="legal">Pháp lý</label>
                                        <select name="legal" data-placeholder="Chọn pháp lý"
                                            class="chosen-select on-radius no-search-select">
                                            <option value="">Chọn pháp lý</option>
                                            @foreach ($legals as $key => $value)
                                            <option value="{{ $value }}" {{ Request::input('legal')==$value ? 'selected'
                                                : '' }}>{{ $value }}</option>
                                            @endforeach
                                        </select>
                                    </div>
                                </div>
                                <!-- listsearch-input-item end-->

                                <!-- listsearch-input-item -->
                                <div class="col-sm-2">
                                    <div class="listsearch-input-item">
                                        <label for="direction">Hướng</label>
                                        <select name='direction' data-placeholder="Chọn hướng"
                                            class="chosen-select on-radius no-search-select">
                                            <option value="">Chọn hướng</option>
                                            @foreach ($directions as $key => $value)
                                            <option value="{{ $value }}" {{ Request::input('direction')==$value
                                                ? 'selected' : '' }}>{{ $value }}</option>
                                            @endforeach
                                        </select>
                                    </div>
                                </div>
                                <!-- listsearch-input-item end-->
                                <!-- listsearch-input-item -->
                                <div class="col-sm-3">
                                    <div class="listsearch-input-item">
                                        <label>Diện tích (m²)</label>
                                        <div class="price-rage-item pr-nopad fl-wrap">
                                            <input name="area" type="text" class="area-range-double" data-min="1"
                                                data-max="{{config('global.max_area')}}" data-step="10" data-prefix=""
                                                value="{{ request()->input('area') }}">
                                        </div>
                                    </div>
                                </div>
                                <!-- listsearch-input-item -->

                                <!-- listsearch-input-item -->
                                <div class="col-sm-2">
                                    <div class="listsearch-input-item">
                                        <label>Số tầng</label>
                                        <select name='number_floor' data-placeholder="Số tầng"
                                            class="chosen-select on-radius no-search-select">
                                            <option value="0">Chọn số tầng</option>
                                            @for ($i = 1; $i <= 10; $i++) <option value="{{ $i }}" {{ request()->
                                                input('number_floor') == $i ?
                                                'selected' : ''}}>
                                                {{ $i == 10 ? '10+' : $i }}
                                                </option>
                                                @endfor
                                        </select>
                                    </div>
                                </div>
                                <!-- listsearch-input-item end-->
                                <!-- listsearch-input-item -->
                                <div class="col-sm-2">
                                    <div class="listsearch-input-item">
                                        <label>Số phòng</label>
                                        <select name='number_room' data-placeholder="Số phòng ngủ"
                                            class="chosen-select on-radius no-search-select">
                                            <option value="0">Chọn số phòng</option>
                                            @for ($i = 1; $i <= 10; $i++) <option value="{{ $i }}" {{ request()->
                                                input('number_room') == $i ? 'selected' : ''}}>{{ $i }}</option>
                                                @endfor
                                                <option value="10" {{ request()->input('number_room') == '10' ?
                                                    'selected' : ''}}>10+</option>
                                        </select>
                                    </div>
                                </div>
                                <!-- listsearch-input-item end-->
                                <!-- listsearch-input-item -->
                                {{-- <div class="col-sm-2">
                                    <div class="listsearch-input-item">
                                        <label>Mã bất động sản</label>
                                        <input type="text" onClick="this.select()" placeholder="Mã" value="" />
                                    </div>
                                </div> --}}
                                <!-- listsearch-input-item end-->
                            </div>
                            <div class="clearfix"></div>
                            <!-- listsearch-input-item-->
                            {{-- <div class="listsearch-input-item">
                                <label>Tiện ích</label>
                                <div class=" fl-wrap filter-tags">
                                    <ul class="no-list-style">
                                        <li>
                                            <input id="check-aa" type="checkbox" name="check">
                                            <label for="check-aa">Thang máy</label>
                                        </li>
                                        <li>
                                            <input id="check-b" type="checkbox" name="check">
                                            <label for="check-b">Phòng giặt</label>
                                        </li>
                                        <li>
                                            <input id="check-c" type="checkbox" name="check" checked>
                                            <label for="check-c">Bếp đầy đủ tiện nghi</label>
                                        </li>
                                        <li>
                                            <input id="check-d" type="checkbox" name="check">
                                            <label for="check-d">Điều hòa</label>
                                        </li>
                                        <li>
                                            <input id="check-d2" type="checkbox" name="check" checked>
                                            <label for="check-d2">Bãi đậu xe</label>
                                        </li>
                                        <li>
                                            <input id="check-d3" type="checkbox" name="check" checked>
                                            <label for="check-d3">Bể bơi</label>
                                        </li>
                                        <li>
                                            <input id="check-d4" type="checkbox" name="check">
                                            <label for="check-d4">Phòng gym</label>
                                        </li>
                                        <li>
                                            <input id="check-d5" type="checkbox" name="check">
                                            <label for="check-d5">An ninh</label>
                                        </li>
                                        <li>
                                            <input id="check-d6" type="checkbox" name="check">
                                            <label for="check-d6">Garage kết nối</label>
                                        </li>
                                        <li>
                                            <input id="check-d7" type="checkbox" name="check">
                                            <label for="check-d7">Sân sau</label>
                                        </li>
                                        <li>
                                            <input id="check-d8" type="checkbox" name="check">
                                            <label for="check-d8">Lò sưởi</label>
                                        </li>
                                        <li>
                                            <input id="check-d9" type="checkbox" name="check">
                                            <label for="check-d9">Bảo vệ cửa sổ</label>
                                        </li>
                                    </ul>
                                </div>
                            </div> --}}
                            <!-- listsearch-input-item end-->
                        </div>
                    </form>
                </div>
                <div class="more-filter-option-wrap">
                    <div class="more-filter-option-btn more-filter-option act-hiddenpanel"> <span>Tìm kiếm nâng
                            cao</span> <i class="fas fa-caret-down"></i></div>
                    <div class="reset-form reset-btn"> <i class="far fa-sync-alt"></i> Đặt lại bộ lọc</div>
                </div>
            </div>
            <!-- list-searh-input-wrap end-->
           
            <!-- list-main-wrap-header-->
            <div class="list-main-wrap-header box-list-header fl-wrap">
                <!-- list-main-wrap-title-->
                <div class="list-main-wrap-title">
                    <h2>{{ $searchResult }}
                        <strong>{{ $properties->total() }}</strong>
                    </h2>
                </div>
                <!-- list-main-wrap-title end-->
                <!-- list-main-wrap-opt-->
                <div class="list-main-wrap-opt">
                    <!-- price-opt-->
                    <!-- price-opt-->
                    <div class="price-opt">
                        <span class="price-opt-title">Sắp xếp theo:</span>
                        <div class="listsearch-input-item">
                            <select name="sort_status" class="chosen-select no-search-select">
                                <option value="">Bình thường</option>
                                <option value="view_count" {{ Request::input('sort_status')=='view_count' ? 'selected'
                                    : '' }}>Phổ biến</option>
                                {{-- <option>Điểm đánh giá trung bình</option> --}}
                                <option value="price_asc" {{ Request::input('sort_status')=='price_asc' ? 'selected'
                                    : '' }}>Giá: thấp đến cao</option>
                                <option value="price_desc" {{ Request::input('sort_status')=='price_desc' ? 'selected'
                                    : '' }}>Giá: cao đến thấp</option>
                            </select>
                        </div>
                    </div>
                    <!-- price-opt end-->
                    <!-- price-opt-->
                    {{-- <div class="grid-opt">
                        <ul class="no-list-style">
                            <li class="grid-opt_act"><span class="two-col-grid act-grid-opt tolt"
                                    data-microtip-position="bottom" data-tooltip="Xem dạng lưới"><i
                                        class="far fa-th"></i></span></li>
                            <li class="grid-opt_act"><span class="one-col-grid tolt" data-microtip-position="bottom"
                                    data-tooltip="Xem dạng danh sách"><i class="far fa-list"></i></span></li>
                        </ul>
                    </div> --}}
                    <!-- price-opt end-->
                </div>
                <!-- list-main-wrap-opt end-->
            </div>
            <!-- list-main-wrap-header end-->
            <!-- listing-item-wrap-->
            <div class="listing-item-container three-columns-grid  box-list_ic fl-wrap">
                @foreach($properties as $productItem )
                <!-- listing-item -->
                @include('frontends.components.product_card',['productCard'=>$productItem ])
                <!-- listing-item end-->
                @endforeach
            </div>
            <!-- listing-item-wrap end-->
            <!-- pagination-->
            {{-- <div class="pagination">
                @if ($properties->onFirstPage())
                <a href="#" class="prevposts-link disabled"><i class="fa fa-caret-left"></i></a>
                @else
                <a href="{{ $properties->previousPageUrl() }}" class="prevposts-link"><i class="fa fa-caret-left"></i></a>
                @endif

                @foreach ($properties->getUrlRange(1, $properties->lastPage()) as $page => $url)
                @if ($page == $properties->currentPage())
                <a href="#" class="current-page">{{ $page }}</a>
                @else
                <a href="{{ $url }}">{{ $page }}</a>
                @endif
                @endforeach

                @if ($properties->hasMorePages())
                <a href="{{ $properties->nextPageUrl() }}" class="nextposts-link"><i class="fa fa-caret-right"></i></a>
                @else
                <a href="#" class="nextposts-link disabled"><i class="fa fa-caret-right"></i></a>
                @endif
            </div> --}}
            <div class="pagination">
                @if ($properties->previousPageUrl())
                    <a href="{{ $properties->previousPageUrl() }}" class="prevposts-link"><i class="fa fa-caret-left"></i></a>
                @else
                    <a href="#" class="prevposts-link disabled"><i class="fa fa-caret-left"></i></a>
                @endif
            
                @foreach ($properties->getUrlRange(1, $properties->lastPage()) as $page => $url)
                    @if ($page == $properties->currentPage())
                        <a href="#" class="current-page">{{ $page }}</a>
                    @else
                        <a href="{{ $url }}">{{ $page }}</a>
                    @endif
                @endforeach
            
                @if ($properties->nextPageUrl())
                    <a href="{{ $properties->nextPageUrl() }}" class="nextposts-link"><i class="fa fa-caret-right"></i></a>
                @else
                    <a href="#" class="nextposts-link disabled"><i class="fa fa-caret-right"></i></a>
                @endif
            </div>
            
            <!-- pagination end-->
        </div>
    </section>
    <div class="limit-box fl-wrap"></div>
</div>
<!-- end content -->
@endsection