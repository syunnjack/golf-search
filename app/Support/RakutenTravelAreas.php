<?php

namespace App\Support;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

/**
 * 楽天トラベルの地区コード（都道府県 → middleClassCode）。
 *
 * 出典: 楽天ウェブサービス 地区コード取得API
 *       https://webservice.rakuten.co.jp/documentation/get-area-class
 *
 * **キーワード検索だけでは県が埋まらない。** 「山梨県 温泉」で引くと
 * あいまい一致で県外の宿ばかりが返り、住所で絞ると0件になっていた
 * （2026-09-02 実測。47県中、山梨県だけが0件）。
 * 地区コードで絞れば確実にその県の宿が返る。
 *
 * **バージョンに注意。** 20131024 は 400 を返す。正しくは 20140210。
 * レスポンスは `largeClass` が配列ではなくオブジェクトで、
 * `areaClasses.largeClasses[].largeClass.middleClasses[].middleClass` と辿る。
 */
class RakutenTravelAreas
{
    private const ENDPOINT = 'https://openapi.rakuten.co.jp/engine/api/Travel/GetAreaClass/20140210';

    /**
     * 都道府県名 → ['largeClassCode' => ..., 'middleClassCode' => ...]
     *
     * @return array<string, array{largeClassCode: string, middleClassCode: string}>
     */
    public static function all(): array
    {
        return Cache::remember('rakuten-travel-areas', now()->addDays(30), function () {
            $appId = config('services.rakuten.app_id');
            $accessKey = config('services.rakuten.access_key');

            if (! $appId || ! $accessKey) {
                return [];
            }

            try {
                $response = Http::timeout(10)
                    ->withHeaders([
                        'Referer' => config('app.url'),
                        'Origin' => config('app.url'),
                    ])
                    ->get(self::ENDPOINT, [
                        'format' => 'json',
                        'applicationId' => $appId,
                        'accessKey' => $accessKey,
                    ]);
            } catch (ConnectionException) {
                return [];
            }

            if (! $response->successful()) {
                return [];
            }

            $areas = [];

            foreach ($response->json('areaClasses.largeClasses') ?? [] as $entry) {
                $large = $entry['largeClass'] ?? [];
                $largeCode = $large['largeClassCode'] ?? '';

                foreach ($large['middleClasses'] ?? [] as $middleEntry) {
                    $middle = $middleEntry['middleClass'] ?? [];
                    $name = $middle['middleClassName'] ?? '';
                    $code = $middle['middleClassCode'] ?? '';

                    if ($name !== '' && $code !== '') {
                        $areas[$name] = ['largeClassCode' => $largeCode, 'middleClassCode' => $code];
                    }
                }
            }

            return $areas;
        });
    }

    /**
     * @return array{largeClassCode: string, middleClassCode: string}|null
     */
    public static function forPrefecture(string $prefecture): ?array
    {
        return self::all()[$prefecture] ?? null;
    }
}
