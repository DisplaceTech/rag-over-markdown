<?php

declare(strict_types=1);

namespace Displace\Rag\Adapter;

use Displace\AI\Contracts\Reranker;
use Displace\Infer\RerankModel;

/**
 * `Displace\AI\Contracts\Reranker` over ext-infer's `RerankModel`.
 *
 * A pure pass-through: `RerankModel::rank()` already returns best-first
 * `['index' => int, 'score' => float]` rows — the contract shape was
 * designed against it.
 */
final class InferReranker implements Reranker
{
    public function __construct(private readonly RerankModel $model) {}

    public function rerank(string $query, array $documents, ?int $topK = null): array
    {
        return $this->model->rank($query, $documents, $topK);
    }
}
