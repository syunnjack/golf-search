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

        return Cache::remember("rakuten-travel:{$prefecture}", now()->addHour(), function () use ($prefecture, $appId, $accessKey) {
            try {
                $response = Http::timeout(5)
                    ->withHeaders([
                        'Referer' => config('app.url'),
                        'Origin' => config('app.url'),
                    ])
                    ->get('https://openapi.rakuten.co.jp/engine/api/Travel/KeywordHotelSearch/20260731', [
                        'format' => 'json',
                        'applicationId' => $appId,
                        'accessKey' => $accessKey,
                        'affiliateId' => config('services.rakuten.affiliate_id'),
                        'keyword' => $prefecture . ' ゴルフ場 前泊',
                        'hits' => 6,
                        'responseType' => 'small',
                    ]);
            } catch (ConnectionException) {
                return [];
            }

            if (! $response->successful()) {
                return [];
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
        });
    }
}
