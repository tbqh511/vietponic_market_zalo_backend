@extends('frontends.master')
@section('content')
<div class="content">
    <!--  section  -->
    <section class="hidden-section single-par2  " data-scrollax-parent="true">
        <div class="bg-wrap bg-parallax-wrap-gradien">
            <div class="bg par-elem " data-bg="images/bg/1.jpg" data-scrollax="properties: { translateY: '30%' }"></div>
        </div>
        <div class="container">
            <div class="section-title center-align big-title">
                <h2><span>Green Allies</span></h2>
                <h4>Điểm đến tin cậy cho mọi nhu cầu bất động sản tại Đà Lạt.</h4>
            </div>
            <div class="scroll-down-wrap">
                <div class="mousey">
                    <div class="scroller"></div>
                </div>
                <span>Kéo xuống để khám phá</span>
            </div>
        </div>
    </section>
    <!--  section  end-->
    <!-- breadcrumbs-->
    <div class="breadcrumbs fw-breadcrumbs sp-brd fl-wrap">
        <div class="container">
            <div class="breadcrumbs-list">
                <a href="/">Trang chủ</a> <span>Green Allies</span>
            </div>
            <div class="share-holder hid-share">
                <a href="#" class="share-btn showshare sfcs"> <i class="fas fa-share-alt"></i> Chia sẻ </a>
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
                        <i class="fal fa-phone-laptop"></i>
                        <h4>Công nghệ tiên tiến <span>02</span></h4>
                        <p>Ứng dụng công nghệ hiện đại, mang đến trải nghiệm tìm kiếm, giao dịch bất động sản nhanh chóng, thuận tiện và an toàn.</p>
                        {{-- <a href="#" class="serv-link">Read more</a> --}}
                    </div>
                </div>
                <!-- services-item  end-->
                <!-- services-item -->
                <div class="col-md-4">
                    <div class="services-item fl-wrap">
                        <i class="fal fa-headset"></i>
                        <h4>Thông tin minh bạch <span>01</span></h4>
                        <p>Cung cấp thông tin thị trường bất động sản Đà Lạt chính xác, đáng tin cậy, giúp bạn đưa ra quyết định đầu tư sáng suốt.</p>
                        {{-- <a href="#" class="serv-link">Read more</a> --}}
                    </div>
                </div>
                <!-- services-item  end-->
                <!-- services-item -->
                <div class="col-md-4">
                    <div class="services-item fl-wrap">
                        <i class="fal fa-users-cog"></i>
                        <h4>Đồng hành tin cậy <span>03</span></h4>
                        <p>Đội ngũ chuyên gia giàu kinh nghiệm luôn sẵn sàng hỗ trợ, tư vấn và đồng hành cùng bạn trong suốt quá trình giao dịch.</p>
                        {{-- <a href="#" class="serv-link">Read more</a> --}}
                    </div>
                </div>
                <!-- services-item  end-->
            </div>
        </div>
    </section>
    <!-- section end-->
    <!-- section -->
    <section>
        <div class="container">
            <!--about-wrap -->
            <div class="about-wrap">
                <div class="row">
                    <div class="col-md-5">
                        <div class="about-title fl-wrap">
                            <h2>Hành trình  <span>Dalatbds</span></h2>
                            <h4>Nâng tầm giá trị bất động sản Đà Lạt.</h4>
                        </div>
                        <p>Dalatbds không chỉ đơn thuần là một nền tảng công nghệ, mà còn là minh chứng cho sự nỗ lực không ngừng nghỉ trong việc kiến tạo một thị trường bất động sản minh bạch, hiệu quả và bền vững tại Đà Lạt.</p>
                        <p>Chúng tôi hiểu rằng, mỗi giao dịch bất động sản đều chứa đựng những kỳ vọng và giá trị to lớn. Chính vì vậy, Dalatbds ra đời với sứ mệnh đồng hành cùng quý khách hàng, từ chủ sở hữu bất động sản đến các chuyên gia môi giới, trên mọi chặng đường từ đăng tin, định giá, kết nối đối tác đến hỗ trợ pháp lý chuyên sâu.</p>
                        <p>Với nền tảng công nghệ tiên tiến, kết hợp cùng đội ngũ chuyên gia giàu kinh nghiệm và am hiểu thị trường, Dalatbds không ngừng cải tiến để mang đến những trải nghiệm vượt trội, đáp ứng tối đa nhu cầu của quý khách hàng. Mỗi tính năng trên nền tảng đều được phát triển dựa trên sự thấu hiểu sâu sắc về thị trường và mong muốn của quý khách.</p>
                        {{-- <a href="#" class="btn small-btn float-btn color-bg">Read More</a> --}}
                    </div>
                    <div class="col-md-1"></div>
                    <div class="col-md-6">
                        <div class="about-img fl-wrap">
                            <img src="/images/bg/about.jpg" class="respimg" alt="Green Allies">
                            <div class="about-img-hotifer color-bg">
                                <p>Green Allies - Hợp tác để kiến tạo thị trường bất động sản Đà Lạt minh bạch, bền vững và thịnh vượng.</p>
                                <h4>Tâm Võ</h4>
                                <h5>Green Allies CEO</h5>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <!-- about-wrap end  -->
        </div>
    </section>
    <!-- section end-->
    <!-- section -->
    @include('frontends.components.home_report_info',['infos' => $infos])
    <!-- section end-->
    <!-- section -->
    <section>
        <div class="container">
            <!-- section-title -->
            <div class="section-title st-center fl-wrap">
                <h4>Đội ngũ</h4>
                <h2>Đội ngũ tuyệt vời của chúng tôi</h2>
            </div>
            <!-- section-title end -->
            <div class="clearfix"></div>
            <div class="row">
                <!-- team-item -->
                <div class="col-md-4">
                    <div class="team-item fl-wrap">
                        <div class="team-img fl-wrap">
                            <img src="images/agency/agent/2.jpg" class="respimg" alt="">
                        </div>
                        <div class="team-content fl-wrap">
                            <h4>Huy Thái</h4>
                            <h5>CEO / Developer </h5>
                            <p>
                                "Đam mê công nghệ và khát khao đổi mới là động lực để chúng tôi kiến tạo những giải pháp vượt trội, góp phần xây dựng một tương lai tốt đẹp hơn."
                            </p>
                        </div>
                        <div class="team-footer fl-wrap">
                            <ul class="team-social">
                                <li><a href="https://www.facebook.com/tbqh511/" target="_blank"><i class="fab fa-facebook-f"></i></a></li>
                                {{-- <li><a href="#" target="_blank"><i class="fab fa-twitter"></i></a></li>
                                <li><a href="#" target="_blank"><i class="fab fa-instagram"></i></a></li>
                                <li><a href="#" target="_blank"><i class="fab fa-vk"></i></a></li> --}}
                            </ul>
                            <a href="mailto:yourmail@email.com" class="tolt tf-btn" data-microtip-position="top-right"
                                data-tooltip="Write Message"><i class="fal fa-envelope"></i></a>
                        </div>
                    </div>
                </div>
                <!-- team-item end -->
                <!-- team-item -->
                <div class="col-md-4">
                    <div class="team-item fl-wrap">
                        <div class="team-img fl-wrap">
                            <img src="images/agency/agent/2.jpg" class="respimg" alt="">
                        </div>
                        <div class="team-content fl-wrap">
                            <h4>Huy Thái</h4>
                            <h5>CEO / Developer </h5>
                            <p>
                                "Đam mê công nghệ và khát khao đổi mới là động lực để chúng tôi kiến tạo những giải pháp vượt trội, góp phần xây dựng một tương lai tốt đẹp hơn."
                            </p>
                        </div>
                        <div class="team-footer fl-wrap">
                            <ul class="team-social">
                                <li><a href="https://www.facebook.com/tbqh511/" target="_blank"><i class="fab fa-facebook-f"></i></a></li>
                                {{-- <li><a href="#" target="_blank"><i class="fab fa-twitter"></i></a></li>
                                <li><a href="#" target="_blank"><i class="fab fa-instagram"></i></a></li>
                                <li><a href="#" target="_blank"><i class="fab fa-vk"></i></a></li> --}}
                            </ul>
                            <a href="mailto:tbqh0511@gmail.com" class="tolt tf-btn" data-microtip-position="top-right"
                                data-tooltip="Write Message"><i class="fal fa-envelope"></i></a>
                        </div>
                    </div>
                </div>
                <!-- team-item end -->
                <!-- team-item -->
                <div class="col-md-4">
                    <div class="team-item fl-wrap">
                        <div class="team-img fl-wrap">
                            <img src="images/agency/agent/2.jpg" class="respimg" alt="">
                        </div>
                        <div class="team-content fl-wrap">
                            <h4>Huy Thái</h4>
                            <h5>CEO / Developer </h5>
                            <p>
                                "Đam mê công nghệ và khát khao đổi mới là động lực để chúng tôi kiến tạo những giải pháp vượt trội, góp phần xây dựng một tương lai tốt đẹp hơn."
                            </p>
                        </div>
                        <div class="team-footer fl-wrap">
                            <ul class="team-social">
                                <li><a href="https://www.facebook.com/tbqh511/" target="_blank"><i class="fab fa-facebook-f"></i></a></li>
                                {{-- <li><a href="#" target="_blank"><i class="fab fa-twitter"></i></a></li>
                                <li><a href="#" target="_blank"><i class="fab fa-instagram"></i></a></li>
                                <li><a href="#" target="_blank"><i class="fab fa-vk"></i></a></li> --}}
                            </ul>
                            <a href="mailto:yourmail@email.com" class="tolt tf-btn" data-microtip-position="top-right"
                                data-tooltip="Write Message"><i class="fal fa-envelope"></i></a>
                        </div>
                    </div>
                </div>
                <!-- team-item end -->
            </div>
        </div>
    </section>
    <!-- section end-->
    <!--section  -->
    <section class="parallax-section ps-bg video-section" data-scrollax-parent="true" id="sec2">
        <div class="bg-wrap">
            <div class="bg par-elem " data-bg="images/bg/1.jpg" data-scrollax="properties: { translateY: '30%' }"></div>
        </div>
        <div class="overlay"></div>
        <!--container-->
        <div class="container">
            <div class="video_section-title fl-wrap">
                <h2>Video Câu Chuyện Của Chúng Tôi</h2>
                <h4>Dalatbds - Đồng hành cùng bạn trên mọi chặng đường, kiến tạo thị trường bất động sản minh bạch và bền vững."</h4>
            </div>
            <a href="https://vimeo.com/158059890" class="promo-link big_prom color-bg   image-popup"><i
                    class="fas fa-play"></i></a>
        </div>
    </section>
    <!--section end-->
    <!-- section -->
    @include('frontends.components.home_client_say')
    <!-- section end-->
</div>
@endsection
