<?php

namespace App\Http\Controllers;

use App\Models\Review;
use App\Support\GolfTagger;
use App\Support\JmaWeather;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

class GolfController extends Controller
{
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

    public function search(Request $request)
    {
        $prefecture = $request->input('prefecture', '');

        if ($prefecture === '') {
            return redirect()->route('golf.index');
        }

        $results = Cache::remember("golf-search:{$prefecture}", now()->addHour(), function () use ($prefecture) {
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
                        'keyword' => $prefecture . ' ゴルフ場',
                        'hits' => 30,
                    ]);
            } catch (ConnectionException) {
                return [];
            }

            $items = $response->successful() ? ($response->json('items') ?? []) : [];

            // keyword検索はあいまい一致のため、選択された都道府県以外の
            // ゴルフ場も混ざる。住所に都道府県名が含まれるかで厳密に絞り込む。
            return array_values(array_filter($items, function ($item) use ($prefecture) {
                return str_starts_with($item['address'] ?? '', $prefecture);
            }));
        });

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
        $faq = $this->buildFaq($prefecture, $reviews, $tagsByCourseId, $weather);

        return view('golf.results', compact('results', 'prefecture', 'reviews', 'tagsByCourseId', 'availableTags', 'tag', 'faq', 'weather'));
    }

    private function buildFaq(string $prefecture, Collection $reviews, array $tagsByCourseId, ?array $weather): array
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

        return $faq;
    }
}
