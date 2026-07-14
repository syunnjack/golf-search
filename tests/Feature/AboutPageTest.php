<?php

namespace Tests\Feature;

use Tests\TestCase;

class AboutPageTest extends TestCase
{
    public function test_about_page_is_reachable(): void
    {
        $response = $this->get(route('about'));

        $response->assertStatus(200);
        $response->assertSee('このサイトについて');
        $response->assertSee('楽天ウェブサービス');
    }

    public function test_about_link_is_present_in_footer(): void
    {
        $response = $this->get('/');

        $response->assertSee(route('about'), false);
    }
}
