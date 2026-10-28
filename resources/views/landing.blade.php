@php
    use App\Support\SiteContent as C;
    use App\Support\SiteSchema;

    $it = $items;
    $home = url('/');
    $title = C::plain($s['meta_title']);
    $ogTitle = C::plain($s['og_title'] ?: $s['meta_title']);
    $ogDesc = $s['og_description'] ?: $s['meta_description'];
    $ogImg = C::ogImage($s);
    $sym = $s['currency_symbol'];
    $safeUrl = fn ($u) => preg_match('~^(https?://|/|#|mailto:|tel:)~i', (string) $u) ? $u : '#';
    $favicon = $s['logo_url'] ?: 'data:image/svg+xml,'.rawurlencode("<svg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 100 100'><rect width='100' height='100' rx='24' fill='#f5a300'/><text x='50' y='68' font-size='56' text-anchor='middle'>{$s['brand_icon']}</text></svg>");
    $on = fn ($k) => (bool) ($s['show_'.$k] ?? true);
    $svOn = $on('services') && ! empty($it['service']);
    $first = $on('store') ? ($products[0] ?? null) : null;
    // structured data must only describe content that is actually visible
    $ldItems = $it;
    if (! $on('services')) { unset($ldItems['service']); }
    if (! $on('faq')) { unset($ldItems['faq']); }
    $ldProducts = $on('store') ? $products : [];
    $registerUrl = route('login').'?tab=register';
    $brandMark = $s['logo_url'] ? '<img src="'.e($s['logo_url']).'" alt="'.e($s['brand_name']).'">' : '<i>'.e($s['brand_icon']).'</i>';
    $columns = collect($it['footer_link'] ?? [])->groupBy('column');
    $socials = ['Facebook' => 'social_facebook', 'Instagram' => 'social_instagram', 'X' => 'social_x', 'LinkedIn' => 'social_linkedin', 'YouTube' => 'social_youtube', 'TikTok' => 'social_tiktok'];
    $testis = collect($it['testimonial'] ?? []);
    $featured = $testis->firstWhere('featured', true) ?? $testis->first();
    $others = $testis->reject(fn ($t) => $t === $featured)->values();
@endphp
<!doctype html>
<html lang="{{ str_replace('_', '-', $s['locale']) }}">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>{{ $title }}</title>
<meta name="description" content="{{ $s['meta_description'] }}">
@if ($s['meta_keywords'])<meta name="keywords" content="{{ $s['meta_keywords'] }}">@endif
<meta name="robots" content="{{ $s['robots'] }}, max-image-preview:large, max-snippet:-1, max-video-preview:-1">
<link rel="canonical" href="{{ $safeUrl($s['canonical_url'] ?: $home) }}">
<meta name="theme-color" content="{{ $s['theme_color'] }}">
@if ($s['google_verification'])<meta name="google-site-verification" content="{{ $s['google_verification'] }}">@endif
@if ($s['bing_verification'])<meta name="msvalidate.01" content="{{ $s['bing_verification'] }}">@endif
<meta name="author" content="{{ $s['brand_name'] }}">
<link rel="icon" href="{{ $favicon }}">
<link rel="apple-touch-icon" href="{{ $s['logo_url'] ?: $ogImg }}">

<meta property="og:type" content="website">
<meta property="og:site_name" content="{{ $s['brand_name'] }}">
<meta property="og:locale" content="{{ $s['locale'] }}">
<meta property="og:title" content="{{ $ogTitle }}">
<meta property="og:description" content="{{ $ogDesc }}">
<meta property="og:url" content="{{ $home }}">
<meta property="og:image" content="{{ $ogImg }}">
<meta property="og:image:alt" content="{{ $s['brand_name'] }} — {{ C::plain($s['hero_title']) }}">
@unless ($s['og_image_url'])<meta property="og:image:width" content="1200"><meta property="og:image:height" content="630">@endunless
<meta name="twitter:card" content="summary_large_image">
@if ($s['twitter_handle'])<meta name="twitter:site" content="{{ $s['twitter_handle'] }}"><meta name="twitter:creator" content="{{ $s['twitter_handle'] }}">@endif
<meta name="twitter:title" content="{{ $ogTitle }}">
<meta name="twitter:description" content="{{ $ogDesc }}">
<meta name="twitter:image" content="{{ $ogImg }}">
<script type="application/ld+json">{!! C::jsonLd(['s' => $s, 'items' => $ldItems, 'products' => $ldProducts]) !!}</script>

