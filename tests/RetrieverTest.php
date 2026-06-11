<?php

declare(strict_types=1);

namespace Displace\Rag\Tests;

use Displace\Rag\Retriever;
use Displace\Rag\Tests\Fake\InMemoryIndex;
use Displace\Rag\Tests\Fake\RecordingEmbedder;
use Displace\Rag\Tests\Fake\ScriptedReranker;
use PHPUnit\Framework\TestCase;

/**
 * The Retriever depends only on the ai-contracts interfaces, so every
 * behavior here is provable with in-memory fakes — no extensions, no
 * models, runs in CI. This is the testability the contracts buy.
 */
final class RetrieverTest extends TestCase
{
    private const CHUNKS = [
        0 => ['id' => 0, 'file' => 'a.md', 'text' => 'alpha text'],
        1 => ['id' => 1, 'file' => 'b.md', 'text' => 'beta text'],
        2 => ['id' => 2, 'file' => 'c.md', 'text' => 'gamma text'],
    ];

    private RecordingEmbedder $embedder;
    private InMemoryIndex $index;

    protected function setUp(): void
    {
        $this->embedder = new RecordingEmbedder();
        $this->index = new InMemoryIndex();

        // Three orthogonal document vectors; the query below is aligned
        // with document 1, so similarity ordering is fully determined.
        $this->index->add(
            pack('g*', 1.0, 0.0, 0.0, 0.0)
            . pack('g*', 0.0, 1.0, 0.0, 0.0)
            . pack('g*', 0.0, 0.0, 1.0, 0.0),
            [0, 1, 2],
        );

        $this->embedder->register(
            Retriever::QWEN3_QUERY_PREFIX . 'find beta',
            [0.1, 0.9, 0.2, 0.0],
        );
    }

    public function testQueriesAreEmbeddedWithTheInstructionPrefix(): void
    {
        $retriever = new Retriever($this->embedder, $this->index, self::CHUNKS);

        $retriever->retrieve('find beta');

        self::assertSame([Retriever::QWEN3_QUERY_PREFIX . 'find beta'], $this->embedder->embedded);
    }

    public function testCustomPrefixIsHonored(): void
    {
        $retriever = new Retriever($this->embedder, $this->index, self::CHUNKS, queryPrefix: '');

        $retriever->retrieve('find beta');

        self::assertSame(['find beta'], $this->embedder->embedded);
    }

    public function testHitsAreHydratedWithChunkMetadataBestFirst(): void
    {
        $retriever = new Retriever($this->embedder, $this->index, self::CHUNKS);

        $hits = $retriever->retrieve('find beta', k: 2);

        self::assertCount(2, $hits);
        self::assertSame('b.md', $hits[0]['file']);
        self::assertSame('beta text', $hits[0]['text']);
        self::assertGreaterThan($hits[1]['score'], $hits[0]['score']);
    }

    public function testMissingChunkMetadataIsALoudError(): void
    {
        $retriever = new Retriever($this->embedder, $this->index, chunks: []);   // index ↔ sidecar drift

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessageMatches('/rebuild the index/');

        $retriever->retrieve('find beta');
    }

    public function testRerankerOverFetchesThenTrimsToK(): void
    {
        $reranker = new ScriptedReranker([
            ['index' => 2, 'score' => 0.95],
            ['index' => 0, 'score' => 0.10],
        ]);
        $retriever = new Retriever($this->embedder, $this->index, self::CHUNKS, reranker: $reranker);

        $hits = $retriever->retrieve('find beta', k: 1);

        // The reranker saw the full over-fetched candidate list...
        self::assertCount(3, $reranker->lastDocuments);
        self::assertSame(1, $reranker->lastTopK);
        // ...and was asked with the *raw* query, not the embedding prefix.
        self::assertSame('find beta', $reranker->lastQuery);

        // Rows are re-mapped through the candidate list to chunk metadata,
        // carrying the reranker's calibrated score.
        self::assertCount(1, $hits);
        self::assertSame('beta text', $reranker->lastDocuments[0]); // best embedding hit first
        self::assertSame(0.95, $hits[0]['score']);
    }

    public function testRerankedHitsMapBackToTheRightChunks(): void
    {
        // Reranker flips the embedding order: candidate #2 wins.
        $reranker = new ScriptedReranker([
            ['index' => 1, 'score' => 0.9],
            ['index' => 0, 'score' => 0.4],
        ]);
        $retriever = new Retriever($this->embedder, $this->index, self::CHUNKS, reranker: $reranker);

        $hits = $retriever->retrieve('find beta', k: 2);

        // Candidate order by embedding score was [b.md 0.9, c.md 0.2,
        // a.md 0.1]; the reranker's index 1 therefore maps to c.md.
        self::assertSame(['c.md', 'b.md'], array_column($hits, 'file'));
        self::assertSame([0.9, 0.4], array_column($hits, 'score'));
    }
}
