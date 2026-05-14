@extends('frontends.vietponics_master')

@push('styles')
<style>
  .vp-home { max-width: 100%; }

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

  /* ── HERO ── */
  .hero {
    background: var(--white); overflow: hidden; position: relative;
    min-height: 580px; display: flex; align-items: center;
  }
  .hero-bg {
    position: absolute; inset: 0;
    background:
      radial-gradient(ellipse 55% 75% at 68% 50%, var(--primary-pale) 0%, transparent 70%),
      radial-gradient(ellipse 35% 55% at 5% 85%, #fffde8 0%, transparent 60%);
  }
  .hero-inner {
    max-width: 1280px; margin: auto;
    padding: clamp(3rem,6vw,5rem) clamp(1rem,4vw,3rem);
    display: grid; grid-template-columns: 1fr 1fr; gap: 60px; align-items: center;
    width: 100%; position: relative; z-index: 1;
  }
  .hero-badge {
    display: inline-flex; align-items: center; gap: 8px;
    background: var(--cream2); border: 1px solid var(--border2);
    border-radius: var(--r-sm); padding: 5px 14px;
    font-size: 11px; color: var(--primary-deeper); font-weight: 700;
    letter-spacing: .8px; text-transform: uppercase; margin-bottom: 20px;
  }
  .hero-badge .dot { width: 6px; height: 6px; border-radius: 50%; background: var(--primary); }
  .hero h1 {
    font-family: 'Lora', serif;
    font-size: clamp(2.8rem,5vw,4.2rem);
    font-weight: 700; line-height: 1.08;
    color: var(--dark); margin-bottom: 18px;
  }
  .hero h1 em { font-style: italic; color: var(--primary-deeper); }
  .hero p { font-size: 15px; color: var(--text-muted); margin-bottom: 32px; max-width: 420px; line-height: 1.8; }
  .hero-actions { display: flex; align-items: center; gap: 12px; flex-wrap: wrap; }
  .btn-primary {
    background: var(--primary); color: var(--dark); border: none; border-radius: var(--r-sm);
    padding: 13px 28px; font-size: 15px; font-weight: 600; cursor: pointer;
    font-family: inherit; transition: all .22s; min-width: 44px; min-height: 44px;
    box-shadow: 0 3px 12px rgba(144,197,45,.35);
    display: inline-flex; align-items: center; justify-content: center;
  }
  .btn-primary:hover,.btn-primary:focus-visible { background: var(--primary-dark); color: #fff; transform: translateY(-1px); box-shadow: 0 5px 18px rgba(144,197,45,.4); }
  .btn-secondary {
    background: transparent; color: var(--dark); border: 1.5px solid var(--border2);
    border-radius: var(--r-sm); padding: 12px 26px; font-size: 15px; font-weight: 500;
    cursor: pointer; font-family: inherit; transition: all .2s;
  }
  .btn-secondary:hover { background: var(--primary-pale); border-color: var(--primary); }
  .hero-stats { display: flex; gap: 32px; margin-top: 36px; padding-top: 28px; border-top: 1px solid var(--border); }
  .hero-stat .num { font-family: 'Lora', serif; font-size: 26px; font-weight: 700; color: var(--primary-deeper); line-height: 1; }
  .hero-stat .lbl { font-size: 12px; color: var(--text-muted); margin-top: 3px; }
  .hero-visual { position: relative; display: flex; align-items: center; justify-content: center; }
  .hero-img-main {
    width: 100%; max-width: 460px; aspect-ratio: 1;
    border-radius: var(--r); overflow: hidden;
    background: linear-gradient(145deg, var(--primary-light) 0%, var(--primary) 60%, var(--primary-deeper) 100%);
    display: flex; align-items: flex-end; justify-content: center;
  }
  .veg-artwork { width: 88%; margin-bottom: -4px; }
  .float-card {
    position: absolute; background: white; border-radius: var(--r);
    box-shadow: var(--shadow-lg); padding: 12px 16px;
    display: flex; align-items: center; gap: 10px;
    border: 1px solid var(--border);
  }
  .float-card-1 { bottom: 68%; left: -6%; animation: floatY 3s ease-in-out infinite; }
  .float-card-2 { top: 12%; right: -8%; animation: floatY 3.5s ease-in-out infinite .5s; }
  .float-card-3 { top: 50%; left: -10%; animation: floatY 4s ease-in-out infinite 1s; }
  @keyframes floatY { 0%,100%{transform:translateY(0)} 50%{transform:translateY(-8px)} }
  .fc-dot { width: 38px; height: 38px; border-radius: var(--r-sm); flex-shrink: 0; display: flex; align-items: center; justify-content: center; font-size: 13px; font-weight: 800; color: #fff; }
  .fc-title { font-weight: 600; font-size: 13px; color: var(--text); white-space: nowrap; }
  .fc-sub { font-size: 11px; color: var(--text-muted); white-space: nowrap; }

  /* ── TRUST STRIP ── */
  .trust-strip { background: var(--dark); padding: 0 clamp(1rem,4vw,3rem); }
  .trust-inner {
    max-width: 1280px; margin: auto;
    display: flex; justify-content: space-around; align-items: stretch; flex-wrap: wrap;
  }
  .trust-item {
    display: flex; align-items: center; gap: 14px;
    padding: 22px 16px; flex: 1 1 180px;
    border-right: 1px solid rgba(255,255,255,.1);
  }
  .trust-item:last-child { border-right: none; }
  .trust-num { font-family: 'Lora', serif; font-size: 28px; font-weight: 700; color: var(--primary); line-height: 1; flex-shrink: 0; }
  .t-title { font-weight: 600; font-size: 14px; color: #fff; }
  .t-sub { font-size: 12px; color: rgba(255,255,255,.5); }

  /* ── SECTION COMMONS ── */
  .section { padding: clamp(3rem,5vw,5rem) clamp(1rem,4vw,3rem); }
  .section-inner { max-width: 1280px; margin: auto; }
  .section-header { display: flex; justify-content: space-between; align-items: flex-end; margin-bottom: 36px; }
  .section-label { font-size: 11px; font-weight: 700; color: var(--primary-dark); text-transform: uppercase; letter-spacing: 1px; margin-bottom: 8px; }
  .section-title { font-family: 'Lora', serif; font-size: clamp(1.8rem,3vw,2.5rem); font-weight: 700; color: var(--dark); line-height: 1.12; }
  .section-sub { font-size: 14px; color: var(--text-muted); margin-top: 6px; max-width: 380px; }
  .see-all {
    font-size: 13px; font-weight: 600; color: var(--dark); text-decoration: none;
    border: 1px solid var(--border2); border-radius: var(--r-sm);
    padding: 8px 16px; transition: all .2s; white-space: nowrap;
  }
  .see-all:hover,.see-all:focus-visible { background: var(--primary); color: var(--dark); border-color: var(--primary); }

  /* ── FEATURE GRID ── */
  .feature-grid { display: grid; grid-template-columns: 1fr; gap: 14px; }
  .feature-card { background: var(--white); border: 1px solid var(--border); border-radius: 8px; padding: 16px; box-shadow: 0 2px 4px rgba(0,0,0,.1); display: flex; gap: 14px; align-items: flex-start; }
  .feature-icon { width: 40px; height: 40px; border-radius: 8px; background: var(--primary-pale); border: 1px solid var(--border2); display: flex; align-items: center; justify-content: center; flex-shrink: 0; }
  .feature-icon img { width: 22px; height: 22px; }
  .feature-card h3 { font-size: 15px; font-weight: 800; color: var(--dark); line-height: 1.25; margin-bottom: 6px; }
  .feature-card p { font-size: 14px; color: var(--text-muted); line-height: 1.6; }

  /* ── PRODUCT CARDS ── */
  .product-grid { display: grid; grid-template-columns: repeat(4, 1fr); gap: 18px; }
  .prod-card {
    background: var(--white); border-radius: var(--r); border: 1px solid var(--border);
    overflow: hidden; transition: all .22s; cursor: pointer;
  }
  .prod-card:hover { transform: translateY(-4px); box-shadow: var(--shadow-lg); }
  .prod-img {
    aspect-ratio: 1 / .82; width: 100%;
    display: flex; align-items: center; justify-content: center;
    font-size: 52px; position: relative; overflow: hidden;
  }
  .prod-img img { width: 100%; height: 100%; object-fit: cover; position: absolute; inset: 0; }
  .prod-img::after { content:''; position:absolute; inset:0; background:linear-gradient(to bottom, transparent 55%, rgba(0,0,0,.06)); pointer-events:none; }
  .prod-tag {
    position: absolute; top: 10px; left: 10px; z-index: 1;
    background: var(--primary); color: #fff; font-size: 10px; font-weight: 700;
    padding: 3px 9px; border-radius: 2px; text-transform: uppercase; letter-spacing: .5px;
  }
  .prod-tag.gold { background: var(--gold); }
  .prod-body { padding: 14px 15px 16px; }
  .prod-category { font-size: 11px; font-weight: 700; color: var(--primary-dark); text-transform: uppercase; letter-spacing: .5px; margin-bottom: 4px; }
  .prod-name { font-weight: 600; font-size: 14px; color: var(--text); margin-bottom: 10px; line-height: 1.35; }
  .prod-meta { display: flex; justify-content: flex-end; align-items: center; }
  .prod-price { font-family: 'Lora', serif; font-size: 22px; font-weight: 700; color: var(--dark); }
  .prod-price small { font-family: 'Be Vietnam Pro',sans-serif; font-size: 11px; font-weight: 400; color: var(--text-light); }
  .prod-origin { display: none; }
  .add-btn {
    width: 100%; margin-top: 12px; padding: 9px; border-radius: var(--r-sm);
    background: var(--primary-pale); color: var(--primary-deeper);
    border: 1px solid var(--primary-light);
    font-size: 13px; font-weight: 600; cursor: pointer; font-family: inherit;
    transition: all .3s;
  }
  .add-btn:hover,.add-btn:focus-visible { background: var(--primary); color: var(--dark); border-color: var(--primary); }

  /* ── PROMO BANNER ── */
  .promo-banner {
    margin: 0 clamp(1rem,4vw,3rem);
    border-radius: var(--r); overflow: hidden;
    background: linear-gradient(115deg, var(--dark) 0%, var(--dark2) 45%, var(--primary-deeper) 100%);
    position: relative;
  }
  .promo-banner::before {
    content: ''; position: absolute; inset: 0; pointer-events: none;
    background: repeating-linear-gradient(45deg, transparent, transparent 40px, rgba(144,197,45,.04) 40px, rgba(144,197,45,.04) 80px);
  }
  .promo-inner {
    max-width: 1280px; margin: auto;
    padding: clamp(2.5rem,5vw,4rem) clamp(2rem,5vw,4rem);
    display: grid; grid-template-columns: 1fr 1fr; gap: 50px; align-items: center;
    position: relative; z-index: 1;
  }
  .promo-visual { display: grid; grid-template-columns: 1fr 1fr; grid-template-rows: 1fr 1fr; gap: 10px; height: 280px; }
  .promo-tile { border-radius: var(--r-sm); display: flex; align-items: center; justify-content: center; font-size: 36px; overflow: hidden; }
  .pt1 { background: var(--primary); grid-column: span 1; grid-row: span 2; font-size: 48px; }
  .pt2 { background: rgba(255,255,255,.08); border: 1px solid rgba(255,255,255,.1); }
  .pt3 { background: rgba(255,255,255,.08); border: 1px solid rgba(255,255,255,.1); }
  .promo-badge { display: inline-flex; background: rgba(144,197,45,.2); border: 1px solid rgba(144,197,45,.4); border-radius: 2px; padding: 4px 12px; font-size: 10px; font-weight: 700; color: var(--primary-light); letter-spacing: 1px; text-transform: uppercase; margin-bottom: 16px; }
  .promo-content h2 { font-family: 'Lora', serif; font-size: clamp(2rem,3.5vw,3rem); font-weight: 700; color: #fff; line-height: 1.08; margin-bottom: 14px; }
  .promo-content h2 em { color: var(--gold-light); font-style: italic; }
  .promo-content p { color: rgba(255,255,255,.65); font-size: 14px; line-height: 1.8; margin-bottom: 24px; }
  .promo-feats { display: flex; flex-direction: column; gap: 9px; margin-bottom: 28px; }
  .promo-feat { display: flex; align-items: center; gap: 10px; color: rgba(255,255,255,.85); font-size: 14px; }
  .promo-feat-dot { width: 6px; height: 6px; border-radius: 50%; background: var(--primary); flex-shrink: 0; }
  .btn-gold {
    display: inline-flex; align-items: center; gap: 8px;
    background: var(--primary); color: var(--dark); border: none;
    border-radius: var(--r-sm); padding: 13px 26px; font-size: 14px; font-weight: 700;
    cursor: pointer; font-family: inherit; transition: all .2s;
    box-shadow: 0 3px 14px rgba(144,197,45,.45);
  }
  .btn-gold:hover { transform: translateY(-2px); box-shadow: 0 5px 20px rgba(144,197,45,.55); background: var(--primary-dark); color: #fff; }

  /* ── CAT FILTER ── */
  .cat-filter { display: flex; gap: 8px; flex-wrap: wrap; margin-bottom: 32px; }
  .cat-pill {
    padding: 7px 18px; border-radius: var(--r-sm); font-size: 13px; font-weight: 500;
    border: 1px solid var(--border); background: var(--white); color: var(--text-muted);
    cursor: pointer; transition: all .3s;
  }
  .cat-pill:hover,.cat-pill.active { background: var(--primary); color: #fff; border-color: var(--primary); font-weight: 600; }

  /* ── CATEGORY TILES ── */
  .cat-grid { display: grid; grid-template-columns: repeat(3, 1fr); grid-template-rows: repeat(2, 240px); gap: 14px; }
  .cat-tile { border-radius: var(--r); overflow: hidden; position: relative; cursor: pointer; display: flex; flex-direction: column; justify-content: flex-end; padding: 20px 18px; transition: transform .22s, box-shadow .22s; box-shadow: 0 4px 16px rgba(0,0,0,.18); }
  .cat-tile:hover { transform: scale(1.025); box-shadow: 0 8px 28px rgba(0,0,0,.28); }
  .ct-bg { position: absolute; inset: 0; display: flex; align-items: center; justify-content: center; font-size: 56px; }
  .ct-overlay { position: absolute; inset: 0; background: linear-gradient(to top, rgba(0,0,0,.82) 0%, rgba(0,0,0,.45) 45%, rgba(0,0,0,.08) 100%); }
  .ct-content { position: relative; z-index: 1; text-align: center; }
  .ct-label { font-size: 17px; font-weight: 800; color: #fff; font-family: 'Lora', serif; text-shadow: 0 2px 8px rgba(0,0,0,.9), 0 1px 3px rgba(0,0,0,.8); letter-spacing: .2px; line-height: 1.3; }
  .ct-count { font-size: 12px; font-weight: 600; color: #fff; opacity: .95; margin: 4px 0 12px; text-shadow: 0 1px 4px rgba(0,0,0,.8); background: rgba(0,0,0,.3); display: inline-block; padding: 2px 8px; border-radius: 20px; }
  .ct-btn { display: inline-block; background: var(--primary); border-radius: 4px; padding: 6px 14px; font-size: 12px; font-weight: 700; color: #fff; letter-spacing: .5px; box-shadow: 0 2px 8px rgba(0,0,0,.3); transition: background .2s, transform .15s; }
  .ct-btn:hover { background: var(--primary-dark,#2d7a1f); transform: translateY(-1px); }
  .ct1 { background: linear-gradient(135deg, #3a6b1a, #82c43a); }
  .ct2 { background: linear-gradient(135deg, #1a4728, #2d8050); }
  .ct3 { background: linear-gradient(135deg, #7a3a1a, #c47a32); }
  .ct4 { background: linear-gradient(135deg, #3a4a1e, #7a8a3a); }
  .ct5 { background: linear-gradient(135deg, #1a2a50, #3a5a90); }
  .ct6 { background: linear-gradient(135deg, #5a2a1a, #a05030); }

  /* ── STORE SECTION ── */
  .store-section { background: var(--white); }
  .store-inner { display: grid; grid-template-columns: 1fr 1fr; gap: 60px; align-items: center; }
  .store-visual { border-radius: var(--r); overflow: hidden; position: relative; height: 420px; background: linear-gradient(145deg, var(--primary-light), var(--primary), var(--primary-deeper)); display: flex; align-items: center; justify-content: center; font-size: 80px; }
  .store-badge { position: absolute; bottom: 24px; right: 24px; background: white; border-radius: var(--r-sm); padding: 14px 18px; box-shadow: var(--shadow-lg); text-align: center; }
  .store-badge .sb-num { font-family: 'Lora', serif; font-size: 32px; font-weight: 700; color: var(--primary-deeper); line-height: 1; }
  .store-badge .sb-unit { font-size: 13px; font-weight: 600; color: var(--primary); }
  .store-badge .sb-desc { font-size: 11px; color: var(--text-muted); margin-top: 2px; }
  .eyebrow { font-size: 11px; font-weight: 700; color: var(--primary-dark); text-transform: uppercase; letter-spacing: 1px; margin-bottom: 12px; }
  .store-content h2 { font-family: 'Lora', serif; font-size: clamp(1.8rem,3vw,2.8rem); font-weight: 700; color: var(--dark); margin-bottom: 14px; line-height: 1.12; }
  .store-content p { color: var(--text-muted); font-size: 15px; line-height: 1.8; margin-bottom: 28px; }
  .store-feats { display: flex; flex-direction: column; gap: 14px; margin-bottom: 32px; }
  .store-feat { display: flex; gap: 14px; align-items: flex-start; }
  .sf-num { width: 32px; height: 32px; border-radius: var(--r-sm); background: var(--primary-pale); border: 1px solid var(--primary-light); display: flex; align-items: center; justify-content: center; font-size: 13px; font-weight: 700; color: var(--primary-deeper); flex-shrink: 0; }
  .sf-title { font-weight: 600; font-size: 14px; color: var(--text); }
  .sf-desc { font-size: 13px; color: var(--text-muted); }

  /* ── TESTIMONIALS ── */
  .testi-section { background: var(--cream); }
  .testi-track { display: grid; grid-auto-flow: column; grid-auto-columns: minmax(280px,1fr); gap: 14px; overflow-x: auto; scroll-snap-type: x mandatory; padding-bottom: 6px; }
  .testi-card { scroll-snap-align: start; background: var(--white); border-radius: var(--r); border: 1px solid var(--border); padding: 24px; transition: box-shadow .2s; min-height: 0; }
  .testi-card:hover { box-shadow: var(--shadow); }
  .stars { display: flex; gap: 2px; margin-bottom: 14px; }
  .star { width: 13px; height: 13px; fill: var(--gold-light); }
  .testi-text { font-size: 14px; color: var(--text-muted); line-height: 1.75; margin-bottom: 18px; font-style: italic; }
  .testi-author { display: flex; align-items: center; gap: 12px; }
  .avatar-img { width: 80px; height: 80px; border-radius: 50%; border: 1px solid var(--border2); background: var(--primary-pale); flex-shrink: 0; }
  .testi-name { font-weight: 600; font-size: 13px; color: var(--text); }
  .testi-role { font-size: 12px; color: var(--text-light); margin-top: 2px; }

  /* ── APP SECTION ── */
  .app-section { background: var(--white); }
  .app-inner { max-width: 1280px; margin: auto; display: grid; grid-template-columns: 1fr 1fr; gap: 60px; align-items: center; padding: clamp(3rem,6vw,5rem) clamp(1rem,4vw,3rem); }
  .app-visual { display: flex; align-items: center; justify-content: center; gap: 16px; max-height: 600px; }
  .app-phone { background: var(--dark); border-radius: 24px; padding: 18px 14px; width: 155px; min-height: 270px; display: flex; flex-direction: column; gap: 8px; box-shadow: 0 18px 48px rgba(30,45,10,.3); position: relative; overflow: hidden; }
  .app-phone::before { content:''; position:absolute; top:12px; left:50%; transform:translateX(-50%); width:44px; height:4px; background:rgba(255,255,255,.25); border-radius:2px; }
  .app-header { margin-top:10px; background:rgba(255,255,255,.08); border-radius:var(--r-sm); padding:8px 10px; }
  .app-header .ah-title { font-size:10px; font-weight:700; color:var(--primary); }
  .app-header .ah-sub { font-size:9px; color:rgba(255,255,255,.4); }
  .app-mini-card { background:rgba(255,255,255,.07); border-radius:var(--r-sm); padding:7px 8px; display:flex; align-items:center; gap:7px; }
  .amc-dot { width:26px; height:26px; border-radius:4px; flex-shrink:0; }
  .amc-lines { flex:1; }
  .amc-l1 { height:5px; background:rgba(255,255,255,.55); border-radius:2px; width:80%; margin-bottom:3px; }
  .amc-l2 { height:4px; background:rgba(255,255,255,.25); border-radius:2px; width:50%; }
  .app-btn-row { display:flex; gap:5px; margin-top:4px; }
  .app-action { flex:1; background:var(--primary); border-radius:3px; padding:6px; text-align:center; font-size:9px; font-weight:700; color:var(--dark); }
  .app-content h2 { font-family: 'Lora', serif; font-size: clamp(1.8rem,3vw,2.7rem); font-weight: 700; color: var(--dark); margin-bottom: 14px; line-height: 1.12; }
  .app-content p { color: var(--text-muted); font-size: 15px; line-height: 1.8; margin-bottom: 28px; }
  .app-btns { display: flex; gap: 10px; flex-wrap: wrap; }
  .app-btn { display: flex; align-items: center; gap: 10px; background: var(--dark); color: #fff; border-radius: var(--r-sm); padding: 11px 18px; text-decoration: none; transition: all .2s; border: 1.5px solid transparent; }
  .app-btn:hover { background: var(--dark2); transform: translateY(-1px); }
  .app-btn.zalo { background: var(--primary); color: var(--dark); }
  .app-btn.zalo:hover { background: var(--primary-dark); color: #fff; }
  .app-btn-text .line-s { font-size: 10px; opacity: .65; }
  .app-btn-text .line-b { font-size: 14px; font-weight: 700; }
  .app-platform { width: 20px; height: 20px; border-radius: 3px; display: flex; align-items: center; justify-content: center; font-size: 11px; font-weight: 800; flex-shrink: 0; }
  .ap-ios { background: #fff; color: var(--dark); }
  .ap-android { background: #a4c639; color: #fff; }
  .ap-zalo { background: #fff; color: #0068ff; }

  /* ── ACCESSIBILITY & FOCUS ── */
  .sr-only { position: absolute; width: 1px; height: 1px; padding: 0; margin: -1px; overflow: hidden; clip: rect(0,0,0,0); white-space: nowrap; border: 0; }
  *:focus-visible { outline: none; box-shadow: 0 0 0 3px rgba(144,197,45,.35); border-radius: 8px; }

  /* ── RESPONSIVE ── */
  @media (min-width: 768px) { .feature-grid { grid-template-columns: repeat(2, 1fr); } }
  @media (min-width: 1024px) {
    .feature-grid { grid-template-columns: repeat(3, 1fr); }
    .testi-track { grid-auto-flow: row; grid-auto-columns: unset; grid-template-columns: repeat(3, 1fr); overflow: visible; }
    .testi-card { min-height: 360px; }
  }
  @media (min-width: 1440px) { .section { padding: 5rem 3rem; } }
  @media (max-width: 1023.98px) {
    .hero-inner { grid-template-columns: 1fr; gap: 28px; }
    .float-card { display: none; }
    .store-inner,.promo-inner,.app-inner { grid-template-columns: 1fr; gap: 28px; }
    .product-grid { grid-template-columns: repeat(2, 1fr); }
    .cat-grid { grid-template-columns: repeat(2, 1fr); grid-template-rows: repeat(3, 210px); }
    .footer-grid { grid-template-columns: repeat(2, 1fr); gap: 28px; }
  }
  @media (max-width: 767.98px) {
    .product-grid { grid-template-columns: 1fr; }
    .cat-grid { grid-template-columns: 1fr; grid-template-rows: none; }
    .cat-tile { min-height: 180px; }
    .footer-grid { grid-template-columns: 1fr; gap: 28px; }
    .promo-visual { height: 200px; }
  }
</style>
@endpush

@section('content')
<div class="vp-home">

  <!-- ══ HERO ══ -->
  <section class="hero">
    <div class="hero-bg"></div>
    <div class="hero-inner">
      <div class="hero-content">
        <div class="hero-badge"><div class="dot"></div>Thủy canh sạch từ Đà Lạt</div>
        <h1>Sống Xanh,<br><em>Sống Khỏe</em><br>Mỗi Ngày</h1>
        <p>Rau sạch thủy canh trồng không đất, không thuốc trừ sâu — tươi xanh mỗi sáng trực tiếp từ vườn Đà Lạt đến bàn ăn của bạn.</p>
        <div class="hero-actions">
          <button class="btn-primary">Khám phá ngay</button>
          <button class="btn-secondary">Xem cách trồng</button>
        </div>
        <div class="hero-stats">
          <div class="hero-stat"><div class="num">125+</div><div class="lbl">Loại rau sạch</div></div>
          <div class="hero-stat"><div class="num">2.4K+</div><div class="lbl">Khách hài lòng</div></div>
          <div class="hero-stat"><div class="num">100%</div><div class="lbl">Hữu cơ tự nhiên</div></div>
        </div>
      </div>
      <div class="hero-visual">
        <div class="hero-img-main">
          <img src="{{ asset('images/home-banner.png') }}" alt="Vietponic Banner" class="veg-artwork" style="width:100%;height:100%;object-fit:cover;border-radius:inherit;">
        </div>
        <div class="float-card float-card-1">
          <div class="fc-dot" style="background:var(--primary-deeper)">VP</div>
          <div><div class="fc-title">Chứng nhận VietGAP</div><div class="fc-sub">Kiểm định hàng tuần</div></div>
        </div>
        <div class="float-card float-card-2">
          <div class="fc-dot" style="background:var(--gold)">4H</div>
          <div><div class="fc-title">Giao trong 4 giờ</div><div class="fc-sub">Từ vườn đến bàn ăn</div></div>
        </div>
        <div class="float-card float-card-3">
          <div class="fc-dot" style="background:var(--primary)">★</div>
          <div><div class="fc-title">1.2K đánh giá</div><div class="fc-sub">4.9 / 5 sao</div></div>
        </div>
      </div>
    </div>
  </section>

  <!-- ══ TRUST STRIP ══ -->
  <div class="trust-strip">
    <div class="trust-inner">
      <div class="trust-item">
        <div class="trust-num">100%</div>
        <div><div class="t-title">Hữu Cơ Tự Nhiên</div><div class="t-sub">Không hoá chất độc hại</div></div>
      </div>
      <div class="trust-item">
        <div class="trust-num">48h</div>
        <div><div class="t-title">Thu Hoạch & Giao Hàng</div><div class="t-sub">Từ vườn đến tay bạn</div></div>
      </div>
      <div class="trust-item">
        <div class="trust-num">50K</div>
        <div><div class="t-title">Miễn Phí Vận Chuyển</div><div class="t-sub">Đơn hàng từ 50.000đ</div></div>
      </div>
      <div class="trust-item">
        <div class="trust-num">125+</div>
        <div><div class="t-title">Loại Rau Sạch</div><div class="t-sub">Trải dài 10 danh mục</div></div>
      </div>
      <div class="trust-item">
        <div class="trust-num">4.9</div>
        <div><div class="t-title">Đánh Giá Trung Bình</div><div class="t-sub">Từ 2.400+ khách hàng</div></div>
      </div>
    </div>
  </div>

  <!-- ══ FEATURES ══ -->
  <section class="section" id="tinh-nang" style="background:var(--white)">
    <div class="section-inner">
      <div class="section-header">
        <div>
          <div class="section-label">Tính năng</div>
          <div class="section-title">Tối Giản, Nhanh, Dễ Dùng Trên Mobile</div>
          <div class="section-sub">Tập trung vào trải nghiệm một tay, khoảng trắng hợp lý và độ tương phản tốt để đọc rõ trong mọi tình huống.</div>
        </div>
      </div>
      <div class="feature-grid">
        <article class="feature-card">
          <div class="feature-icon">
            <img loading="lazy" decoding="async" width="22" height="22" alt="" src="data:image/svg+xml;utf8,%3Csvg%20xmlns='http://www.w3.org/2000/svg'%20width='22'%20height='22'%20viewBox='0%200%2024%2024'%20fill='none'%3E%3Cpath%20d='M12%202a7%207%200%200%200-7%207c0%202.03.86%203.86%202.23%205.14L6%2022h12l-1.23-7.86A7.07%207.07%200%200%200%2019%209a7%207%200%200%200-7-7Z'%20stroke='%231e2d0a'%20stroke-width='2'%20stroke-linecap='round'%20stroke-linejoin='round'/%3E%3Cpath%20d='M9%2022h6'%20stroke='%231e2d0a'%20stroke-width='2'%20stroke-linecap='round'/%3E%3C/svg%3E">
          </div>
          <div>
            <h3>Visual Hierarchy Rõ Ràng</h3>
            <p>Heading, subheading và nội dung được sắp xếp có chủ đích để bạn nắm thông tin chính ngay lập tức, đồng thời đảm bảo đọc dễ trên màn hình nhỏ.</p>
          </div>
        </article>
        <article class="feature-card">
          <div class="feature-icon">
            <img loading="lazy" decoding="async" width="22" height="22" alt="" src="data:image/svg+xml;utf8,%3Csvg%20xmlns='http://www.w3.org/2000/svg'%20width='22'%20height='22'%20viewBox='0%200%2024%2024'%20fill='none'%3E%3Cpath%20d='M7%207h14l-1.2%2012H8.2L7%207Z'%20stroke='%231e2d0a'%20stroke-width='2'%20stroke-linecap='round'%20stroke-linejoin='round'/%3E%3Cpath%20d='M7%207l-2-3h-2'%20stroke='%231e2d0a'%20stroke-width='2'%20stroke-linecap='round'%20stroke-linejoin='round'/%3E%3Cpath%20d='M9%2021a1%201%200%201%200%200-2%201%201%200%200%200%200%202Z'%20fill='%231e2d0a'/%3E%3Cpath%20d='M18%2021a1%201%200%201%200%200-2%201%201%200%200%200%200%202Z'%20fill='%231e2d0a'/%3E%3C/svg%3E">
          </div>
          <div>
            <h3>Touch Target Chuẩn 48×48</h3>
            <p>Tất cả nút bấm và liên kết quan trọng đều đủ lớn để chạm chính xác, hạn chế thao tác sai; hover/focus rõ ràng với chuyển động 0.3s.</p>
          </div>
        </article>
        <article class="feature-card">
          <div class="feature-icon">
            <img loading="lazy" decoding="async" width="22" height="22" alt="" src="data:image/svg+xml;utf8,%3Csvg%20xmlns='http://www.w3.org/2000/svg'%20width='22'%20height='22'%20viewBox='0%200%2024%2024'%20fill='none'%3E%3Cpath%20d='M12%202l8%204v6c0%205-3.5%209-8%2010-4.5-1-8-5-8-10V6l8-4Z'%20stroke='%231e2d0a'%20stroke-width='2'%20stroke-linecap='round'%20stroke-linejoin='round'/%3E%3Cpath%20d='M9%2012l2%202%204-4'%20stroke='%231e2d0a'%20stroke-width='2'%20stroke-linecap='round'%20stroke-linejoin='round'/%3E%3C/svg%3E">
          </div>
          <div>
            <h3>Nhẹ Và Nhanh</h3>
            <p>Icon dùng SVG gọn nhẹ và ảnh trong card được lazy-load để tăng tốc tải trang. Bố cục grid/flex đảm bảo responsive mượt trên mọi thiết bị.</p>
          </div>
        </article>
      </div>
    </div>
  </section>

  <!-- ══ SẢN PHẨM NỔI BẬT (dữ liệu động từ $newestProducts) ══ -->
  <section class="section" id="san-pham" style="background: var(--white)">
    <div class="section-inner">
      <div class="section-header">
        <div>
          <div class="section-label">Nổi bật</div>
          <div class="section-title">Rau Thủy Canh Bán Chạy Nhất</div>
          <div class="section-sub">Tươi ngon từ vườn Đà Lạt, giao nhanh mỗi buổi sáng</div>
        </div>
        <a href="#" class="see-all">Xem tất cả</a>
      </div>
      <div class="product-grid">
        @php $zaloAppId = '2984181565024919663'; @endphp
        @forelse($newestProducts as $product)
        @php $zaloProductUrl = 'https://zalo.me/s/' . $zaloAppId . '?path=' . urlencode('/product/' . $product->id); @endphp
        <div class="prod-card" onclick="window.open('{{ $zaloProductUrl }}','_blank','noopener')" style="cursor:pointer">
          <div class="prod-img" style="background:linear-gradient(145deg,var(--primary-light),var(--primary))">
            @if(!empty($product->image))
              <img src="{{ asset($product->image) }}" alt="{{ $product->name }}" loading="lazy">
            @else
              <span style="font-size:52px">🥬</span>
            @endif
            @if($loop->first)
              <div class="prod-tag">Bán chạy</div>
            @endif
          </div>
          <div class="prod-body">
            <div class="prod-category">{{ $product->category->name ?? 'Rau sạch' }}</div>
            <div class="prod-name">{{ $product->name }}</div>
            <div class="prod-meta">
              <div class="prod-price">{{ number_format((float)$product->price) }}<small>đ</small></div>
              <div class="prod-origin">Đà Lạt</div>
            </div>
            <a href="{{ $zaloProductUrl }}" target="_blank" rel="noopener" class="add-btn" style="text-decoration:none;display:block;text-align:center">Thêm vào giỏ</a>
          </div>
        </div>
        @empty
        <div class="prod-card" style="cursor:pointer" onclick="window.open('https://zalo.me/s/{{ $zaloAppId }}','_blank','noopener')">
          <div class="prod-img" style="background:linear-gradient(145deg,#d0e8b0,#90c040)">
            <span style="font-size:58px">🥬</span>
            <div class="prod-tag">Bán chạy</div>
          </div>
          <div class="prod-body">
            <div class="prod-category">Rau lá xanh</div>
            <div class="prod-name">Xà lách thủy canh Đà Lạt</div>
            <div class="prod-meta">
              <div class="prod-price">25.000<small>đ/túi</small></div>
              <div class="prod-origin">Đà Lạt</div>
            </div>
            <a href="https://zalo.me/s/{{ $zaloAppId }}" target="_blank" rel="noopener" class="add-btn" style="text-decoration:none;display:block;text-align:center">Thêm vào giỏ</a>
          </div>
        </div>
        <div class="prod-card" style="cursor:pointer" onclick="window.open('https://zalo.me/s/{{ $zaloAppId }}','_blank','noopener')">
          <div class="prod-img" style="background:linear-gradient(145deg,#c8e8d0,#70b888)">
            <span style="font-size:58px">🌿</span>
          </div>
          <div class="prod-body">
            <div class="prod-category">Rau thơm</div>
            <div class="prod-name">Cải thảo thủy canh hữu cơ</div>
            <div class="prod-meta">
              <div class="prod-price">30.000<small>đ/bó</small></div>
              <div class="prod-origin">Đà Lạt</div>
            </div>
            <a href="https://zalo.me/s/{{ $zaloAppId }}" target="_blank" rel="noopener" class="add-btn" style="text-decoration:none;display:block;text-align:center">Thêm vào giỏ</a>
          </div>
        </div>
        <div class="prod-card" style="cursor:pointer" onclick="window.open('https://zalo.me/s/{{ $zaloAppId }}','_blank','noopener')">
          <div class="prod-img" style="background:linear-gradient(145deg,#faecc0,#e8c050)">
            <span style="font-size:58px">🌽</span>
            <div class="prod-tag gold">Mới về</div>
          </div>
          <div class="prod-body">
            <div class="prod-category">Rau củ quả</div>
            <div class="prod-name">Baby corn thủy canh non</div>
            <div class="prod-meta">
              <div class="prod-price">45.000<small>đ/200g</small></div>
              <div class="prod-origin">Đà Lạt</div>
            </div>
            <a href="https://zalo.me/s/{{ $zaloAppId }}" target="_blank" rel="noopener" class="add-btn" style="text-decoration:none;display:block;text-align:center">Thêm vào giỏ</a>
          </div>
        </div>
        <div class="prod-card" style="cursor:pointer" onclick="window.open('https://zalo.me/s/{{ $zaloAppId }}','_blank','noopener')">
          <div class="prod-img" style="background:linear-gradient(145deg,#f8d8c8,#e07050)">
            <span style="font-size:58px">🍅</span>
          </div>
          <div class="prod-body">
            <div class="prod-category">Cà chua</div>
            <div class="prod-name">Cà chua bi cherry thủy canh</div>
            <div class="prod-meta">
              <div class="prod-price">35.000<small>đ/hộp</small></div>
              <div class="prod-origin">Đà Lạt</div>
            </div>
            <a href="https://zalo.me/s/{{ $zaloAppId }}" target="_blank" rel="noopener" class="add-btn" style="text-decoration:none;display:block;text-align:center">Thêm vào giỏ</a>
          </div>
        </div>
        @endforelse
      </div>
    </div>
  </section>

  <!-- ══ PROMO BANNER ══ -->
  <div class="promo-banner">
    <div class="promo-inner">
      <div class="promo-visual">
        <div class="promo-tile pt1"><img src="{{ asset('images/vegetable-box/Banner.jpg') }}" alt="Rau tươi" style="width:100%;height:100%;object-fit:cover;border-radius:inherit;" /></div>
        <div class="promo-tile pt2"><img src="{{ asset('images/vegetable-box/banner1.jpg') }}" alt="Rau thủy canh" style="width:100%;height:100%;object-fit:cover;border-radius:inherit;" /></div>
        <div class="promo-tile pt3"><img src="{{ asset('images/vegetable-box/banner2.jpg') }}" alt="Rau sạch" style="width:100%;height:100%;object-fit:cover;border-radius:inherit;" /></div>
      </div>
      <div class="promo-content">
        <div class="promo-badge">Ưu đãi hôm nay</div>
        <h2>Combo Rau Sạch<br><em>Cuối Tuần</em></h2>
        <p>Mua combo 5 loại rau thủy canh tươi được chọn lọc kỹ càng — tiết kiệm hơn 30% so với mua lẻ. Giao tận nhà trước 8 giờ sáng.</p>
        <div class="promo-feats">
          <div class="promo-feat"><div class="promo-feat-dot"></div>5 loại rau được tuyển chọn theo mùa</div>
          <div class="promo-feat"><div class="promo-feat-dot"></div>1kg rau cải và rau lá xanh tươi</div>
          <div class="promo-feat"><div class="promo-feat-dot"></div>Giao miễn phí toàn quốc trong ngày</div>
        </div>
        <button class="btn-gold">Mua combo chỉ 99.000đ</button>
      </div>
    </div>
  </div>

  <!-- ══ KHÁM PHÁ THEO DANH MỤC (filter + sản phẩm) ══ -->
  <section class="section" style="background:var(--cream)">
    <div class="section-inner">
      <div class="section-header">
        <div>
          <div class="section-label">Khám phá</div>
          <div class="section-title">Thực Phẩm Sạch Cho Sức Khoẻ</div>
          <div class="section-sub">Chọn lọc từ vườn thủy canh uy tín nhất Đà Lạt</div>
        </div>
        <a href="#" class="see-all">Xem tất cả</a>
      </div>
      <div class="cat-filter" id="explore-filter" role="group" aria-label="Lọc theo danh mục">
        <button class="cat-pill active" type="button" data-id="">Tất cả</button>
        @foreach($allCategories as $cat)
          <button class="cat-pill" type="button" data-id="{{ $cat->id }}">{{ $cat->name }}</button>
        @endforeach
      </div>
      <div class="product-grid" id="explore-grid">
        @forelse($exploreProducts as $product)
          @php $zaloProductUrl = 'https://zalo.me/s/2984181565024919663?path=' . urlencode('/product/' . $product->id); @endphp
          <div class="prod-card" data-category-id="{{ $product->category_id }}" data-product-id="{{ $product->id }}" data-zalo-url="{{ $zaloProductUrl }}" onclick="window.open(this.dataset.zaloUrl,'_blank','noopener')" style="cursor:pointer">
            <div class="prod-img" style="background:linear-gradient(145deg,var(--primary-light),var(--primary))">
              @if(!empty($product->image))
                <img src="{{ asset($product->image) }}" alt="{{ $product->name }}" loading="lazy">
              @else
                <span style="font-size:52px">🥬</span>
              @endif
            </div>
            <div class="prod-body">
              <div class="prod-category">{{ $product->category->name ?? 'Rau sạch' }}</div>
              <div class="prod-name">{{ $product->name }}</div>
              <div class="prod-meta">
                <div class="prod-price">{{ number_format((float)$product->price) }}<small>đ</small></div>
                <div class="prod-origin">Đà Lạt</div>
              </div>
              <a href="{{ $zaloProductUrl }}" target="_blank" rel="noopener" class="add-btn" style="text-decoration:none;display:block;text-align:center">Thêm vào giỏ</a>
            </div>
          </div>
        @empty
          <p style="color:var(--text-muted);grid-column:1/-1;text-align:center;padding:2rem 0;">Chưa có sản phẩm nào.</p>
        @endforelse
      </div>
    </div>
  </section>

  <!-- ══ DANH MỤC TILES ══ -->
  <section class="section" style="background:var(--white)">
    <div class="section-inner">
      <div class="section-header">
        <div>
          <div class="section-label">Danh mục</div>
          <div class="section-title">Khám Phá Theo Danh Mục</div>
          <div class="section-sub">{{ $allCategories->count() }} danh mục rau sạch, tươi ngon mỗi ngày</div>
        </div>
        <a href="#san-pham" class="see-all">Xem tất cả</a>
      </div>
      @php
        $ctColors = ['ct1','ct2','ct3','ct4','ct5','ct6'];
        $ctEmojis = ['🥬','🌿','🍅','🌱','🥗','🫐'];
      @endphp
      <div class="cat-grid">
        @forelse($featuredCategories as $i => $cat)
        @php $zaloCatUrl = 'https://zalo.me/s/2984181565024919663?path=' . urlencode('/category/' . $cat->id); @endphp
        <a href="{{ $zaloCatUrl }}" target="_blank" rel="noopener" class="cat-tile {{ $ctColors[$i % 6] }}" data-filter-id="{{ $cat->id }}" style="text-decoration:none">
          <div class="ct-bg">
            @if($cat->getRawOriginal('image'))
              <img src="{{ $cat->image }}" alt="{{ $cat->name }}" style="width:100%;height:100%;object-fit:cover;position:absolute;inset:0;z-index:0;">
            @else
              {{ $ctEmojis[$i % 6] }}
            @endif
          </div>
          <div class="ct-overlay"></div>
          <div class="ct-content">
            <div class="ct-label">{{ $cat->name }}</div>
            <div class="ct-count">{{ $cat->products_count }} sản phẩm</div>
            <div class="ct-btn">Xem ngay</div>
          </div>
        </a>
        @empty
        <p style="color:var(--text-muted)">Chưa có danh mục nào.</p>
        @endforelse
      </div>
    </div>
  </section>

  <!-- ══ VỀ VIETPONICS ══ -->
  <section class="section store-section">
    <div class="section-inner">
      <div class="store-inner">
        <div class="store-visual">
          <img src="{{ asset('images/banner.jpeg') }}" alt="Vietponics banner" style="width:100%;height:100%;object-fit:cover;border-radius:inherit;" />
          <div class="store-badge">
            <div class="sb-num">48h</div>
            <div class="sb-unit">Tươi bảo đảm</div>
            <div class="sb-desc">kể từ khi thu hoạch</div>
          </div>
        </div>
        <div class="store-content">
          <div class="eyebrow">Về Vietponics</div>
          <h2>Vườn Thủy Canh Công Nghệ Cao Giữa Lòng Đà Lạt</h2>
          <p>Vietponics ứng dụng công nghệ thủy canh hiện đại — trồng rau không cần đất, kiểm soát chặt chẽ dinh dưỡng và nhiệt độ — để mang đến rau sạch tiêu chuẩn quốc tế ngay tại Việt Nam.</p>
          <div class="store-feats">
            <div class="store-feat">
              <div class="sf-num">01</div>
              <div><div class="sf-title">Được chứng nhận VietGAP & GlobalGAP</div><div class="sf-desc">Kiểm định định kỳ bởi bên thứ ba độc lập</div></div>
            </div>
            <div class="store-feat">
              <div class="sf-num">02</div>
              <div><div class="sf-title">Thu hoạch & đóng gói trong 2 giờ</div><div class="sf-desc">Giữ nguyên dưỡng chất và độ tươi tối đa</div></div>
            </div>
            <div class="store-feat">
              <div class="sf-num">03</div>
              <div><div class="sf-title">Đa dạng 125+ loại rau sạch</div><div class="sf-desc">Trải dài 10 danh mục từ rau lá đến trái cây</div></div>
            </div>
          </div>
          <button class="btn-primary" type="button">Tham quan vườn ảo</button>
        </div>
      </div>
    </div>
  </section>

  <!-- ══ ĐÁNH GIÁ KHÁCH HÀNG ══ -->
  <section class="section testi-section" id="danh-gia">
    <div class="section-inner">
      <div class="section-header">
        <div>
          <div class="section-label">Phản hồi</div>
          <div class="section-title">Khách Hàng Nói Gì?</div>
          <div class="section-sub">Hơn 2.400 gia đình đã tin tưởng Vietponics</div>
        </div>
        <a href="#" class="see-all">Xem tất cả đánh giá</a>
      </div>
      <div class="testi-track" role="list" aria-label="Đánh giá khách hàng">
        <article class="testi-card" role="listitem">
          <div class="stars" aria-label="5 sao">
            <svg class="star" viewBox="0 0 24 24" aria-hidden="true"><path d="M12 2l3.09 6.26L22 9.27l-5 4.87 1.18 6.88L12 17.77l-6.18 3.25L7 14.14 2 9.27l6.91-1.01L12 2z"/></svg>
            <svg class="star" viewBox="0 0 24 24" aria-hidden="true"><path d="M12 2l3.09 6.26L22 9.27l-5 4.87 1.18 6.88L12 17.77l-6.18 3.25L7 14.14 2 9.27l6.91-1.01L12 2z"/></svg>
            <svg class="star" viewBox="0 0 24 24" aria-hidden="true"><path d="M12 2l3.09 6.26L22 9.27l-5 4.87 1.18 6.88L12 17.77l-6.18 3.25L7 14.14 2 9.27l6.91-1.01L12 2z"/></svg>
            <svg class="star" viewBox="0 0 24 24" aria-hidden="true"><path d="M12 2l3.09 6.26L22 9.27l-5 4.87 1.18 6.88L12 17.77l-6.18 3.25L7 14.14 2 9.27l6.91-1.01L12 2z"/></svg>
            <svg class="star" viewBox="0 0 24 24" aria-hidden="true"><path d="M12 2l3.09 6.26L22 9.27l-5 4.87 1.18 6.88L12 17.77l-6.18 3.25L7 14.14 2 9.27l6.91-1.01L12 2z"/></svg>
          </div>
          <div class="testi-text">Gia đình mình chuyển sang đặt rau thủy canh của Vietponics vì muốn bữa ăn "nhẹ" hơn và yên tâm nguồn gốc. Cảm giác tươi rất rõ: lá giòn, ít dập, đóng gói sạch sẽ và thông tin hiển thị rõ ràng. Mình thường đặt tối, sáng hôm sau là có rau dùng ngay. Quan trọng nhất là chất lượng ổn định qua từng tuần và rau giữ tươi trong tủ mát lâu hơn, giảm lãng phí đáng kể.</div>
          <div class="testi-author">
            <img class="avatar-img" loading="lazy" decoding="async" width="80" height="80" alt="Ảnh đại diện của Lê Ngọc Anh" src="data:image/svg+xml;utf8,%3Csvg%20xmlns='http://www.w3.org/2000/svg'%20width='80'%20height='80'%20viewBox='0%200%2080%2080'%3E%3Cdefs%3E%3CradialGradient%20id='g'%20cx='30%25'%20cy='25%25'%20r='75%25'%3E%3Cstop%20offset='0%25'%20stop-color='%23d8edaa'/%3E%3Cstop%20offset='100%25'%20stop-color='%2390c52d'/%3E%3C/radialGradient%3E%3C/defs%3E%3Crect%20width='80'%20height='80'%20rx='40'%20fill='url(%23g)'/%3E%3Ctext%20x='50%25'%20y='54%25'%20text-anchor='middle'%20font-family='system-ui'%20font-size='22'%20font-weight='800'%20fill='%231e2d0a'%3ELN%3C/text%3E%3C/svg%3E">
            <div>
              <div class="testi-name">Lê Ngọc Anh</div>
              <div class="testi-role">Nhân viên văn phòng · TP. Hồ Chí Minh</div>
            </div>
          </div>
        </article>
        <article class="testi-card" role="listitem">
          <div class="stars" aria-label="5 sao">
            <svg class="star" viewBox="0 0 24 24" aria-hidden="true"><path d="M12 2l3.09 6.26L22 9.27l-5 4.87 1.18 6.88L12 17.77l-6.18 3.25L7 14.14 2 9.27l6.91-1.01L12 2z"/></svg>
            <svg class="star" viewBox="0 0 24 24" aria-hidden="true"><path d="M12 2l3.09 6.26L22 9.27l-5 4.87 1.18 6.88L12 17.77l-6.18 3.25L7 14.14 2 9.27l6.91-1.01L12 2z"/></svg>
            <svg class="star" viewBox="0 0 24 24" aria-hidden="true"><path d="M12 2l3.09 6.26L22 9.27l-5 4.87 1.18 6.88L12 17.77l-6.18 3.25L7 14.14 2 9.27l6.91-1.01L12 2z"/></svg>
            <svg class="star" viewBox="0 0 24 24" aria-hidden="true"><path d="M12 2l3.09 6.26L22 9.27l-5 4.87 1.18 6.88L12 17.77l-6.18 3.25L7 14.14 2 9.27l6.91-1.01L12 2z"/></svg>
            <svg class="star" viewBox="0 0 24 24" aria-hidden="true"><path d="M12 2l3.09 6.26L22 9.27l-5 4.87 1.18 6.88L12 17.77l-6.18 3.25L7 14.14 2 9.27l6.91-1.01L12 2z"/></svg>
          </div>
          <div class="testi-text">Mình kinh doanh quán ăn chay nên nguồn rau phải ổn định và "đẹp" khi lên món. Vietponics làm tốt ở cả hai: rau sạch, thân lá đồng đều, ít hao khi sơ chế và màu sắc nhìn rất "tươi" khi bày đĩa. Việc đặt hàng trên điện thoại cũng đơn giản, nút thao tác lớn nên nhân viên mới làm được ngay, giảm sai sót khi đặt số lượng.</div>
          <div class="testi-author">
            <img class="avatar-img" loading="lazy" decoding="async" width="80" height="80" alt="Ảnh đại diện của Trần Minh Khoa" src="data:image/svg+xml;utf8,%3Csvg%20xmlns='http://www.w3.org/2000/svg'%20width='80'%20height='80'%20viewBox='0%200%2080%2080'%3E%3Cdefs%3E%3CradialGradient%20id='g'%20cx='30%25'%20cy='25%25'%20r='75%25'%3E%3Cstop%20offset='0%25'%20stop-color='%23f5c842'/%3E%3Cstop%20offset='100%25'%20stop-color='%23e8a20c'/%3E%3C/radialGradient%3E%3C/defs%3E%3Crect%20width='80'%20height='80'%20rx='40'%20fill='url(%23g)'/%3E%3Ctext%20x='50%25'%20y='54%25'%20text-anchor='middle'%20font-family='system-ui'%20font-size='22'%20font-weight='800'%20fill='%231e2d0a'%3ETM%3C/text%3E%3C/svg%3E">
            <div>
              <div class="testi-name">Trần Minh Khoa</div>
              <div class="testi-role">Chủ quán chay · Đà Nẵng</div>
            </div>
          </div>
        </article>
        <article class="testi-card" role="listitem">
          <div class="stars" aria-label="5 sao">
            <svg class="star" viewBox="0 0 24 24" aria-hidden="true"><path d="M12 2l3.09 6.26L22 9.27l-5 4.87 1.18 6.88L12 17.77l-6.18 3.25L7 14.14 2 9.27l6.91-1.01L12 2z"/></svg>
            <svg class="star" viewBox="0 0 24 24" aria-hidden="true"><path d="M12 2l3.09 6.26L22 9.27l-5 4.87 1.18 6.88L12 17.77l-6.18 3.25L7 14.14 2 9.27l6.91-1.01L12 2z"/></svg>
            <svg class="star" viewBox="0 0 24 24" aria-hidden="true"><path d="M12 2l3.09 6.26L22 9.27l-5 4.87 1.18 6.88L12 17.77l-6.18 3.25L7 14.14 2 9.27l6.91-1.01L12 2z"/></svg>
            <svg class="star" viewBox="0 0 24 24" aria-hidden="true"><path d="M12 2l3.09 6.26L22 9.27l-5 4.87 1.18 6.88L12 17.77l-6.18 3.25L7 14.14 2 9.27l6.91-1.01L12 2z"/></svg>
            <svg class="star" viewBox="0 0 24 24" aria-hidden="true"><path d="M12 2l3.09 6.26L22 9.27l-5 4.87 1.18 6.88L12 17.77l-6.18 3.25L7 14.14 2 9.27l6.91-1.01L12 2z"/></svg>
          </div>
          <div class="testi-text">Mình hay mua rau cho bố mẹ nên ưu tiên chỗ nào đặt nhanh, theo dõi đơn rõ và giao đúng giờ. Trải nghiệm trên điện thoại của Vietponics rất dễ dùng: menu gọn, chữ rõ, bấm một tay vẫn thoải mái. Rau nhận đúng mô tả: sạch, thơm, tươi; bó rau không bị dập và giữ được lâu trong tủ mát. Bố mẹ mình cũng nhận xét vị rau ngọt, dễ ăn.</div>
          <div class="testi-author">
            <img class="avatar-img" loading="lazy" decoding="async" width="80" height="80" alt="Ảnh đại diện của Hoàng Thị Bích" src="data:image/svg+xml;utf8,%3Csvg%20xmlns='http://www.w3.org/2000/svg'%20width='80'%20height='80'%20viewBox='0%200%2080%2080'%3E%3Cdefs%3E%3CradialGradient%20id='g'%20cx='30%25'%20cy='25%25'%20r='75%25'%3E%3Cstop%20offset='0%25'%20stop-color='%23d6e8a8'/%3E%3Cstop%20offset='100%25'%20stop-color='%232e4210'/%3E%3C/radialGradient%3E%3C/defs%3E%3Crect%20width='80'%20height='80'%20rx='40'%20fill='url(%23g)'/%3E%3Ctext%20x='50%25'%20y='54%25'%20text-anchor='middle'%20font-family='system-ui'%20font-size='22'%20font-weight='800'%20fill='%23ffffff'%3EHT%3C/text%3E%3C/svg%3E">
            <div>
              <div class="testi-name">Hoàng Thị Bích</div>
              <div class="testi-role">Nội trợ · Hà Nội</div>
            </div>
          </div>
        </article>
      </div>
    </div>
  </section>

  <!-- ══ ZALO MINI APP ══ -->
  <section class="app-section">
    <div class="app-inner">
      <div class="app-visual">
        <img src="{{ asset('images/mobi-banner.png') }}" alt="Vietponics app" style="max-height:560px;width:auto;max-width:100%;object-fit:contain;display:block;margin:0 auto;drop-shadow(0 24px 48px rgba(0,0,0,.15));" />
      </div>
      <div class="app-content">
        <div class="eyebrow">Zalo Mini App</div>
        <h2>Đặt Rau Sạch Ngay Trên Zalo — Không Cần Cài App</h2>
        <p>Vietponics tích hợp hoàn toàn trên Zalo Mini App. Duyệt sản phẩm, đặt hàng, thanh toán ZaloPay và theo dõi giao hàng — tất cả trong một ứng dụng quen thuộc.</p>
        <div class="app-btns">
          <a href="https://zalo.me/s/2984181565024919663" target="_blank" rel="noopener" class="app-btn zalo">
            <div class="app-platform ap-zalo">Z</div>
            <div class="app-btn-text"><div class="line-s">Mở trên</div><div class="line-b">Zalo App</div></div>
          </a>
        </div>
      </div>
    </div>
  </section>

</div>{{-- /.vp-home --}}
@endsection

@push('scripts')
<script>
  /* ── Explore section: category pill filter with AJAX ── */
  (function () {
    var filter = document.getElementById('explore-filter');
    var grid   = document.getElementById('explore-grid');
    if (!filter || !grid) return;

    var scrollObs = null;
    if ('IntersectionObserver' in window) {
      scrollObs = new IntersectionObserver(function (entries) {
        entries.forEach(function (e) { if (e.isIntersecting) { e.target.style.opacity = '1'; e.target.style.transform = 'translateY(0)'; } });
      }, { threshold: 0.08 });
    }
    function observeCard(card) {
      if (!scrollObs) return;
      card.style.opacity = '0'; card.style.transform = 'translateY(14px)'; card.style.transition = 'opacity .45s ease, transform .45s ease';
      scrollObs.observe(card);
    }

    var ZALO_APP_ID = '2984181565024919663';
    function zaloProductUrl(productId) {
      return 'https://zalo.me/s/' + ZALO_APP_ID + '?path=' + encodeURIComponent('/product/' + productId);
    }

    function makeCard(product) {
      var card = document.createElement('div');
      card.className = 'prod-card';
      card.setAttribute('data-category-id', product.category_id);
      card.style.cursor = 'pointer';
      var url = zaloProductUrl(product.id);
      card.addEventListener('click', function () { window.open(url, '_blank', 'noopener'); });

      var imgWrap = document.createElement('div');
      imgWrap.className = 'prod-img';
      imgWrap.style.background = 'linear-gradient(145deg,var(--primary-light),var(--primary))';
      if (product.image) {
        var img = document.createElement('img');
        img.src = product.image; img.alt = product.name; img.loading = 'lazy';
        imgWrap.appendChild(img);
      } else {
        var fb = document.createElement('span'); fb.style.fontSize = '52px'; fb.textContent = '🥬';
        imgWrap.appendChild(fb);
      }
      card.appendChild(imgWrap);

      var body = document.createElement('div'); body.className = 'prod-body';
      var catEl = document.createElement('div'); catEl.className = 'prod-category'; catEl.textContent = product.category_name || 'Rau sạch';
      var nameEl = document.createElement('div'); nameEl.className = 'prod-name'; nameEl.textContent = product.name;
      var meta = document.createElement('div'); meta.className = 'prod-meta';
      var priceEl = document.createElement('div'); priceEl.className = 'prod-price';
      priceEl.innerHTML = (parseFloat(product.price) || 0).toLocaleString('vi-VN') + '<small>đ</small>';
      var originEl = document.createElement('div'); originEl.className = 'prod-origin'; originEl.textContent = 'Đà Lạt';
      meta.appendChild(priceEl); meta.appendChild(originEl);
      var addLink = document.createElement('a');
      addLink.className = 'add-btn'; addLink.href = url; addLink.target = '_blank'; addLink.rel = 'noopener';
      addLink.style.cssText = 'text-decoration:none;display:block;text-align:center';
      addLink.textContent = 'Thêm vào giỏ';
      addLink.addEventListener('click', function (e) { e.stopPropagation(); });
      body.appendChild(catEl); body.appendChild(nameEl); body.appendChild(meta); body.appendChild(addLink);
      card.appendChild(body);
      return card;
    }

    function renderProducts(products) {
      grid.innerHTML = '';
      var slice = products.slice(0, 8);
      if (!slice.length) {
        var empty = document.createElement('p');
        empty.style.cssText = 'color:var(--text-muted);grid-column:1/-1;text-align:center;padding:2rem 0;';
        empty.textContent = 'Chưa có sản phẩm nào trong danh mục này.';
        grid.appendChild(empty); return;
      }
      slice.forEach(function (p) { var c = makeCard(p); grid.appendChild(c); observeCard(c); });
    }

    function setLoading(on) {
      grid.style.opacity = on ? '0.45' : '1';
      grid.style.pointerEvents = on ? 'none' : '';
      grid.style.transition = 'opacity .2s';
    }

    filter.addEventListener('click', function (e) {
      var btn = e.target.closest('.cat-pill');
      if (!btn) return;
      filter.querySelectorAll('.cat-pill').forEach(function (b) { b.classList.remove('active'); });
      btn.classList.add('active');
      var catId = btn.getAttribute('data-id');
      var url = '/api/products' + (catId ? '?categoryId=' + encodeURIComponent(catId) : '');
      setLoading(true);
      fetch(url)
        .then(function (r) { if (!r.ok) throw new Error('HTTP ' + r.status); return r.json(); })
        .then(function (json) { setLoading(false); if (!json.error) renderProducts(json.data); })
        .catch(function (err) { setLoading(false); console.error('Explore filter error:', err); });
    });
  })();

  /* Scroll-in animation via IntersectionObserver */
  if ('IntersectionObserver' in window) {
    var obs = new IntersectionObserver(function(entries) {
      entries.forEach(function(e) {
        if (e.isIntersecting) {
          e.target.style.opacity = '1';
          e.target.style.transform = 'translateY(0)';
        }
      });
    }, { threshold: 0.08 });
    document.querySelectorAll('.prod-card, .testi-card, .cat-tile, .store-feat, .feature-card').forEach(function(el) {
      el.style.opacity = '0';
      el.style.transform = 'translateY(14px)';
      el.style.transition = 'opacity .45s ease, transform .45s ease';
      obs.observe(el);
    });
  }
</script>
@endpush