<meta name="csrf-token" content="{{ csrf_token() }}">
<script>try{if(localStorage.getItem('theme')==='dark')document.documentElement.setAttribute('data-theme','dark')}catch(e){}</script>
<link rel="stylesheet" href="@v('css/app.css')">
<link rel="stylesheet" href="@v('css/landing.css')">
</head>
<body class="lp">

<nav class="lp-nav" aria-label="Main">
  <div class="bar">
    <div class="nav-l">
      <button type="button" class="theme-btn menu-btn" id="menuBtn" aria-label="Open menu" aria-expanded="false" aria-controls="navList">☰</button>
      <a href="/" class="logo" aria-label="{{ $s['brand_name'] }} home">{!! $brandMark !!}@unless ($s['logo_url']) {{ $s['brand_name'] }}@endunless</a>
    </div>
    <ul id="navList">
      <li class="drawer-head">
        <a href="/" class="logo" aria-label="{{ $s['brand_name'] }} home">{!! $brandMark !!}@unless ($s['logo_url']) {{ $s['brand_name'] }}@endunless</a>
        <button type="button" class="theme-btn" id="menuClose" aria-label="Close menu">✕</button>
      </li>
      @if ($svOn)<li><a href="#services">Services</a></li>@endif
      @if ($on('process') && !empty($it['step']))<li><a href="#process">Process</a></li>@endif
      @if ($on('store'))<li><a href="#store">Store</a></li>@endif
      @if ($on('work') && !empty($it['case']))<li><a href="#work">Work</a></li>@endif
      @if ($on('faq') && !empty($it['faq']))<li><a href="#faq">FAQ</a></li>@endif
      {{-- account links live in the mobile menu because the header buttons are hidden on phones --}}
      @auth
        <li class="mob"><a href="{{ route('dashboard') }}">Dashboard →</a></li>
      @else
        <li class="mob"><a href="{{ route('login') }}">Login</a></li>
        <li class="mob"><a href="{{ $registerUrl }}">Register</a></li>
      @endauth
    </ul>
    <div class="nav-r">
      <button type="button" class="theme-btn" data-theme-toggle aria-label="Switch to dark mode">🌙</button>
      @auth
        <a class="btn sm dark" href="{{ route('dashboard') }}">Dashboard →</a>
      @else
        <a class="btn sm ghost" href="{{ route('login') }}">Login</a><a class="btn sm dark" href="{{ $registerUrl }}">Register</a>
      @endauth
    </div>
  </div>
  <div class="nav-overlay" id="navOverlay"></div>
</nav>

