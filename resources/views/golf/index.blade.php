@extends('layouts.app')

@section('content')
<div class="container">
    <h1>都道府県からゴルフ場を探す</h1>

    <div class="row row-cols-2 row-cols-md-4 g-2 mt-3">
        @foreach ($prefectures as $pref)
            <div class="col">
                <a href="{{ route('golf.search', ['prefecture' => $pref]) }}" class="btn btn-outline-primary w-100">
                    {{ $pref }}
                </a>
            </div>
        @endforeach
    </div>

    @if (isset($courses) && count($courses) > 0)
        <h2>{{ $prefecture }}のゴルフ場一覧</h2>
        <ul class="list-group mt-3">
            @foreach ($courses as $course)
                <li class="list-group-item">
                    <strong>{{ $course['Item']['golfCourseName'] ?? '名称未取得' }}</strong><br>
                    @if (!empty($course['Item']['thumbnailImageUrl']))
                        <img src="{{ $course['Item']['thumbnailImageUrl'] }}" width="150"><br>
                    @endif
                    <a href="{{ $course['Item']['affiliateUrl'] }}" class="btn btn-sm btn-primary mt-2" target="_blank">詳細を見る</a>
                </li>
            @endforeach
        </ul>
    @else
        <p class="text-muted mt-3">該当するゴルフ場は見つかりませんでした。</p>
    @endif
</div>
@endsection
