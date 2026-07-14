@extends('layouts.app')

@section('title', ($tag ? $tag . '｜' : '') . $prefecture . 'のゴルフ場一覧 | ゴルフ場口コミ検索')
@section('description', $prefecture . 'にあるゴルフ場の一覧です。コース名・住所・写真・予約リンク・実際にプレーした人の口コミをまとめて確認できます。')

@push('structured-data')
<script type="application/ld+json">
{!! json_encode([
    '@context' => 'https://schema.org',
    '@type' => 'BreadcrumbList',
    'itemListElement' => [
        ['@type' => 'ListItem', 'position' => 1, 'name' => 'ゴルフ場口コミ検索', 'item' => url('/')],
        ['@type' => 'ListItem', 'position' => 2, 'name' => $prefecture . 'のゴルフ場'],
    ],
], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) !!}
</script>
@if (!empty($faq))
<script type="application/ld+json">
{!! json_encode([
    '@context' => 'https://schema.org',
    '@type' => 'FAQPage',
    'mainEntity' => collect($faq)->map(fn ($qa) => [
        '@type' => 'Question',
        'name' => $qa['question'],
        'acceptedAnswer' => [
            '@type' => 'Answer',
            'text' => $qa['answer'],
        ],
    ])->all(),
], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) !!}
</script>
@endif
@if (!empty($results))
<script type="application/ld+json">
{!! json_encode([
    '@context' => 'https://schema.org',
    '@type' => 'ItemList',
    'name' => $prefecture . 'のゴルフ場一覧',
    'itemListElement' => collect($results)->values()->map(function ($item, $i) use ($reviews) {
        $hotelReviews = $reviews->get($item['golfCourseId'] ?? null);

        $entry = [
            '@type' => 'GolfCourse',
            'name' => $item['golfCourseName'] ?? '',
            'url' => $item['golfCourseDetailUrl'] ?? null,
            'image' => $item['golfCourseImageUrl'] ?? null,
            'address' => $item['address'] ?? '',
        ];

        if ($hotelReviews && $hotelReviews->count() > 0) {
            $entry['aggregateRating'] = [
                '@type' => 'AggregateRating',
                'ratingValue' => round($hotelReviews->avg('rating'), 1),
                'reviewCount' => $hotelReviews->count(),
            ];
        }

        return [
            '@type' => 'ListItem',
            'position' => $i + 1,
            'item' => $entry,
        ];
    })->all(),
], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) !!}
</script>
@endif
@endpush

