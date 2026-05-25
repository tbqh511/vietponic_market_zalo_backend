<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('title', 'Vietponics - Rau sạch thủy canh Đà Lạt')</title>
    <meta name="description" content="@yield('description', 'Rau sạch thủy canh trồng tại Đà Lạt, giao tận nhà')">
    <link rel="shortcut icon" href="{{ asset('images/favicon.ico') }}">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Be+Vietnam+Pro:ital,wght@0,300;0,400;0,500;0,600;0,700;0,800;1,400;1,500&family=Lora:ital,wght@0,600;0,700;1,600;1,700&display=swap" rel="stylesheet">
    <style>
  *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
  :root {
    --primary:       #90c52d;
    --primary-dark:  #6a9320;
    --primary-deeper:#4a6614;
    --primary-light: #d8edaa;
    --primary-pale:  #f0f7e0;
    --dark:          #1e2d0a;
    --dark2:         #2e4210;
    --gold:          #e8a20c;
    --gold-light:    #f5c842;
    --cream:         #f6f9ee;
    --cream2:        #edf5d5;
    --white:         #ffffff;
    --text:          #1e2d0a;
    --text-muted:    #546038;
    --text-light:    #89a05c;
    --border:        #d6e8a8;
    --border2:       #c5dc8c;
    --shadow:        0 2px 16px rgba(90,130,20,.10);
    --shadow-lg:     0 6px 32px rgba(90,130,20,.16);
    --r:             6px;
    --r-sm:          4px;
    --r-pill:        50px;
  }
  html { scroll-behavior: smooth; }
  body {
    font-family: 'Be Vietnam Pro', ui-sans-serif, system-ui, -apple-system, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif;
    background: var(--cream);
    color: var(--text);
    font-size: 15px;
    line-height: 1.65;
  }

  /* ── NAVBAR ── */
  nav {
    position: sticky; top: 0; z-index: 200;
    background: transparent;
    border-bottom: 1px solid var(--border);
    padding: 0 clamp(1rem,4vw,3rem);
    isolation: isolate;
  }
  nav::before {
    content: "";
    position: absolute;
    inset: 0;
    background: rgba(255,255,255,.95);
    backdrop-filter: blur(10px);
    -webkit-backdrop-filter: blur(10px);
    z-index: -1;
    pointer-events: none;
  }
  .nav-inner {
    max-width: 1280px; margin: auto;
    display: flex; align-items: center; gap: 20px;
    height: 66px;
  }
  .logo {
    display: flex; align-items: center; gap: 9px;
    text-decoration: none; flex-shrink: 0;
  }
  .logo-img { height: 52px; width: auto; display: block; object-fit: contain; }
  .nav-links { display: flex; gap: 2px; margin: 0 auto; }
  .nav-links a {
    text-decoration: none; color: var(--text-muted); font-size: 14px; font-weight: 500;
    padding: 7px 14px; border-radius: var(--r-sm); transition: all .2s;
  }
  .nav-links a:hover { background: var(--primary-pale); color: var(--dark); }
  .nav-links a.active { color: var(--dark); background: var(--primary-pale); font-weight: 600; }
  .nav-search {
    display: flex; align-items: center; gap: 8px;
    background: var(--cream2); border: 1px solid var(--border); border-radius: var(--r-sm);
    padding: 8px 14px; flex: 0 0 210px;
  }
  .nav-search input { border: none; background: transparent; outline: none; font-size: 14px; color: var(--text); width: 100%; font-family: inherit; }
  .nav-search input::placeholder { color: var(--text-light); }
  .nav-actions { display: flex; align-items: center; gap: 6px; }
  .nav-action-btn {
    padding: 8px 14px; border-radius: var(--r-sm); border: 1px solid var(--border);
    background: transparent; cursor: pointer; font-family: inherit;
    font-size: 13px; font-weight: 500; color: var(--text-muted);
    transition: all .2s; white-space: nowrap;
  }
  .nav-action-btn:hover { background: var(--primary-pale); border-color: var(--primary); color: var(--dark); }
  .nav-action-btn.cart { background: var(--primary); border-color: var(--primary); color: #fff; }
  .nav-action-btn.cart:hover { background: var(--primary-dark); }
  .nav-toggle { width:48px; height:48px; border-radius:8px; border:1px solid var(--border); background:transparent; display:none; align-items:center; justify-content:center; cursor:pointer; transition:background .3s,border-color .3s,transform .3s; }
  .nav-toggle:hover, .nav-toggle:focus-visible { background:var(--primary-pale); border-color:var(--primary); }
  .nav-toggle-lines { width:24px; height:24px; position:relative; }
  .nav-toggle-lines span { position:absolute; left:3px; right:3px; height:2px; background:var(--dark); border-radius:2px; transition:transform .3s,top .3s,opacity .3s; }
  .nav-toggle-lines span:nth-child(1){ top:7px; }
  .nav-toggle-lines span:nth-child(2){ top:11px; }
  .nav-toggle-lines span:nth-child(3){ top:15px; }
  nav.nav-open .nav-toggle-lines span:nth-child(1){ top:11px; transform:rotate(45deg); }
  nav.nav-open .nav-toggle-lines span:nth-child(2){ opacity:0; }
  nav.nav-open .nav-toggle-lines span:nth-child(3){ top:11px; transform:rotate(-45deg); }
  .nav-drawer { display:flex; align-items:center; gap:20px; margin-left:auto; }
  .nav-drawer-top, .nav-close { display:none; }
  .nav-backdrop { position:fixed; inset:0; background:rgba(0,0,0,.35); opacity:0; pointer-events:none; transition:opacity .3s; z-index:215; }
  body.nav-open .nav-backdrop { opacity:1; pointer-events:auto; }
  .sr-only { position:absolute; width:1px; height:1px; padding:0; margin:-1px; overflow:hidden; clip:rect(0,0,0,0); white-space:nowrap; border:0; }

  @media (max-width: 767.98px) {
    .nav-toggle { display: flex; margin-left: auto; }
    .nav-drawer { position: fixed; top: 0; right: 0; height: 100vh; width: min(92vw, 380px); background: var(--white); border-left: 1px solid var(--border); flex-direction: column; align-items: stretch; gap: 14px; padding: 18px; transform: translateX(105%); transition: transform .3s; z-index: 220; overflow-y: auto; }
    body.nav-open .nav-drawer { transform: translateX(0); }
    .nav-drawer-top { display: flex; justify-content: flex-end; align-items: center; min-height: 48px; }
    .nav-close { display: flex; align-items: center; justify-content: center; width: 48px; height: 48px; border-radius: 8px; border: 1px solid var(--border); background: var(--primary-pale); color: var(--dark); cursor: pointer; transition: background .3s, border-color .3s, transform .3s; font: inherit; font-size: 24px; line-height: 1; }
    .nav-close:hover, .nav-close:focus-visible { background: var(--primary-light); border-color: var(--primary); }
    .nav-links { flex-direction: column; gap: 6px; margin: 0; }
    .nav-links a { justify-content: flex-start; }
    .nav-search { flex: 0 0 auto; width: 100%; }
    .nav-actions { flex-direction: column; align-items: stretch; }
    .nav-action-btn { width: 100%; }
  }

  /* ── FOOTER ── */
  footer { background: var(--dark); color: rgba(255,255,255,.8); padding: clamp(2.5rem,5vw,4rem) clamp(1rem,4vw,3rem) 0; overflow: hidden; }
  .footer-inner { max-width: 1280px; margin: auto; }
  .footer-grid { display: grid; grid-template-columns: 2fr 1fr 1fr 1.5fr; gap: 40px; padding-bottom: 3rem; border-bottom: 1px solid rgba(255,255,255,.1); }
  .footer-logo-img { height: 96px; width: auto; object-fit: contain; margin-bottom: 12px; display: block; }
  .footer-desc { font-size: 13px; color: rgba(255,255,255,.55); line-height: 1.8; margin-bottom: 20px; }
  .footer-contacts { display: flex; flex-direction: column; gap: 8px; }
  .fci { font-size: 13px; color: rgba(255,255,255,.6); }
  .fci strong { color: rgba(255,255,255,.85); font-weight: 600; }
  .footer-col-title { font-weight: 700; font-size: 11px; text-transform: uppercase; letter-spacing: 1px; color: rgba(255,255,255,.5); margin-bottom: 16px; }
  .footer-links { display: flex; flex-direction: column; gap: 10px; }
  .footer-links a { font-size: 13px; color: rgba(255,255,255,.6); text-decoration: none; transition: color .2s; }
  .footer-links a:hover { color: var(--primary); }
  .newsletter-input { display: flex; gap: 6px; margin-top: 10px; }
  .newsletter-input input { flex: 1; background: rgba(255,255,255,.08); border: 1px solid rgba(255,255,255,.15); border-radius: var(--r-sm); padding: 9px 13px; font-size: 13px; color: #fff; font-family: inherit; outline: none; }
  .newsletter-input input::placeholder { color: rgba(255,255,255,.35); }
  .newsletter-input button { background: var(--primary); color: var(--dark); border: none; border-radius: var(--r-sm); padding: 9px 14px; font-size: 13px; font-weight: 700; cursor: pointer; font-family: inherit; white-space: nowrap; transition: all .2s; }
  .newsletter-input button:hover { background: var(--primary-dark); color: #fff; }
  .footer-bottom { display: flex; justify-content: space-between; align-items: center; padding: 20px 0; font-size: 12px; color: rgba(255,255,255,.38); flex-wrap: wrap; gap: 8px; }
  .footer-bottom a { color: rgba(255,255,255,.5); text-decoration: none; }
  .footer-bottom a:hover { color: var(--primary); }
  .payment-icons { display: flex; gap: 6px; }
  .pay-icon { background: rgba(255,255,255,.1); border-radius: 3px; padding: 4px 10px; font-size: 10px; font-weight: 700; color: rgba(255,255,255,.6); }

  @media (max-width: 1023.98px) {
    .footer-grid { grid-template-columns: repeat(2, 1fr); gap: 28px; }
  }
  @media (max-width: 767.98px) {
    .footer-grid { grid-template-columns: 1fr; gap: 28px; }
  }
    </style>
    @stack('styles')
</head>
<body>
    @include('frontends.vietponics_header')
    @yield('content')
    @include('frontends.vietponics_footer')
    <script>
  /* Mobile nav drawer */
  (function() {
    var nav = document.querySelector('nav');
    var navInner = document.querySelector('.nav-inner');
    var navToggle = document.querySelector('.nav-toggle');
    var navDrawer = document.getElementById('navDrawer');
    var focusablesSelector = 'a[href],button:not([disabled]),input:not([disabled])';
    var lastFocused = null;
    var mobileMQ = window.matchMedia('(max-width: 767.98px)');

    /* Only move drawer to body on mobile (overlay needs to escape nav's stacking context).
       On desktop, drawer must stay inside .nav-inner to render inline. */
    function syncDrawerLocation() {
      if (!navDrawer || !navInner) return;
      if (mobileMQ.matches) {
        if (navDrawer.parentNode !== document.body) document.body.appendChild(navDrawer);
      } else {
        if (navDrawer.parentNode !== navInner) navInner.appendChild(navDrawer);
      }
    }
    syncDrawerLocation();
    if (mobileMQ.addEventListener) mobileMQ.addEventListener('change', syncDrawerLocation);
    else if (mobileMQ.addListener) mobileMQ.addListener(syncDrawerLocation);

    var backdrop = document.querySelector('.nav-backdrop');
    if (backdrop) document.body.appendChild(backdrop);

    function setNavOpen(open) {
      if (!nav || !navToggle || !navDrawer) return;
      nav.classList.toggle('nav-open', open);
      document.body.classList.toggle('nav-open', open);
      navToggle.setAttribute('aria-expanded', String(open));
      document.body.style.overflowY = open ? 'hidden' : '';
      if (open) {
        lastFocused = document.activeElement;
        var first = navDrawer.querySelector(focusablesSelector);
        if (first) first.focus();
      } else if (lastFocused && typeof lastFocused.focus === 'function') {
        lastFocused.focus();
      }
    }

    if (navToggle) {
      navToggle.addEventListener('click', function() {
        setNavOpen(!document.body.classList.contains('nav-open'));
      });
    }

    document.addEventListener('click', function(e) {
      if (e.target && e.target.closest && e.target.closest('[data-nav-close="true"]')) {
        setNavOpen(false);
      }
    });
    if (navDrawer) {
      navDrawer.addEventListener('click', function(e) {
        if (e.target && e.target.tagName === 'A') setNavOpen(false);
      });
    }
    document.addEventListener('keydown', function(e) {
      if (!document.body.classList.contains('nav-open')) return;
      if (e.key === 'Escape') { setNavOpen(false); return; }
      if (e.key !== 'Tab') return;
      var focusables = Array.prototype.slice.call(navDrawer.querySelectorAll(focusablesSelector));
      if (focusables.length === 0) return;
      var first = focusables[0];
      var last = focusables[focusables.length - 1];
      if (e.shiftKey && document.activeElement === first) { e.preventDefault(); last.focus(); }
      else if (!e.shiftKey && document.activeElement === last) { e.preventDefault(); first.focus(); }
    });
  })();
    </script>
    @stack('scripts')
</body>
</html>