<main>
{{-- HERO --}}
@if ($on('hero'))
<section class="lp-hero">
  <div class="wrap">
    <div>
      @if ($s['hero_chip'])<span class="chip rv">@if ($s['hero_badge'])<i>{{ $s['hero_badge'] }}</i>@endif {{ $s['hero_chip'] }}</span>@endif
      <h1 class="rv" style="--d:.08s">{{ C::emph($s['hero_title'], '<span class="mark">', '</span>') }}</h1>
      <p class="lead rv" style="--d:.16s">{{ $s['hero_lead'] }}</p>
      <div class="cta-row rv" style="--d:.24s">
        <a class="btn lg" href="{{ $member ? route('dashboard') : $registerUrl }}">{{ $s['hero_cta1'] }}</a>
        @if ($svOn)<a class="btn lg ghost" href="#services">{{ $s['hero_cta2'] }}</a>@endif
      </div>
      @if ($s['hero_proof'])
      <div class="proof rv" style="--d:.32s">
        <div class="avs" aria-hidden="true"><span style="background:#ffd36b">A</span><span style="background:#f5a300">M</span><span style="background:#fff1cc">R</span><span style="background:#e2b44a">S</span></div>
        <div><span class="stars" aria-label="5 stars">★★★★★</span><br>{{ $s['hero_proof'] }}</div>
      </div>
      @endif
    </div>

    <div class="stage rv" style="--d:.2s" aria-hidden="true">
      <div class="hexbg"></div><div class="hexbg two"></div>
      <div class="float-chip a"><span class="dot">{{ $s['hero_chip_a_icon'] }}</span><div><b>{{ $s['hero_chip_a_value'] }}</b>{{ $s['hero_chip_a_label'] }}</div></div>
      <div class="float-chip b"><span class="dot">{{ $s['hero_chip_b_icon'] }}</span><div><b>{{ $s['hero_chip_b_value'] }}</b>{{ $s['hero_chip_b_label'] }}</div></div>
      <div class="board">
        <div class="logoq"><b>{{ mb_strtoupper(mb_substr($s['brand_name'], 0, 1)) }}</b><span>{{ $s['hero_board_title'] }}</span></div>
        <div class="type">Aa<small>{{ $s['hero_board_font'] }}</small></div>
        <div class="sw"><span style="background:{{ $s['theme_color'] }}"></span><span style="background:#17130c"></span><span style="background:#fff1cc"></span><span style="background:#c98300"></span></div>
        <small style="color:var(--muted)">{{ $s['hero_board_voice'] }}</small>
      </div>
      @if ($first)
      <div class="prodcard">
        <div class="img">@if ($first['image_url'])<img src="{{ $first['image_url'] }}" alt="" width="200" height="96" style="width:100%;height:100%;object-fit:cover;border-radius:14px">@else 🛍️ @endif</div>
        <b>{{ $first['name'] }}</b>
        <small>{{ $member ? 'In catalog' : 'Members only pricing' }}</small>
        <div class="row">
          @if ($member)<b>{{ $sym }}{{ number_format($first['price'], 2) }}</b>@else<b class="lk">{{ $sym }}{{ $first['locked_price'] }}</b>@endif
          <i>{{ $member ? 'Select' : '🔒 Unlock' }}</i>
        </div>
      </div>
      @endif
    </div>
  </div>

</section>
@else
<div class="hspace"></div>
@endif

{{-- INDUSTRIES STRIP --}}
@if ($on('industries') && !empty($it['industry']))
<div class="marq" aria-hidden="true">
  <div class="tr">
    @foreach (range(1, 2) as $_)
      @foreach ($it['industry'] as $row)<span>{{ $row['name'] }}</span>@endforeach
    @endforeach
  </div>
</div>
@endif

{{-- STATS --}}
@if ($on('stats') && !empty($it['stat']))
<section>
  <div class="wrap kp">
    @foreach ($it['stat'] as $i => $row)
      <div class="rv" style="--d:{{ $i * .08 }}s"><b data-count="{{ $row['value'] }}" data-suffix="{{ $row['suffix'] ?? '' }}" data-dec="{{ (int) ($row['decimals'] ?? 0) }}">{{ $row['value'] }}{{ $row['suffix'] ?? '' }}</b><span>{{ $row['label'] }}</span></div>
    @endforeach
  </div>
</section>
@endif

{{-- SERVICES --}}
@if ($svOn)
<section class="sv" id="services">
  <div class="wrap">
    <span class="eyebrow">{{ $s['services_eyebrow'] }}</span>
    <h2 class="h2">{{ C::emph($s['services_title']) }}</h2>
    <p class="sub">{{ $s['services_sub'] }}</p>
    <div class="bento">
      @foreach ($it['service'] as $i => $sv)
        @php
          $cls = ['dark' => 'dk', 'accent' => 'yl'][$sv['style'] ?? 'light'] ?? '';
          $w = ['3' => 'w3', '4' => 'w4'][$sv['span'] ?? '2'] ?? '';
          $tags = array_filter(array_map('trim', explode(',', $sv['tags'] ?? '')));
        @endphp
        <article class="tile {{ $cls }} {{ $w }} rv" style="--d:{{ ($i % 3) * .08 }}s">
          <span class="num">{{ str_pad($i + 1, 2, '0', STR_PAD_LEFT) }}</span>
          <div class="ic"><svg viewBox="0 0 24 24" aria-hidden="true">{!! SiteSchema::ICONS[$sv['icon'] ?? 'star'] ?? SiteSchema::ICONS['star'] !!}</svg></div>
          <h3>{{ $sv['title'] }}</h3>
          <p>{{ $sv['description'] }}</p>
          @if ($tags)<ul>@foreach ($tags as $t)<li>{{ $t }}</li>@endforeach</ul>@endif
        </article>
      @endforeach
    </div>
  </div>
