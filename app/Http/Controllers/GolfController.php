<?php

namespace App\Http\Controllers;

use App\Models\Review;
use App\Support\GolfTagger;
use App\Support\JmaWeather;
use App\Support\RakutenTravel;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

class GolfController extends Controller
{
    private const HITS_PER_PAGE = 30;
    private const MAX_PAGES = 3;
    private const MAX_COURSES = 60;

    /** 都道府県ページのURLに使うローマ字。クエリ付きURLより共有・被リンクされやすい。 */
    public const PREFECTURE_SLUGS = [
        '北海道' => 'hokkaido', '青森県' => 'aomori', '岩手県' => 'iwate', '宮城県' => 'miyagi',
        '秋田県' => 'akita', '山形県' => 'yamagata', '福島県' => 'fukushima', '茨城県' => 'ibaraki',
        '栃木県' => 'tochigi', '群馬県' => 'gunma', '埼玉県' => 'saitama', '千葉県' => 'chiba',
        '東京都' => 'tokyo', '神奈川県' => 'kanagawa', '新潟県' => 'niigata', '富山県' => 'toyama',
        '石川県' => 'ishikawa', '福井県' => 'fukui', '山梨県' => 'yamanashi', '長野県' => 'nagano',
        '岐阜県' => 'gifu', '静岡県' => 'shizuoka', '愛知県' => 'aichi', '三重県' => 'mie',
        '滋賀県' => 'shiga', '京都府' => 'kyoto', '大阪府' => 'osaka', '兵庫県' => 'hyogo',
        '奈良県' => 'nara', '和歌山県' => 'wakayama', '鳥取県' => 'tottori', '島根県' => 'shimane',
        '岡山県' => 'okayama', '広島県' => 'hiroshima', '山口県' => 'yamaguchi', '徳島県' => 'tokushima',
        '香川県' => 'kagawa', '愛媛県' => 'ehime', '高知県' => 'kochi', '福岡県' => 'fukuoka',
        '佐賀県' => 'saga', '長崎県' => 'nagasaki', '熊本県' => 'kumamoto', '大分県' => 'oita',
        '宮崎県' => 'miyazaki', '鹿児島県' => 'kagoshima', '沖縄県' => 'okinawa',
    ];

    private const PREFECTURES = [
        '北海道', '青森県', '岩手県', '宮城県', '秋田県', '山形県', '福島県',
        '茨城県', '栃木県', '群馬県', '埼玉県', '千葉県', '東京都', '神奈川県',
        '新潟県', '富山県', '石川県', '福井県', '山梨県', '長野県', '岐阜県',
        '静岡県', '愛知県', '三重県', '滋賀県', '京都府', '大阪府', '兵庫県',
        '奈良県', '和歌山県', '鳥取県', '島根県', '岡山県', '広島県', '山口県',
        '徳島県', '香川県', '愛媛県', '高知県', '福岡県', '佐賀県', '長崎県',
        '熊本県', '大分県', '宮崎県', '鹿児島県', '沖縄県',
    ];

    public function index()
    {
        return view('golf.index', ['prefectures' => self::PREFECTURES]);
    }

    /** トップの検索フォームからの遷移。都道府県ページの正しいURLへ送る。 */
    public function search(Request $request)
    {
        $prefecture = (string) $request->input('prefecture', '');
        $slug = self::PREFECTURE_SLUGS[$prefecture] ?? null;

        if ($slug === null) {
            return redirect()->route('golf.index');
        }

        $tag = (string) $request->input('tag', '');

        return redirect()->route('golf.prefecture', array_filter([
            'prefectureSlug' => $slug,
            'tag' => $tag !== '' ? $tag : null,
        ]), 301);
    }

    /**
     * 楽天GORAから都道府県のゴルフ場を取る。
     *
     * **keyword 検索は使わない。** あいまい一致なので県外のコースが混ざり、
     * 住所の先頭で絞ると1ページ目が県外で埋まった県は0件になっていた。
     * 実際に神奈川・静岡・茨城など18県が「見つかりませんでした」の
     * 145字ページになっていた（2026-09-01 実測）。
     *
     * GORA には areaCode があり、値は **JISの都道府県番号**（1=北海道 … 47=沖縄）。
     * PREFECTURE_SLUGS はその順に並べてあるので、並び順から番号を作れる。
     * 住所の先頭での絞り込みは、念のため残す。
     *
     * @return array{0: array<int, array<string, mixed>>, 1: bool} 結果と、APIが応答したか
     */
    private function fetchCourses(string $prefecture): array
    {
        $matches = [];
        $succeeded = false;

        $index = array_search($prefecture, array_keys(self::PREFECTURE_SLUGS), true);

        if ($index === false) {
            return [[], false];
        }

        $areaCode = $index + 1;

        for ($page = 1; $page <= self::MAX_PAGES; $page++) {
            if ($page > 1) {
                // 楽天ウェブサービスは1秒あたり1リクエストまで
                usleep(1_100_000);
            }

            try {
                $response = Http::timeout(5)
                    ->withHeaders([
                        'Referer' => config('app.url'),
                        'Origin' => config('app.url'),
                    ])
                    ->get('https://openapi.rakuten.co.jp/engine/api/Gora/GoraGolfCourseSearch/20170623', [
                        'format' => 'json',
                        'formatVersion' => 2,
                        'applicationId' => config('services.rakuten.app_id'),
                        'accessKey' => config('services.rakuten.access_key'),
                        'affiliateId' => config('services.rakuten.affiliate_id'),
                        'areaCode' => $areaCode,
                        'hits' => self::HITS_PER_PAGE,
                        'page' => $page,
                    ]);
            } catch (ConnectionException) {
                break;
            }

            // 次のページが無いときは404が返る。1ページ目が取れていれば成功扱い。
            if (! $response->successful()) {
                break;
            }

            $succeeded = true;
            $items = $response->json('Items') ?? [];

            foreach ($items as $item) {
                if (str_starts_with($item['address'] ?? '', $prefecture)) {
                    $key = $item['golfCourseId'] ?? count($matches);
                    $matches[$key] = $item;
                }
            }

            if (count($items) < self::HITS_PER_PAGE || count($matches) >= self::MAX_COURSES) {
                break;
            }
        }

        return [array_values($matches), $succeeded];
    }

