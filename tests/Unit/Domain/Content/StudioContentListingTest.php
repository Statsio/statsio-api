<?php

namespace Tests\Unit\Domain\Content;

use App\Domain\Content\Support\StudioContentBlocks;
use App\Domain\Content\Support\StudioContentListing;
use Tests\TestCase;

class StudioContentListingTest extends TestCase
{
    public function test_extract_format_normalizes_accents(): void
    {
        $this->assertSame('enquete', StudioContentListing::extractFormat(['Économie', 'Enquête']));
        $this->assertSame('decryptage', StudioContentListing::extractFormat(['Décryptage']));
        $this->assertNull(StudioContentListing::extractFormat(['Économie', 'Santé']));
    }

    public function test_reading_minutes_from_text_blocks(): void
    {
        $blocks = [
            ['type' => 'paragraph', 'config' => ['text' => str_repeat('mot ', 180)]],
            ['type' => 'bar', 'config' => []],
        ];

        $this->assertSame(1, StudioContentBlocks::readingMinutes($blocks));
        $this->assertSame(1, StudioContentBlocks::chartCount($blocks));
        $this->assertSame(0, StudioContentBlocks::linkedDatasetCount($blocks));
    }
}
