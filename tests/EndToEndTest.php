<?php

declare(strict_types=1);

namespace Displace\Rag\Tests;

use Displace\Infer\Model;
use Displace\Rag\Adapter\InferEmbedder;
use Displace\Rag\Adapter\TurbovecIndex;
use Displace\Rag\Answerer;
use Displace\Rag\Corpus;
use Displace\Rag\Indexer;
use Displace\Rag\Retriever;
use PHPUnit\Framework\TestCase;

/**
 * Full-pipeline smoke test against the real extensions and models.
 * Skips cleanly when either is absent (CI runs without them, same
 * policy as the extensions' own suites); locally:
 *
 *     composer test   # with models/ populated per the README
 */
final class EndToEndTest extends TestCase
{
    private const EMBED_MODEL = __DIR__ . '/../models/Qwen3-Embedding-0.6B-Q8_0.gguf';
    private const CHAT_MODEL = __DIR__ . '/../models/Qwen3-0.6B-Q8_0.gguf';

    private string $dataDir;

    protected function setUp(): void
    {
        if (!\extension_loaded('infer') || !\extension_loaded('turbovec')) {
            self::markTestSkipped('requires the infer + turbovec extensions');
        }

        if (!is_file(self::EMBED_MODEL) || !is_file(self::CHAT_MODEL)) {
            self::markTestSkipped('requires the GGUF models from the README under models/');
        }

        $this->dataDir = sys_get_temp_dir() . '/rag-e2e-' . bin2hex(random_bytes(6));
    }

    protected function tearDown(): void
    {
        @unlink($this->dataDir . '/index.tvim');
        @unlink($this->dataDir . '/chunks.json');
        @rmdir($this->dataDir);
    }

    public function testIndexSearchAskOverTheSampleCorpus(): void
    {
        $embedder = new InferEmbedder(Model::load(self::EMBED_MODEL, [
            'embedding' => true,
            'pooling' => 'last',
        ]));

        // Index the shipped sample corpus into a temp dir.
        $chunks = (new Corpus())->chunks(__DIR__ . '/../corpus');
        self::assertNotEmpty($chunks);

        (new Indexer($embedder))->build($chunks, $this->dataDir);
        self::assertFileExists($this->dataDir . '/index.tvim');
        self::assertFileExists($this->dataDir . '/chunks.json');

        // Retrieval: the lockout question must surface the runbook first.
        $retriever = new Retriever(
            $embedder,
            TurbovecIndex::open($this->dataDir . '/index.tvim'),
            array_column($chunks, null, 'id'),
        );

        $hits = $retriever->retrieve('how long does an account lockout last?', k: 2);
        self::assertSame('password-reset-runbook.md', $hits[0]['file']);

        // Generation: schema-constrained answer cites the runbook and
        // contains the fact ("one hour").
        $chat = Model::load(self::CHAT_MODEL);
        $result = (new Answerer($chat))->answer('How long does an account lockout last?', $hits);

        self::assertStringContainsStringIgnoringCase('one hour', $result['answer']);
        self::assertContains(
            'password-reset-runbook.md',
            array_column($result['sources'], 'file'),
        );

        $chat->close();
    }
}
