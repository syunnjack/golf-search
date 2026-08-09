<?php

namespace App\Support;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

/**
 * Thin client for the Japan Meteorological Agency's public forecast API
 * (no key required). Used to show a real short-range weather forecast
 * alongside golf course listings, since "is it going to rain this
 * weekend" is a genuine part of the golf-course-search intent.
 */
class JmaWeather
{
    // JMA forecast "office" area codes, one per prefecture. Hokkaido,
    // Kagoshima, and Okinawa have several office areas each; the code
    // below is the one covering the prefecture's main population center.
    private const OFFICE_CODES = [
        '北海道' => '016000', '青森県' => '020000', '岩手県' => '030000', '宮城県' => '040000',
        '秋田県' => '050000', '山形県' => '060000', '福島県' => '070000', '茨城県' => '080000',
        '栃木県' => '090000', '群馬県' => '100000', '埼玉県' => '110000', '千葉県' => '120000',
        '東京都' => '130000', '神奈川県' => '140000', '新潟県' => '150000', '富山県' => '160000',
        '石川県' => '170000', '福井県' => '180000', '山梨県' => '190000', '長野県' => '200000',
        '岐阜県' => '210000', '静岡県' => '220000', '愛知県' => '230000', '三重県' => '240000',
        '滋賀県' => '250000', '京都府' => '260000', '大阪府' => '270000', '兵庫県' => '280000',
        '奈良県' => '290000', '和歌山県' => '300000', '鳥取県' => '310000', '島根県' => '320000',
        '岡山県' => '330000', '広島県' => '340000', '山口県' => '350000', '徳島県' => '360000',
        '香川県' => '370000', '愛媛県' => '380000', '高知県' => '390000', '福岡県' => '400000',
        '佐賀県' => '410000', '長崎県' => '420000', '熊本県' => '430000', '大分県' => '440000',
        '宮崎県' => '450000', '鹿児島県' => '460100', '沖縄県' => '471000',
    ];

    /**
     * @return list<array{date: string, weather: string, hasRain: bool}>|null
     */
    public static function forecast(string $prefecture): ?array
    {
        $code = self::OFFICE_CODES[$prefecture] ?? null;
        if ($code === null) {
            return null;
        }

        return Cache::remember("jma-forecast:{$code}", now()->addHour(), function () use ($code) {
            try {
                $response = Http::timeout(5)->get("https://www.jma.go.jp/bosai/forecast/data/forecast/{$code}.json");
            } catch (\Illuminate\Http\Client\ConnectionException) {
                return null;
            }

            if (! $response->successful()) {
                return null;
            }

            $series = $response->json('0.timeSeries.0');
            if (! is_array($series) || ! isset($series['areas'][0], $series['timeDefines'])) {
                return null;
            }
            $area = $series['areas'][0];

            $days = [];
            foreach ($series['timeDefines'] as $i => $timeDefine) {
                $weather = $area['weathers'][$i] ?? null;
                if ($weather === null) {
                    continue;
                }

                $days[] = [
                    'date' => \Carbon\Carbon::parse($timeDefine)->locale('ja')->isoFormat('M月D日(ddd)'),
                    'weather' => trim(preg_replace('/\x{3000}+/u', ' ', $weather)),
                    'hasRain' => mb_strpos($weather, '雨') !== false || mb_strpos($weather, '雷') !== false,
                ];
            }

            return $days === [] ? null : $days;
        });
    }
}