@section('content')
<div class="container">
  <nav aria-label="breadcrumb">
    <ol class="breadcrumb">
      <li class="breadcrumb-item"><a href="{{ route('golf.index') }}">ゴルフ場口コミ検索</a></li>
      <li class="breadcrumb-item active" aria-current="page">{{ $prefecture }}{{ $tag ? '（' . $tag . '）' : '' }}</li>
    </ol>
  </nav>

  <h1>{{ $prefecture }}のゴルフ場一覧</h1>

  @if(!empty($availableTags))
    <div class="mb-3">
      <span class="small text-muted">目的で絞り込む:</span>
      @foreach($availableTags as $t)
        <a href="{{ route('golf.search', ['prefecture' => $prefecture, 'tag' => $t]) }}"
           class="btn btn-sm {{ $tag === $t ? 'btn-primary' : 'btn-outline-secondary' }} me-1 mb-1">{{ $t }}</a>
      @endforeach
      @if($tag !== '')
        <a href="{{ route('golf.search', ['prefecture' => $prefecture]) }}" class="btn btn-sm btn-link">絞り込み解除</a>
      @endif
    </div>
  @endif

  @if(empty($results))
    <p>{{ $prefecture }}のゴルフ場{{ $tag ? "（{$tag}）" : '' }}が見つかりませんでした。他の条件もあわせてご確認ください。</p>
    <a href="{{ route('golf.index') }}" class="btn btn-outline-primary">都道府県一覧に戻る</a>
  @else
    <p class="text-muted">{{ $prefecture }}にあるゴルフ場 {{ count($results) }}件を掲載しています。</p>
    @foreach($results as $item)
      @php
        $courseReviews = $reviews->get($item['golfCourseId'] ?? null, collect());
        $courseTags = $tagsByCourseId[$item['golfCourseId'] ?? null] ?? [];
      @endphp
      <article class="mb-4 pb-4 border-bottom">
        <h2 class="h5"><a href="{{ $item['golfCourseDetailUrl'] ?? '#' }}" target="_blank" rel="noopener noreferrer">{{ $item['golfCourseName'] ?? '' }}</a></h2>
        <address class="mb-2">{{ $item['address'] ?? '' }}</address>
        @if(!empty($courseTags))
          <p class="mb-2">
            @foreach($courseTags as $t)
              <span class="badge bg-info text-dark me-1">{{ $t }}</span>
            @endforeach
          </p>
        @endif
        @if(!empty($item['golfCourseImageUrl']))
          <img src="{{ $item['golfCourseImageUrl'] }}" alt="{{ $item['golfCourseName'] ?? $prefecture . 'のゴルフ場' }}" width="200" loading="lazy">
        @endif

        <div class="mt-3">
          @if($courseReviews->isEmpty())
            <p class="text-muted small">まだ口コミがありません。最初の口コミを投稿してみませんか？</p>
          @else
            <p class="fw-bold small mb-2">
              口コミ {{ $courseReviews->count() }}件（平均★{{ round($courseReviews->avg('rating'), 1) }}）
            </p>
            @foreach($courseReviews as $review)
              <div class="border rounded p-2 mb-2 small">
                <div>{{ str_repeat('★', $review->rating) }}{{ str_repeat('☆', 5 - $review->rating) }}
                  <strong>{{ $review->nickname }}</strong>
                  <span class="text-muted">{{ $review->created_at->format('Y-m-d') }}</span>
                </div>
                <div>{{ $review->comment }}</div>
              </div>
            @endforeach
          @endif

          <details class="mt-2">
            <summary class="small">口コミを投稿する</summary>
            <form method="POST" action="{{ route('reviews.store') }}" class="mt-2">
              @csrf
              <input type="hidden" name="course_id" value="{{ $item['golfCourseId'] ?? '' }}">
              <input type="hidden" name="course_name" value="{{ $item['golfCourseName'] ?? '' }}">
              <input type="hidden" name="prefecture" value="{{ $prefecture }}">
              <div style="position:absolute;left:-9999px;" aria-hidden="true">
                <label>ウェブサイト <input type="text" name="website" tabindex="-1" autocomplete="off"></label>
              </div>
              <div class="mb-2">
                <label class="form-label small">ニックネーム（任意）</label>
                <input type="text" name="nickname" class="form-control form-control-sm" maxlength="30">
              </div>
              <div class="mb-2">
                <label class="form-label small">評価</label>
                <select name="rating" class="form-select form-select-sm" required>
                  <option value="">選択してください</option>
                  <option value="5">★★★★★</option>
                  <option value="4">★★★★☆</option>
                  <option value="3">★★★☆☆</option>
                  <option value="2">★★☆☆☆</option>
                  <option value="1">★☆☆☆☆</option>
                </select>
              </div>
              <div class="mb-2">
                <label class="form-label small">口コミ</label>
                <textarea name="comment" class="form-control form-control-sm" rows="3" minlength="5" maxlength="1000" required></textarea>
              </div>
              @if ($errors->any())
                <p class="text-danger small">{{ $errors->first() }}</p>
              @endif
              <button type="submit" class="btn btn-sm btn-outline-primary">投稿する</button>
            </form>
          </details>
        </div>
      </article>
    @endforeach

    @if(!empty($faq))
      <section class="mt-4 pt-4 border-top">
        <h2 class="h5">よくある質問</h2>
        @foreach($faq as $qa)
          <div class="mb-3">
            <p class="fw-bold mb-1">Q. {{ $qa['question'] }}</p>
            <p class="mb-0">A. {{ $qa['answer'] }}</p>
          </div>
        @endforeach
      </section>
    @endif
  @endif
</div>
@endsection
