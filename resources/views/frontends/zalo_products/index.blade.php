@extends('frontends.master')
@section('content')
<!-- content -->
<div class="content">
    <!-- section -->
    <section class="gray-bg small-padding">
        <div class="container">
            <div class="row">
                <!-- sidebar -->
                <div class="col-md-3">
                    <div class="box-widget fl-wrap">
                        <div class="box-widget-title fl-wrap">Danh mục sản phẩm</div>
                        <div class="box-widget-content fl-wrap">
                            <ul class="cat-item">
                                <li><a href="{{ route('frontend.zalo-products.index') }}">Tất cả</a></li>
                                @foreach($categories as $category)
                                <li>
                                    <a href="{{ route('frontend.zalo-products.index', ['category_id' => $category->id]) }}">
                                        {{ $category->name }}
                                    </a>
                                </li>
                                @endforeach
                            </ul>
                        </div>
                    </div>
                </div>
                <!-- sidebar end -->

                <!-- content -->
                <div class="col-md-9">
                    <div class="mob-nav-content-btn color-bg show-list-wrap-search ntm fl-wrap">Tìm kiếm</div>
                    <!-- list-searh-input-wrap-->
                    <div class="list-searh-input-wrap box_list-searh-input-wrap lws_mobile fl-wrap">
                        <div class="list-searh-input-wrap-title fl-wrap"><i class="far fa-search"></i><span>Tìm kiếm sản phẩm</span></div>
                        <div class="custom-form fl-wrap">
                            <form action="{{ route('frontend.zalo-products.index') }}" method="GET">
                                <div class="row">
                                    <div class="col-sm-8">
                                        <input name="search" type="text" placeholder="Tìm sản phẩm..." value="{{ request()->input('search') }}" />
                                    </div>
                                    <div class="col-sm-4">
                                        <button type="submit" class="btn color-bg fw-btn">Tìm kiếm</button>
                                    </div>
                                </div>
                            </form>
                        </div>
                    </div>
                    <!-- list-searh-input-wrap end -->

                    <!-- products list -->
                    <div class="listing-item-container init-grid-items fl-wrap">
                        @forelse($products as $product)
                        <div class="listing-item">
                            <article class="geodir-category-listing fl-wrap">
                                <div class="geodir-category-img">
                                    @if($product->image)
                                    <img src="{{ $product->image_url }}" alt="{{ $product->name }}" style="width: 100%; height: 200px; object-fit: cover;">
                                    @else
                                    <img src="{{ $product->image_url }}" alt="No image" style="width: 100%; height: 200px; object-fit: cover;">
                                    @endif
                                    <div class="overlay"></div>
                                </div>
                                <div class="geodir-category-content fl-wrap">
                                    <h3><a href="{{ route('frontend.zalo-products.show', $product->id) }}">{{ $product->name }}</a></h3>
                                    <div class="geodir-category-location fl-wrap">
                                        @if($product->category)
                                        <a href="#"><i class="fas fa-tag"></i> {{ $product->category->name }}</a>
                                        @endif
                                    </div>
                                    <div class="geodir-category-price fl-wrap">
                                        @if($product->price > 0)
                                        <span>{{ number_format($product->price) }} VND</span>
                                        @if($product->original_price > $product->price)
                                        <span class="original-price">{{ number_format($product->original_price) }} VND</span>
                                        @endif
                                        @else
                                        <span>Liên hệ</span>
                                        @endif
                                    </div>
                                    <p>{{ Str::limit($product->detail, 100) }}</p>
                                </div>
                            </article>
                        </div>
                        @empty
                        <div class="col-md-12">
                            <p class="text-center">Không có sản phẩm nào.</p>
                        </div>
                        @endforelse
                    </div>
                    <!-- products list end -->

                    <!-- pagination -->
                    @if($products->hasPages())
                    <div class="pagination">
                        {{ $products->links() }}
                    </div>
                    @endif
                    <!-- pagination end -->
                </div>
                <!-- content end -->
            </div>
        </div>
    </section>
    <!-- section end -->
</div>
<!-- content end -->
@endsection