# golf-search

golf-search は、全国47都道府県のゴルフ場を都道府県別に検索し、目的タグ、口コミ、前泊ホテル、天気予報までまとめて比較できる Laravel アプリケーションです。

## 主な機能

- 全国47都道府県からのゴルフ場検索
- 「早朝プレー」「ナイター」「温泉付き」などのタグ絞り込み
- 利用者口コミの投稿・閲覧
- 気象庁データによる都道府県別天気予報表示
- 楽天トラベル API を使った前泊ホテル提案
- sitemap.xml と llms.txt による検索・参照最適化

## 開発手順

```bash
composer install
npm install
cp .env.example .env
php artisan key:generate
php artisan serve
npm run dev
```

## 品質確認

```bash
php artisan test
npm run build
```

## 全国対応について

- トップページに全国47都道府県を表示します。
- 検索結果、FAQ、サイトマップは都道府県ごとに生成されます。
- 楽天GORA の keyword 検索結果は住所先頭の都道府県名で再フィルタし、他県コースの混入を防いでいます。

## 必要な環境変数

- `RAKUTEN_APP_ID`
- `RAKUTEN_ACCESS_KEY`
- `RAKUTEN_AFFILIATE_ID`
- `APP_URL`
