<section class="hero-section hero-section_dec" data-scrollax-parent="true">
    <div class="bg-wrap">
        <div class="bg par-elem" data-bg="{{ asset('images/bg/1.jpg') }}" data-scrollax="properties: { translateY: '30%' }"></div>
    </div>
    <div class="overlay"></div>
    <div class="container">
        <div class="hero-title hero-title_small">
            <h4>Mạng lưới thổ địa lớn nhất Đà Lạt</h4>
            <h2>Tìm bất động sản Đà Lạt của bạn</h2>
        </div>
        <div class="main-search-input-wrap">
            <form action="{{ route('properties.index') }}" method="GET">
                <div class="main-search-input fl-wrap">
                    <div class="main-search-input-item">
                        <input name="text" type="text" placeholder="Tìm BDS" value=""/>
                    </div>
                    <div class="main-search-input-item">
                        <select name="propery_type" data-placeholder="Tất cả danh mục" class="chosen-select no-search-select">
                            <option value="">Cho thuê & Bán</option>
                            <option value="0">Bán</option>
                            <option value="1">Cho Thuê</option>
                        </select>
                    </div>
                    {{-- <div class="main-search-input-item">
                        <select name="category" data-placeholder="Loại BDS" class="chosen-select no-search-select">
                            <option value="">Loại BDS</option>
                            @foreach ($categories as $categorie)
                            <option value="{{$categorie->id}}">{{$categorie->category}}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="main-search-input-item">
                        <select name="street" data-placeholder="All Categories" class="chosen-select">
                            <option value="">Đường</option>
                            @foreach ($locationsStreets as $locationsStreet)
                                <option value="{{$locationsStreet->code}}">{{$locationsStreet->street_name}}</option>
                            @endforeach
                        </select>
                    </div> --}}
                    <div class="main-search-input-item">
                        <select name="ward" data-placeholder="All Categories" class="chosen-select">
                            <option value="">Phường Xã</option>
                            @foreach ($locationsWards as $locationsWard)
                                <option value="{{$locationsWard->code}}">{{$locationsWard->full_name}}</option>
                            @endforeach
                        </select>
                    </div>
                    <button type="submit" class="main-search-button color-bg">Tìm kiếm <i class="far fa-search"></i></button>
                </div>
            </form>
        </div>
        <div class="hero-notifer fl-wrap">Lọc thêm? <a href="{{route('properties.index')}}">Tìm kiếm nâng cao</a></div>
        <div class="scroll-down-wrap">
            <div class="mousey">
                <div class="scroller"></div>
            </div>
            <span>Cuộn xuống để khám phá thêm</span>
        </div>
    </div>
</section>
<!-- Đưa script xuống cuối trang -->

{{-- <script>
    $(document).ready(function() {
        $("#streetInput").autocomplete({
            source: function(request, response) {
                $.ajax({
                    url: "{{ route('autocomplete.street') }}",
                    dataType: "json",
                    data: {
                        term: request.term
                    },
                    success: function(data) {
                        response(data);
                    }
                });
            },
            minLength: 2 // Số ký tự tối thiểu trước khi suggest
        });
    });
</script> --}}
