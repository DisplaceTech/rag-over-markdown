<?php

declare(strict_types=1);

namespace Displace\Rag\Tests\Fake;

use Displace\AI\Contracts\Embedder;

/**
 * Deterministic 4-dim embedder that records every text it embeds —
 * the recorder half proves the query-prefix behavior, the
 * deterministic half feeds the in-memory index.
 *
 * Texts can be registered with a fixed vector; unregistered texts hash
 * to a stable pseudo-vector.
 */
final class RecordingEmbedder implements Embedder
{
    /** @var list<string> */
    public array $embedded = [];

    /** @var array<string, list<float>> */
    private array $fixed = [];

    /**
     * @param list<float> $vector
     */
    public function register(string $text, array $vector): void
    {
        $this->fixed[$text] = $vector;
    }

    public function embed(string $text): string
    {
        $this->embedded[] = $text;

        $vector = $this->fixed[$text] ?? null;

        if ($vector === null) {
            $hash = crc32($text);
            $vector = [
                (($hash >> 0) & 0xFF) / 255.0,
                (($hash >> 8) & 0xFF) / 255.0,
                (($hash >> 16) & 0xFF) / 255.0,
                (($hash >> 24) & 0xFF) / 255.0,
            ];
        }

        return pack('g*', ...$vector);
    }

    public function embedBatch(array $texts): string
    {
        return implode('', array_map($this->embed(...), $texts));
    }

    public function dimensions(): int
    {
        return 4;
    }
}
