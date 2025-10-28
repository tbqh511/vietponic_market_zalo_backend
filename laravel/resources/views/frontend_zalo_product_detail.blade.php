@extends('frontends.master')
@section('content')
<!-- content -->
<div class="content">
    <section class="hidden-section single-hero-section" data-scrollax-parent="true" id="sec1">
        <div class="bg-wrap bg-parallax-wrap-gradien">
            <div class="bg par-elem" data-bg="{{ $product->image }}" data-scrollax="properties: { translateY: '30%' }"></div>
        </div>
        <div class="container">
            <!-- list-single-opt_header-->
            <div class="list-single-opt_header fl-wrap">
                <ul class="list-single-opt_header_cat">
                    @if($product->category)
                    <li><a href="{{ route('frontend.zalo-products.index', ['category_id' => $product->category->id]) }}" class="cat-opt blue-bg">{{ $product->category->name }}</a></li>
                    @endif
                    <li><a href="#" class="cat-opt color-bg">{{ $product->created_at->diffForHumans() }}</a></li>
                </ul>
            </div>
            <!-- list-single-opt_header end -->
            <!-- list-single-header-item-->
            <div class="list-single-header-item no-bg-list_sh fl-wrap">
                <div class="row">
                    <div class="col-md-12">
                        <h1>{{ $product->name }}</h1>
                        <div class="share-holder hid-share">
                            <a href="#" class="share-btn showshare sfcs"> <i class="fas fa-share-alt"></i> Chia sẻ </a>
                            <div class="share-container isShare"></div>
                        </div>
                    </div>
                </div>
                <div class="list-single-header-footer fl-wrap">
                    <div class="list-single-header-price" data-propertyprise="{{ $product->price ?? 0 }}">
                        <strong>Giá:</strong>
                        @if($product->price)
                            {{ number_format($product->price) }} VND
                        @else
                            Liên hệ
                        @endif
                    </div>
                    <div class="list-single-header-date"><span>Đã tạo:</span>{{ $product->created_at->format('d/m/Y') }}</div>
                </div>
            </div>
        </div>
    </section>
    <!-- breadcrumbs-->
    <div class="breadcrumbs fw-breadcrumbs sp-brd fl-wrap">
        <div class="container">
            <div class="breadcrumbs-list">
                <a href="/">Trang chủ</a>
                <a href="{{ route('frontend.zalo-products.index') }}">Sản phẩm Zalo</a>
                <span>{{ $product->name }}</span>
            </div>
        </div>
    </div>
    <!-- breadcrumbs end -->
    <div class="gray-bg small-padding fl-wrap">
        <div class="container">
            <div class="row">
                <!-- listing-single content -->
                <div class="col-md-8">
                    <div class="list-single-main-wrapper fl-wrap">
                        <!-- scroll-nav-wrap -->
                        <div class="scroll-nav-wrap">
                            <nav class="scroll-nav scroll-init fixed-column_menu-init">
                                <ul class="no-list-style">
                                    <li><a class="act-scrlink" href="#sec1"><i class="fal fa-home-lg-alt"></i></a><span>Nội dung chính</span></li>
                                    <li><a href="#sec2"><i class="fal fa-image"></i></a><span>Hình ảnh</span></li>
                                    <li><a href="#sec3"><i class="fal fa-info"></i></a><span>Chi tiết</span></li>
                                </ul>
                                <div class="progress-indicator">
                                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="-1 -1 34 34">
                                        <circle cx="16" cy="16" r="15.9155" class="progress-bar__background" />
                                        <circle cx="16" cy="16" r="15.9155" class="progress-bar__progress js-progress-bar" />
                                    </svg>
                                </div>
                            </nav>
                        </div>
                        <!-- scroll-nav-wrap end-->
                        <div class="list-single-main-media fl-wrap" id="sec2">
                            <!-- gallery-items -->
                            <div class="gallery-items grid-small-pad list-single-gallery three-coulms lightgallery">
                                <div class="gallery-item">
                                    <div class="grid-item-holder">
                                        <div class="box-item">
                                            <img src="{{ $product->image }}" alt="{{ $product->name }}">
                                            <a href="{{ $product->image }}" class="gal-link popup-image"><i class="fa fa-search"></i></a>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <!-- end gallery items -->
                        </div>
                        <div class="list-single-main-container fl-wrap" id="sec3">
                            <!-- list-single-main-item -->
                            <div class="list-single-main-item fl-wrap">
                                <div class="list-single-main-item-title">
                                    <h3>Mô tả sản phẩm</h3>
                                </div>
                                <div class="list-single-main-item_content fl-wrap">
                                    <p>{{ $product->description }}</p>
                                </div>
                            </div>
                            <!-- list-single-main-item end -->
                            <!-- list-single-main-item -->
                            <div class="list-single-main-item fl-wrap">
                                <div class="list-single-main-item-title">
                                    <h3>Thông tin chi tiết</h3>
                                </div>
                                <div class="list-single-main-item_content fl-wrap">
                                    <div class="details-list">
                                        <ul>
                                            <li><span>Tên sản phẩm:</span>{{ $product->name }}</li>
                                            @if($product->category)
                                            <li><span>Danh mục:</span>{{ $product->category->name }}</li>
                                            @endif
                                            @if($product->price)
                                            <li><span>Giá:</span>{{ number_format($product->price) }} VND</li>
                                            @endif
                                            <li><span>Ngày tạo:</span>{{ $product->created_at->format('d/m/Y H:i') }}</li>
                                            <li><span>Ngày cập nhật:</span>{{ $product->updated_at->format('d/m/Y H:i') }}</li>
                                        </ul>
                                    </div>
                                </div>
                            </div>
                            <!-- list-single-main-item end -->
                        </div>
                    </div>
                </div>
                <!-- listing-single content end-->
                <!-- sidebar -->
                <div class="col-md-4">
                    <!--box-widget-->
                    <div class="box-widget fl-wrap">
                        <div class="box-widget-title fl-wrap">Sản phẩm liên quan</div>
                        <div class="box-widget-content fl-wrap">
                            <div class="widget-posts fl-wrap">
                                <ul class="no-list-style">
                                    @forelse($relatedProducts as $relatedProduct)
                                    <li>
                                        <div class="widget-posts-img">
                                            <a href="{{ route('frontend.zalo-products.show', $relatedProduct->id) }}">
                                                <img src="{{ $relatedProduct->image }}" alt="{{ $relatedProduct->name }}">
                                            </a>
                                        </div>
                                        <div class="widget-posts-descr">
                                            <h4>
                                                <a href="{{ route('frontend.zalo-products.show', $relatedProduct->id) }}">
                                                    {{ $relatedProduct->name }}
                                                </a>
                                            </h4>
                                            <div class="widget-posts-descr-price">
                                                <span>Giá: </span>
                                                @if($relatedProduct->price)
                                                    {{ number_format($relatedProduct->price) }} VND
                                                @else
                                                    Liên hệ
                                                @endif
                                            </div>
                                        </div>
                                    </li>
                                    @empty
                                    <li>
                                        <p>Không có sản phẩm liên quan.</p>
                                    </li>
                                    @endforelse
                                </ul>
                            </div>
                            <a href="{{ route('frontend.zalo-products.index') }}" class="btn float-btn color-bg small-btn">
                                Xem tất cả sản phẩm
                            </a>
                        </div>
                    </div>
                    <!--box-widget end -->
                </div>
                <!-- sidebar end-->
            </div>
            <div class="fl-wrap limit-box"></div>
        </div>
    </div>
</div>
<!-- content end -->
@endsection