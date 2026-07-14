<?php

namespace Tests\Unit;

use App\Support\GolfTagger;
use PHPUnit\Framework\TestCase;

class GolfTaggerTest extends TestCase
{
    public function test_extracts_tags_from_course_name_and_abbr(): void
    {
        $tags = GolfTagger::extract('早朝プレー可能な林間コース', 'ナイター営業');

        $this->assertContains('早朝プレー', $tags);
        $this->assertContains('林間コース', $tags);
        $this->assertContains('ナイター', $tags);
    }

    public function test_returns_empty_array_when_no_keywords_match(): void
    {
        $tags = GolfTagger::extract('ふつうのコース', '');

        $this->assertSame([], $tags);
    }
}
