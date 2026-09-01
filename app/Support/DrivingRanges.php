<?php

namespace App\Support;

use Illuminate\Support\Facades\Cache;

/**
 * ゴルフ練習場（打ちっぱなし）。
 *
 * 出典: OpenStreetMap contributors（ODbL）
 *       https://www.openstreetmap.org/copyright
 *
 * 楽天GORAはコースしか返さないため、練習場は OSM から取っている
 * （`scripts/fetch-driving-ranges.py` が storage/app/driving-ranges.json を作る）。
 *
 * **名前が入っている施設だけ。** 日本全体で 1,042件のうち 386件。
 * OSM に登録が無い練習場は出ない。**「その県に練習場が無い」ではない**ので、
 * 0件のときはその旨を画面に書く。
 */
class DrivingRanges
{
    private const FILE = 'driving-ranges.json';

    /**
     * @return array{ranges: list<array<string, mixed>>, confirmedOn: string, incomplete: bool}
     */
    public static function forPrefecture(string $prefecture): array
    {
        $data = Cache::remember('driving-ranges', now()->addHours(12), function () {
            $path = storage_path('app/' . self::FILE);

            if (! is_file($path)) {
                return null;
            }

            return json_decode((string) file_get_contents($path), true);
        });

        if (! is_array($data)) {
            return ['ranges' => [], 'confirmedOn' => '', 'incomplete' => false];
        }

        return [
            'ranges' => $data['byPrefecture'][$prefecture] ?? [],
            'confirmedOn' => $data['confirmedOn'] ?? '',
            // 取得できなかった県は「0件」と言い切らない
            'incomplete' => in_array($prefecture, $data['failedPrefectures'] ?? [], true),
        ];
    }
}
