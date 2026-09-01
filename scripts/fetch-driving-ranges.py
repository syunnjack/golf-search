"""ゴルフ練習場（打ちっぱなし）を OpenStreetMap から取る。

出典: OpenStreetMap contributors（ODbL）
      https://www.openstreetmap.org/copyright

## なぜ OSM か

楽天GORAが返すのは**コースだけ**で、練習場は入っていない。
練習場の公開データで全国を網羅しているものが他に見当たらないため、
OSM の `golf=driving_range` を使う。

## 実測（2026-09-01）

  日本全体で 1,042件（node 26 / way 1,006 / relation 10）
  **うち名前が入っているのは 386件だけ。** 住所タグはほとんど無い
  （addr:city が入っているのは19件）

**名前の無いものは載せない。**「無名の練習場」を並べても読む人の役に立たない。

## 都道府県の決め方

住所タグが無いので、**県ごとに Overpass へ問い合わせて**振り分ける。
緯度経度から自前で判定するより確実で、県名がそのまま出典に沿う。

**Overpass は 0件を「エラーではなく空」で返すことがある。**
取れなかった県は `failedPrefectures` に記録し、**0件として上書きしない**。
実際 1回目は14県が取れず（北海道・東京都・静岡など）、
そのまま信じると「東京に練習場は無い」ことになってしまう。

環境変数:
  ONLY  県名をカンマ区切りで並べると、その県だけ取り直して他は引き継ぐ

使い方:
  python scripts/fetch-driving-ranges.py
  ONLY=北海道,東京都 python scripts/fetch-driving-ranges.py
"""
import json
import os
import sys
import time
import urllib.parse
import urllib.request
from datetime import date
from pathlib import Path

ENDPOINTS = (
    'https://overpass-api.de/api/interpreter',
    'https://overpass.kumi.systems/api/interpreter',
)
UA = 'golf-kuchikomi.jp data build (contact: info@golf-kuchikomi.jp)'
PAUSE = 6.0   # 公開サーバーは混むと504を返す。間隔を空ける

OUT = Path(__file__).resolve().parent.parent / 'storage' / 'app' / 'driving-ranges.json'

PREFECTURES = [
    '北海道', '青森県', '岩手県', '宮城県', '秋田県', '山形県', '福島県', '茨城県',
    '栃木県', '群馬県', '埼玉県', '千葉県', '東京都', '神奈川県', '新潟県', '富山県',
    '石川県', '福井県', '山梨県', '長野県', '岐阜県', '静岡県', '愛知県', '三重県',
    '滋賀県', '京都府', '大阪府', '兵庫県', '奈良県', '和歌山県', '鳥取県', '島根県',
    '岡山県', '広島県', '山口県', '徳島県', '香川県', '愛媛県', '高知県', '福岡県',
    '佐賀県', '長崎県', '熊本県', '大分県', '宮崎県', '鹿児島県', '沖縄県',
]


def ask(query: str) -> dict | None:
    for endpoint in ENDPOINTS:
        try:
            request = urllib.request.Request(
                endpoint,
                data=urllib.parse.urlencode({'data': query}).encode(),
                headers={'User-Agent': UA, 'Accept': 'application/json'},
            )
            with urllib.request.urlopen(request, timeout=300) as response:
                return json.loads(response.read().decode('utf-8', 'replace'))
        except Exception as error:
            print(f'    {endpoint.split("/")[2]}: {str(error)[:60]}', file=sys.stderr)
            time.sleep(15)

    return None


def clean(element: dict) -> dict | None:
    tags = element.get('tags') or {}
    name = (tags.get('name') or tags.get('name:ja') or '').strip()

    if not name:
        return None

    center = element.get('center') or element
    lat, lon = center.get('lat'), center.get('lon')

    record = {
        'id': f'{element.get("type")}/{element.get("id")}',
        'name': name,
        'lat': lat,
        'lon': lon,
    }

    # 入っているものだけ載せる。無い項目は書かない。
    for key, field in (('website', 'website'), ('phone', 'phone'),
                       ('opening_hours', 'openingHours'), ('addr:postcode', 'postcode')):
        if tags.get(key):
            record[field] = tags[key].strip()

    parts = [tags.get(f'addr:{k}', '') for k in
             ('province', 'city', 'suburb', 'neighbourhood', 'block_number', 'housenumber')]
    address = ''.join(p for p in parts if p)
    if address:
        record['address'] = address

    return record


def load_previous() -> dict:
    if not OUT.exists():
        return {}
    try:
        return json.loads(OUT.read_text(encoding='utf-8'))
    except Exception:
        return {}


def main() -> int:
    previous = load_previous()
    found = dict(previous.get('byPrefecture') or {})
    failed = []

    only = [p.strip() for p in (os.environ.get('ONLY') or '').split(',') if p.strip()]
    targets = only or PREFECTURES

    if only:
        print(f'{len(only)}県だけ取り直します。残りは前回の結果を引き継ぎます。', file=sys.stderr)

    for prefecture in targets:
        query = (
            '[out:json][timeout:90];'
            f'area["name"="{prefecture}"]["admin_level"="4"]->.a;'
            'nwr["golf"="driving_range"](area.a);'
            'out center tags;'
        )
        payload = ask(query)

        if payload is None:
            # **前回の結果を消さない。** 取れなかっただけで「0件」ではない。
            print(f'  {prefecture}: 取れませんでした（前回の {len(found.get(prefecture, []))}件を残します）',
                  file=sys.stderr)
            failed.append(prefecture)
            time.sleep(PAUSE)
            continue

        rows = [clean(e) for e in payload.get('elements', [])]
        rows = [r for r in rows if r]
        rows.sort(key=lambda r: r['name'])

        found[prefecture] = rows
        print(f'  {prefecture}: {len(rows)}件（名前の無いものを除く）', file=sys.stderr)
        time.sleep(PAUSE)

    # 今回触らなかった県のうち、前回失敗していたものは失敗のまま残す。
    for prefecture in (previous.get('failedPrefectures') or []):
        if prefecture not in targets and prefecture not in failed:
            failed.append(prefecture)

    total = sum(len(v) for v in found.values())

    OUT.parent.mkdir(parents=True, exist_ok=True)
    OUT.write_text(json.dumps({
        'confirmedOn': date.today().isoformat(),
        'source': 'OpenStreetMap contributors',
        'sourceUrl': 'https://www.openstreetmap.org/copyright',
        'license': 'ODbL',
        'note': '名前が入っている施設だけを載せています。OSM に登録が無い練習場は出ません。',
        'failedPrefectures': failed,
        'total': total,
        'byPrefecture': found,
    }, ensure_ascii=False), encoding='utf-8')

    print(f'\n合計 {total}件 / 取れなかった県 {len(failed)}', file=sys.stderr)
    return 0


if __name__ == '__main__':
    raise SystemExit(main())
