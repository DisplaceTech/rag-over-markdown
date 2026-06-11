<?php

declare(strict_types=1);

namespace Displace\Rag\Tests\Fake;

use Displace\AI\Contracts\Reranker;

/**
 * Returns a pre-scripted ranking and records what it was asked —
 * enough to prove the Retriever's over-fetch and re-mapping behavior.
 */
final class ScriptedReranker implements Reranker
{
    public ?string $lastQuery = null;

    /** @var list<string> */
    public array $lastDocuments = [];

    public ?int $lastTopK = null;

    /**
     * @param list<array{index: int, score: float}> $rows
     */
    public function __construct(private readonly array $rows) {}

    public function rerank(string $query, array $documents, ?int $topK = null): array
    {
        $this->lastQuery = $query;
        $this->lastDocuments = $documents;
        $this->lastTopK = $topK;

        return $topK === null ? $this->rows : \array_slice($this->rows, 0, $topK);
    }
}
