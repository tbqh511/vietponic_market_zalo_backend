<div class="slick-slide-item">
    <!--  agent card item -->
    <div class="listing-item">
        <article class="geodir-category-listing fl-wrap">
            <div class="geodir-category-img fl-wrap  agent_card">
                <a href="{{route('agent.showid',['id'=> $agent->id])}}" class="geodir-category-img_item">
                    <img src="{{$agent->profile}}" alt="Thổ địa Đà lat BDS">
                    <ul class="list-single-opt_header_cat">
                        <li><span class="cat-opt color-bg">{{$agent->customer_total_post}} listings</span></li>
                    </ul>
                </a>
                <div class="agent-card-social fl-wrap">
                    {{-- <ul>
                        <li><a href="#" target="_blank"><i class="fab fa-facebook-f"></i></a></li>
                        <li><a href="#" target="_blank"><i class="fab fa-twitter"></i></a></li>
                        <li><a href="#" target="_blank"><i class="fab fa-instagram"></i></a></li>
                    </ul> --}}
                </div>
                <div class="listing-rating card-popup-rainingvis" data-starrating2="5"><span
                        class="re_stars-title">Excellent</span></div>
            </div>
            <div class="geodir-category-content fl-wrap">
                <div class="card-verified tolt" data-microtip-position="left" data-tooltip="Verified"><i
                        class="fal fa-user-check"></i></div>
                <div class="agent_card-title fl-wrap">
                    <h4><a href="{{route('agent.showid',['id'=> $agent->id])}}">{{$agent->name}}</a></h4>
                    <h5><a href="{{route('agent.showid',['id'=> $agent->id])}}">Thổ địa Đà lat BDS</a></h5>
                </div>
                {{-- <p>Lorem ipsum dolor sit amet, consectetur adipiscing elit. Maecenas in pulvinar
                    neque. Nulla finibus lobortis pulvinar. Donec a consectetur nulla.</p> --}}
                <div class="geodir-category-footer fl-wrap">
                    <a href="{{route('agent.showid',['id'=> $agent->id])}}" class="btn float-btn color-bg small-btn">Xem hồ sơ</a>
                    {{-- <a href="mailto:yourmail@email.com" class="tolt ftr-btn" data-microtip-position="left"
                        data-tooltip="Write Message"><i class="fal fa-envelope"></i></a>
                    <a href="tel:123-456-7890" class="tolt ftr-btn" data-microtip-position="left"
                        data-tooltip="Call Now"><i class="fal fa-phone"></i></a> --}}
                </div>
            </div>
        </article>
    </div>
    <!--  agent card item end -->
</div>
