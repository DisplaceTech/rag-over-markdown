<?php

declare(strict_types=1);

namespace Displace\Rag\Tests\Fake;

use Displace\AI\Contracts\VectorIndex;

/**
 * Brute-force inner-product index over packed 4-dim vectors. Exact and
 * tiny — a conformance oracle, not a performance statement.
 */
final class InMemoryIndex implements VectorIndex
{
    /** @var array<int, list<float>> */
    private array $vectors = [];

    public function add(string $vectors, array $ids): void
    {
        $perVector = 16; // 4 dims × 4 bytes

        foreach ($ids as $i => $id) {
            $raw = unpack('g*', substr($vectors, $i * $perVector, $perVector));
            assert($raw !== false);
            $this->vectors[$id] = array_values(array_map(floatval(...), $raw));
        }
    }

    public function search(string $query, int $k = 10, ?array $allowlist = null): array
    {
        $raw = unpack('g*', $query);
        assert($raw !== false);
        $q = array_values(array_map(floatval(...), $raw));

        $rows = [];

        foreach ($this->vectors as $id => $vector) {
            if ($allowlist !== null && !\in_array($id, $allowlist, true)) {
                continue;
            }

            $score = 0.0;

            foreach ($vector as $i => $coordinate) {
                $score += $coordinate * $q[$i];
            }

            $rows[] = ['id' => $id, 'score' => $score];
        }

        usort($rows, static fn(array $a, array $b): int => $b['score'] <=> $a['score']);

        return \array_slice($rows, 0, $k);
    }

    public function remove(int $id): void
    {
        unset($this->vectors[$id]);
    }

    public function count(): int
    {
        return \count($this->vectors);
    }
}
