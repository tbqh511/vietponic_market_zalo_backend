@extends('frontends.master')

@section('content')
<!-- content -->
<div class="content">
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
                    <div class="card-info smpar fl-wrap">
                        <div class="box-widget-menu-btn smact"><i class="far fa-ellipsis-h"></i></div>
                        {{-- <div class="show-more-snopt-tooltip bxwt">
                            <a href="#"> <i class="fas fa-comment-alt"></i> Viết nhận xét</a>
                            <a href="#"> <i class="fas fa-exclamation-triangle"></i> Báo cáo </a>
                        </div> --}}
                        <div class="bg-wrap bg-parallax-wrap-gradien">
                            <div class="bg"  data-bg="{{ asset('images/bg/1.jpg') }}"></div>
                        </div>
                        <div class="card-info-media">
                            <div class="bg"  data-bg="{{$agent->profile}}"></div>
                        </div>
                        <div class="card-info-content">
                            <div class="agent_card-title fl-wrap">
                                <h4> {{$agent->name}} </h4>
                                <div class="geodir-category-location fl-wrap">
                                    <h5><a href="agency-single.html">Mạng lưới thổ địa GreenAllies</a></h5>
                                    <div class="listing-rating card-popup-rainingvis" data-starrating2="4"><span class="re_stars-title">Tốt</span></div>
                                </div>
                            </div>
                            <div class="list-single-stats">
                                <ul class="no-list-style">
                                    <li><span class="viewed-counter"><i class="fas fa-eye"></i> Lượt xem -  156 </span></li>
                                    <li><span class="bookmark-counter"><i class="fas fa-comment-alt"></i> Nhận xét -  4 </span></li>
                                    <li><span class="bookmark-counter"><i class="fas fa-sitemap"></i> Danh sách -  6 </span></li>
                                </ul>
                            </div>
                            <div class="card-verified tolt" data-microtip-position="left" data-tooltip="Đã xác minh"><i class="fal fa-user-check"></i></div>
                        </div>
                    </div>

                    <div class="list-single-main-container fl-wrap">
                        <!-- list-single-main-item -->
                        <div class="list-single-main-item fl-wrap">
                            <div class="list-single-main-item-title">
                                <h3>Đối tác Green Allies</h3>
                            </div>
                            <div class="list-single-main-item_content fl-wrap">
                                <p>Green Allies hân hạnh chào đón đối tác vào hành trình khám phá thị trường bất động sản phát triển của Đà Lạt.</p>
                                <p>Với một nền tảng linh hoạt và các dịch vụ chuyên nghiệp, chúng tôi cam kết mang lại trải nghiệm tuyệt vời và cơ hội kinh doanh đáng giá cho bạn. Hãy cùng nhau tạo ra những thành công mới và phát triển bền vững trong ngành bất động sản Đà Lạt!</p>
                                <div class="list-single-tags fl-wrap tags-stylwrap" style="margin-top: 20px;">
                                    <span>Khu Vực Dịch Vụ:</span>
                                    @foreach($agent->agentWards as $agentWard)
                                        <a href="{{route('properties.index', ['ward'=>$agentWard->code])}}">{{$agentWard->full_name}}</a>
                                    @endforeach
                                </div>
                            </div>
                        </div>
                        <!-- list-single-main-item end -->
                    </div>

                    <!-- content-tabs-wrap -->
                    <div class="content-tabs-wrap tabs-act fl-wrap">
                        <div class="content-tabs fl-wrap">
                            <ul class="tabs-menu fl-wrap no-list-style">
                                <li class="current"><a href="#tab-listing">Danh sách BDS</a></li>
                                {{-- <li class="current"><a href="#tab-listing">Danh sách BDS</a></li>
                                <li><a href="#tab-reviews">Nhận xét</a></li> --}}
                            </ul>
                        </div>

                        <!--tabs -->
                        <div class="tabs-container">
                            <!--tab -->
                            <div class="tab">
                                <div id="tab-listing" class="tab-content first-tab">
                                    <!-- listing-item-wrap-->
                                    <div class="listing-item-container one-column-grid-wrap  box-list_ic fl-wrap">
                                        @foreach($properties as $productItem )
                                        <!-- listing-item -->
                                        @include('frontends.components.product_card',['productCard'=>$productItem ])
                                        <!-- listing-item end-->
                                        @endforeach
                                    </div>
                                    <!-- listing-item-wrap end-->
                                    <!-- pagination-->
                                    <div class="pagination">
                                        @if ($properties->previousPageUrl())
                                            <a href="{{ $properties->previousPageUrl() }}" class="prevposts-link"><i class="fa fa-caret-left"></i></a>
                                        @else
                                            <a href="#" class="prevposts-link disabled"><i class="fa fa-caret-left"></i></a>
                                        @endif

                                        @foreach ($properties->getUrlRange(1, $properties->lastPage()) as $page => $url)
                                            @if ($page == $properties->currentPage())
                                                <a href="#" class="current-page">{{ $page }}</a>
                                            @else
                                                <a href="{{ $url }}">{{ $page }}</a>
                                            @endif
                                        @endforeach

                                        @if ($properties->nextPageUrl())
                                            <a href="{{ $properties->nextPageUrl() }}" class="nextposts-link"><i class="fa fa-caret-right"></i></a>
                                        @else
                                            <a href="#" class="nextposts-link disabled"><i class="fa fa-caret-right"></i></a>
                                        @endif
                                    </div>
                                    <!-- pagination end-->
                                </div>
                            </div>
                            <!--tab  end-->
                            <!--tab -->
                            {{-- <div class="tab">
                                <div id="tab-reviews" class="tab-content">
                                    <div class="list-single-main-container fl-wrap" style="margin-top: 30px;">
                                        <!-- list-single-main-item -->
                                        <div class="list-single-main-item fl-wrap" id="sec6">
                                            <div class="list-single-main-item-title">
                                                <h3>Reviews <span>2</span></h3>
                                            </div>
                                            <div class="list-single-main-item_content fl-wrap">
                                                <div class="reviews-comments-wrap fl-wrap">
                                                    <div class="review-total">
                                                        <span class="review-number blue-bg">5.0</span>
                                                        <div class="listing-rating card-popup-rainingvis" data-starrating2="5"><span class="re_stars-title">Excellent</span></div>
                                                    </div>
                                                    <!-- reviews-comments-item -->
                                                    <div class="reviews-comments-item">
                                                        <div class="review-comments-avatar">
                                                            <img src="images/avatar/1.jpg" alt="">
                                                        </div>
                                                        <div class="reviews-comments-item-text smpar">
                                                            <div class="box-widget-menu-btn smact"><i class="far fa-ellipsis-h"></i></div>
                                                            <div class="show-more-snopt-tooltip bxwt">
                                                                <a href="#"> <i class="fas fa-reply"></i> Reply</a>
                                                                <a href="#"> <i class="fas fa-exclamation-triangle"></i> Report </a>
                                                            </div>
                                                            <h4><a href="#">Liza Rose</a></h4>
                                                            <div class="listing-rating card-popup-rainingvis" data-starrating2="5"><span class="re_stars-title">Excellent</span></div>
                                                            <div class="clearfix"></div>
                                                            <p>" Donec quam felis, ultricies nec, pellentesque eu, pretium quis, sem. Nulla consequat massa quis enim. Donec pede justo, fringilla vel, aliquet nec, vulputate eget, arcu. In enim justo, rhoncus ut, imperdiet a, venenatis vitae, justo. Nullam dictum felis eu pede mollis pretium. "</p>
                                                            <div class="reviews-comments-item-date"><span class="reviews-comments-item-date-item"><i class="far fa-calendar-check"></i>12 April 2018</span><a href="#" class="rate-review"><i class="fal fa-thumbs-up"></i>  Helpful Review  <span>6</span> </a></div>
                                                        </div>
                                                    </div>
                                                    <!--reviews-comments-item end-->
                                                    <!-- reviews-comments-item -->
                                                    <div class="reviews-comments-item">
                                                        <div class="review-comments-avatar">
                                                            <img src="images/avatar/1.jpg" alt="">
                                                        </div>
                                                        <div class="reviews-comments-item-text smpar">
                                                            <div class="box-widget-menu-btn smact"><i class="far fa-ellipsis-h"></i></div>
                                                            <div class="show-more-snopt-tooltip bxwt">
                                                                <a href="#"> <i class="fas fa-reply"></i> Reply</a>
                                                                <a href="#"> <i class="fas fa-exclamation-triangle"></i> Report </a>
                                                            </div>
                                                            <h4><a href="#">Adam Koncy</a></h4>
                                                            <div class="listing-rating card-popup-rainingvis" data-starrating2="5"><span class="re_stars-title">Excellent</span></div>
                                                            <div class="clearfix"></div>
                                                            <p>" Lorem ipsum dolor sit amet, consectetur adipiscing elit. Nunc posuere convallis purus non cursus. Cras metus neque, gravida sodales massa ut. "</p>
                                                            <div class="reviews-comments-item-date"><span class="reviews-comments-item-date-item"><i class="far fa-calendar-check"></i>03 December 2017</span><a href="#" class="rate-review"><i class="fal fa-thumbs-up"></i>  Helpful Review  <span>2</span> </a></div>
                                                        </div>
                                                    </div>
                                                    <!--reviews-comments-item end-->
                                                </div>
                                            </div>
                                        </div>
                                        <!-- list-single-main-item end -->
                                        <!-- list-single-main-item -->
                                        <div class="list-single-main-item fl-wrap" id="sec5">
                                            <div class="list-single-main-item-title fl-wrap">
                                                <h3>Add Your Review</h3>
                                            </div>
                                            <!-- Add Review Box -->
                                            <div id="add-review" class="add-review-box">
                                                <div class="leave-rating-wrap">
                                                    <span class="leave-rating-title">Your rating  for this listing : </span>
                                                    <div class="leave-rating">
                                                        <input type="radio"    data-ratingtext="Excellent"   name="rating" id="rating-1" value="1"/>
                                                        <label for="rating-1" class="fal fa-star"></label>
                                                        <input type="radio" data-ratingtext="Good" name="rating" id="rating-2" value="2"/>
                                                        <label for="rating-2" class="fal fa-star"></label>
                                                        <input type="radio" name="rating"  data-ratingtext="Average" id="rating-3" value="3"/>
                                                        <label for="rating-3" class="fal fa-star"></label>
                                                        <input type="radio" data-ratingtext="Fair" name="rating" id="rating-4" value="4"/>
                                                        <label for="rating-4" class="fal fa-star"></label>
                                                        <input type="radio" data-ratingtext="Very Bad "   name="rating" id="rating-5" value="5"/>
                                                        <label for="rating-5"    class="fal fa-star"></label>
                                                    </div>
                                                    <div class="count-radio-wrapper">
                                                        <span id="count-checked-radio">Your Rating</span>
                                                    </div>
                                                </div>
                                                <!-- Review Comment -->
                                                <form   class="add-comment custom-form">
                                                    <fieldset>
                                                        <div class="row">
                                                            <div class="col-md-6">
                                                                <label>Your name* <span class="dec-icon"><i class="fas fa-user"></i></span></label>
                                                                <input   name="phone" type="text"    onClick="this.select()" value="">
                                                            </div>
                                                            <div class="col-md-6">
                                                                <label>Yourmail* <span class="dec-icon"><i class="fas fa-envelope"></i></span></label>
                                                                <input   name="reviewwname" type="text"    onClick="this.select()" value="">
                                                            </div>
                                                        </div>
                                                        <textarea cols="40" rows="3" placeholder="Your Review:"></textarea>
                                                    </fieldset>
                                                    <button class="btn big-btn color-bg float-btn">Submit Review <i class="fa fa-paper-plane-o" aria-hidden="true"></i></button>
                                                </form>
                                            </div>
                                            <!-- Add Review Box / End -->
                                        </div>
                                        <!-- list-single-main-item end -->
                                    </div>
                                </div>
                            </div> --}}
                            <!--tab end-->
                        </div>
                        <!--tabs end-->
                    </div>
                    <!-- content-tabs-wrap end -->
                </div>
                <!-- col-md 8 end -->
                <!--  sidebar-->
                <div class="col-md-4">
                    <!--box-widget-->
                    {{-- <div class="box-widget bwt-first fl-wrap">
                        <div class="box-widget-title fl-wrap box-widget-title-color color-bg no-top-margin">Agent Contacts</div>
                        <div class="box-widget-content fl-wrap">
                            <div class="contats-list clm fl-wrap">
                                <ul class="no-list-style">
                                    <li><span><i class="fal fa-phone"></i> Phone :</span> <a href="#">+7(123)987654</a></li>
                                    <li><span><i class="fal fa-envelope"></i> Mail :</span> <a href="#">MaversRealEstate@domain.com</a></li>
                                    <li><span><i class="fal fa-map-marker"></i> Adress :</span> <a href="#"> 70 Bright St New York, USA</a></li>
                                    <li><span><i class="fal fa-browser"></i> Website :</span> <a href="#">themeforest.net</a></li>
                                </ul>
                            </div>
                            <div class="profile-widget-footer fl-wrap">
                                <div class="card-info-content_social ">
                                    <ul>
                                        <li><a href="#" target="_blank"><i class="fab fa-facebook-f"></i></a></li>
                                        <li><a href="#" target="_blank"><i class="fab fa-twitter"></i></a></li>
                                        <li><a href="#" target="_blank"><i class="fab fa-instagram"></i></a></li>
                                        <li><a href="#" target="_blank"><i class="fab fa-vk"></i></a></li>
                                    </ul>
                                </div>
                                <a href="#sec-contact" class="custom-scroll-link tolt csls" data-microtip-position="left" data-tooltip="Write Message"><i class="fal fa-paper-plane"></i></a>
                            </div>
                        </div>
                    </div> --}}
                    <!--box-widget end -->
                    <!--box-widget-->
                    <div class="box- bwt-first fl-wrap">
                        <div class="box-widget-fixed-init fl-wrap" id="sec-contact">
                            <div class="box-widget-title fl-wrap box-widget-title-color color-bg no-top-margin">Liên hệ</div>
                            <div class="box-widget-content fl-wrap">
                                <div class="custom-form">
                                    <form method="post" name="contact-property-form">
                                        <label>Tên của bạn* <span class="dec-icon"><i class="fas fa-user"></i></span></label>
                                        <input name="phone" type="text" onClick="this.select()" value="">
                                        <label>Email của bạn* <span class="dec-icon"><i class="fas fa-envelope"></i></span></label>
                                        <input name="mail" type="text" onClick="this.select()" value="">
                                        <textarea cols="40" rows="3" placeholder="Tin nhắn của bạn:" style="height: 150px"></textarea>
                                        <button type="submit" class="btn float-btn color-bg fw-btn"> Gửi</button>
                                    </form>
                                </div>
                            </div>
                        </div>
                    </div>
                    <!--box-widget end -->
                </div>
                <!--   sidebar end-->
            </div>
        </div>
        <div class="limit-box fl-wrap"></div>
    </section>
</div>
<!-- content end -->
@endsection