</section>
@endif

{{-- PROCESS --}}
@if ($on('process') && !empty($it['step']))
<section class="pr" id="process">
  <div class="wrap">
    <span class="eyebrow">{{ $s['process_eyebrow'] }}</span>
    <h2 class="h2">{{ C::emph($s['process_title']) }}</h2>
    <p class="sub">{{ $s['process_sub'] }}</p>
    <div class="tl">
      @foreach ($it['step'] as $i => $st)
        <div class="st2 rv" style="--d:{{ $i * .1 }}s"><div class="n">{{ $i + 1 }}</div><h3>{{ $st['title'] }}</h3><p>{{ $st['description'] }}</p>@if (!empty($st['timing']))<small>{{ $st['timing'] }}</small>@endif</div>
      @endforeach
    </div>
  </div>
</section>
@endif

{{-- STORE --}}
@if ($on('store'))
<section class="sh" id="store">
  <div class="wrap">
    <div class="sh-head">
      <div>
        <span class="eyebrow">{{ $s['store_eyebrow'] }}</span>
        <h2 class="h2" style="margin-bottom:0">{{ C::emph($s['store_title']) }}</h2>
      </div>
      <a class="btn dark" href="{{ $member ? route('dashboard') : $registerUrl }}">{{ $member ? 'Open my dashboard →' : $s['store_button'] }}</a>
    </div>

    @unless ($member)
      <div class="gate rv"><span class="lockico" aria-hidden="true">🔒</span><div><b>Prices and details are for members only.</b><br><span>Create a free account to unlock full product details, select products and send purchase requests.</span></div><a class="btn sm" href="{{ $registerUrl }}">Register free</a></div>
    @endunless

    <div class="shop">
      @forelse ($products as $i => $p)
        <article class="pc rv" style="--d:{{ $i * .08 }}s" @if ($member) data-id="{{ $p['id'] }}" data-qty="{{ $selected[$p['id']] ?? 0 }}" @endif>
          <div class="im">
            @if ($p['image_url'])<img src="{{ $p['image_url'] }}" alt="{{ $p['name'] }}" loading="lazy" decoding="async" width="400" height="400">@else{{ mb_strtoupper(mb_substr($p['name'], 0, 1)) }}@endif
            @unless ($member)<span class="lockbadge">🔒 Members only</span>@endunless
          </div>
          <div class="bd">
            <h3>{{ $p['name'] }}</h3>
            @if ($member)
              <p>{{ $p['description'] }}</p>
              <div class="ft"><b>{{ $sym }}{{ number_format($p['price'], 2) }}</b><span class="pick"></span></div>
            @else
              <p class="lk" aria-hidden="true">{{ $p['locked_desc'] }}</p>
              <div class="ft"><b class="lk" aria-hidden="true">{{ $sym }}{{ $p['locked_price'] }}</b><a href="{{ $registerUrl }}">Unlock →</a></div>
            @endunless
          </div>
        </article>
      @empty
        <p class="sub">Our catalog is being stocked — check back soon.</p>
      @endforelse
    </div>
  </div>
</section>
@endif

{{-- WORK --}}
@if ($on('work') && !empty($it['case']))
<section class="wk" id="work">
  <div class="wrap">
    <span class="eyebrow">{{ $s['work_eyebrow'] }}</span>
    <h2 class="h2">{{ C::emph($s['work_title']) }}</h2>
    <div class="cases">
      @foreach ($it['case'] as $i => $c)
        <article class="case rv" style="--d:{{ ($i % 3) * .1 }}s"><div><small>{{ $c['category'] }}</small><h3>{{ $c['title'] }}</h3><p>{{ $c['description'] }}</p></div><div class="big">{{ $c['metric'] }}<span>{{ $c['metric_label'] }}</span></div></article>
      @endforeach
    </div>
  </div>
</section>
@endif

