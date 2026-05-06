<div class="list-single-carousel-wrap carousel-wrap fl-wrap" id="sec1">
    <div class="fw-carousel single-carousel carousel fl-wrap full-height lightgallery">
        @foreach (range(1,4) as $index)
        <!-- slick-slide-item -->
        @include('frontends.components.product_slider_card')
        <!-- slick-slide-item end -->
        @endforeach
    </div>
    <div class="swiper-button-prev sw-btn"><i class="fal fa-angle-left"></i></div>
    <div class="swiper-button-next sw-btn"><i class="fal fa-angle-right"></i></div>
</div>
