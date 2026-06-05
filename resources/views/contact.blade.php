@extends('frontends.vietponics_master')

@section('title', 'Liên hệ - Vietponics')
@section('description', 'Liên hệ với Vietponics - Rau sạch thủy canh Đà Lạt. Điện thoại, email và địa chỉ.')

@push('styles')
<style>
  .ct-hero {
    background: linear-gradient(135deg, var(--primary-pale), var(--cream2));
    border-bottom: 1px solid var(--border);
    padding: clamp(2.5rem, 6vw, 5rem) clamp(1rem, 4vw, 2rem);
    text-align: center;
  }
  .ct-hero h1 { font-family: 'Lora', Georgia, serif; font-size: clamp(28px, 5vw, 42px); font-weight: 700; color: var(--dark); line-height: 1.2; margin-bottom: 12px; }
  .ct-hero p { font-size: 16px; color: var(--text-muted); max-width: 600px; margin: 0 auto; line-height: 1.7; }

  .ct-section { max-width: 1000px; margin: 0 auto; padding: clamp(2.5rem, 6vw, 4.5rem) clamp(1rem, 4vw, 2rem); }
  .ct-cards { display: grid; grid-template-columns: repeat(3, 1fr); gap: 24px; }
  .ct-card { background: var(--white); border: 1px solid var(--border); border-radius: var(--r); padding: 32px 24px; text-align: center; box-shadow: var(--shadow); }
  .ct-card-icon { width: 56px; height: 56px; border-radius: 50%; background: var(--primary-pale); display: flex; align-items: center; justify-content: center; font-size: 24px; margin: 0 auto 16px; }
  .ct-card h3 { font-size: 16px; font-weight: 700; color: var(--dark); margin-bottom: 6px; }
  .ct-card p { font-size: 13.5px; color: var(--text-muted); line-height: 1.6; margin-bottom: 12px; }
  .ct-card a { font-size: 15px; font-weight: 600; color: var(--primary-dark); text-decoration: none; word-break: break-word; }
  .ct-card a:hover { text-decoration: underline; }

  .ct-social { text-align: center; margin-top: 36px; }
  .ct-social-title { font-size: 14px; color: var(--text-muted); margin-bottom: 14px; }
  .ct-social-links { display: flex; gap: 12px; justify-content: center; }
  .ct-social-links a { width: 44px; height: 44px; border-radius: 50%; background: var(--primary-pale); border: 1px solid var(--border); display: flex; align-items: center; justify-content: center; color: var(--primary-dark); text-decoration: none; transition: all .2s; }
  .ct-social-links a:hover { background: var(--primary); color: #fff; border-color: var(--primary); }

  @media (max-width: 767.98px) {
    .ct-cards { grid-template-columns: 1fr; }
  }
</style>
@endpush

@section('content')
<section class="ct-hero">
  <h1>Liên hệ với Vietponics</h1>
  <p>Chúng tôi luôn sẵn sàng lắng nghe và hỗ trợ bạn. Hãy liên hệ qua các kênh dưới đây.</p>
</section>

<section class="ct-section">
  <div class="ct-cards">
    <div class="ct-card">
      <div class="ct-card-icon">✉️</div>
      <h3>Email</h3>
      <p>Gửi thắc mắc của bạn, chúng tôi sẽ phản hồi sớm nhất.</p>
      <a href="mailto:tbqh0511@gmail.com">tbqh0511@gmail.com</a>
    </div>
    <div class="ct-card">
      <div class="ct-card-icon">📞</div>
      <h3>Điện thoại</h3>
      <p>Gọi cho chúng tôi để được tư vấn và đặt hàng trực tiếp.</p>
      <a href="tel:0908041047">0908041047</a>
    </div>
    <div class="ct-card">
      <div class="ct-card-icon">📍</div>
      <h3>Địa chỉ</h3>
      <p>Ghé thăm chúng tôi tại:</p>
      <a href="https://maps.google.com/?q=9C+Lữ+Gia+Đà+Lạt" target="_blank" rel="noopener">9C Lữ Gia, Phường Lâm Viên - Đà Lạt, Tỉnh Lâm Đồng, Việt Nam</a>
    </div>
  </div>

  <div class="ct-social">
    <div class="ct-social-title">Theo dõi Vietponics</div>
    <div class="ct-social-links">
      <a href="https://www.facebook.com/" target="_blank" rel="noopener" aria-label="Facebook"><i class="fab fa-facebook-f"></i></a>
      <a href="https://zalo.me/s/2984181565024919663" target="_blank" rel="noopener" aria-label="Zalo"><strong>Z</strong></a>
    </div>
  </div>
</section>
@endsection
