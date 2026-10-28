<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<meta name="csrf-token" content="{{ csrf_token() }}">
<title>Dashboard — {{ $brand['name'] }}</title>
<meta name="robots" content="noindex,nofollow">
<script>try{if(localStorage.getItem('theme')==='dark')document.documentElement.setAttribute('data-theme','dark')}catch(e){}</script>
<link rel="icon" href="data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 100 100'%3E%3Crect width='100' height='100' rx='24' fill='%23f5a300'/%3E%3C/svg%3E">
<link rel="stylesheet" href="@v('css/app.css')">
</head>
<body>
<header class="app-top">
  <div class="wrap">
    <a href="/" class="logo">@if ($brand['logo'])<img src="{{ $brand['logo'] }}" alt="{{ $brand['name'] }}">@else<i>{{ $brand['icon'] }}</i> {{ $brand['name'] }}@endif</a>
    <div class="right">
      <span class="who">{{ auth()->user()->name }}</span>
      <span class="badge-role">{{ auth()->user()->role }}</span>
      <button type="button" class="theme-btn" data-theme-toggle aria-label="Switch to dark mode">🌙</button>
      <button class="btn ghost sm bell" id="bell" aria-label="Notifications">🔔<b id="bellN" hidden>0</b></button>
      <button class="btn ghost sm" id="logout">Logout</button>
    </div>
  </div>
</header>
<div class="dd" id="dd"><header>Notifications <button id="readAll">Mark all read</button></header><div id="ddList"></div></div>

<main class="wrap page">
  <div class="tabbar" id="tabs"></div>
  <div id="view"></div>
</main>

<div class="modal" id="modal"><div class="box"><button class="x" id="mx" aria-label="Close">✕</button><div id="mbody"></div></div></div>
<div class="toast-area" id="toasts"></div>

<script>window.APP = {role: @json(auth()->user()->role), name: @json(auth()->user()->name), currency: @json($brand['currency'])};</script>
<script src="@v('js/theme.js')" defer></script>
<script src="@v('js/dashboard.js')"></script>
</body>
</html>
