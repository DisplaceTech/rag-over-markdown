<?php

declare(strict_types=1);

namespace Displace\Rag\Adapter;

use Displace\AI\Contracts\Embedder;
use Displace\Infer\Model;

/**
 * `Displace\AI\Contracts\Embedder` over an ext-infer model handle.
 *
 * Vectors come out normalized (unit length) so the index's inner-product
 * scores read as cosine similarity, and packed (little-endian float32)
 * straight from the Rust side — coordinates never inflate into PHP
 * values between the model and the index.
 */
final class InferEmbedder implements Embedder
{
    private ?int $dimensions = null;

    public function __construct(private readonly Model $model) {}

    public function embed(string $text): string
    {
        $embedding = $this->model->embed($text)->normalize();
        $this->dimensions ??= $embedding->dimensions();

        return $embedding->packed();
    }

    public function embedBatch(array $texts): string
    {
        return implode('', array_map($this->embed(...), $texts));
    }

    public function dimensions(): int
    {
        // Cheapest probe when nothing has been embedded yet: embed a
        // single space and read the width off the packed buffer.
        return $this->dimensions ??= intdiv(\strlen($this->embed(' ')), 4);
    }
}
