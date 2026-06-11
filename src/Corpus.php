<?php

declare(strict_types=1);

namespace Displace\Rag;

use Displace\AI\Toolkit\Text\Chunker;
use Displace\AI\Toolkit\Text\RecursiveCharacterChunker;

/**
 * Walks a directory of markdown files and chunks them for embedding.
 *
 * Chunk ids are sequential integers in walk order — they double as the
 * stable ids inside the vector index, and the metadata sidecar maps them
 * back to (file, text) at query time.
 */
final class Corpus
{
    private readonly Chunker $chunker;

    public function __construct(?Chunker $chunker = null)
    {
        // ~1500 chars ≈ 375 tokens per chunk: small enough for precise
        // retrieval, big enough that a chunk answers a question on its own.
        $this->chunker = $chunker ?? new RecursiveCharacterChunker(size: 1500, overlap: 200);
    }

    /**
     * @return list<array{id: int, file: string, text: string}>
     */
    public function chunks(string $directory): array
    {
        $root = rtrim($directory, '/');

        if (!is_dir($root)) {
            throw new \RuntimeException("Not a directory: {$directory}");
        }

        $files = [];
        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($root, \FilesystemIterator::SKIP_DOTS),
        );

        foreach ($iterator as $file) {
            if ($file instanceof \SplFileInfo && strtolower($file->getExtension()) === 'md') {
                $files[] = $file->getPathname();
            }
        }

        sort($files); // deterministic ids across runs

        $chunks = [];
        $id = 0;

        foreach ($files as $path) {
            $body = file_get_contents($path);

            if ($body === false) {
                throw new \RuntimeException("Unreadable file: {$path}");
            }

            $relative = ltrim(substr($path, \strlen($root)), '/');

            foreach ($this->chunker->chunk($body) as $text) {
                $chunks[] = ['id' => $id++, 'file' => $relative, 'text' => $text];
            }
        }

        return $chunks;
    }
}
