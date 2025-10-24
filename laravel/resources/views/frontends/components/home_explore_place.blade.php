<section class="hidden-section no-padding-section">
    <div class="half-carousel-wrap">
        <div class="half-carousel-title color-bg">
            <div class="half-carousel-title-item fl-wrap">
                <h2>Khám phá thị trường Đà Lạt</h2>
                <h5>Danh sách các phường xã có lượng giao dịch bất động sản nhộn nhịp</h5>
            </div>
            <div class="pwh_bg"></div>
        </div>
        <div class="half-carousel-conatiner">
            <div class="half-carousel fl-wrap full-height">
                @foreach($locationsWards as $locationsWard)
                <!--slick-item -->
                @include('frontends.components.home_explore_stick',['locationsWard'=>$locationsWard])
                <!--slick-item end -->
                @endforeach
            </div>
        </div>
    </div>
</section>
