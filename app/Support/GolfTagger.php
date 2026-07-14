<?php

namespace App\Support;

class GolfTagger
{
    private const KEYWORD_TAGS = [
        '早朝' => '早朝プレー',
        'ナイター' => 'ナイター',
        'レディース' => 'レディース向け',
        '女子会' => '女子会向け',
        '温泉' => '温泉付き',
        '宿泊' => '宿泊パック',
        'コンペ' => 'コンペ向け',
        '初心者' => '初心者歓迎',
        '本格' => '本格コース',
        '林間' => '林間コース',
    ];

    /**
     * @return list<string>
     */
    public static function extract(string $courseName, string $courseAbbr): array
    {
        $text = $courseName . ' ' . $courseAbbr;

        $tags = [];
        foreach (self::KEYWORD_TAGS as $keyword => $label) {
            if (mb_stripos($text, $keyword) !== false) {
                $tags[] = $label;
            }
        }

        return $tags;
    }
}
