@extends('frontends.master')
@section('content')
<!-- content -->
<div class="content">
    <!-- breadcrumbs-->
    <div class="breadcrumbs fw-breadcrumbs sp-brd fl-wrap">
        <div class="container">
            <div class="breadcrumbs-list">
                <a href="/">Trang chủ</a> <span>Sản phẩm Zalo</span>
            </div>
        </div>
    </div>
    <!-- breadcrumbs end -->

    <!-- col-list-wrap -->
    <section class="gray-bg small-padding ">
        <div class="container">
            <div class="mob-nav-content-btn  color-bg show-list-wrap-search ntm fl-wrap">Tìm kiếm</div>
            <!-- list-searh-input-wrap-->
            <div class="list-searh-input-wrap box_list-searh-input-wrap lws_mobile fl-wrap">
                <div class="list-searh-input-wrap-title fl-wrap"><i class="far fa-sliders-h"></i><span>Bộ Lọc Tìm Kiếm</span></div>
                <div class="custom-form fl-wrap">
                    <form id="searchForm" action="{{ route('frontend.zalo-products.index') }}" method="GET">
                        @csrf
                        <div class="row">
                            <!-- Ô nhập liệu tìm kiếm -->
                            <div class="col-sm-6">
                                <div class="listsearch-input-item">
                                    <input name="search" type="text" placeholder="Tìm sản phẩm..." value="{{ request()->input('search') }}" />
                                </div>
                            </div>
                            <!-- Kết thúc ô nhập liệu tìm kiếm -->

                            <!-- Ô lựa chọn danh mục -->
                            <div class="col-sm-3">
                                <div class="listsearch-input-item">
                                    <select name="category_id" data-placeholder="Danh mục" class="chosen-select on-radius no-search-select">
                                        <option value="">Tất cả danh mục</option>
                                        @foreach ($categories as $category)
                                        <option value="{{ $category->id }}" {{ request()->input('category_id') == $category->id ? 'selected' : '' }}>
                                            {{ $category->name }}
                                        </option>
                                        @endforeach
                                    </select>
                                </div>
                            </div>
                            <!-- Kết thúc ô lựa chọn danh mục -->

                            <!-- Nút tìm kiếm -->
                            <div class="col-sm-3">
                                <div class="listsearch-input-item">
                                    <button type="submit" class="btn color-bg fw-btn float-btn small-btn">Tìm kiếm</button>
                                </div>
                            </div>
                            <!-- Kết thúc nút tìm kiếm -->
                        </div>
                    </form>
                </div>
            </div>
            <!-- list-searh-input-wrap end-->

            <!-- list-main-wrap-header-->
            <div class="list-main-wrap-header box-list-header fl-wrap">
                <!-- list-main-wrap-title-->
                <div class="list-main-wrap-title">
                    <h2>Sản phẩm Zalo
                        <strong>{{ $products->total() }}</strong>
                    </h2>
                </div>
                <!-- list-main-wrap-title end-->
                <!-- list-main-wrap-opt-->
                <div class="list-main-wrap-opt">
                    <!-- price-opt-->
                    <div class="price-opt">
                        <span class="price-opt-title">Sắp xếp theo:</span>
                        <div class="listsearch-input-item">
                            <select name="sort" class="chosen-select no-search-select" onchange="this.form.submit()">
                                <option value="">Mặc định</option>
                                <option value="name_asc" {{ request()->input('sort') == 'name_asc' ? 'selected' : '' }}>Tên A-Z</option>
                                <option value="name_desc" {{ request()->input('sort') == 'name_desc' ? 'selected' : '' }}>Tên Z-A</option>
                                <option value="price_asc" {{ request()->input('sort') == 'price_asc' ? 'selected' : '' }}>Giá thấp đến cao</option>
                                <option value="price_desc" {{ request()->input('sort') == 'price_desc' ? 'selected' : '' }}>Giá cao đến thấp</option>
                                <option value="newest" {{ request()->input('sort') == 'newest' ? 'selected' : '' }}>Mới nhất</option>
                            </select>
                        </div>
                    </div>
                    <!-- price-opt end-->
                </div>
                <!-- list-main-wrap-opt end-->
            </div>
            <!-- list-main-wrap-header end-->

            <!-- listing-item-wrap-->
            <div class="listing-item-container three-columns-grid box-list_ic fl-wrap">
                @forelse($products as $product)
                <!-- listing-item -->
                <div class="listing-item">
                    <article class="geodir-category-listing fl-wrap">
                        <div class="geodir-category-img fl-wrap">
                            <a href="{{ route('frontend.zalo-products.show', $product->id) }}" class="geodir-category-img_item">
                                <img src="{{ $product->image }}" alt="{{ $product->name }}">
                                <div class="overlay"></div>
                            </a>
                            <ul class="list-single-opt_header_cat">
                                @if($product->category)
                                <li><a href="{{ route('frontend.zalo-products.index', ['category_id' => $product->category->id]) }}" class="cat-opt blue-bg">{{ $product->category->name }}</a></li>
                                @endif
                                <li><a href="#" class="cat-opt color-bg">{{ $product->created_at->diffForHumans() }}</a></li>
                            </ul>
                        </div>
                        <div class="geodir-category-content fl-wrap">
                            <h3 class="title-sin_item">
                                <a href="{{ route('frontend.zalo-products.show', $product->id) }}">{{ $product->name }}</a>
                            </h3>
                            <div class="geodir-category-content_price">
                                @if($product->price)
                                    {{ number_format($product->price) }} VND
                                @else
                                    Liên hệ
                                @endif
                            </div>
                            <p>{{ Str::limit($product->description, 100) }}</p>
                            <div class="geodir-category-footer fl-wrap">
                                <div class="listing-rating card-popup-rainingvis tolt" data-microtip-position="top" data-tooltip="Sản phẩm chất lượng" data-starrating2="5"></div>
                            </div>
                        </div>
                    </article>
                </div>
                <!-- listing-item end-->
                @empty
                <div class="col-12 text-center">
                    <p>Không tìm thấy sản phẩm nào.</p>
                </div>
                @endforelse
            </div>
            <!-- listing-item-wrap end-->

            <!-- pagination-->
            <div class="pagination">
                @if ($products->previousPageUrl())
                    <a href="{{ $products->previousPageUrl() }}" class="prevposts-link"><i class="fa fa-caret-left"></i></a>
                @else
                    <a href="#" class="prevposts-link disabled"><i class="fa fa-caret-left"></i></a>
                @endif

                @foreach ($products->getUrlRange(1, $products->lastPage()) as $page => $url)
                    @if ($page == $products->currentPage())
                        <a href="#" class="current-page">{{ $page }}</a>
                    @else
                        <a href="{{ $url }}">{{ $page }}</a>
                    @endif
                @endforeach

                @if ($products->nextPageUrl())
                    <a href="{{ $products->nextPageUrl() }}" class="nextposts-link"><i class="fa fa-caret-right"></i></a>
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