    public function prefecture(Request $request, string $prefectureSlug)
    {
        $prefecture = array_search($prefectureSlug, self::PREFECTURE_SLUGS, true);

        if ($prefecture === false) {
            abort(404);
        }

        $cacheKey = "golf-search:{$prefecture}";
        $results = Cache::get($cacheKey);

        if ($results === null) {
            [$results, $succeeded] = $this->fetchCourses($prefecture);

            // 失敗したときの「0件」を長く残さない。APIが403や429を返すと、
            // その県のページが次の更新まで空のままになってしまう。
            Cache::put(
                $cacheKey,
                $results,
                $succeeded ? now()->addHours(6) : now()->addMinutes(5)
            );
        }

        $tagsByCourseId = [];
        $availableTags = [];
        foreach ($results as $item) {
            $tags = GolfTagger::extract($item['golfCourseName'] ?? '', $item['golfCourseAbbr'] ?? '');
            if (isset($item['golfCourseId'])) {
                $tagsByCourseId[$item['golfCourseId']] = $tags;
            }
            $availableTags = array_unique(array_merge($availableTags, $tags));
        }
        sort($availableTags);

        $tag = $request->input('tag', '');
        if ($tag !== '') {
            $results = array_values(array_filter($results, function ($item) use ($tag, $tagsByCourseId) {
                $courseId = $item['golfCourseId'] ?? null;
                return in_array($tag, $tagsByCourseId[$courseId] ?? [], true);
            }));
        }

        $courseIds = collect($results)
            ->map(fn ($item) => $item['golfCourseId'] ?? null)
            ->filter()
            ->values();

        $reviews = Review::whereIn('course_id', $courseIds)
            ->latest()
            ->get()
            ->groupBy('course_id');

        $weather = JmaWeather::forecast($prefecture);
        $hotels = RakutenTravel::hotelsNear($prefecture);
        $faq = $this->buildFaq($prefecture, $reviews, $tagsByCourseId, $weather, $hotels);

        return view('golf.results', compact(
            'results', 'prefecture', 'prefectureSlug', 'reviews', 'tagsByCourseId',
            'availableTags', 'tag', 'faq', 'weather', 'hotels'
        ));
    }

    private function buildFaq(string $prefecture, Collection $reviews, array $tagsByCourseId, ?array $weather, array $hotels): array
    {
        $morningCount = collect($tagsByCourseId)->filter(fn ($tags) => in_array('早朝プレー', $tags, true))->count();

        $topRated = $reviews->filter(fn ($group) => $group->count() > 0)
            ->sortByDesc(fn ($group) => $group->avg('rating'))
            ->first();
        $topRatedName = $topRated ? $topRated->first()->course_name : null;

        $faq = [
            [
                'question' => $prefecture . 'で早朝プレーができるゴルフ場はありますか？',
                'answer' => $morningCount > 0
                    ? "はい、{$prefecture}には早朝プレーに対応しているゴルフ場が{$morningCount}件あります。一覧の「早朝プレー」タグで絞り込めます。"
                    : "現在の掲載データでは、{$prefecture}で早朝プレーを明記しているゴルフ場は見つかりませんでした。",
            ],
            [
                'question' => $prefecture . 'のゴルフ場の口コミは見られますか？',
                'answer' => '各ゴルフ場のページで、実際にプレーした方の口コミ（評価と感想）を確認できます。口コミはどなたでもログイン不要で投稿できます。',
            ],
        ];

        if ($topRatedName) {
            $faq[] = [
                'question' => $prefecture . 'でおすすめのゴルフ場は？',
                'answer' => "口コミ評価をもとにすると、{$topRatedName}が現在最も高い評価を得ています。ただしコースの好みは人それぞれのため、他のゴルフ場の口コミもあわせてご確認ください。",
            ];
        }

        if ($weather) {
            $rainyDays = collect($weather)->where('hasRain', true)->pluck('date');
            $answer = $rainyDays->isEmpty()
                ? "気象庁の予報によると、当面{$prefecture}で雨マークは出ていません。プレー予定を立てやすい状況です。"
                : "気象庁の予報によると、{$rainyDays->implode('、')}に雨または雷の可能性があります。詳しい時間帯の予報は各予報日の内容をご確認ください。";
            $faq[] = [
                'question' => $prefecture . 'でゴルフをするのに天気は大丈夫ですか？',
                'answer' => $answer,
            ];
        }

        if (! empty($hotels)) {
            $faq[] = [
                'question' => $prefecture . 'のゴルフ場に前泊する場合、近くにホテルはありますか？',
                'answer' => "早朝スタートに備えて前泊したい方向けに、{$prefecture}周辺のホテルを一覧に掲載しています。早朝プレーのゴルフ場と合わせてご確認ください。",
            ];
        }

        return $faq;
    }
}
