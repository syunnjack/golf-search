@extends('layouts.app')

@section('title', 'ゴルフ場口コミ検索 | 都道府県からゴルフ場を探す')
@section('description', '全国47都道府県からゴルフ場を検索できるゴルフ場情報サイトです。行きたい都道府県を選ぶだけで、楽天GORAの最新のゴルフ場情報を一覧表示します。')

@push('structured-data')
<script type="application/ld+json">
{!! json_encode([
    '@@context' => 'https://schema.org',
    '@type' => 'WebSite',
    'name' => 'ゴルフ場口コミ検索',
    'url' => url('/'),
    'description' => '全国47都道府県からゴルフ場を検索できるゴルフ場情報サイト。',
    'inLanguage' => 'ja',
], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) !!}
</script>
@endpush

@section('content')
<div class="container">
  <h1>都道府県からゴルフ場を探す</h1>
  <p class="text-muted">
    ゴルフ場口コミ検索では、47都道府県すべてのゴルフ場を無料で検索できます。
    下の都道府県ボタンを選ぶと、その地域のゴルフ場一覧（コース名・住所・写真・予約リンク）が表示されます。
  </p>

  <div class="row row-cols-2 row-cols-md-4 g-2 mt-3">
    @foreach ($prefectures as $pref)
      <div class="col">
        <a href="{{ route('golf.search', ['prefecture' => $pref]) }}" class="btn btn-outline-primary w-100">
          {{ $pref }}
        </a>
      </div>
    @endforeach
  </div>

  <section class="mt-5 pt-4 border-top">
    <h2 class="h5">ゴルフ場選びで失敗しないために</h2>
    <p class="text-muted small">
      コース設計・料金・アクセスだけでなく、早朝プレーの可否やナイター設備、温泉付き・宿泊パックの有無など、
      目的に合わせた選び方が重要です。当サイトでは実際にプレーした方の口コミもあわせて確認できます。
      詳しくは<a href="{{ route('about') }}">このサイトについて</a>をご覧ください。
    </p>
  </section>
</div>
@endsection
