@extends('frontends.vietponics_master')

@section('title', 'Giới thiệu - Vietponics')
@section('description', 'Vietponics - Rau sạch thủy canh trồng tại Đà Lạt, giao tận nhà. Cam kết sạch, tươi và bền vững.')

@push('styles')
<style>
  .ab-hero {
    background: linear-gradient(135deg, var(--primary-pale), var(--cream2));
    border-bottom: 1px solid var(--border);
    padding: clamp(2.5rem, 6vw, 5rem) clamp(1rem, 4vw, 2rem);
    text-align: center;
  }
  .ab-hero-eyebrow { font-size: 13px; font-weight: 600; letter-spacing: 1px; text-transform: uppercase; color: var(--primary-dark); margin-bottom: 10px; }
  .ab-hero h1 { font-family: 'Lora', Georgia, serif; font-size: clamp(28px, 5vw, 44px); font-weight: 700; color: var(--dark); line-height: 1.2; margin-bottom: 12px; }
  .ab-hero p { font-size: 16px; color: var(--text-muted); max-width: 640px; margin: 0 auto; line-height: 1.7; }

  .ab-section { max-width: 1100px; margin: 0 auto; padding: clamp(2.5rem, 6vw, 4.5rem) clamp(1rem, 4vw, 2rem); }

  .ab-features { display: grid; grid-template-columns: repeat(3, 1fr); gap: 24px; }
  .ab-card { background: var(--white); border: 1px solid var(--border); border-radius: var(--r); padding: 28px 24px; box-shadow: var(--shadow); }
  .ab-card-icon { width: 48px; height: 48px; border-radius: 12px; background: var(--primary-pale); display: flex; align-items: center; justify-content: center; font-size: 22px; margin-bottom: 16px; }
  .ab-card h3 { font-size: 17px; font-weight: 700; color: var(--dark); margin-bottom: 8px; }
  .ab-card p { font-size: 14px; color: var(--text-muted); line-height: 1.7; }

  .ab-story { display: grid; grid-template-columns: 1fr 1fr; gap: 48px; align-items: center; }
  .ab-story-title { font-family: 'Lora', Georgia, serif; font-size: clamp(24px, 3.5vw, 34px); font-weight: 700; color: var(--dark); margin-bottom: 6px; }
  .ab-story-sub { font-size: 16px; color: var(--primary-dark); font-weight: 600; margin-bottom: 18px; }
  .ab-story p { font-size: 15px; color: var(--text-muted); line-height: 1.85; margin-bottom: 14px; }
  .ab-story-img { width: 100%; height: 100%; min-height: 320px; border-radius: var(--r); overflow: hidden; box-shadow: var(--shadow-lg); }
  .ab-story-img img { width: 100%; height: 100%; object-fit: cover; display: block; }

  .ab-stats { background: var(--dark); border-radius: var(--r); padding: clamp(2rem, 4vw, 3rem); display: grid; grid-template-columns: repeat(4, 1fr); gap: 20px; }
  .ab-stat { text-align: center; }
  .ab-stat-value { font-family: 'Lora', Georgia, serif; font-size: clamp(28px, 4vw, 40px); font-weight: 700; color: var(--gold-light); line-height: 1; }
  .ab-stat-label { font-size: 13px; color: rgba(255,255,255,.7); margin-top: 8px; }

  @media (max-width: 1023.98px) {
    .ab-features { grid-template-columns: repeat(2, 1fr); }
    .ab-story { grid-template-columns: 1fr; gap: 28px; }
    .ab-stats { grid-template-columns: repeat(2, 1fr); }
  }
  @media (max-width: 575.98px) {
    .ab-features { grid-template-columns: 1fr; }
  }
</style>
@endpush

@section('content')
<section class="ab-hero">
  <div class="ab-hero-eyebrow">HTX Dịch vụ nông nghiệp thủy canh Việt</div>
  <h1>Vietponics</h1>
  <p>Mang rau sạch thủy canh tươi ngon từ vùng cao nguyên Đà Lạt đến mọi bàn ăn Việt Nam. Cam kết sạch, tươi và bền vững.</p>
</section>

<section class="ab-section">
  <div class="ab-features">
    <div class="ab-card">
      <div class="ab-card-icon">🌱</div>
      <h3>Trồng thủy canh sạch</h3>
      <p>Rau được trồng bằng phương pháp thủy canh hiện đại, không thuốc trừ sâu, kiểm soát dinh dưỡng chặt chẽ.</p>
    </div>
    <div class="ab-card">
      <div class="ab-card-icon">🚚</div>
      <h3>Giao tươi tận nhà</h3>
      <p>Thu hoạch và giao trong ngày, giữ trọn độ tươi ngon và dưỡng chất cho từng bữa ăn của gia đình bạn.</p>
    </div>
    <div class="ab-card">
      <div class="ab-card-icon">✅</div>
      <h3>Nguồn gốc minh bạch</h3>
      <p>Truy xuất nguồn gốc rõ ràng từ nông trại Đà Lạt, cam kết chất lượng và an toàn cho sức khỏe.</p>
    </div>
  </div>
</section>

<section class="ab-section" style="padding-top:0">
  <div class="ab-story">
    <div>
      <div class="ab-story-title">Hành trình Vietponics</div>
      <div class="ab-story-sub">Nâng tầm bữa ăn sạch của người Việt.</div>
      <p>Vietponics ra đời với sứ mệnh đưa rau sạch thủy canh chất lượng cao từ cao nguyên Đà Lạt đến với mọi gia đình Việt Nam một cách thuận tiện và minh bạch nhất.</p>
      <p>Chúng tôi hiểu rằng mỗi bữa ăn đều quan trọng. Vì vậy, từ khâu gieo trồng, chăm sóc đến thu hoạch và giao hàng, Vietponics luôn đặt sự an toàn và độ tươi ngon của sản phẩm lên hàng đầu.</p>
      <p>Với mô hình hợp tác xã và công nghệ canh tác hiện đại, Vietponics không ngừng cải tiến để mang đến những sản phẩm sạch, bền vững và xứng đáng với niềm tin của khách hàng.</p>
    </div>
    <div class="ab-story-img">
      <img src="{{ asset('images/logo-vietponics.png') }}" alt="Vietponics" style="object-fit:contain;background:var(--primary-pale);padding:40px">
    </div>
  </div>
</section>

<section class="ab-section" style="padding-top:0">
  <div class="ab-stats">
    @foreach($infos as $info)
      <div class="ab-stat">
        <div class="ab-stat-value">{{ $info['value'] }}</div>
        <div class="ab-stat-label">{{ $info['title'] }}</div>
      </div>
    @endforeach
  </div>
</section>
@endsection
