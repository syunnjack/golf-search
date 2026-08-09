<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class GolfSearchTest extends TestCase
{
    use RefreshDatabase;

    public function test_index_lists_all_47_prefectures(): void
    {
        $response = $this->get('/');

        $response->assertStatus(200);
        $response->assertSee('北海道');
        $response->assertSee('沖縄県');
    }

    public function test_search_without_prefecture_redirects_to_index(): void
    {
        $response = $this->get('/search');

        $response->assertRedirect(route('golf.index'));
    }

    public function test_search_renders_courses_returned_by_rakuten(): void
    {
        Http::fake([
            'openapi.rakuten.co.jp/*' => Http::response([
                'Items' => [
                    [
                        'golfCourseId' => 1,
                        'golfCourseName' => 'テストゴルフ倶楽部',
                        'address' => '千葉県千葉市テスト1-1-1',
                        'golfCourseDetailUrl' => 'https://example.com/course/1',
                        'golfCourseImageUrl' => 'https://example.com/course/1.jpg',
                    ],
                ],
            ], 200),
        ]);

        $response = $this->get('/search?prefecture=' . urlencode('千葉県'));

        $response->assertStatus(200);
        $response->assertSee('テストゴルフ倶楽部');
    }

    public function test_search_excludes_courses_from_other_prefectures(): void
    {
        Http::fake([
            'openapi.rakuten.co.jp/*' => Http::response([
                'Items' => [
                    ['golfCourseId' => 1, 'golfCourseName' => '千葉県のコース', 'address' => '千葉県千葉市1-1-1'],
                    ['golfCourseId' => 2, 'golfCourseName' => '埼玉県のコース', 'address' => '埼玉県さいたま市1-1-1'],
                ],
            ], 200),
        ]);

        $response = $this->get('/search?prefecture=' . urlencode('千葉県'));

        $response->assertStatus(200);
        $response->assertSee('千葉県のコース');
        $response->assertDontSee('埼玉県のコース');
    }

    public function test_search_shows_empty_message_when_no_courses_found(): void
    {
        Http::fake([
            'openapi.rakuten.co.jp/*' => Http::response(['Items' => []], 200),
        ]);

        $response = $this->get('/search?prefecture=' . urlencode('沖縄県'));

        $response->assertStatus(200);
        $response->assertSee('見つかりませんでした');
    }

    public function test_search_handles_api_failure_gracefully(): void
    {
        Http::fake([
            'openapi.rakuten.co.jp/*' => Http::response(null, 500),
        ]);

        $response = $this->get('/search?prefecture=' . urlencode('京都府'));

        $response->assertStatus(200);
        $response->assertSee('見つかりませんでした');
    }

    public function test_tag_filter_narrows_results_to_matching_courses(): void
    {
        Http::fake([
            'openapi.rakuten.co.jp/*' => Http::response([
                'Items' => [
                    ['golfCourseId' => 1, 'golfCourseName' => '早朝プレー可能なコース', 'address' => '大阪府大阪市1-1-1'],
                    ['golfCourseId' => 2, 'golfCourseName' => 'ふつうのコース', 'address' => '大阪府大阪市2-2-2'],
                ],
            ], 200),
        ]);

        $response = $this->get('/search?prefecture=' . urlencode('大阪府') . '&tag=' . urlencode('早朝プレー'));

        $response->assertStatus(200);
        $response->assertSee('早朝プレー可能なコース');
        $response->assertDontSee('ふつうのコース');
    }
}
