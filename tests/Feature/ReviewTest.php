<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ReviewTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_review_can_be_submitted(): void
    {
        $response = $this->post(route('reviews.store'), [
            'course_id' => 1,
            'course_name' => 'テストゴルフ倶楽部',
            'prefecture' => '千葉県',
            'nickname' => 'テスト太郎',
            'rating' => 5,
            'comment' => 'コース設計がとても良かったです。',
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('reviews', [
            'course_id' => 1,
            'nickname' => 'テスト太郎',
            'rating' => 5,
        ]);
    }

    public function test_review_without_nickname_defaults_to_anonymous(): void
    {
        $this->post(route('reviews.store'), [
            'course_id' => 1,
            'course_name' => 'テストゴルフ倶楽部',
            'prefecture' => '千葉県',
            'rating' => 4,
            'comment' => '良かったです。',
        ]);

        $this->assertDatabaseHas('reviews', ['nickname' => '匿名']);
    }

    public function test_honeypot_field_silently_rejects_the_review(): void
    {
        $this->post(route('reviews.store'), [
            'course_id' => 1,
            'course_name' => 'テストゴルフ倶楽部',
            'prefecture' => '千葉県',
            'rating' => 5,
            'comment' => 'スパムコメントです。',
            'website' => 'https://spam.example.com',
        ]);

        $this->assertDatabaseCount('reviews', 0);
    }

    public function test_ng_word_is_rejected(): void
    {
        $response = $this->post(route('reviews.store'), [
            'course_id' => 1,
            'course_name' => 'テストゴルフ倶楽部',
            'prefecture' => '千葉県',
            'rating' => 1,
            'comment' => 'このコースは死ねばいいのに',
        ]);

        $response->assertSessionHasErrors('comment');
        $this->assertDatabaseCount('reviews', 0);
    }

    public function test_rating_out_of_range_is_rejected(): void
    {
        $response = $this->post(route('reviews.store'), [
            'course_id' => 1,
            'course_name' => 'テストゴルフ倶楽部',
            'prefecture' => '千葉県',
            'rating' => 6,
            'comment' => '評価が範囲外です。',
        ]);

        $response->assertSessionHasErrors('rating');
    }
}
