@extends('frontends.master')
@section('content')
<!-- content -->
<div class="content">
    <!-- section -->
    <section class="gray-bg small-padding">
        <div class="container">
            <div class="row">
                <!-- product detail -->
                <div class="col-md-8">
                    <div class="listing-item-container fl-wrap">
                        <div class="listing-item">
                            <article class="geodir-category-listing fl-wrap">
                                <div class="geodir-category-img">
                                    @if($product->image)
                                    <img src="{{ asset('storage/' . $product->image) }}" alt="{{ $product->name }}" style="width: 100%; height: 400px; object-fit: cover;">
                                    @else
                                    <img src="{{ asset('images/no-image.png') }}" alt="No image" style="width: 100%; height: 400px; object-fit: cover;">
                                    @endif
                                </div>
                                <div class="geodir-category-content fl-wrap">
                                    <h2>{{ $product->name }}</h2>
                                    <div class="geodir-category-location fl-wrap">
                                        @if($product->category)
                                        <a href="#"><i class="fas fa-tag"></i> {{ $product->category->name }}</a>
                                        @endif
                                    </div>
                                    <div class="geodir-category-price fl-wrap">
                                        @if($product->price > 0)
                                        <span class="price">{{ number_format($product->price) }} VND</span>
                                        @if($product->original_price > $product->price)
                                        <span class="original-price">{{ number_format($product->original_price) }} VND</span>
                                        @endif
                                        @else
                                        <span class="price">Liên hệ</span>
                                        @endif
                                    </div>
                                    <div class="product-detail">
                                        {!! nl2br(e($product->detail)) !!}
                                    </div>
                                </div>
                            </article>
                        </div>
                    </div>
                </div>
                <!-- product detail end -->

                <!-- sidebar -->
                <div class="col-md-4">
                    <!-- related products -->
                    @if($relatedProducts->count() > 0)
                    <div class="box-widget fl-wrap">
                        <div class="box-widget-title fl-wrap">Sản phẩm liên quan</div>
                        <div class="box-widget-content fl-wrap">
                            @foreach($relatedProducts as $related)
                            <div class="related-product">
                                <div class="related-product-img">
                                    @if($related->image)
                                    <img src="{{ asset('storage/' . $related->image) }}" alt="{{ $related->name }}" style="width: 80px; height: 80px; object-fit: cover;">
                                    @else
                                    <img src="{{ asset('images/no-image.png') }}" alt="No image" style="width: 80px; height: 80px; object-fit: cover;">
                                    @endif
                                </div>
                                <div class="related-product-content">
                                    <h4><a href="{{ route('frontend.zalo-products.show', $related->id) }}">{{ Str::limit($related->name, 30) }}</a></h4>
                                    <div class="price">
                                        @if($related->price > 0)
                                        {{ number_format($related->price) }} VND
                                        @else
                                        Liên hệ
                                        @endif
                                    </div>
                                </div>
                            </div>
                            @endforeach
                        </div>
                    </div>
                    @endif
                    <!-- related products end -->
                </div>
                <!-- sidebar end -->
            </div>
        </div>
    </section>
    <!-- section end -->
</div>
<!-- content end -->
@endsection