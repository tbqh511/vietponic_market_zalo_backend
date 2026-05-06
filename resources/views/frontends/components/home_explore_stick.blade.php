<div class="slick-item">
    <div class="half-carousel-item fl-wrap">
        <div class="bg-wrap bg-parallax-wrap-gradien">
            <div class="bg" data-bg="/images/bg/{{$locationsWard->code}}.jpg"></div>
        </div>
        <div class="half-carousel-content">
            <a href="{{ route('properties.index', ['ward' => $locationsWard->code]) }}" class="hc-counter small-btn color-bg">{{ $locationsWard->properties_count }} BĐS đang giao dịch</a>
            <h3><a href="{{ route('properties.index', ['ward' => $locationsWard->code]) }}">{{$locationsWard->full_name}}</a></h3>
        </div>
    </div>
</div>