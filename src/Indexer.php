<?php

declare(strict_types=1);

namespace Displace\Rag;

use Displace\AI\Contracts\Embedder;
use Displace\Rag\Adapter\TurbovecIndex;

/**
 * Embeds a corpus and persists the searchable artifacts: the quantized
 * index (`index.tvim`) plus a JSON sidecar mapping chunk ids back to
 * their file and text.
 *
 * Embed once, search forever: FPM/queue workers `Retriever::open()` the
 * artifacts read-only; re-run the indexer when the corpus changes.
 */
final class Indexer
{
    private const BATCH_SIZE = 32;

    public function __construct(private readonly Embedder $embedder) {}

    /**
     * @param list<array{id: int, file: string, text: string}> $chunks
     * @param callable(int, int): void|null                    $onProgress
     */
    public function build(array $chunks, string $dataDir, ?callable $onProgress = null): void
    {
        if ($chunks === []) {
            throw new \RuntimeException('Nothing to index — no markdown chunks found.');
        }

        if (!is_dir($dataDir) && !mkdir($dataDir, 0o755, true)) {
            throw new \RuntimeException("Cannot create data directory: {$dataDir}");
        }

        $index = TurbovecIndex::create($this->embedder->dimensions());
        $total = \count($chunks);
        $done = 0;

        // Packed batches compose by concatenation: one embedBatch() call
        // and one add() call per BATCH_SIZE chunks.
        foreach (array_chunk($chunks, self::BATCH_SIZE) as $batch) {
            $index->add(
                $this->embedder->embedBatch(array_column($batch, 'text')),
                array_column($batch, 'id'),
            );

            $done += \count($batch);

            if ($onProgress !== null) {
                $onProgress($done, $total);
            }
        }

        $index->save("{$dataDir}/index.tvim");

        $metadata = [
            'dimensions' => $this->embedder->dimensions(),
            'built_at' => date(\DateTimeInterface::ATOM),
            'chunks' => $chunks,
        ];

        if (file_put_contents(
            "{$dataDir}/chunks.json",
            json_encode($metadata, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR),
        ) === false) {
            throw new \RuntimeException("Cannot write {$dataDir}/chunks.json");
        }
    }
}
