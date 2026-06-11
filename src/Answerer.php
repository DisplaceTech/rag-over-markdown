<?php

declare(strict_types=1);

namespace Displace\Rag;

use Displace\Infer\Model;
use Displace\Infer\Prompt;

/**
 * Generation side of the pipeline: stuff the retrieved chunks into a
 * context block and ask the chat model — with the answer constrained to
 * a JSON schema, so "which sources did you actually use" is a structured
 * field instead of a regex over prose.
 */
final class Answerer
{
    public function __construct(private readonly Model $chat) {}

    /**
     * @param list<array{id: int, file: string, text: string, score: float}> $hits
     *
     * @return array{answer: string, confident: bool, sources: list<array{file: string, score: float}>}
     */
    public function answer(string $question, array $hits): array
    {
        if ($hits === []) {
            return ['answer' => 'The index returned no relevant documents.', 'confident' => false, 'sources' => []];
        }

        $context = '';

        foreach ($hits as $i => $hit) {
            $context .= sprintf("--- Document %d (%s) ---\n%s\n\n", $i + 1, $hit['file'], $hit['text']);
        }

        // Prompt details that matter at 0.6B scale: the confidence
        // instruction must be symmetric (describe the true case as well
        // as the false case, or the model takes the lone "false" branch
        // as the default), and citation must be scoped to documents
        // actually used or the model dutifully lists every number it
        // was shown.
        $prompt = Prompt::system(
            'You answer questions using only the provided documents. '
            . 'Answer in one or two complete sentences. '
            . 'In "sources", list ONLY the document numbers you actually drew the answer from — not every document. '
            . 'Set "confident" to true when the documents directly answer the question; '
            . 'set it to false only when they do not.',
        )->withUser("Documents:\n\n{$context}\nQuestion: {$question}");

        // The schema makes the response shape load-bearing: answer text,
        // machine-readable citations, and a self-reported confidence bit.
        // Property order is generation order — `confident` comes last so
        // the model judges *after* writing the answer and citations.
        $response = $this->chat->chat($prompt, maxTokens: 512, nCtx: 8192, options: ['schema' => [
            'type' => 'object',
            'properties' => [
                'answer' => ['type' => 'string'],
                'sources' => ['type' => 'array', 'items' => ['type' => 'integer']],
                'confident' => ['type' => 'boolean'],
            ],
        ]]);

        /** @var array{answer: string, sources: list<int>, confident: bool} $decoded */
        $decoded = json_decode($response->answer(), true, flags: JSON_THROW_ON_ERROR);

        $sources = [];

        foreach (array_unique($decoded['sources']) as $number) {
            $hit = $hits[$number - 1] ?? null;   // model cites 1-based document numbers

            if ($hit !== null) {
                $sources[] = ['file' => $hit['file'], 'score' => $hit['score']];
            }
        }

        return ['answer' => $decoded['answer'], 'confident' => $decoded['confident'], 'sources' => $sources];
    }
}
