<?php

declare(strict_types=1);

namespace Displace\Rag;

use Displace\AI\Contracts\Embedder;
use Displace\AI\Contracts\Reranker;
use Displace\AI\Contracts\VectorIndex;

/**
 * Query side of the pipeline: embed the question, search the index,
 * optionally rerank, and hydrate hits back into (file, text) chunks.
 *
 * Qwen3-Embedding detail that matters: *queries* are embedded with the
 * model's trained instruction prefix; *documents* are not. Skipping the
 * prefix silently degrades retrieval — the asymmetry is part of how the
 * model was trained.
 */
final class Retriever
{
    public const QWEN3_QUERY_PREFIX =
        "Instruct: Given a web search query, retrieve relevant passages that answer the query\nQuery: ";

    /**
     * @param list<array{id: int, file: string, text: string}> $chunks
     */
    public function __construct(
        private readonly Embedder $embedder,
        private readonly VectorIndex $index,
        private readonly array $chunks,
        private readonly string $queryPrefix = self::QWEN3_QUERY_PREFIX,
        private readonly ?Reranker $reranker = null,
    ) {}

    /**
     * @return list<array{id: int, file: string, text: string, score: float}>
     */
    public function retrieve(string $query, int $k = 4): array
    {
        // Over-fetch when a reranker will trim the list back down:
        // recall from the index, precision from the reranker.
        $fetch = $this->reranker !== null ? max(4 * $k, 20) : $k;

        $rows = $this->index->search($this->embedder->embed($this->queryPrefix . $query), $fetch);

        $hits = [];

        foreach ($rows as $row) {
            $chunk = $this->chunks[$row['id']] ?? null;

            if ($chunk === null) {
                throw new \RuntimeException(
                    "Index returned id {$row['id']} with no chunk metadata — rebuild the index.",
                );
            }

            $hits[] = [...$chunk, 'score' => $row['score']];
        }

        if ($this->reranker === null) {
            return $hits;
        }

        $reranked = $this->reranker->rerank($query, array_column($hits, 'text'), $k);

        return array_map(
            static fn(array $row): array => [...$hits[$row['index']], 'score' => $row['score']],
            $reranked,
        );
    }
}
