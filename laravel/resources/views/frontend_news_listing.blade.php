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
                <h2><span>Tin tức mới nhất</span></h2>
                <h4>Bất Động Sản Đà Lạt</h4>
            </div>
            <div class="scroll-down-wrap">
                <div class="mousey">
                    <div class="scroller"></div>
                </div>
                <span>Scroll Down To Discover</span>
            </div>
        </div>
    </section>
    <!--  section  end-->
    <!-- breadcrumbs-->
    @include('frontends.components.home_breadcrumb', [
    'title' => 'Wiki BDS',
    'nodes' => [
            ['title' => 'Trang chủ', 'url' => route('index')],
        ]
    ])
    <!-- breadcrumbs end -->
    <!-- col-list-wrap -->
    <div class="gray-bg small-padding fl-wrap">
        <div class="container">
            <div class="row">
                <div class="col-md-8">
                    <div class="post-container fl-wrap">
                        @foreach (range(1,3) as $index)
                        <!-- article> -->
                        <article class="post-article fl-wrap">
                            <div class="list-single-main-media fl-wrap">
                                <div class="single-slider-wrapper carousel-wrap fl-wrap">
                                    <div class="single-slider fl-wrap carousel lightgallery">
                                        <!--  slick-slide-item -->
                                        <div class="slick-slide-item">
                                            <div class="box-item">
                                                <a href="images/all/blog/1.jpg" class="gal-link popup-image"><i
                                                        class="fal fa-search"></i></a>
                                                <img src="images/all/blog/1.jpg" alt="">
                                            </div>
                                        </div>
                                        <!--  slick-slide-item end -->
                                        <!--  slick-slide-item -->
                                        <div class="slick-slide-item">
                                            <div class="box-item">
                                                <a href="images/all/blog/1.jpg" class="gal-link popup-image"><i
                                                        class="fal fa-search"></i></a>
                                                <img src="images/all/blog/1.jpg" alt="">
                                            </div>
                                        </div>
                                        <!--  slick-slide-item end -->
                                        <!--  slick-slide-item -->
                                        <div class="slick-slide-item">
                                            <div class="box-item">
                                                <a href="images/all/blog/1.jpg" class="gal-link popup-image"><i
                                                        class="fal fa-search"></i></a>
                                                <img src="images/all/blog/1.jpg" alt="">
                                            </div>
                                        </div>
                                        <!--  slick-slide-item end -->
                                    </div>
                                    <div class="swiper-button-prev ssw-btn"><i class="fas fa-caret-left"></i></div>
                                    <div class="swiper-button-next ssw-btn"><i class="fas fa-caret-right"></i></div>
                                </div>
                            </div>
                            <div class="list-single-main-item fl-wrap block_box">
                                <h2 class="post-opt-title"><a href="blog-single.html">Best House to Your Family .</a>
                                </h2>
                                <p>Ut euismod ultricies sollicitudin. Curabitur sed dapibus nulla. Nulla eget iaculis
                                    lectus. Mauris ac maximus neque. Nam in mauris quis libero sodales eleifend. Morbi
                                    varius, nulla sit amet rutrum elementum, est elit finibus tellus, ut tristique elit
                                    risus at metus.</p>
                                <p>Lorem ipsum dolor sit amet, consectetur adipiscing elit. Maecenas in pulvinar neque.
                                    Nulla finibus lobortis pulvinar. Donec a consectetur nulla. Nulla posuere sapien
                                    vitae lectus suscipit, et pulvinar nisi tincidunt...</p>
                                <span class="fw-separator fl-wrap"></span>
                                <div class="post-author"><a href="#"><img src="images/avatar/1.jpg" alt=""><span>By ,
                                            Alisa Noory</span></a></div>
                                <div class="post-opt">
                                    <ul class="no-list-style">
                                        <li><i class="fal fa-calendar"></i> <span>15 April 2019</span></li>
                                        <li><i class="fal fa-eye"></i> <span>164</span></li>
                                        <li><i class="fal fa-tags"></i> <a href="#">Shop</a> , <a href="#">Hotels</a>
                                        </li>
                                    </ul>
                                </div>
                                <a href="blog-single.html" class="btn color-bg float-btn small-btn">Read more</a>
                            </div>
                        </article>
                        <!-- article end -->
                        @endforeach
                        <!-- pagination-->
                        <div class="pagination">
                            <a href="#" class="prevposts-link"><i class="fa fa-caret-left"></i></a>
                            <a href="#">1</a>
                            <a href="#" class="current-page">2</a>
                            <a href="#">3</a>
                            <a href="#">4</a>
                            <a href="#" class="nextposts-link"><i class="fa fa-caret-right"></i></a>
                        </div>
                        <!-- pagination end-->
                    </div>
                </div>
                <!-- col-md 8 end -->
                <!--  sidebar-->
                <div class="col-md-4">
                    <div class="box-widget-wrap fl-wrap fixed-bar">
                        <!--box-widget-->
                        <div class="box-widget fl-wrap">
                            <div class="search-widget fl-wrap">
                                <form action="#" class="fl-wrap custom-form">
                                    <input name="se" id="se" type="text" class="search" placeholder="Tìm kiếm"
                                        value="" />
                                    <button class="search-submit" id="submit_btn"><i class="far fa-search"></i></button>
                                </form>
                            </div>
                        </div>
                        <!--box-widget end -->
                        <!--box-widget-->
                        {{-- <div class="box-widget fl-wrap">
                            <div class="box-widget-title fl-wrap">Popular Posts</div>
                            <div class="box-widget-content fl-wrap">
                                <!--widget-posts-->
                                <div class="widget-posts  fl-wrap">
                                    <ul class="no-list-style">
                                        <li>
                                            <div class="widget-posts-img"><a href="blog-single.html"><img
                                                        src="images/all/blog/1.jpg" alt=""></a></div>
                                            <div class="widget-posts-descr">
                                                <h4><a href="listing-single.html">Nullam dictum felis</a></h4>
                                                <div class="geodir-category-location fl-wrap"><a href="#"><i
                                                            class="fal fa-calendar"></i> 27 Mar 2020</a></div>
                                            </div>
                                        </li>
                                        <li>
                                            <div class="widget-posts-img"><a href="blog-single.html"><img
                                                        src="images/all/blog/1.jpg" alt=""></a></div>
                                            <div class="widget-posts-descr">
                                                <h4><a href="listing-single.html">Scrambled it to mak</a></h4>
                                                <div class="geodir-category-location fl-wrap"><a href="#"><i
                                                            class="fal fa-calendar"></i> 12 May 2020</a></div>
                                            </div>
                                        </li>
                                        <li>
                                            <div class="widget-posts-img"><a href="blog-single.html"><img
                                                        src="images/all/blog/1.jpg" alt=""></a> </div>
                                            <div class="widget-posts-descr">
                                                <h4><a href="listing-single.html">Fermentum nis type</a></h4>
                                                <div class="geodir-category-location fl-wrap"><a href="#"><i
                                                            class="fal fa-calendar"></i>22 Feb 2020</a></div>
                                            </div>
                                        </li>
                                        <li>
                                            <div class="widget-posts-img"><a href="blog-single.html"><img
                                                        src="images/all/blog/1.jpg" alt=""></a> </div>
                                            <div class="widget-posts-descr">
                                                <h4><a href="listing-single.html">Rutrum elementum</a></h4>
                                                <div class="geodir-category-location fl-wrap"><a href="#"><i
                                                            class="fal fa-calendar"></i> 7 Mar 2019</a></div>
                                            </div>
                                        </li>
                                    </ul>
                                </div>
                                <!-- widget-posts end-->
                            </div>
                        </div> --}}
                        <!--box-widget end -->
                        <!--box-widget-->
                        <div class="box-widget fl-wrap">
                            <div class="box-widget-title fl-wrap">Danh mục</div>
                            <div class="box-widget-content fl-wrap">
                                <ul class="cat-item no-list-style">
                                    <li><a href="#">Tin thị trường</a> <span>3</span></li>
                                    <li><a href="#">Quy hoạch</a> <span>6 </span></li>
                                    <li><a href="#">Chính sách</a> <span>12 </span></li>
                                </ul>
                            </div>
                        </div>
                        <!--box-widget end -->
                        <!--box-widget-->
                        <div class="box-widget fl-wrap">
                            <div class="banner-widget fl-wrap">
                                <div class="bg-wrap bg-parallax-wrap-gradien">
                                    <div class="bg  " data-bg="https://i.pravatar.cc/388"></div>
                                </div>
                                <div class="banner-widget_content">
                                    <h5>Bạn có muốn tham gia mạng lưới thổ địa cùng Green Allies?</h5>
                                    <a href="#" class="btn float-btn color-bg small-btn">Hãy trở thành Đối Tác của Green Allies</a>
                                </div>
                            </div>
                        </div>
                        <!--box-widget end -->
                        <!--box-widget-->
                        <div class="box-widget fl-wrap">
                            <div class="box-widget-title fl-wrap">Tags</div>
                            <div class="box-widget-content fl-wrap">
                                <!--tags-->
                                <div class="list-single-tags fl-wrap tags-stylwrap" style="margin-top: 20px;">
                                    <a href="#">Nhà bán</a>
                                    <a href="#">Đất bán</a>
                                    <a href="#">Nhà Đà Lạt bán</a>
                                    <a href="#">Villa Đà Lạt bán</a>
                                    <a href="#">Nhà phố</a>
                                    <a href="#">Khách sạn bán</a>
                                </div>
                                <!--tags end-->
                            </div>
                        </div>
                        <!--box-widget end -->
                        <!--box-widget-->
                        <div class="box-widget fl-wrap">
                            <div class="box-widget-title fl-wrap">Ngày đăng</div>
                            <div class="box-widget-content fl-wrap">
                                <ul class="cat-item cat-item_dec no-list-style">
                                    <li><a href="#">tháng 6 năm 2023</a></li>
                                    <li><a href="#">tháng 5 năm 2023</a></li>
                                    <li><a href="#">tháng 4 năm 2023</a></li>
                                </ul>
                            </div>
                        </div>
                        <!--box-widget end -->
                    </div>
                    <!-- sidebar end-->
                </div>
            </div>
        </div>
    </div>
    <div class="limit-box fl-wrap"></div>
</div>
@endsection
