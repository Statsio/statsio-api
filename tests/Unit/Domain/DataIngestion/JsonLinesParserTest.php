<?php

namespace Tests\Unit\Domain\DataIngestion;

use App\Domain\DataIngestion\Exceptions\FileParsingException;
use App\Services\DataIngestion\Parsers\JsonLinesParser;
use Tests\TestCase;

class JsonLinesParserTest extends TestCase
{
    private function writeJsonl(array $records): string
    {
        $path = tempnam(sys_get_temp_dir(), 'jsonl_test_').'.jsonl';
        $handle = fopen($path, 'w');
        foreach ($records as $record) {
            fwrite($handle, json_encode($record)."\n");
        }
        fclose($handle);

        return $path;
    }

    public function test_parse_infers_union_of_headers_and_row_count(): void
    {
        $path = $this->writeJsonl([
            ['id' => 1, 'name' => 'Alice'],
            ['id' => 2, 'name' => 'Bob', 'email' => 'bob@example.com'],
        ]);

        $parsed = (new JsonLinesParser())->parse($path, 500_000);

        $this->assertSame(['id', 'name', 'email'], $parsed->headers);
        $this->assertSame(2, $parsed->rowCount);

        unlink($path);
    }

    public function test_sample_does_not_exhaust_rows_for_a_later_full_iteration(): void
    {
        $records = array_map(fn (int $i) => ['id' => $i], range(1, 10));
        $path = $this->writeJsonl($records);

        $parsed = (new JsonLinesParser())->parse($path, 500_000);

        $sample = $parsed->sample(3);
        $this->assertCount(3, $sample);
        $this->assertSame(['1', '2', '3'], array_column($sample, 'id'));

        $full = iterator_to_array($parsed->rows);
        $this->assertCount(10, $full);
        $this->assertSame(
            array_map('strval', range(1, 10)),
            array_column($full, 'id'),
        );

        unlink($path);
    }

    public function test_parse_coerces_values_like_json_parser(): void
    {
        $path = $this->writeJsonl([
            ['active' => true, 'active2' => false, 'tags' => ['a', 'b'], 'note' => null, 'count' => 5],
        ]);

        $parsed = (new JsonLinesParser())->parse($path, 500_000);
        $row = iterator_to_array($parsed->rows)[0];

        $this->assertSame('true', $row['active']);
        $this->assertSame('false', $row['active2']);
        $this->assertSame('["a","b"]', $row['tags']);
        $this->assertNull($row['note']);
        $this->assertSame('5', $row['count']);

        unlink($path);
    }

    public function test_parse_respects_max_rows(): void
    {
        $records = array_map(fn (int $i) => ['id' => $i], range(1, 5));
        $path = $this->writeJsonl($records);

        $parsed = (new JsonLinesParser())->parse($path, 2);

        $this->assertSame(2, $parsed->rowCount);
        $this->assertCount(2, iterator_to_array($parsed->rows));

        unlink($path);
    }

    public function test_parse_throws_on_empty_file(): void
    {
        $path = $this->writeJsonl([]);

        $this->expectException(FileParsingException::class);

        (new JsonLinesParser())->parse($path, 500_000);

        unlink($path);
    }

    public function test_parse_throws_on_invalid_line(): void
    {
        $path = tempnam(sys_get_temp_dir(), 'jsonl_test_').'.jsonl';
        file_put_contents($path, "{\"id\":1}\nnot json\n");

        $this->expectException(FileParsingException::class);

        (new JsonLinesParser())->parse($path, 500_000);

        unlink($path);
    }
}