{{-- TESTIMONIALS --}}
@if ($featured && $on('testimonials'))
<section class="ts">
  <div class="wrap">
    <span class="eyebrow">{{ $s['testimonials_eyebrow'] }}</span>
    <h2 class="h2">{{ C::emph($s['testimonials_title']) }}</h2>
    <div class="tgrid">
      <figure class="big-q rv">
        <blockquote>{{ $featured['quote'] }}</blockquote>
        <figcaption class="who2"><i>{{ mb_strtoupper(mb_substr($featured['name'], 0, 1)) }}</i><div><b>{{ $featured['name'] }}</b>@if (!empty($featured['role']))<small>{{ $featured['role'] }}</small>@endif</div></figcaption>
      </figure>
      @foreach ($others as $i => $t)
        <figure class="sm-q rv" style="--d:{{ ($i + 1) * .1 }}s"><span class="stars" aria-hidden="true">★★★★★</span><p>{{ $t['quote'] }}</p><div class="who2"><i>{{ mb_strtoupper(mb_substr($t['name'], 0, 1)) }}</i><div><b>{{ $t['name'] }}</b>@if (!empty($t['role']))<small>{{ $t['role'] }}</small>@endif</div></div></figure>
      @endforeach
    </div>
  </div>
</section>
@endif

{{-- FAQ --}}
@if ($on('faq') && !empty($it['faq']))
<section class="fq" id="faq">
  <div class="wrap">
    <div>
      <span class="eyebrow">{{ $s['faq_eyebrow'] }}</span>
      <h2 class="h2">{{ C::emph($s['faq_title']) }}</h2>
      <p class="sub">{{ $s['faq_sub'] }}</p>
    </div>
    <div class="rv">
      @foreach ($it['faq'] as $i => $q)
        <details @if ($i === 0) open @endif><summary>{{ $q['question'] }}</summary><p>{{ $q['answer'] }}</p></details>
      @endforeach
    </div>
  </div>
</section>
@endif

{{-- CTA --}}
@if ($on('cta'))
<section class="fin">
  <div class="wrap">
    <div class="box rv">
      <h2>{{ C::emph($s['cta_title']) }}</h2>
      <p>{{ $s['cta_text'] }}</p>
      <div class="cta-row" style="justify-content:center">
        <a class="btn dark lg" href="{{ $member ? route('dashboard') : $registerUrl }}">{{ $s['cta_btn1'] }}</a>
        @if ($svOn)<a class="btn lg ghost" style="border-color:#1c1200" href="#services">{{ $s['cta_btn2'] }}</a>@endif
      </div>
    </div>
  </div>
</section>
@endif
</main>

{{-- FOOTER --}}
<footer class="lp-foot">
  <div class="wrap">
    @if ($on('newsletter'))
    <div class="news">
      <div><h3>{{ $s['footer_news_title'] }}</h3><p>{{ $s['footer_news_text'] }}</p></div>
      <form id="newsForm" novalidate>
        <input type="email" name="email" placeholder="you@company.com" aria-label="Email address" required autocomplete="email">
        <button class="btn">Subscribe</button>
        <div class="msg" id="newsMsg" role="status"></div>
      </form>
    </div>
    @endif

    <div class="fg">
      <div id="contact">
        <a href="/" class="logo">{!! $brandMark !!} {{ $s['brand_name'] }}</a>
        <p class="about">{{ $s['footer_about'] }}</p>
        <address>
          @if ($s['email'])<a href="mailto:{{ $s['email'] }}">✉ {{ $s['email'] }}</a>@endif
          @if ($s['phone'])<a href="tel:{{ preg_replace('/[^\d+]/', '', $s['phone']) }}">☎ {{ $s['phone'] }}</a>@endif
          @if ($s['address'])<span>📍 {{ $s['address'] }}</span>@endif
        </address>
        <div class="soc">
          @foreach ($socials as $label => $key)
            @if ($s[$key])<a href="{{ $safeUrl($s[$key]) }}" target="_blank" rel="noopener noreferrer me" aria-label="{{ $label }}">{{ $label }}</a>@endif
          @endforeach
        </div>
      </div>
      @foreach ($columns as $heading => $links)
        <div><h4>{{ $heading }}</h4>@foreach ($links as $l)<a href="{{ $safeUrl($l['url']) }}">{{ $l['label'] }}</a>@endforeach</div>
      @endforeach
    </div>

    <div class="bt">
      <span>© {{ date('Y') }} {{ $s['brand_name'] }}. {{ $s['footer_copyright'] }}</span>
      <span>{{ $s['tagline'] }}</span>
      <a href="#top" class="totop" aria-label="Back to top">↑ Top</a>
    </div>
  </div>
