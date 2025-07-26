<!DOCTYPE html>
<html>
<head><title>検索結果</title></head>
<body>
  <h1>検索結果</h1>

  @if (empty($results))
    <p>検索結果が見つかりませんでした。</p>
  @else
    @foreach ($results as $item)
      @php $golf = $item['Item']['golfCourse']; @endphp
      <h2>
        <a href="{{ $golf['golfCourseDetailUrl'] }}" target="_blank">
          {{ $golf['golfCourseName'] }}
        </a>
      </h2>
      <p>{{ $golf['address'] }}</p>
      <img src="{{ $golf['golfCourseImageUrl'] }}" width="200">
      <hr>
    @endforeach
  @endif

  <a href="{{ url('/') }}">← 戻る</a>
</body>
</html>
