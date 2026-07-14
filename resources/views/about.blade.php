@extends('layouts.app')

@section('title', 'このサイトについて | ゴルフ場口コミ検索')
@section('description', 'ゴルフ場口コミ検索の運営方針、データの出典、口コミの取り扱いについて説明しています。')

@section('content')
<div class="container">
  <nav aria-label="breadcrumb">
    <ol class="breadcrumb">
      <li class="breadcrumb-item"><a href="{{ route('golf.index') }}">ゴルフ場口コミ検索</a></li>
      <li class="breadcrumb-item active" aria-current="page">このサイトについて</li>
    </ol>
  </nav>

  <h1>このサイトについて</h1>

  <section class="mb-4">
    <h2 class="h5">サイトの目的</h2>
    <p>
      「ゴルフ場口コミ検索」は、全国47都道府県のゴルフ場を、目的（早朝プレー・ナイター・温泉付きなど）で絞り込みながら探せる検索サイトです。
      ゴルフ場の情報だけでなく、実際にプレーした方の口コミもあわせて確認できるようにしています。
    </p>
  </section>

  <section class="mb-4">
    <h2 class="h5">掲載データの出典</h2>
    <p>
      掲載しているゴルフ場の情報（コース名・住所・写真・予約リンク等）は、楽天GORAが提供する
      <a href="https://webservice.rakuten.co.jp/" target="_blank" rel="noopener noreferrer">楽天ウェブサービス</a>
      のAPIを通じて取得しており、随時最新の情報に更新されます。予約は楽天GORAのサイトで行われます。
    </p>
  </section>

  <section class="mb-4">
    <h2 class="h5">口コミについて</h2>
    <p>
      口コミは、どなたでもログイン不要で投稿できます。投稿内容は運営による事前確認を行わず即時公開されますが、
      不適切な投稿を発見された場合は内容を精査のうえ対応します。口コミはあくまで投稿者個人の感想であり、
      当サイトが内容の正確性を保証するものではありません。
    </p>
  </section>
</div>
@endsection
