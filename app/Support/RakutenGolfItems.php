<?php

namespace App\Support;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

/**
 * 楽天市場からゴルフ用品を引く。
 *
 * 出典: 楽天ウェブサービス（楽天市場商品検索API）
 *       https://webservice.rakuten.co.jp/
 *
 * GORA はコースの予約しか扱えないので、用品はこちらから引く。
 *
 * **エンドポイントは API ごとに違う。** このアプリの資格情報は
 * openapi.rakuten.co.jp 系で、accessKey が要る（RakutenTravel と同じ）。
 *
 *   GORA    /engine/api/Gora/GoraGolfCourseSearch/20170623
 *   トラベル /engine/api/Travel/KeywordHotelSearch/20260731
 *   市場     /ichibams/api/IchibaItem/Search/20220601   ← これ
 *
 * app.rakuten.co.jp/services のほうを叩くと、このアプリIDでは通らない。
 *
 * ページの内容と関係のない商品を並べても押されない。**見ている人の
 * 目当てに近いキーワードを渡すこと**（練習場のページなら練習器具、
 * コースの一覧なら消耗品）。
 *
 * 失敗を長く抱えないよう、成功と失敗で保存時間を変える（GolfController と同じ）。
 */
class RakutenGolfItems
{
    private const ENDPOINT = 'https://openapi.rakuten.co.jp/ichibams/api/IchibaItem/Search/20220601';

    /** 1つのキーワードで並べる数 */
    private const HITS = 6;

    /** 取れたときの保存時間 */
    private const OK_HOURS = 12;

    /** 取れなかったときの保存時間。失敗を焼き付けない */
    private const NG_MINUTES = 10;

    /**
     * @return list<array{name: string, url: string, image: string, price: ?int, shop: string, reviews: int}>
     */
    public static function search(string $keyword): array
    {
        // 市場APIは別アプリの資格情報を使う（GORA用では通らない）
        $appId = config('services.rakuten.ichiba_app_id');
        $accessKey = config('services.rakuten.ichiba_access_key');

        if (! $appId || ! $accessKey) {
            return [];
        }

        $key = 'rakuten-items:' . sha1($keyword);
        $cached = Cache::get($key);

        if ($cached !== null) {
            return $cached;
        }

        [$items, $succeeded] = self::fetch($keyword, $appId, $accessKey);

        Cache::put(
            $key,
            $items,
            $succeeded ? now()->addHours(self::OK_HOURS) : now()->addMinutes(self::NG_MINUTES)
        );

        return $items;
    }

    /**
     * @return array{0: list<array<string, mixed>>, 1: bool}
     */
    private static function fetch(string $keyword, string $appId, string $accessKey): array
    {
        try {
            $response = Http::timeout(5)
                ->withHeaders([
                    'Referer' => config('app.url'),
                    'Origin' => config('app.url'),
                ])
                ->get(self::ENDPOINT, [
                    'format' => 'json',
                    'formatVersion' => 2,
                    'applicationId' => $appId,
                    'accessKey' => $accessKey,
                    'affiliateId' => config('services.rakuten.affiliate_id'),
                    'keyword' => $keyword,
                    'hits' => self::HITS,
                    // 数が出ているものから。売れ筋のほうが押されやすい
                    'sort' => '-reviewCount',
                    'imageFlag' => 1,
                    'availability' => 1,
                ]);
        } catch (ConnectionException) {
            return [[], false];
        }

        if (! $response->successful()) {
            return [[], false];
        }

        $items = [];

        foreach ($response->json('Items') ?? [] as $item) {
            $url = $item['affiliateUrl'] ?? $item['itemUrl'] ?? '';
            $name = $item['itemName'] ?? '';

            if ($url === '' || $name === '') {
                continue;
            }

            // 画像は楽天が返すURLをそのまま使う。加工しない。
            $image = $item['mediumImageUrls'][0] ?? $item['smallImageUrls'][0] ?? '';

            $items[] = [
                'name' => $name,
                'url' => $url,
                'image' => is_array($image) ? ($image['imageUrl'] ?? '') : (string) $image,
                'price' => $item['itemPrice'] ?? null,
                'shop' => $item['shopName'] ?? '',
                'reviews' => (int) ($item['reviewCount'] ?? 0),
            ];
        }

        return [$items, true];
    }
}
