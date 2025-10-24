<div class="breadcrumbs fw-breadcrumbs sp-brd fl-wrap">
    <div class="container">
        <div class="breadcrumbs-list">
            {{-- <a href="{{ route('index') }}">Home</a> --}}
            {{-- <a href="#">Agency</a> --}}
            @if(isset($nodes))
            @foreach($nodes as $node)
            <a href="{{ $node['url'] }}">{{ $node['title'] }}</a>
            @endforeach
            @endif

            @if(isset($title))
            <span>{{ $title }}</span>
            @endif
            {{-- <span>Mới nhất</span> --}}
        </div>
        {{-- <div class="share-holder hid-share">
            <a href="#" class="share-btn showshare sfcs"> <i class="fas fa-share-alt"></i> Chia sẻ </a>
            <div class="share-container  isShare"></div>
        </div> --}}
    </div>
</div>