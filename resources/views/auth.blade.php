<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<meta name="csrf-token" content="{{ csrf_token() }}">
<title>Sign in — {{ $brand['name'] }}</title>
<meta name="robots" content="noindex,nofollow">
<meta name="theme-color" content="{{ $brand['theme'] }}">
<script>try{if(localStorage.getItem('theme')==='dark')document.documentElement.setAttribute('data-theme','dark')}catch(e){}</script>
<link rel="stylesheet" href="/css/app.css">
</head>
<body>
<button type="button" class="theme-btn auth-theme" data-theme-toggle aria-label="Switch to dark mode">🌙</button>
<div class="auth">
  <div class="auth-box">
    <a href="/" class="logo">@if ($brand['logo'])<img src="{{ $brand['logo'] }}" alt="{{ $brand['name'] }}">@else<i>{{ $brand['icon'] }}</i> {{ $brand['name'] }}@endif</a>
    <h1 id="title">Welcome back</h1>
    <p class="sub" id="sub">Sign in to browse products and send requests.</p>
    <div class="tabs"><button class="on" data-m="login">Login</button><button data-m="register">Register</button></div>

    <form id="form" novalidate>
      <div id="nameRow" hidden><label for="name">Name</label><input id="name" name="name" autocomplete="name"><div class="err" data-e="name"></div></div>
      <label for="email">Email</label><input id="email" name="email" type="email" autocomplete="email" required><div class="err" data-e="email"></div>
      <label for="password">Password</label><input id="password" name="password" type="password" autocomplete="current-password" required><div class="err" data-e="password"></div>
      <div id="confRow" hidden><label for="pc">Confirm password</label><input id="pc" name="password_confirmation" type="password" autocomplete="new-password"></div>
      <button class="btn" style="width:100%;margin-top:22px" id="go">Login</button>
    </form>
    <div class="hint">Demo — admin: <b>admin@sellbees.test</b> · user: <b>user@sellbees.test</b> · password: <b>password</b></div>
  </div>
</div>
<script src="/js/theme.js" defer></script>
<script>
const $ = s => document.querySelector(s);
let mode = 'login';
document.querySelectorAll('.tabs button').forEach(b => b.onclick = () => {
  mode = b.dataset.m;
  document.querySelectorAll('.tabs button').forEach(x => x.classList.toggle('on', x === b));
  const reg = mode === 'register';
  $('#nameRow').hidden = $('#confRow').hidden = !reg;
  $('#go').textContent = reg ? 'Create account' : 'Login';
  $('#title').textContent = reg ? 'Create your account' : 'Welcome back';
  $('#password').autocomplete = reg ? 'new-password' : 'current-password';
  document.querySelectorAll('.err').forEach(e => e.textContent = '');
});
// /login?tab=register opens the registration form (used by "Register" links on the landing page)
if (new URLSearchParams(location.search).get('tab') === 'register') document.querySelector('[data-m=register]').click();
$('#form').onsubmit = async e => {
  e.preventDefault();
  const btn = $('#go'); btn.disabled = true;
  document.querySelectorAll('.err').forEach(x => x.textContent = '');
  try {
    const res = await fetch('/' + mode, {
      method: 'POST',
      headers: {'Accept': 'application/json', 'Content-Type': 'application/json', 'X-CSRF-TOKEN': $('meta[name=csrf-token]').content},
      body: JSON.stringify(Object.fromEntries(new FormData(e.target))),
    });
    const data = await res.json().catch(() => ({}));
    if (res.ok) return location.href = data.redirect;
    if (data.errors) Object.entries(data.errors).forEach(([k, v]) => { const el = document.querySelector(`[data-e=${k}]`); if (el) el.textContent = v[0]; });
    else $('[data-e=email]').textContent = data.message || 'Something went wrong. Please refresh and retry.';
  } finally { btn.disabled = false; }
};
</script>
</body>
</html>
