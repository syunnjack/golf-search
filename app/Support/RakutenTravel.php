<?php

namespace App\Support;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

/**
 * Thin client for the Rakuten Travel API (KeywordHotelSearch), used to
 * suggest nearby hotels for players staying the night before an early
 * tee time ("前泊"). Uses openapi.rakuten.co.jp/engine (not
 * app.rakuten.co.jp/services) with accessKey — the same endpoint shape
 * required by the GORA API for this application's credentials.
 *
 * The API version moved from /20170426/ to /20260731/ in Rakuten's May
 * 2026 platform migration; the old version now silently returns a 503
 * "Authentication service error" instead of hotel data (caught below
 * and treated as "no hotels", which is why this stopped showing
 * results without throwing a visible error).
 */
class RakutenTravel
{
    /**
     * @return list<array{name: string, url: string, thumbnailUrl: ?string, minCharge: ?int, access: ?string, reviewAverage: ?float}>
     */
    public static function hotelsNear(string $prefecture): array
    {
        $appId = config('services.rakuten.app_id');
        $accessKey = config('services.rakuten.access_key');
        if (! $appId || ! $accessKey) {
            return [];
        }

        // **キーワードだけでは県が埋まらない。**「埼玉県 ゴルフ場 前泊」で
        // 引くと 0件だった（2026-09-02 実測）。あいまい一致なので、
        // 語を並べるほど当たらなくなる。地区コードで先に絞る。
        $area = RakutenTravelAreas::forPrefecture($prefecture);

        // **失敗を1時間焼き付けない。** 連続で叩くと 403 が返り、
        // Cache::remember だと「0件」がそのまま1時間残る（沖縄で実際に起きた）。
        // 取れたときだけ長く持つ。
        $key = "rakuten-travel:{$prefecture}";
        $cached = Cache::get($key);

        if ($cached !== null) {
            return $cached;
        }

        $hotels = (function () use ($prefecture, $appId, $accessKey, $area) {
            try {
                $response = Http::timeout(5)
                    ->withHeaders([
                        'Referer' => config('app.url'),
                        'Origin' => config('app.url'),
                    ])
                    ->get('https://openapi.rakuten.co.jp/engine/api/Travel/KeywordHotelSearch/20260731',
                        array_filter([
                            'format' => 'json',
                            'applicationId' => $appId,
                            'accessKey' => $accessKey,
                            'affiliateId' => config('services.rakuten.affiliate_id'),
                            // 語は絞りすぎない。地区コードで県は確定している
                            'keyword' => 'ゴルフ',
                            'largeClassCode' => $area['largeClassCode'] ?? null,
                            'middleClassCode' => $area['middleClassCode'] ?? null,
                            'hits' => 6,
                            'responseType' => 'small',
                        ]));
            } catch (ConnectionException) {
                return null;
            }

            if (! $response->successful()) {
                return null;
            }

            $hotelGroups = $response->json('hotels') ?? [];

            $hotels = [];
            foreach ($hotelGroups as $group) {
                $basicInfo = $group['hotel'][0]['hotelBasicInfo'] ?? null;
                if (! $basicInfo) {
                    continue;
                }

                $hotels[] = [
                    'name' => $basicInfo['hotelName'] ?? '',
                    'url' => $basicInfo['hotelInformationUrl'] ?? '',
                    'thumbnailUrl' => $basicInfo['hotelThumbnailUrl'] ?? null,
                    'minCharge' => $basicInfo['hotelMinCharge'] ?? null,
                    'access' => $basicInfo['access'] ?? null,
                    'reviewAverage' => $basicInfo['reviewAverage'] ?? null,
                ];
            }

            return $hotels;
        })();

        // 取れたときは1時間、取れなかったときは5分。
        // 403 で空になったものを長く抱えると、その県のホテル欄が
        // 次の更新まで消えたままになる。
        Cache::put($key, $hotels ?? [], $hotels === null ? now()->addMinutes(5) : now()->addHour());

        return $hotels ?? [];
    }
}
