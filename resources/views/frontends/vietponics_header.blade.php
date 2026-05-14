<nav>
  <div class="nav-inner">
    <a href="{{ url('/') }}" class="logo">
      <img src="{{ asset('images/logo-vietponics.png') }}" alt="Vietponics" class="logo-img">
    </a>
    <button class="nav-toggle" type="button" aria-controls="navDrawer" aria-expanded="false" aria-label="Mở menu">
      <span class="nav-toggle-lines" aria-hidden="true"><span></span><span></span><span></span></span>
    </button>
    <div class="nav-drawer" id="navDrawer">
      <div class="nav-drawer-top">
        <button class="nav-close" type="button" data-nav-close="true" aria-label="Đóng menu">×</button>
      </div>
      <div class="nav-links" aria-label="Điều hướng">
        <a href="{{ url('/') }}" class="active">Trang chủ</a>
        <a href="#tinh-nang">Tính năng</a>
        <a href="#san-pham">Sản phẩm</a>
        <a href="#danh-gia">Đánh giá</a>
        <a href="#lien-he">Liên hệ</a>
      </div>
      <div class="nav-search">
        <label class="sr-only" for="navSearch">Tìm kiếm</label>
        <input id="navSearch" type="search" placeholder="Tìm rau, củ, quả…" autocomplete="off" inputmode="search">
      </div>
      <div class="nav-actions">
        <a href="https://zalo.me/s/2984181565024919663" target="_blank" rel="noopener" class="nav-action-btn cart" style="text-decoration:none;display:flex;align-items:center;justify-content:center;gap:6px">
          <span style="font-size:15px;font-weight:800;letter-spacing:-.5px">Z</span> Mở Mini App
        </a>
      </div>
    </div>
  </div>
</nav>
<div class="nav-backdrop" data-nav-close="true" aria-hidden="true"></div>
