<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;

class GolfController extends Controller
{
    // 都道府県リストを返す
    public function getPrefectures()
    {
        return [
            '北海道', '青森県', '岩手県', '宮城県', '秋田県', '山形県', '福島県',
            '茨城県', '栃木県', '群馬県', '埼玉県', '千葉県', '東京都', '神奈川県',
            '新潟県', '富山県', '石川県', '福井県', '山梨県', '長野県', '岐阜県',
            '静岡県', '愛知県', '三重県', '滋賀県', '京都府', '大阪府', '兵庫県',
            '奈良県', '和歌山県', '鳥取県', '島根県', '岡山県', '広島県', '山口県',
            '徳島県', '香川県', '愛媛県', '高知県', '福岡県', '佐賀県', '長崎県',
            '熊本県', '大分県', '宮崎県', '鹿児島県', '沖縄県',
        ];
    }

    // 都道府県のゴルフ場を検索する
    public function search(Request $request)
    {
        dd([
        'RAKUTEN_APP_ID' => env('RAKUTEN_APP_ID'),
        'RAKUTEN_AFFILIATE_ID' => env('RAKUTEN_AFFILIATE_ID'),
        ]);
        $prefecture = $request->input('prefecture');  // 選ばれた都道府県
        $prefectures = $this->getPrefectures();  // 都道府県リスト

        // 楽天APIを使ってゴルフ場データを取得
        $response = Http::get('https://app.rakuten.co.jp/services/api/Gora/GoraGolfCourseSearch/20131113', [
            'applicationId' => env('RAKUTEN_APP_ID'),  // アプリID
            'affiliateId' => env('RAKUTEN_AFFILIATE_ID'),  // アフィリエイトID
            'format' => 'json',
            'prefecture' => $prefecture,  // 都道府県を指定
        ]);
        dd($response->json());
        // APIのレスポンスからゴルフ場情報を取得
        $courses = $response->json()['Items'] ?? [];

        // 結果をビューに渡す
        return view('golf.index', compact('prefectures', 'prefecture', 'courses'));
    }
}
