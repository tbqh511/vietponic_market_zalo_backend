@extends('frontends.master')
@section('content')
<!-- content -->
<div class="content">
    <section class="hidden-section   single-hero-section" data-scrollax-parent="true" id="sec1">
        <div class="bg-wrap bg-parallax-wrap-gradien">
            <div class="bg par-elem " data-bg="{{$property->title_image}}"
                data-scrollax="properties: { translateY: '30%' }"></div>
        </div>
        <div class="container">
            <!--  list-single-opt_header-->
            <div class="list-single-opt_header fl-wrap">
                <ul class="list-single-opt_header_cat">
                    <li><a href="{{ route('properties.index') }}" class="cat-opt blue-bg">{{
                            $property->category->category }}</a></li>
                    <li><a href="{{ route('properties.index') }}" class="cat-opt color-bg">{{
                            $property->created_at->diffForHumans() }}</a></li>
                </ul>
            </div>
            <!--  list-single-opt_header end -->
            <!--  list-single-header-item-->
            <div class="list-single-header-item no-bg-list_sh fl-wrap">
                <div class="row">
                    <div class="col-md-12">
                        <h1>{{ $property->title_by_address }} <span class="verified-badge tolt"
                                data-microtip-position="bottom" data-tooltip="Đã xác nhận"><i
                                    class="fas fa-check"></i></span></h1>
                        <div class="geodir-category-location fl-wrap">
                            <a href="#"><i class="fas fa-map-marker-alt"></i> {{$property->address_location}}</a>
                            {{-- <div class="listing-rating card-popup-rainingvis" data-starrating2="4"><span
                                    class="re_stars-title">Good</span></div> --}}
                        </div>
                        <div class="share-holder hid-share">
                            {{-- <a href="#" class="share-btn showshare sfcs"> <i class="fas fa-share-alt"></i> Share
                            </a>
                            <div class="share-container  isShare"></div> --}}
                        </div>
                    </div>
                </div>
                <div class="list-single-header-footer fl-wrap">
                    <div class="list-single-header-price" data-propertyprise="50500">
                        <strong>Giá:</strong>{{$property->formatted_prices}}
                    </div>
                    <div class="list-single-header-date"><span>Diện tích:</span>{{
                        $property->area }} m²</div>
                    <div class="list-single-stats">
                        <ul class="no-list-style">
                            <li><span class="viewed-counter"><i class="fas fa-eye"></i> Lượt xem -
                                    {{$property->total_click}} </span></li>
                            {{-- <li><span class="bookmark-counter"><i class="fas fa-heart"></i> Bookmark - 24 </span>
                            </li> --}}
                        </ul>
                    </div>
                </div>
            </div>
        </div>
    </section>
    <!-- breadcrumbs-->
    @include('frontends.components.home_breadcrumb', [
    'title' => 'BDS',
    'nodes' => [
    ['title' => 'Trang chủ', 'url' => route('index')],
    ['title' => $property->ward->full_name, 'url' => route('properties.index', ['ward' => $property->ward_code])],
    ['title' => $property->street->street_name, 'url' => route('properties.index', ['street' =>
    $property->street_code])],
    ]
    ])
    <!-- breadcrumbs end -->
    <div class="gray-bg small-padding fl-wrap">
        <div class="container">
            <div class="row">
                <!--  listing-single content -->
                <div class="col-md-8">
                    <div class="list-single-main-wrapper fl-wrap">
                        <!--  scroll-nav-wrap -->
                        <div class="scroll-nav-wrap">
                            <nav class="scroll-nav scroll-init fixed-column_menu-init">
                                <ul class="no-list-style">
                                    <li><a class="act-scrlink" href="#sec1"><i
                                                class="fal fa-home-lg-alt"></i></a><span>Nội dung chính</span></li>
                                    <li><a href="#sec2"><i class="fal fa-image"></i></a><span>Hình ảnh</span></li>
                                    <li><a href="#sec3"><i class="fal fa-info"></i> </a><span>Chi tiết</span></li>
                                    <li><a href="#sec4"><i class="fal fa-bed"></i></a><span>Phòng</span></li>
                                    <li><a href="#sec5"><i class="fal fa-video"></i></a><span>Video</span></li>
                                    <li><a href="#sec6"><i class="fal fa-map-pin"></i></a><span>Vị trí</span></li>
                                    <li><a href="#sec7"><i class="fal fa-comment-alt-lines"></i></a><span>Đánh
                                            giá</span></li>
                                </ul>

                                <div class="progress-indicator">
                                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="-1 -1 34 34">
                                        <circle cx="16" cy="16" r="15.9155" class="progress-bar__background" />
                                        <circle cx="16" cy="16" r="15.9155" class="progress-bar__progress
                                            js-progress-bar" />
                                    </svg>
                                </div>
                            </nav>
                        </div>
                        <!--  scroll-nav-wrap end-->
                        <div class="list-single-main-media fl-wrap" id="sec2">
                            <!-- gallery-items -->
                            <div class="gallery-items grid-small-pad list-single-gallery three-coulms lightgallery">
                                @foreach ($property->getGalleryAttribute() as $image)
                                <div class="gallery-item">
                                    <div class="grid-item-holder">
                                        <div class="box-item">
                                            <img src="{{ $image['image_url'] }}"
                                                alt="{{ $property->title_by_address }}">
                                            <a href="{{ $image['image_url'] }}" class="gal-link popup-image"><i
                                                    class="fa fa-search"></i></a>
                                        </div>
                                    </div>
                                </div>
                                @endforeach
                            </div>
                            <!-- end gallery items -->
                        </div>
                        <div class="list-single-facts fl-wrap">
                            <!-- inline-facts -->
                            <div class="inline-facts-wrap">
                                <div class="inline-facts">
                                    <i class="fas fa-home-lg"></i>
                                    <h6>
                                        {{$property->type}}
                                    </h6>
                                    <span>{{$property->category->category}}</span>
                                </div>
                            </div>
                            <!-- inline-facts end -->
                            <!-- inline-facts  -->
                            <div class="inline-facts-wrap">
                                <div class="inline-facts">
                                    <i class="fas fa-arrows-alt"></i>
                                    <h6>{{config('global.area_title')}}</h6>
                                    <span>{{$property->area}} m²</span>
                                </div>
                            </div>
                            <!-- inline-facts end -->
                            <!-- inline-facts -->
                            <div class="inline-facts-wrap">
                                <div class="inline-facts">
                                    <i class="fas fa-door-open"></i>
                                    <h6>{{config('global.number_room_title')}}</h6>
                                    <span>{{$property->number_room}}</span>
                                </div>
                            </div>
                            <!-- inline-facts end -->
                            <!-- inline-facts -->
                            <div class="inline-facts-wrap">
                                <div class="inline-facts">
                                    <i class="fas fa-layer-group"></i>
                                    <h6>{{config('global.number_floor_title')}}</h6>
                                    <span>{{$property->number_floor}}</span>
                                </div>
                            </div>
                            <!-- inline-facts end -->
                        </div>
                        <div class="list-single-main-container fl-wrap" id="sec3">
                            <!-- list-single-main-item -->
                            <div class="list-single-main-item fl-wrap">
                                <div class="list-single-main-item-title">
                                    <h3>Thông tin chi tiết</h3>
                                </div>
                                <div class="list-single-main-item_content fl-wrap">
                                    <p>{{$property->description}}</p>
                                </div>
                            </div>
                            <!-- list-single-main-item end -->
                            <!-- list-single-main-item -->
                            <div class="list-single-main-item fl-wrap">
                                <div class="list-single-main-item-title">
                                    <h3>Chi tiết</h3>
                                </div>
                                {{-- <div class="list-single-main-item_content fl-wrap">
                                    <div class="details-list">
                                        <ul>
                                            <li><span>Mã Bất động sản:</span>154</li>
                                            <li><span>Diện tích Lô đất:</span>850 m2</li>
                                            <li><span>Phòng tắm:</span>4</li>
                                            <li><span>Phòng:</span>8</li>
                                            <li><span>Phòng ngủ:</span>2</li>
                                            <li><span>Diện tích Gara:</span>2 xe hơi</li>
                                            <li><span>Khả dụng từ ngày:</span>25.05.2020</li>
                                            <li><span>Giá:</span>$ 50.500,00</li>
                                            <li><span>Loại:</span>Căn hộ/Nhà</li>
                                        </ul>
                                    </div>
                                </div> --}}
                                <div class="list-single-main-item_content fl-wrap">
                                    <div class="details-list">
                                        <ul>
                                            @if($property->code)
                                            <li><span>{{config('global.code_title')}}:</span>{{$property->code}}</li>
                                            @endif
                                            @if($property->area)
                                            <li><span>{{config('global.area_title')}}:</span>{{$property->area}} m²</li>
                                            @endif
                                            @if($property->floor_area)
                                            <li><span>{{config('global.floor_area_title')}}:</span>{{$property->floor_area}}
                                            </li>
                                            @endif
                                            @if($property->legal)
                                            <li><span>{{config('global.legal_title')}}:</span>{{$property->legal}}</li>
                                            @endif
                                            @if($property->direction)
                                            <li><span>{{config('global.direction_title')}}:</span>{{$property->direction}}
                                            </li>
                                            @endif
                                            @if($property->road_width)
                                            <li><span>{{config('global.road_width_title')}}:</span>{{$property->road_width}}
                                                m
                                            </li>
                                            @endif
                                            @if($property->formatted_price_m2)
                                            <li><span>{{config('global.price_m2_title')}}:</span>{{
                                                $property->formatted_price_m2}}
                                            </li>
                                            @endif
                                            @if($property->number_floor)
                                            <li><span>{{config('global.number_floor_title')}}:</span>{{$property->number_floor}}
                                            </li>
                                            @endif
                                            @if($property->number_room)
                                            <li><span>{{config('global.number_room_title')}}:</span>{{$property->number_room}}
                                            </li>
                                            @endif
                                            @if($property->bathroom)
                                            <li><span>{{config('global.bathroom_title')}}:</span>{{$property->bathroom}}
                                            </li>
                                            @endif
                                            @if($property->garage)
                                            <li><span>{{config('global.garage_title')}}:</span>{{$property->garage}}
                                            </li>
                                            @endif
                                            @if($property->pool)
                                            <li><span>{{config('global.pool_title')}}:</span>{{$property->pool}}</li>
                                            @endif
                                            @if($property->furniture)
                                            <li><span>{{config('global.furniture_title')}}:</span>{{$property->furniture}}
                                            </li>
                                            @endif
                                            @if($property->construction_status)
                                            <li><span>{{config('global.construction_status_title')}}:</span>{{$property->construction_status}}
                                            </li>
                                            @endif
                                            @if($property->rental_period)
                                            <li><span>{{config('global.rental_period_title')}}:</span>{{$property->rental_period}}
                                            </li>
                                            @endif
                                        </ul>
                                    </div>
                                </div>
                            </div>
                            <!-- list-single-main-item end -->
                            <!--   list-single-main-item -->
                            {{-- <div class="list-single-main-item fl-wrap" id="sec4">
                                <div class="list-single-main-item-title fl-wrap">
                                    <h3>Available Rooms</h3>
                                </div>
                                <!--   rooms-container -->
                                <div class="rooms-container fl-wrap">
                                    <!--  rooms-item -->
                                    <div class="rooms-item fl-wrap">
                                        <div class="rooms-media">
                                            <img src="images/all/1.jpg" alt="">
                                            <div class="dynamic-gal more-photos-button color-bg"
                                                data-dynamicPath="[{'src': 'images/all/1.jpg'}, {'src': 'images/all/1.jpg'},{'src': 'images/all/1.jpg'}]">
                                                <i class="fas fa-camera"></i> <span>3 photos</span>
                                            </div>
                                        </div>
                                        <div class="rooms-details">
                                            <div class="rooms-details-header fl-wrap">
                                                <span class="rooms-area">44<strong> / sq ft</strong></span>
                                                <h3>Standard Family Room</h3>
                                                <h5>Additional Rooms: <span>Guest Bath</span></h5>
                                            </div>
                                            <p>Morbi varius, nulla sit amet rutrum elementum, est elit finibus tellus,
                                                ut tristique elit risus at metus. Lorem ipsum dolor sit amet,
                                                consectetur adipiscing elit.</p>
                                            <div class="facilities-list fl-wrap">
                                                <ul>
                                                    <li class="tolt" data-microtip-position="top"
                                                        data-tooltip="Air conditioner"><i class="fal fa-snowflake"></i>
                                                    </li>
                                                    <li class="tolt" data-microtip-position="top"
                                                        data-tooltip="Tv Inside"><i class="fal fa-tv"></i> </li>
                                                    <li class="tolt" data-microtip-position="top"
                                                        data-tooltip="Bed Inside"><i class="fal fa-bed"></i></li>
                                                    <li class="tolt" data-microtip-position="top"
                                                        data-tooltip="Fireplace"><i class="fal fa-fireplace"></i> </li>
                                                </ul>
                                            </div>
                                        </div>
                                    </div>
                                    <!--  rooms-item end -->
                                    <!--  rooms-item -->
                                    <div class="rooms-item fl-wrap">
                                        <div class="rooms-media">
                                            <img src="images/all/1.jpg" alt="">
                                            <div class="dynamic-gal more-photos-button color-bg"
                                                data-dynamicPath="[{'src': 'images/all/1.jpg'}, {'src': 'images/all/1.jpg'} ]">
                                                <i class="fas fa-camera"></i> <span>2 photos</span>
                                            </div>
                                        </div>
                                        <div class="rooms-details">
                                            <div class="rooms-details-header fl-wrap">
                                                <span class="rooms-area">18<strong> / sq ft</strong></span>
                                                <h3>Modern Bathroom</h3>
                                                <h5>Additional Rooms: <span>Sauna</span></h5>
                                            </div>
                                            <p>Morbi varius, nulla sit amet rutrum elementum, est elit finibus tellus,
                                                ut tristique elit risus at metus. Lorem ipsum dolor sit amet,
                                                consectetur adipiscing elit.</p>
                                            <div class="facilities-list fl-wrap">
                                                <ul>
                                                    <li class="tolt" data-microtip-position="top"
                                                        data-tooltip="Ceramic bath"><i class="fal fa-bath"></i></li>
                                                    <li class="tolt" data-microtip-position="top"
                                                        data-tooltip="Multifunctional Shower"><i
                                                            class="fal fa-shower"></i></li>
                                                    <li class="tolt" data-microtip-position="top" data-tooltip="Sauna">
                                                        <i class="fal fa-hot-tub"></i>
                                                    </li>
                                                    <li class="tolt" data-microtip-position="top"
                                                        data-tooltip="Panoramic windows"><i class="fal fa-columns"></i>
                                                    </li>
                                                </ul>
                                            </div>
                                        </div>
                                    </div>
                                    <!--  rooms-item end -->
                                    <!--  rooms-item -->
                                    <div class="rooms-item fl-wrap">
                                        <div class="rooms-media">
                                            <img src="images/all/1.jpg" alt="">
                                            <div class="dynamic-gal more-photos-button color-bg"
                                                data-dynamicPath="[{'src': 'images/all/1.jpg'}, {'src': 'images/all/1.jpg'},{'src': 'images/all/1.jpg'}]">
                                                <i class="fas fa-camera"></i> <span>3 photos</span>
                                            </div>
                                        </div>
                                        <div class="rooms-details">
                                            <div class="rooms-details-header fl-wrap">
                                                <span class="rooms-area">27<strong> / sq ft</strong></span>
                                                <h3>Spacious Kitchen</h3>
                                                <h5>Additional Rooms: <span>Pantry</span></h5>
                                            </div>
                                            <p>Morbi varius, nulla sit amet rutrum elementum, est elit finibus tellus,
                                                ut tristique elit risus at metus. Lorem ipsum dolor sit amet,
                                                consectetur adipiscing elit.</p>
                                            <div class="facilities-list fl-wrap">
                                                <ul>
                                                    <li class="tolt" data-microtip-position="top"
                                                        data-tooltip="Microwave"><i class="fal fa-washer"></i> </li>
                                                    <li class="tolt" data-microtip-position="top"
                                                        data-tooltip="Panoramic Windows"><i class="fal fa-columns"></i>
                                                    </li>
                                                    <li class="tolt" data-microtip-position="top"
                                                        data-tooltip="Refrigerator"><i
                                                            class="fal fa-temperature-frigid"></i></li>
                                                </ul>
                                            </div>
                                        </div>
                                    </div>
                                    <!--  rooms-item end -->
                                </div>
                                <!--   rooms-container end -->
                            </div> --}}
                            <!-- list-single-main-item end -->
                            <!-- list-single-main-item -->
                            {{-- <div class="list-single-main-item fl-wrap">
                                <div class="list-single-main-item-title">
                                    <h3>Floor Plans</h3>
                                </div>
                                <div class="accordion">
                                    <a class="toggle act-accordion" href="#"> First Floor Plan <strong>286 sq
                                            ft</strong> <span></span> </a>
                                    <div class="accordion-inner visible">
                                        <img src="images/plans/1.jpg" alt="">
                                        <p>Lorem ipsum dolor sit amet, consectetur adipiscing elit. Maecenas in pulvinar
                                            neque. Nulla finibus lobortis pulvinar. Donec a consectetur nulla. Nulla
                                            posuere sapien vitae lectus suscipit, et pulvinar nisi tincidunt. .</p>
                                    </div>
                                    <a class="toggle" href="#">Second Floor Plan <strong>280 sq ft</strong>
                                        <span></span></a>
                                    <div class="accordion-inner">
                                        <img src="images/plans/1.jpg" alt="">
                                        <p>Aliquam erat volutpat. Curabitur convallis fringilla diam sed aliquam. Sed
                                            tempor iaculis massa faucibus feugiat. In fermentum facilisis massa, a
                                            consequat purus viverra</p>
                                    </div>
                                    <a class="toggle" href="#"> Garage Plan <strong>180 sq ft</strong> <span></span></a>
                                    <div class="accordion-inner">
                                        <img src="images/plans/1.jpg" alt="">
                                        <p>Lorem ipsum dolor sit amet, consectetur adipiscing elit. Sed tempor iaculis
                                            massa faucibus feugiat. In fermentum facilisis massa, a consequat purus
                                            viverra.</p>
                                    </div>
                                </div>
                            </div> --}}
                            <!-- list-single-main-item end -->
                            <!-- list-single-main-item -->
                            @if($property->video_link)
                            <div class="list-single-main-item fl-wrap" id="sec5">
                                <div class="list-single-main-item-title">
                                    <h3>Video</h3>
                                </div>
                                <div class="list-single-main-item_content fl-wrap">
                                    <div class="video-box fl-wrap">
                                        <img src="{{$property->title_image}}" class="respimg"
                                            alt="{{ $property->title_by_address }}">
                                        <a class="video-box-btn image-popup color-bg"
                                            href="{{ $properties->video_link }}"><i class="fas fa-play"></i></a>
                                    </div>
                                </div>
                            </div>
                            @endif
                            <!-- list-single-main-item end -->
                            <!-- list-single-main-item -->
                            {{-- <div class="list-single-main-item fl-wrap">
                                <div class="list-single-main-item-title">
                                    <h3>Features</h3>
                                </div>
                                <div class="list-single-main-item_content fl-wrap">
                                    <div class="listing-features ">
                                        <ul>
                                            <li><a href="#"><i class="fal fa-dumbbell"></i> Gym</a></li>
                                            <li><a href="#"><i class="fal fa-wifi"></i> Wi Fi</a></li>
                                            <li><a href="#"><i class="fal fa-parking"></i> Parking</a></li>
                                            <li><a href="#"><i class="fal fa-cloud"></i> Air Conditioned</a></li>
                                            <li><a href="#"><i class="fal fa-swimmer"></i> Pool</a></li>
                                            <li><a href="#"><i class="fal fa-cctv"></i> Security</a></li>
                                            <li><a href="#"><i class="fal fa-washer"></i> Laundry Room</a></li>
                                            <li><a href="#"><i class="fal fa-utensils"></i> Equipped Kitchen</a></li>
                                        </ul>
                                    </div>
                                </div>
                            </div> --}}
                            <!-- list-single-main-item end -->
                            <!-- list-single-main-item -->
                            {{-- <div class="list-single-main-item fw-lmi fl-wrap" id="sec6">
                                <div class="map-container mapC_vis mapC_vis2">
                                    <div id="singleMap" data-latitude="40.7427837" data-longitude="-73.11445617675781"
                                        data-mapTitle="Our Location" data-infotitle="House in Financial Distric"
                                        data-infotext="70 Bright St New York, USA"></div>
                                    <div class="scrollContorl"></div>
                                </div>
                                <input id="pac-input" class="controls fl-wrap controls-mapwn" autocomplete="on"
                                    type="text" placeholder="What Nearby? Schools, Gym... " value="">
                            </div> --}}
                            <!-- list-single-main-item end -->
                            <!-- list-single-main-item -->
                            {{-- <div class="list-single-main-item fl-wrap" id="sec7">
                                <div class="list-single-main-item-title">
                                    <h3>Đánh giá <span>2</span></h3>
                                </div>
                                <div class="list-single-main-item_content fl-wrap">
                                    <div class="reviews-comments-wrap fl-wrap">
                                        <div class="review-total">
                                            <span class="review-number blue-bg">4.0</span>
                                            <div class="listing-rating card-popup-rainingvis" data-starrating2="4"><span
                                                    class="re_stars-title">Tốt</span></div>
                                        </div>
                                        <!-- reviews-comments-item -->
                                        <div class="reviews-comments-item">
                                            <div class="review-comments-avatar">
                                                <img src="images/avatar/1.jpg" alt="{{ $property->title_by_address }}">
                                            </div>
                                            <div class="reviews-comments-item-text smpar">
                                                <div class="box-widget-menu-btn smact"><i class="far fa-ellipsis-h"></i>
                                                </div>
                                                <div class="show-more-snopt-tooltip bxwt">
                                                    <a href="#"> <i class="fas fa-reply"></i> Trả lời</a>
                                                    <a href="#"> <i class="fas fa-exclamation-triangle"></i> Báo cáo
                                                    </a>
                                                </div>
                                                <h4><a href="#">Liza Rose</a></h4>
                                                <div class="listing-rating card-popup-rainingvis" data-starrating2="3">
                                                    <span class="re_stars-title">Trung bình</span>
                                                </div>
                                                <div class="clearfix"></div>
                                                <p>" Donec quam felis, ultricies nec, pellentesque eu, pretium quis,
                                                    sem. Nulla consequat massa quis enim. Donec pede justo, fringilla
                                                    vel, aliquet nec, vulputate eget, arcu. In enim justo, rhoncus ut,
                                                    imperdiet a, venenatis vitae, justo. Nullam dictum felis eu pede
                                                    mollis pretium. "</p>
                                                <div class="reviews-comments-item-date"><span
                                                        class="reviews-comments-item-date-item"><i
                                                            class="far fa-calendar-check"></i>12 Tháng 4, 2018</span><a
                                                        href="#" class="rate-review"><i class="fal fa-thumbs-up"></i>
                                                        Đánh giá hữu ích <span>6</span> </a></div>
                                            </div>
                                        </div>
                                        <!--reviews-comments-item end-->
                                        <!-- reviews-comments-item -->
                                        <div class="reviews-comments-item">
                                            <div class="review-comments-avatar">
                                                <img src="images/avatar/1.jpg" alt="{{ $property->title_by_address }}">
                                            </div>
                                            <div class="reviews-comments-item-text smpar">
                                                <div class="box-widget-menu-btn smact"><i class="far fa-ellipsis-h"></i>
                                                </div>
                                                <div class="show-more-snopt-tooltip bxwt">
                                                    <a href="#"> <i class="fas fa-reply"></i> Trả lời</a>
                                                    <a href="#"> <i class="fas fa-exclamation-triangle"></i> Báo cáo
                                                    </a>
                                                </div>
                                                <h4><a href="#">Adam Koncy</a></h4>
                                                <div class="listing-rating card-popup-rainingvis" data-starrating2="5">
                                                    <span class="re_stars-title">Xuất sắc</span>
                                                </div>
                                                <div class="clearfix"></div>
                                                <p>" Lorem ipsum dolor sit amet, consectetur adipiscing elit. Nunc
                                                    posuere convallis purus non cursus. Cras metus neque, gravida
                                                    sodales massa ut. "</p>
                                                <div class="reviews-comments-item-date"><span
                                                        class="reviews-comments-item-date-item"><i
                                                            class="far fa-calendar-check"></i>03 Tháng 12, 2017</span><a
                                                        href="#" class="rate-review"><i class="fal fa-thumbs-up"></i>
                                                        Đánh giá hữu ích <span>2</span> </a></div>
                                            </div>
                                        </div>
                                        <!--reviews-comments-item end-->
                                    </div>
                                </div>
                            </div> --}}
                            <!-- list-single-main-item end -->
                            <!-- list-single-main-item -->
                            {{-- <div class="list-single-main-item fl-wrap" id="sec15">
                                <div class="list-single-main-item-title fl-wrap">
                                    <h3>Thêm Đánh giá Của Bạn</h3>
                                </div>
                                <!-- Hộp Thêm Đánh giá -->
                                <div id="add-review" class="add-review-box">
                                    <div class="leave-rating-wrap">
                                        <span class="leave-rating-title">Đánh giá của bạn cho bản ghi này: </span>
                                        <div class="leave-rating">
                                            <input type="radio" data-ratingtext="Tuyệt vời" name="rating" id="rating-1"
                                                value="1" />
                                            <label for="rating-1" class="fal fa-star"></label>
                                            <input type="radio" data-ratingtext="Tốt" name="rating" id="rating-2"
                                                value="2" />
                                            <label for="rating-2" class="fal fa-star"></label>
                                            <input type="radio" name="rating" data-ratingtext="Trung bình" id="rating-3"
                                                value="3" />
                                            <label for="rating-3" class="fal fa-star"></label>
                                            <input type="radio" data-ratingtext="Công bằng" name="rating" id="rating-4"
                                                value="4" />
                                            <label for="rating-4" class="fal fa-star"></label>
                                            <input type="radio" data-ratingtext="Rất tệ" name="rating" id="rating-5"
                                                value="5" />
                                            <label for="rating-5" class="fal fa-star"></label>
                                        </div>
                                        <div class="count-radio-wrapper">
                                            <span id="count-checked-radio">Đánh Giá Của Bạn</span>
                                        </div>
                                    </div>
                                    <!-- Ô Nhận Xét -->
                                    <form class="add-comment custom-form">
                                        <fieldset>
                                            <div class="row">
                                                <div class="col-md-6">
                                                    <label>Tên của bạn* <span class="dec-icon"><i
                                                                class="fas fa-user"></i></span></label>
                                                    <input name="phone" type="text" onClick="this.select()" value="">
                                                </div>
                                                <div class="col-md-6">
                                                    <label>Email của bạn* <span class="dec-icon"><i
                                                                class="fas fa-envelope"></i></span></label>
                                                    <input name="reviewwname" type="text" onClick="this.select()"
                                                        value="">
                                                </div>
                                            </div>
                                            <textarea cols="40" rows="3" placeholder="Đánh giá của bạn:"></textarea>
                                        </fieldset>
                                        <button class="btn big-btn color-bg float-btn">Gửi Đánh giá <i
                                                class="fa fa-paper-plane-o" aria-hidden="true"></i></button>
                                    </form>
                                </div>
                                <!-- Hộp Thêm Đánh giá / Kết thúc -->
                            </div> --}}
                            <!-- list-single-main-item end -->
                        </div>
                    </div>
                </div>
                <!-- listing-single content end-->
                <!-- sidebar -->
                <div class="col-md-4">
                    <!--box-widget-->
                    <div class="box-widget fl-wrap">
                        <div class="profile-widget">
                            <div class="profile-widget-header color-bg smpar fl-wrap">
                                <div class="pwh_bg"></div>
                                {{-- <div class="call-btn">
                                    <a href="tel:123-456-7890" class="tolt color-bg" data-microtip-position="right"
                                        data-tooltip="Gọi ngay">
                                        <i class="fas fa-phone-alt"></i>
                                    </a>
                                </div> --}}
                                {{-- <div class="box-widget-menu-btn smact">
                                    <i class="far fa-ellipsis-h"></i>
                                </div> --}}
                                {{-- <div class="show-more-snopt-tooltip bxwt">
                                    <a href="#"> <i class="fas fa-comment-alt"></i> Viết đánh giá</a>
                                    <a href="#"> <i class="fas fa-exclamation-triangle"></i> Báo cáo </a>
                                </div> --}}
                                <div class="profile-widget-card">
                                    <div class="profile-widget-image">
                                        <img src="{{$property->agent ? ($property->agent->profile ? $property->agent->profile : 'https://dalatbds.com/images/users/1693209486.1303.png'):'https://dalatbds.com/images/users/1693209486.1303.png'}}" alt="Green Allies">
                                    </div>
                                    <div class="profile-widget-header-title">
                                        @if(isset($property->added_by))
                                        <h4><a href="{{ route('agent.showid', ['id' => $property->added_by]) }}">{{
                                                $property->agent->name ?? 'Unknown' }}</a></h4>
                                        @endif
                                        <div class="clearfix"></div>
                                        <div class="pwh_counter"><span>{{ $property->count_properties_by_agent ?? 0
                                                }}</span>Danh sách Bất động sản</div>
                                        <div class="clearfix"></div>
                                        <div class="listing-rating card-popup-rainingvis" data-starrating2="4"></div>
                                    </div>
                                </div>
                            </div>
                            <div class="profile-widget-content fl-wrap">
                                <div class="contats-list fl-wrap">
                                    <ul class="no-list-style">
                                        {{-- <li><span><i class="fal fa-phone"></i> Điện thoại :</span> <a href="#">{{
                                                $property->agent->mobile ?? 'N/A' }}</a></li>
                                        <li><span><i class="fal fa-envelope"></i> Email :</span> <a href="#">{{
                                                $property->agent->email ?? 'N/A' }}</a></li>
                                        <li><span><i class="fal fa-browser"></i> Website :</span> <a href="#">{{
                                                $property->agent->website ?? 'N/A' }}</a></li> --}}
                                    </ul>
                                </div>
                                <div class="profile-widget-footer fl-wrap">
                                    <a href="{{ route('agent.showid', ['id' => $property->added_by]) }}"
                                        class="btn float-btn color-bg small-btn">Xem Hồ sơ</a>
                                    <a href="#sec-contact" class="custom-scroll-link tolt" data-microtip-position="left"
                                        data-tooltip="Xem Bất động sản"><i class="fal fa-paper-plane"></i></a>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!--box-widget end -->
                    <!--box-widget-->
                    <div class="box-widget fl-wrap">
                        <div class="box-widget-title fl-wrap">Bất động sản Nổi bật</div>
                        <div class="box-widget-content fl-wrap">
                            <!-- Bài viết nổi bật -->
                            {{-- <div class="widget-posts  fl-wrap">
                                <ul class="no-list-style">
                                    <li>
                                        <div class="widget-posts-img"><a href="listing-single.html"><img
                                                    src="images/all/small/1.jpg" {{ $property->title_by_address }}"></a>
                                        </div>
                                        <div class="widget-posts-descr">
                                            <h4><a href="listing-single.html">Phòng Đô Thị Phải Chăng</a></h4>
                                            <div class="geodir-category-location fl-wrap"><a href="#"><i
                                                        class="fas fa-map-marker-alt"></i> 40 Journal Square, NJ, Mỹ</a>
                                            </div>
                                            <div class="widget-posts-descr-price"><span>Giá: </span> $ 1500 / mỗi tháng
                                            </div>
                                        </div>
                                    </li>
                                    <li>
                                        <div class="widget-posts-img"><a href="listing-single.html"><img
                                                    src="images/all/small/1.jpg"
                                                    alt="{{ $property->title_by_address }}"></a>
                                        </div>
                                        <div class="widget-posts-descr">
                                            <h4><a href="listing-single.html">Nhà Gia Đình</a></h4>
                                            <div class="geodir-category-location fl-wrap"><a href="#"><i
                                                        class="fas fa-map-marker-alt"></i> 70 Bright St New York, Mỹ</a>
                                            </div>
                                            <div class="widget-posts-descr-price"><span>Giá: </span> $ 50000</div>
                                        </div>
                                    </li>
                                    <li>
                                        <div class="widget-posts-img"><a href="listing-single.html"><img
                                                    src="images/all/small/1.jpg"
                                                    alt="{{ $property->title_by_address }}"></a>
                                        </div>
                                        <div class="widget-posts-descr">
                                            <h4><a href="listing-single.html">Căn hộ Cho Thuê</a></h4>
                                            <div class="geodir-category-location fl-wrap"><a href="#"><i
                                                        class="fas fa-map-marker-alt"></i> 75 Prince St, NY, Mỹ</a>
                                            </div>
                                            <div class="widget-posts-descr-price"><span>Giá: </span> $100 / mỗi đêm
                                            </div>
                                        </div>
                                    </li>
                                    <li>
                                        <div class="widget-posts-img"><a href="listing-single.html"><img
                                                    src="images/all/small/1.jpg"
                                                    alt="{{ $property->title_by_address }}"></a>
                                        </div>
                                        <div class="widget-posts-descr">
                                            <h4><a href="listing-single.html">Căn hộ Cho Thuê</a></h4>
                                            <div class="geodir-category-location fl-wrap"><a href="#"><i
                                                        class="fas fa-map-marker-alt"></i> 75 Prince St, NY, Mỹ</a>
                                            </div>
                                            <div class="widget-posts-descr-price"><span>Giá: </span> $100 / mỗi đêm
                                            </div>
                                        </div>
                                    </li>
                                </ul>
                            </div> --}}
                            <!-- Bài viết nổi bật -->
                            <div class="widget-posts fl-wrap">
                                <ul class="no-list-style">
                                    @foreach($highlightedProducts as $product)
                                    <li>
                                        <div class="widget-posts-img"><a
                                                href="{{ route('property.show', $product->id) }}"><img
                                                    src="{{ $product->title_image  }}"
                                                    alt="{{ $product->title_by_address  }}"></a>
                                        </div>
                                        <div class="widget-posts-descr">
                                            <h4><a href="{{ route('property.show', $product->id) }}">{{
                                                    $product->title_by_address
                                                    }}</a></h4>
                                            <div class="geodir-category-location fl-wrap"><a href="#"><i
                                                        class="fas fa-map-marker-alt"></i> {{ $product->address_location
                                                    }}</a>
                                            </div>
                                            <div class="widget-posts-descr-price"><span>Giá: </span> {{
                                                $product->formatted_prices
                                                }}</div>
                                        </div>
                                    </li>
                                    @endforeach
                                </ul>
                            </div>
                            <!-- Kết thúc bài viết nổi bật -->
                            <!-- Kết thúc bài viết nổi bật -->
                            <a href="{{ route('properties.index') }}" class="btn float-btn color-bg small-btn">Xem Tất
                                cả Bất động sản</a>
                        </div>
                    </div>
                    <!--box-widget end -->
                    <!--box-widget-->
                    {{-- <div class="box-widget fl-wrap hidden-section" style="margin-top: 30px">
                        <div class="box-widget-content fl-wrap color-bg">
                            <div class="color-form reset-action">
                                <div class="color-form-title fl-wrap">
                                    <h4>Calculate Your Mortgage</h4>
                                    <p>Lorem ipsum dolor sit amet, consectetur adipiscing elit. Nunc posuere convallis
                                        purus non cursus. </p>
                                </div>
                                <form method="post" name="mortgage-form">
                                    <div class="fl-wrap">
                                        <label for="amt">Loan Amount </label>
                                        <input id="amt" name="amt" type="text" placeholder="0" value="0">
                                        <div class="use-current-price tolt" data-microtip-position="left"
                                            data-tooltip="Use current price"><i class="fal fa-tag"></i></div>
                                    </div>
                                    <label for="apr">Percentage rate</label>
                                    <div class="price-rage-item fl-wrap">
                                        <input type="text" id="apr" name="apr" class="price-range" data-min="0"
                                            data-max="100" data-step="1" value="0" data-prefix="%">
                                    </div>
                                    <label for="trm">Loan Term (Years) </label>
                                    <div class="price-rage-item fl-wrap">
                                        <input type="text" id="trm" name="trm" class="price-range" data-min="0"
                                            data-max="5" data-step="1" value="0" data-prefix="Y">
                                    </div>
                                    <div class="clearfix"></div>
                                    <button type="button" id="sbt" class="color2-bg">Calculate</button>
                                    <div class="reset-form reset-btn"> <i class="far fa-sync-alt"></i> Reset Form</div>
                                    <div class="monterage-title fl-wrap">
                                        <h5>Monthly payment:</h5>
                                        <input type="text" id="pmt" name="mPmt" value="0">
                                        <div class="monterage-title-item">$<span></span></div>
                                    </div>
                                </form>
                            </div>
                        </div>
                    </div> --}}
                    <!--box-widget end -->
                    <!--box-widget-->
                    {{-- <div class="box-widget fl-wrap">
                        <div class="box-widget-title fl-wrap">Propertie Documents</div>
                        <div class="box-widget-content fl-wrap">
                            <div class="bwc_download-list">
                                <a href="#" download><span><i class="fal fa-file-pdf"></i></span>Property
                                    Presentation</a>
                                <a href="#" download><span><i class="fal fa-file-word"></i></span>Energetic
                                    Certificate</a>
                                <a href="#" download><span><i class="fal fa-file-pdf"></i></span>Property Plans</a>
                            </div>
                        </div>
                    </div> --}}
                    <!--box-widget end -->
                    <!--box-widget-->
                    {{-- <div class="box-widget fl-wrap">
                        <div class="box-widget-fixed-init fl-wrap" id="sec-contact">
                            <div class="box-widget-title fl-wrap box-widget-title-color color-bg">Contact Property</div>
                            <div class="box-widget-content fl-wrap">
                                <div class="custom-form">
                                    <form method="post" name="contact-property-form">
                                        <label>Your name* <span class="dec-icon"><i
                                                    class="fas fa-user"></i></span></label>
                                        <input name="phone" type="text" onClick="this.select()" value="">
                                        <label>Your phone * <span class="dec-icon"><i
                                                    class="fas fa-phone"></i></span></label>
                                        <input name="phone" type="text" onClick="this.select()" value="">
                                        <div class="row">
                                            <div class="col-sm-6">
                                                <label>Date <span class="dec-icon"><i
                                                            class="fas fa-calendar-check"></i></span></label>
                                                <div class="date-container fl-wrap">
                                                    <input type="text" placeholder=""
                                                        style="padding: 16px 5px 16px 60px;" name="datepicker-here"
                                                        value="" />
                                                </div>
                                            </div>
                                            <div class="col-sm-6">
                                                <label>Time </label>
                                                <select data-placeholder="9 AM"
                                                    class="chosen-select on-radius no-search-select">
                                                    <option>9 AM</option>
                                                    <option>10 AM</option>
                                                    <option>11 AM</option>
                                                    <option>12 AM</option>
                                                    <option>13 PM</option>
                                                    <option>14 PM</option>
                                                    <option>15 PM</option>
                                                    <option>16 PM</option>
                                                </select>
                                            </div>
                                        </div>
                                        <button type="submit" class="btn float-btn color-bg fw-btn"> Send</button>
                                    </form>
                                </div>
                            </div>
                        </div>
                    </div> --}}
                    <!--box-widget end -->
                </div>
                <!--  sidebar end-->
            </div>
            <div class="fl-wrap limit-box"></div>
            <div class="listing-carousel-wrapper carousel-wrap fl-wrap">
                <div class="list-single-main-item-title">
                    <h3>Tương Tự</h3>
                </div>
                <div class="listing-carousel carousel ">
                    @foreach($relatedProducts as $productItem )
                    <!-- slick-slide-item -->
                    <div class="slick-slide-item">
                        <!-- listing-item -->
                        @include('frontends.components.product_card',['productCard'=>$productItem ])
                        <!-- listing-item end-->
                    </div>
                    <!-- slick-slide-item end-->
                    @endforeach
                </div>
                {{-- <div class="swiper-button-prev lc-wbtn lc-wbtn_prev"><i class="far fa-angle-left"></i></div>
                <div class="swiper-button-next lc-wbtn lc-wbtn_next"><i class="far fa-angle-right"></i></div> --}}
            </div>
        </div>
    </div>
</div>
<!-- content end -->
@endsection
