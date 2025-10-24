<header class="main-header">
    <!--  logo  -->
    <div class="logo-holder"><a href="{{ route('index')}}"><img src="{{asset('images/logo.svg')}}"
                alt="Đà Lạt Bất Động Sản"></a></div>
    <!-- logo end  -->
    <!-- nav-button-wrap-->
    <div class="nav-button-wrap color-bg nvminit">
        <div class="nav-button">
            <span></span><span></span><span></span>
        </div>
    </div>
    <!-- nav-button-wrap end-->
    <!-- header-search button  -->
    <div class="header-search-button">
        <i class="fal fa-search"></i>
        <span>Tìm nhanh</span>
    </div>
    <!-- header-search button end  -->
    <!--  add new  btn -->
    {{-- <div class="add-list_wrap">
        <a href="/dang-tin" class="add-list color-bg"><i class="fal fa-plus"></i> <span>Đăng tin</span></a>
    </div> --}}
    <!--  add new  btn end -->
    <!--  header-opt_btn -->
    {{-- <div class="header-opt_btn tolt" data-microtip-position="bottom" data-tooltip="Language / Currency">
        <span><i class="fal fa-globe"></i></span>
    </div> --}}
    <!--  header-opt_btn end -->
    <!--  cart-btn   -->
    {{-- <div class="cart-btn  tolt show-header-modal" data-microtip-position="bottom"
        data-tooltip="Your Wishlist / Compare">
        <i class="fal fa-bell"></i>
        <span class="cart-btn_counter color-bg">5</span>
    </div> --}}
    <!--  cart-btn end -->
    <!--  login btn -->
    {{-- <div class="show-reg-form modal-open"><i class="fas fa-user"></i><span>Đăng nhập</span></div> --}}
    <!--  login btn  end -->
    <!--  navigation -->
    <div class="nav-holder main-menu">
        <nav>
            <ul class="no-list-style">
                <li>
                    <a href="/" class="act-link">Trang chủ <i class="fa"></i></a>
                </li>
                <li>
                    <a href="{{ route('properties.index', ['propery_type' => 0]) }}">Bán <i class="fa "></i></a>
                </li>
                <li>
                    <a href="{{ route('properties.index', ['propery_type' => 1]) }}">Cho thuê <i class="fa "></i></a>
                </li>
                {{-- <li>
                    <a href="#">Listings <i class="fa fa-caret-down"></i></a>
                    <!--second level -->
                    <ul>
                        <li><a href="listing.html">Column map</a></li>
                        <li><a href="listing2.html">Column map 2</a></li>
                        <li><a href="listing3.html">Fullwidth Map</a></li>
                        <li><a href="listing4.html">Fullwidth Map 2</a></li>
                        <li><a href="listing5.html">Without Map</a></li>
                        <li><a href="listing6.html">Without Map 2</a></li>
                        <li>
                            <a href="#">Single <i class="fa fa-caret-down"></i></a>
                            <!--third  level  -->
                            <ul>
                                <li><a href="listing-single.html">Style 1</a></li>
                                <li><a href="listing-single2.html">Style 2</a></li>
                                <li><a href="listing-single3.html">Style 3</a></li>
                            </ul>
                            <!--third  level end-->
                        </li>
                    </ul>
                    <!--second level end-->
                </li> --}}
                {{-- <li>
                    <a href="#">Agents<i class="fa fa-caret-down"></i></a>
                    <!--second level -->
                    <ul>
                        <li><a href="agent-list.html">Agent List</a></li>
                        <li><a href="agency-list.html">Agency List</a></li>
                        <li><a href="agent-single.html">Agent Single</a></li>
                        <li><a href="agency-single.html">Agency Single</a></li>
                    </ul>
                    <!--second level end-->
                </li> --}}
                {{-- <li>
                    <a href="{{ route('news.index') }}">Wiki BĐS</a>
                </li>
                <li>
                    <a href="{{ route('agents.index') }}">Đối tác</a>
                </li> --}}
                <li><a href="{{ url('/gioi-thieu') }}">Green Allies</a></li>
                <li><a href="{{ url('/lien-he') }}">Liên hệ</a></li>
            </ul>
        </nav>
    </div>
    <!-- navigation  end -->
    <!-- header-search-wrapper -->
    <div class="header-search-wrapper novis_search">
        {{-- <div class="header-serach-menu">
            <div class="custom-switcher fl-wrap">
                <div class="fieldset fl-wrap">
                    <input type="radio" name="duration-1" id="buy_sw" class="tariff-toggle" checked>
                    <label for="buy_sw">Buy</label>
                    <input type="radio" name="duration-1" class="tariff-toggle" id="rent_sw">
                    <label for="rent_sw" class="lss_lb">Rent</label>
                    <span class="switch color-bg"></span>
                </div>
            </div>
        </div> --}}
        <div class="custom-form">
            <form method="GET" name="registerform" action="{{ route('properties.index') }}">
                <label>Cho thuê & Bán</label>
                <select name="propery_type" data-placeholder="Tình trạng" class="chosen-select on-radius no-search-select" style="z-index: 1;">
                    <option value="">Cho thuê & Bán</option>
                    <option value="0" {{ request()->input('propery_type') == '0' ? 'selected' :
                        ''}}>Bán</option>
                    <option value="1" {{ request()->input('propery_type') == '1' ? 'selected' :
                        ''}}>Cho Thuê</option>
                </select>
                <label>Loại BDS</label>
                <select name="category" data-placeholder="Loại BDS" class="chosen-select on-radius no-search-select" style="z-index: 1;">
                    <option value="">Loại BDS</option>
                    @isset($categories)
                    @foreach ($categories as $categorie)
                    <option value="{{ $categorie->category }}" {{ request()->input('category') == $categorie->category ? 'selected' : ''
                        }}>
                        {{ $categorie->category }}
                    </option>
                    @endforeach
                    @else
                    <!-- Xử lý trường hợp biến $categories không tồn tại hoặc là null -->
                    <option value="" disabled>Không có dữ liệu</option>
                    @endisset
                </select>

                <label>BDS Phướng / Xã</label>
                <select name="ward" data-placeholder="Phường Xã" class="chosen-select on-radius no-search-select" style="z-index: 1;">
                    <option value="">Phường Xã</option>
                    @isset($locationsWards)
                    @foreach ($locationsWards as $locationsWard)
                    <option value="{{$locationsWard->code}}" {{ request()->input('ward') == $locationsWard->code ? 'selected' : '' }}>
                        {{$locationsWard->full_name}}
                    </option>
                    @endforeach
                    @else
                    <!-- Xử lý trường hợp biến $locationsWards không tồn tại hoặc là null -->
                    <option value="" disabled>Không có dữ liệu</option>
                    @endisset
                </select>
                <label style="margin-top:10px;">Mức giá</label>
                <div class="price-range-item fl-wrap">
                    <input name="price-range2" type="text" class="price-range-double" data-min="100000000"
                        data-max="{{config('global.max_price')}}" data-step="100000000"
                        value="{{ request()->input('price-range2') }}" max_postfix="+">
                </div>


                <label>Tìm mã BDS </label>
                <input name="text" type="text" placeholder="Tìm BDS" value="{{ request()->input('text') }}" />
                <button type="submit" class="btn color-bg fw-btn float-btn small-btn">Tìm
                    kiếm</button>
            </form>
        </div>
    </div>
    <!-- header-search-wrapper end  -->
    <!-- wishlist-wrap-->
    <div class="header-modal novis_wishlist tabs-act">
        <ul class="tabs-menu fl-wrap no-list-style">
            <li class="current"><a href="#tab-wish"> Wishlist <span>- 3</span></a></li>
            <li><a href="#tab-compare"> Compare <span>- 2</span></a></li>
        </ul>
        <!--tabs -->
        <div class="tabs-container">
            <div class="tab">
                <!--tab -->
                <div id="tab-wish" class="tab-content first-tab">
                    <!-- header-modal-container-->
                    <div class="header-modal-container scrollbar-inner fl-wrap" data-simplebar>
                        <!--widget-posts-->
                        <div class="widget-posts  fl-wrap">
                            <ul class="no-list-style">
                                <li>
                                    <div class="widget-posts-img"><a href="listing-single.html"><img
                                                src="{{asset('images/all/small/1.jpg')}} " alt=""></a>
                                    </div>
                                    <div class="widget-posts-descr">
                                        <h4><a href="listing-single.html">Affordable Urban Room</a></h4>
                                        <div class="geodir-category-location fl-wrap"><a href="#"><i
                                                    class="fas fa-map-marker-alt"></i> 40 Journal Square , NJ, USA</a>
                                        </div>
                                        <div class="widget-posts-descr-price"><span>Price: </span> $ 1500 / per month
                                        </div>
                                        <div class="clear-wishlist"><i class="fal fa-trash-alt"></i></div>
                                    </div>
                                </li>
                                <li>
                                    <div class="widget-posts-img"><a href="listing-single.html"><img
                                                src="{{asset('images/all/small/1.jpg')}}" alt=""></a>
                                    </div>
                                    <div class="widget-posts-descr">
                                        <h4><a href="listing-single.html">Family House</a></h4>
                                        <div class="geodir-category-location fl-wrap"><a href="#"><i
                                                    class="fas fa-map-marker-alt"></i> 34-42 Montgomery St , NY, USA</a>
                                        </div>
                                        <div class="widget-posts-descr-price"><span>Price: </span> $ 50.000</div>
                                        <div class="clear-wishlist"><i class="fal fa-trash-alt"></i></div>
                                    </div>
                                </li>
                                <li>
                                    <div class="widget-posts-img"><a href="listing-single.html"><img
                                                src="{{asset('images/all/small/1.jpg')}}" alt=""></a>
                                    </div>
                                    <div class="widget-posts-descr">
                                        <h4><a href="listing-single.html">Apartment to Rent</a></h4>
                                        <div class="geodir-category-location fl-wrap"><a href="#"><i
                                                    class="fas fa-map-marker-alt"></i>75 Prince St, NY, USA</a></div>
                                        <div class="widget-posts-descr-price"><span>Price: </span> $100 / per night
                                        </div>
                                        <div class="clear-wishlist"><i class="fal fa-trash-alt"></i></div>
                                    </div>
                                </li>
                            </ul>
                        </div>
                        <!-- widget-posts end-->
                    </div>
                    <!-- header-modal-container end-->
                    <div class="header-modal-top fl-wrap">
                        <div class="clear_wishlist color-bg"><i class="fal fa-trash-alt"></i> Clear all</div>
                    </div>
                </div>
                <!--tab end -->
                <!--tab -->
                <div class="tab">
                    <div id="tab-compare" class="tab-content">
                        <!-- header-modal-container-->
                        <div class="header-modal-container scrollbar-inner fl-wrap" data-simplebar>
                            <!--widget-posts-->
                            <div class="widget-posts  fl-wrap">
                                <ul class="no-list-style">
                                    <li>
                                        <div class="widget-posts-img"><a href="listing-single.html"><img
                                                    src="{{asset('images/all/small/1.jpg')}}" alt=""></a>
                                        </div>
                                        <div class="widget-posts-descr">
                                            <h4><a href="listing-single.html">Gorgeous house for sale</a></h4>
                                            <div class="geodir-category-location fl-wrap"><a href="#"><i
                                                        class="fas fa-map-marker-alt"></i> 70 Bright St New York, USA
                                                </a></div>
                                            <div class="widget-posts-descr-price"><span>Price: </span> $ 52.100</div>
                                            <div class="clear-wishlist"><i class="fal fa-trash-alt"></i></div>
                                        </div>
                                    </li>
                                    <li>
                                        <div class="widget-posts-img"><a href="listing-single.html"><img
                                                    src="{{asset('images/all/small/1.jpg')}}" alt=""></a>
                                        </div>
                                        <div class="widget-posts-descr">
                                            <h4><a href="listing-single.html">Family Apartments</a></h4>
                                            <div class="geodir-category-location fl-wrap"><a href="#"><i
                                                        class="fas fa-map-marker-alt"></i> W 85th St, New York, USA </a>
                                            </div>
                                            <div class="widget-posts-descr-price"><span>Price: </span> $ 72.400</div>
                                            <div class="clear-wishlist"><i class="fal fa-trash-alt"></i></div>
                                        </div>
                                    </li>
                                </ul>
                            </div>
                            <!-- widget-posts end-->
                        </div>
                        <!-- header-modal-container end-->
                        <div class="header-modal-top fl-wrap">
                            <a class="clear_wishlist color-bg" href="compare.html"><i class="fal fa-random"></i>
                                Compare</a>
                        </div>
                    </div>
                </div>
                <!--tab end -->
            </div>
            <!--tabs end -->
        </div>
    </div>
    <!--wishlist-wrap end -->
    <!--header-opt-modal-->
    {{-- <div class="header-opt-modal novis_header-mod">
        <div class="header-opt-modal-container hopmc_init">
            <div class="header-opt-modal-item lang-item fl-wrap">
                <h4>Language: <span>EN</span></h4>
                <div class="header-opt-modal-list fl-wrap">
                    <ul>
                        <li><a href="#" class="current-lan" data-lantext="EN">English</a></li>
                        <li><a href="#" data-lantext="FR">Franais</a></li>
                        <li><a href="#" data-lantext="ES">Espaol</a></li>
                        <li><a href="#" data-lantext="DE">Deutsch</a></li>
                    </ul>
                </div>
            </div>
            <div class="header-opt-modal-item currency-item fl-wrap">
                <h4>Currency: <span>USD</span></h4>
                <div class="header-opt-modal-list fl-wrap">
                    <ul>
                        <li><a href="#" class="current-lan" data-lantext="USD">USD</a></li>
                        <li><a href="#" data-lantext="EUR">EUR</a></li>
                        <li><a href="#" data-lantext="GBP">GBP</a></li>
                        <li><a href="#" data-lantext="RUR">RUR</a></li>
                    </ul>
                </div>
            </div>
        </div>
    </div> --}}
    <!--header-opt-modal end -->
</header>
