<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>@yield('title', 'ゴルフ場口コミ検索 | 都道府県からゴルフ場を探す')</title>
    <meta name="description" content="@yield('description', '全国47都道府県からゴルフ場を検索できるゴルフ場情報サイトです。行きたい都道府県を選ぶだけで、楽天GORAの最新のゴルフ場情報を一覧表示します。')">
    <link rel="canonical" href="{{ url()->current() }}">

    <meta property="og:site_name" content="ゴルフ場口コミ検索">
    <meta property="og:type" content="website">
    <meta property="og:title" content="@yield('title', 'ゴルフ場口コミ検索 | 都道府県からゴルフ場を探す')">
    <meta property="og:description" content="@yield('description', '全国47都道府県からゴルフ場を検索できるゴルフ場情報サイトです。行きたい都道府県を選ぶだけで、楽天GORAの最新のゴルフ場情報を一覧表示します。')">
    <meta property="og:url" content="{{ url()->current() }}">
    <meta property="og:locale" content="ja_JP">

    <meta name="twitter:card" content="summary">
    <meta name="twitter:title" content="@yield('title', 'ゴルフ場口コミ検索 | 都道府県からゴルフ場を探す')">
    <meta name="twitter:description" content="@yield('description', '全国47都道府県からゴルフ場を検索できるゴルフ場情報サイトです。行きたい都道府県を選ぶだけで、楽天GORAの最新のゴルフ場情報を一覧表示します。')">

    <link rel="icon" href="/favicon.ico" sizes="any">

    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">

    @stack('structured-data')
  @if(config('services.ga4.id'))
  <script async src="https://www.googletagmanager.com/gtag/js?id={{ config('services.ga4.id') }}"></script>
  <script>
    window.dataLayer = window.dataLayer || [];
    function gtag(){dataLayer.push(arguments);}
    gtag('js', new Date());
    gtag('config', '{{ config('services.ga4.id') }}');
  </script>
  @endif
</head>
<body>
    <nav class="navbar navbar-dark bg-dark text-white p-3 mb-4">
        <div class="container">
            <a href="{{ route('golf.index') }}" class="h4 mb-0 text-white text-decoration-none">ゴルフ場口コミ検索</a>
        </div>
    </nav>

    <main class="container">
        @yield('content')
    </main>

    <footer class="container text-center text-muted small py-4 mt-4 border-top">
        <a href="{{ route('about') }}" class="text-muted">このサイトについて</a>
    </footer>
</body>
</html>
