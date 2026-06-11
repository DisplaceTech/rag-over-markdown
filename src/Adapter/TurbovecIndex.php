<?php

declare(strict_types=1);

namespace Displace\Rag\Adapter;

use Displace\AI\Contracts\VectorIndex;
use Displace\Vector\IdMapIndex;

/**
 * `Displace\AI\Contracts\VectorIndex` over an ext-turbovec `IdMapIndex`.
 *
 * The contract methods are pass-throughs — the shapes were designed to
 * line up. Persistence (`save()` / `open()`) sits outside the contract
 * because durability is an implementation concern, not an interface one.
 */
final class TurbovecIndex implements VectorIndex
{
    public function __construct(private readonly IdMapIndex $index) {}

    public static function create(int $dimensions): self
    {
        return new self(new IdMapIndex(dim: $dimensions, bitWidth: 4));
    }

    public static function open(string $path): self
    {
        return new self(IdMapIndex::load($path));
    }

    public function save(string $path): void
    {
        $this->index->write($path);
    }

    public function add(string $vectors, array $ids): void
    {
        $this->index->addWithIds($vectors, $ids);
    }

    public function search(string $query, int $k = 10, ?array $allowlist = null): array
    {
        return array_values(iterator_to_array($this->index->search($query, $k, $allowlist)));
    }

    public function remove(int $id): void
    {
        $this->index->remove($id);
    }

    public function count(): int
    {
        return max(0, $this->index->count());
    }
}
