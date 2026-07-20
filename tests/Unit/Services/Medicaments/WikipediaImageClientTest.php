<?php

namespace Tests\Unit\Services\Medicaments;

use App\Services\Medicaments\WikipediaImageClient;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class WikipediaImageClientTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Cache::flush();
    }

    public function test_returns_thumbnail_url_when_page_has_one(): void
    {
        Http::fake([
            'fr.wikipedia.org/*' => Http::response([
                'thumbnail' => ['source' => 'https://upload.wikimedia.org/doliprane.jpg'],
            ]),
        ]);

        $url = (new WikipediaImageClient())->getThumbnailUrl('Doliprane');

        $this->assertSame('https://upload.wikimedia.org/doliprane.jpg', $url);
    }

    public function test_returns_null_when_page_not_found(): void
    {
        Http::fake(['fr.wikipedia.org/*' => Http::response([], 404)]);

        $this->assertNull((new WikipediaImageClient())->getThumbnailUrl('MedicamentInconnuXyz'));
    }

    public function test_returns_null_when_page_has_no_thumbnail(): void
    {
        Http::fake(['fr.wikipedia.org/*' => Http::response(['title' => 'Sans image'])]);

        $this->assertNull((new WikipediaImageClient())->getThumbnailUrl('Sans image'));
    }

    /**
     * Les noms BDPM sont toujours en MAJUSCULES ("DOLIPRANE") — MediaWiki ne capitalise que la
     * 1ère lettre d'un titre, donc "DOLIPRANE" tel quel ne matche jamais l'article réel
     * "Doliprane" et 404 systématiquement sans cette normalisation.
     */
    public function test_normalizes_all_caps_bdpm_name_to_title_case_before_querying(): void
    {
        Http::fake([
            'fr.wikipedia.org/*' => Http::response(['thumbnail' => ['source' => 'https://x/doliprane.jpg']]),
        ]);

        $url = (new WikipediaImageClient())->getThumbnailUrl('DOLIPRANE');

        $this->assertSame('https://x/doliprane.jpg', $url);
        Http::assertSent(fn ($request) => str_contains((string) $request->url(), 'Doliprane'));
    }
}