</footer>

<script src="@v('js/theme.js')" defer></script>
<script>
// mobile menu
const nav = document.querySelector('.lp-nav'), mb = document.getElementById('menuBtn');
// left drawer on tablet/phone: hamburger opens it; ✕, the dark overlay, a link tap or Esc closes it
const setMenu = open => {
  nav.classList.toggle('open', open);
  mb.setAttribute('aria-expanded', open);
  document.body.style.overflow = open ? 'hidden' : '';
  if (open) document.getElementById('menuClose').focus({preventScroll: true}); else if (document.activeElement && nav.contains(document.activeElement)) mb.focus({preventScroll: true});
};
mb.onclick = () => setMenu(!nav.classList.contains('open'));
document.getElementById('menuClose').onclick = () => setMenu(false);
document.getElementById('navOverlay').onclick = () => setMenu(false);
document.querySelectorAll('#navList a').forEach(a => a.addEventListener('click', () => setMenu(false)));
document.addEventListener('keydown', e => { if (e.key === 'Escape') setMenu(false); });
matchMedia('(min-width:961px)').addEventListener('change', e => { if (e.matches) setMenu(false); });

const io = new IntersectionObserver(es => es.forEach(e => { if (e.isIntersecting) { e.target.classList.add('in'); io.unobserve(e.target); } }), {threshold: .12});
document.querySelectorAll('.rv').forEach(el => io.observe(el));

const co = new IntersectionObserver(es => es.forEach(e => {
  if (!e.isIntersecting) return; co.unobserve(e.target);
  const el = e.target, end = parseFloat(el.dataset.count), dec = +(el.dataset.dec || 0), suf = el.dataset.suffix || '', t0 = performance.now();
  const tick = t => { const p = Math.min(1, (t - t0) / 1400); el.textContent = (end * (1 - Math.pow(1 - p, 3))).toFixed(dec) + suf; if (p < 1) requestAnimationFrame(tick); };
  requestAnimationFrame(tick);
}), {threshold: .6});
document.querySelectorAll('[data-count]').forEach(el => co.observe(el));

const nf = document.getElementById('newsForm');
if (nf) nf.onsubmit = async e => {
  e.preventDefault();
  const msg = document.getElementById('newsMsg'), btn = nf.querySelector('button');
  btn.disabled = true; msg.textContent = '';
  try {
    const res = await fetch('/subscribe', {method: 'POST', headers: {'Accept': 'application/json', 'Content-Type': 'application/json', 'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]').content},
      body: JSON.stringify({email: nf.email.value})});
    const d = await res.json().catch(() => ({}));
    msg.textContent = res.ok ? d.message : (d.errors ? Object.values(d.errors)[0][0] : (d.message || 'Please try again.'));
    msg.className = 'msg ' + (res.ok ? 'ok' : 'bad');
    if (res.ok) nf.email.value = '';
  } finally { btn.disabled = false; }
};
</script>
@if ($member)
<script>
// signed-in members can select products straight from the landing page
const tok = document.querySelector('meta[name=csrf-token]').content;
const pick = card => card.querySelector('.pick');
function paint(card) {
  const q = +card.dataset.qty;
  pick(card).innerHTML = q
    ? `<span class="qty"><button data-d="-1" aria-label="Remove one">−</button><span>${q}</span><button data-d="1" aria-label="Add one">+</button></span>`
    : `<button class="btn sm" data-d="1">Select</button>`;
  card.classList.toggle('sel', q > 0);
}
async function setQty(card, q) {
  const res = await fetch('/shop/selections/' + card.dataset.id, {method: 'PUT', headers: {'Accept': 'application/json', 'Content-Type': 'application/json', 'X-CSRF-TOKEN': tok}, body: JSON.stringify({quantity: q})});
  if (!res.ok) return;
  card.dataset.qty = q; paint(card);
}
document.querySelectorAll('.pc[data-id]').forEach(card => {
  paint(card);
  card.addEventListener('click', e => { const b = e.target.closest('[data-d]'); if (b) setQty(card, Math.max(0, +card.dataset.qty + +b.dataset.d)); });
});
</script>
@endif
</body>
</html>
