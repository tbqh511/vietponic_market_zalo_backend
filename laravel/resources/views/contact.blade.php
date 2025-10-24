@extends('frontends.master')
@section('content')
<div class="content">
    <!--  section  -->
    <section class="hidden-section single-par2" data-scrollax-parent="true">
        <div class="bg-wrap bg-parallax-wrap-gradien">
            <div class="bg par-elem" data-bg="images/bg/1.jpg" data-scrollax="properties: { translateY: '30%' }"></div>
        </div>
        <div class="container">
            <div class="section-title center-align big-title">
                <h2><span>Liên Hệ Của Chúng Tôi</span></h2>
                <h4>Điểm đến tin cậy cho mọi nhu cầu bất động sản tại Đà Lạt.</h4>
            </div>
            <div class="scroll-down-wrap">
                <div class="mousey">
                    <div class="scroller"></div>
                </div>
                <span>Cuộn Xuống Để Khám Phá</span>
            </div>
        </div>
    </section>
    <!--  section  end-->
    <!-- breadcrumbs-->
    <div class="breadcrumbs fw-breadcrumbs sp-brd fl-wrap">
        <div class="container">
            <div class="breadcrumbs-list">
                <a href="/">Trang chủ</a> <span>Liên hệ</span>
            </div>
            <div class="share-holder hid-share">
                <a href="#" class="share-btn showshare sfcs"> <i class="fas fa-share-alt"></i> Share </a>
                <div class="share-container  isShare"></div>
            </div>
        </div>
    </div>
    <!-- breadcrumbs end -->
    <!-- section -->
    <section class="gray-bg small-padding">
        <div class="container">
            <div class="row">
                <!-- services-item -->
                <div class="col-md-4">
                    <div class="services-item fl-wrap">
                        <i class="fal fa-envelope"></i>
                        <h4>Email Của Chúng Tôi <span>01</span></h4>
                        <p>Chúng tôi luôn sẵn sàng giải đáp mọi thắc mắc của bạn qua email.</p>
                        <a href="#" class="serv-link sl-b">info@dalatbds.com</a>
                    </div>
                </div>
                <!-- services-item  end-->
                <!-- services-item -->
                <div class="col-md-4">
                    <div class="services-item fl-wrap">
                        <i class="fal fa-phone-rotary"></i>
                        <h4>Số Điện Thoại Của Chúng Tôi <span>02</span></h4>
                        <p>Hãy gọi cho chúng tôi để được tư vấn trực tiếp từ đội ngũ chuyên nghiệp.</p>
                        <a href="#" class="serv-link sl-b">0918.96.38.78</a>
                    </div>
                </div>
                <!-- services-item  end-->
                <!-- services-item -->
                <div class="col-md-4">
                    <div class="services-item fl-wrap">
                        <i class="fal fa-map-marked"></i>
                        <h4>Địa Chỉ Của Chúng Tôi <span>03</span></h4>
                        <p>Hãy ghé thăm văn phòng của chúng tôi để được hỗ trợ tận tình.</p>
                        <a href="#" class="serv-link sl-b">27/6 Yersin, Phường 10, Tp Đà Lạt</a>
                    </div>
                </div>
                <!-- services-item  end-->
            </div>
            <div class="clearfix"></div>
            <div class="contacts-opt fl-wrap">
                <div class="contact-social">
                    <span class="cs-title">Tìm chúng tôi trên:</span>
                    <ul>
                        <li><a href="https://www.facebook.com/dalatbdscom/" target="_blank"><i class="fab fa-facebook-f"></i></a></li>
                        {{-- <li><a href="#" target="_blank"><i class="fab fa-twitter"></i></a></li>
                        <li><a href="#" target="_blank"><i class="fab fa-instagram"></i></a></li>
                        <li><a href="#" target="_blank"><i class="fab fa-vk"></i></a></li> --}}
                    </ul>
                </div>
                {{-- <a href="#" class="btn small-btn float-btn color-bg cf_btn">Gửi Tin Nhắn</a>
                <div class="contact-notifer">Hoặc ghé thăm <a href="help.html">trang trợ giúp</a> của chúng tôi</div> --}}
            </div>
            <!--box-widget  -->
            <div class="box-widget">
                <div class="box-widget-title single_bwt fl-wrap">Địa Điểm Văn Phòng</div>
                <p>Chúng tôi tọa lạc tại một vị trí thuận tiện, dễ dàng tiếp cận và sẵn sàng đón tiếp bạn.</p>
                <!--box-widget end-->
            </div>
            <!--box-widget-->
            <div class="box-widget fl-wrap">
                <div class="map-widget contacts-map fl-wrap">
                    <div class="map-container mapC_vis">
                        <div id="singleMap" data-latitude="11.940419" data-longitude="108.458313"
                            data-infotitle="Văn Phòng Của Chúng Tôi Tại Đà Lạt" data-infotext="Số 123, Đường XYZ, Đà Lạt, Việt Nam"></div>
                        <div class="scrollContorl"></div>
                    </div>
                </div>
            </div>
            <!--box-widget end -->
        </div>
    </section>
    <!-- section end-->
</div>
@endsection
