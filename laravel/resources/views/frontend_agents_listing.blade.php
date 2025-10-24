@extends('frontends.master')

@section('content')
<!-- content -->
<div class="content">
    <!--  section  -->
    <section class="parallax-section single-par color-bg">
        <div class="container">
            <div class="section-title center-align big-title">
                <h2><span>Danh Sách Các đối tác của Green Allies</span></h2>
                <h4>Kết nối ước mơ - Green Allies: Nơi hội tụ của sự đam mê và thành công!</h4>
            </div>
        </div>
        <div class="pwh_bg"></div>
        <div class="mrb_pin vis_mr mrb_pin3 "></div>
        <div class="mrb_pin vis_mr mrb_pin4 "></div>
    </section>
    <!--  section  end-->
    <!-- breadcrumbs-->
    @include('frontends.components.home_breadcrumb', [
    'title' => 'Đối tác',
    'nodes' => [
    ['title' => 'Trang chủ', 'url' => route('index')],
    ]
    ])
    <!-- breadcrumbs end -->
    <!-- col-list-wrap -->
    <section class="gray-bg small-padding ">
        <div class="container">
            <div class="row">
                <div class="col-md-8">
                    <!-- list-main-wrap-header-->
                    <div class="list-main-wrap-header box-list-header fl-wrap">
                        <!-- list-main-wrap-title-->
                        {{-- <div class="list-main-wrap-title">
                            <h2>Results For : <span>New York Agents </span><strong>26</strong></h2>
                        </div> --}}
                        <!-- list-main-wrap-title end-->
                        <!-- list-main-wrap-opt-->
                        <div class="list-main-wrap-opt">
                            <!-- price-opt-->
                            <div class="price-opt">
                                <span class="price-opt-title">Sắp xếp theo:</span>
                                <div class="listsearch-input-item">
                                    <select data-placeholder="Phổ biến" class="chosen-select no-search-select">
                                        <option>Phổ biến</option>
                                        <option>Đánh giá trung bình</option>
                                        <option>Tên: A-Z</option>
                                        <option>Tên: Z-A</option>
                                    </select>
                                </div>
                            </div>
                            <!-- price-opt end-->
                        </div>
                        <!-- list-main-wrap-opt end-->
                    </div>
                    <!-- list-main-wrap-header end-->
                    <!-- listing-item-wrap-->
                    <div class="listing-item-container  box-list_ic fl-wrap">
                        <!--  agent card item -->
                        @foreach ($agents as $agent)
                        <div class="listing-item">
                            <article class="geodir-category-listing fl-wrap">
                                <div class="geodir-category-img fl-wrap  agent_card">
                                    <a href="agent-single.html" class="geodir-category-img_item">
                                        <img src="images/agency/agent/1.jpg" alt="">
                                        <ul class="list-single-opt_header_cat">
                                            <li><span class="cat-opt color-bg">4 bài đăng</span></li>
                                        </ul>
                                    </a>
                                    {{-- <div class="agent-card-social fl-wrap">
                                        <ul>
                                            <li><a href="#" target="_blank"><i class="fab fa-facebook-f"></i></a></li>
                                            <li><a href="#" target="_blank"><i class="fab fa-twitter"></i></a></li>
                                            <li><a href="#" target="_blank"><i class="fab fa-instagram"></i></a></li>
                                            <li><a href="#" target="_blank"><i class="fab fa-vk"></i></a></li>
                                        </ul>
                                    </div> --}}
                                    <div class="listing-rating card-popup-rainingvis" data-starrating2="4"><span
                                            class="re_stars-title">Tốt</span></div>
                                </div>
                                <div class="geodir-category-content fl-wrap">
                                    <div class="card-verified tolt" data-microtip-position="left"
                                        data-tooltip="Đã xác minh"><i class="fal fa-user-check"></i></div>
                                    <div class="agent_card-title fl-wrap">
                                        <h4><a href="agent-single.html">{{$agent->name}}</a></h4>
                                        <h5><a href="agency-single.html">Công ty bất động sản Mavers</a></h5>
                                    </div>
                                    {{-- <p>Lorem ipsum dolor sit amet, consectetur adipiscing elit. Maecenas in pulvinar
                                        neque. Nulla finibus lobortis pulvinar. Donec a consectetur nulla.</p> --}}
                                    <div class="geodir-category-footer fl-wrap">
                                        <a href="agent-single.html" class="btn float-btn color-bg small-btn">Xem
                                            hồ sơ</a>
                                        <a href="mailto:yourmail@email.com" class="tolt ftr-btn"
                                            data-microtip-position="left" data-tooltip="Viết tin nhắn"><i
                                                class="fal fa-envelope"></i></a>
                                        <a href="tel:123-456-7890" class="tolt ftr-btn" data-microtip-position="left"
                                            data-tooltip="Gọi ngay"><i class="fal fa-phone"></i></a>
                                    </div>
                                </div>
                            </article>
                        </div>

                        @endforeach
                        <!--  agent card item end -->
                    </div>
                    <!-- listing-item-wrap end-->
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
                <!-- col-md 8 end -->
                <!-- search sidebar-->
                <div class="col-md-4">
                    <div class="list-searh-input-wrap-title fl-wrap"><i class="far fa-sliders-h"></i><span>Tìm Kiếm Đối
                            Tác</span></div>

                    <div class="block-box fl-wrap search-sb" id="filters-column">
                        <!-- Mục nhập từ khóa -->
                        <div class="listsearch-input-item">
                            <label>Từ khóa</label>
                            <input type="text" onClick="this.select()" placeholder="Tên, đại lý..." value="" />
                        </div>
                        <!-- Kết thúc mục nhập từ khóa -->
                        <!-- Mục nhập thành phố -->
                        <div class="listsearch-input-item">
                            <label>Thành phố</label>
                            <select data-placeholder="Tất cả thành phố"
                                class="chosen-select on-radius no-search-select">
                                <option>Tất cả thành phố</option>
                                <option>New York</option>
                                <option>London</option>
                                <option>Paris</option>
                                <option>Kiev</option>
                                <option>Moscow</option>
                                <option>Dubai</option>
                                <option>Rome</option>
                                <option>Beijing</option>
                            </select>
                        </div>
                        <!-- Kết thúc mục nhập thành phố -->
                        <!-- Mục nhập khoảng giá -->
                        <div class="listsearch-input-item">
                            <div class="price-rage-item fl-wrap">
                                <span class="pr_title">Đánh giá:</span>
                                <input type="text" class="price-range-double" data-min="1" data-max="5"
                                    name="price-range2" data-step="1" value="1" data-prefix="*">
                            </div>
                        </div>
                        <!-- Kết thúc mục nhập khoảng giá -->
                        <div class="msotw_footer">
                            <a href="#" class="btn small-btn float-btn color-bg">Tìm Kiếm Đại Lý</a>
                            <div class="reset-form reset-btn"> <i class="far fa-sync-alt"></i> Đặt lại bộ lọc</div>
                        </div>
                    </div>

                    <!--box-widget-->
                    <div class="box-widget fl-wrap">
                        <div class="box-widget-title fl-wrap">Đối Tác Nổi Bật</div>
                        <div class="box-widget-content fl-wrap">
                            <!--widget-posts-->
                            <div class="widget-posts fl-wrap">
                                <ul class="no-list-style">
                                    <li>
                                        <div class="widget-posts-img">
                                            <a href="agent-single.html"><img src="images/agency/agent/1.jpg" alt=""></a>
                                        </div>
                                        <div class="widget-posts-descr agent-post_descr">
                                            <h4><a href="agent-single.html">Liza Rose</a></h4>
                                            <div class="agent-post_descr_counter fl-wrap"><span>21</span> Danh Sách Bất
                                                Động Sản</div>
                                            <div class="listing-rating card-popup-rainingvis" data-starrating2="4">
                                            </div>
                                            <a href="mailto:yourmail@email.com" class="tolt ftr-btn"
                                                data-microtip-position="top-left" data-tooltip="Gửi Tin Nhắn"><i
                                                    class="fal fa-envelope"></i></a>
                                            <a href="tel:123-456-7890" class="tolt ftr-btn"
                                                data-microtip-position="top-left" data-tooltip="Gọi Ngay"><i
                                                    class="fal fa-phone"></i></a>
                                        </div>
                                    </li>
                                    <li>
                                        <div class="widget-posts-img">
                                            <a href="agent-single.html"><img src="images/agency/agent/1.jpg" alt=""></a>
                                        </div>
                                        <div class="widget-posts-descr agent-post_descr">
                                            <h4><a href="agent-single.html">Martin Smith</a></h4>
                                            <div class="agent-post_descr_counter fl-wrap"><span>5</span> Danh Sách Bất
                                                Động Sản</div>
                                            <div class="listing-rating card-popup-rainingvis" data-starrating2="5">
                                            </div>
                                            <a href="mailto:yourmail@email.com" class="tolt ftr-btn"
                                                data-microtip-position="top-left" data-tooltip="Gửi Tin Nhắn"><i
                                                    class="fal fa-envelope"></i></a>
                                            <a href="tel:123-456-7890" class="tolt ftr-btn"
                                                data-microtip-position="top-left" data-tooltip="Gọi Ngay"><i
                                                    class="fal fa-phone"></i></a>
                                        </div>
                                    </li>
                                    <li>
                                        <div class="widget-posts-img">
                                            <a href="agent-single.html"><img src="images/agency/agent/1.jpg" alt=""></a>
                                        </div>
                                        <div class="widget-posts-descr agent-post_descr">
                                            <h4><a href="agent-single.html">Andy Sposty</a></h4>
                                            <div class="agent-post_descr_counter fl-wrap"><span>10</span> Danh Sách Bất
                                                Động Sản</div>
                                            <div class="listing-rating card-popup-rainingvis" data-starrating2="4">
                                            </div>
                                            <a href="mailto:yourmail@email.com" class="tolt ftr-btn"
                                                data-microtip-position="top-left" data-tooltip="Gửi Tin Nhắn"><i
                                                    class="fal fa-envelope"></i></a>
                                            <a href="tel:123-456-7890" class="tolt ftr-btn"
                                                data-microtip-position="top-left" data-tooltip="Gọi Ngay"><i
                                                    class="fal fa-phone"></i></a>
                                        </div>
                                    </li>
                                    <li>
                                        <div class="widget-posts-img">
                                            <a href="agent-single.html"><img src="images/agency/agent/1.jpg" alt=""></a>
                                        </div>
                                        <div class="widget-posts-descr agent-post_descr">
                                            <h4><a href="agent-single.html">Anna Lips</a></h4>
                                            <div class="agent-post_descr_counter fl-wrap"><span>12</span> Danh Sách Bất
                                                Động Sản</div>
                                            <div class="listing-rating card-popup-rainingvis" data-starrating2="5">
                                            </div>
                                            <a href="mailto:yourmail@email.com" class="tolt ftr-btn"
                                                data-microtip-position="top-left" data-tooltip="Gửi Tin Nhắn"><i
                                                    class="fal fa-envelope"></i></a>
                                            <a href="tel:123-456-7890" class="tolt ftr-btn"
                                                data-microtip-position="top-left" data-tooltip="Gọi Ngay"><i
                                                    class="fal fa-phone"></i></a>
                                        </div>
                                    </li>
                                </ul>
                            </div>
                            <!-- widget-posts end-->
                            <a href="listing.html" class="btn float-btn color-bg small-btn">Xem Tất Cả Đại Lý</a>
                        </div>
                    </div>

                    <!--box-widget end -->
                    <!--box-widget-->
                    <div class="box-widget fl-wrap">
                        <div class="banner-widget fl-wrap">
                            <div class="bg-wrap bg-parallax-wrap-gradien">
                                <div class="bg" data-bg="images/all/blog/1.jpg"></div>
                            </div>
                            <div class="banner-widget_content">
                                <h5>Bạn muốn tham gia mạng lưới bất động sản của chúng tôi?</h5>
                                <a href="#" class="btn float-btn color-bg small-btn">Trở thành Đại lý</a>
                            </div>
                        </div>
                    </div>
                    <!--box-widget end -->
                </div>
                <!-- search sidebar end-->
            </div>
        </div>
    </section>
    <div class="limit-box fl-wrap"></div>
</div>
<!-- content end -->
@endsection
