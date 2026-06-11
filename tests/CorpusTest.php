<?php

declare(strict_types=1);

namespace Displace\Rag\Tests;

use Displace\Rag\Corpus;
use PHPUnit\Framework\TestCase;

final class CorpusTest extends TestCase
{
    private string $dir;

    protected function setUp(): void
    {
        $this->dir = sys_get_temp_dir() . '/rag-corpus-' . bin2hex(random_bytes(6));
        mkdir($this->dir . '/sub', 0o755, recursive: true);

        file_put_contents($this->dir . '/beta.md', "Beta note body.\n");
        file_put_contents($this->dir . '/alpha.md', "Alpha note body.\n");
        file_put_contents($this->dir . '/sub/gamma.md', "Gamma nested body.\n");
        file_put_contents($this->dir . '/ignored.txt', "Not markdown.\n");
    }

    protected function tearDown(): void
    {
        foreach (['/alpha.md', '/beta.md', '/sub/gamma.md', '/ignored.txt'] as $file) {
            @unlink($this->dir . $file);
        }
        @rmdir($this->dir . '/sub');
        @rmdir($this->dir);
    }

    public function testWalksMarkdownOnlyWithSequentialIdsInSortedOrder(): void
    {
        $chunks = (new Corpus())->chunks($this->dir);

        self::assertSame([0, 1, 2], array_column($chunks, 'id'));
        self::assertSame(['alpha.md', 'beta.md', 'sub/gamma.md'], array_column($chunks, 'file'));
        self::assertStringContainsString('Alpha note body.', $chunks[0]['text']);
    }

    public function testIdsAreStableAcrossRuns(): void
    {
        $corpus = new Corpus();

        self::assertSame($corpus->chunks($this->dir), $corpus->chunks($this->dir));
    }

    public function testLongFilesSplitIntoMultipleChunks(): void
    {
        $paragraphs = implode("\n\n", array_fill(0, 60, str_repeat('Lorem ipsum dolor sit amet. ', 4)));
        file_put_contents($this->dir . '/long.md', $paragraphs);

        try {
            $chunks = (new Corpus())->chunks($this->dir);
            $longChunks = array_values(array_filter($chunks, static fn(array $c): bool => $c['file'] === 'long.md'));

            self::assertGreaterThan(1, \count($longChunks));

            foreach ($longChunks as $chunk) {
                self::assertLessThanOrEqual(1500, mb_strlen($chunk['text']));
            }
        } finally {
            unlink($this->dir . '/long.md');
        }
    }

    public function testMissingDirectoryThrows(): void
    {
        $this->expectException(\RuntimeException::class);

        (new Corpus())->chunks($this->dir . '/no-such-dir');
    }
}
