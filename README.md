<h1 align="center">rag-over-markdown</h1>

<p align="center">
  <strong>Retrieval-augmented generation over a folder of markdown — entirely inside the PHP process.</strong><br>
  No vector database, no Python sidecar, no API key, no data leaving the box.
</p>

<p align="center">
  <a href="https://github.com/DisplaceTech/rag-over-markdown/actions/workflows/ci.yml"><img src="https://github.com/DisplaceTech/rag-over-markdown/actions/workflows/ci.yml/badge.svg" alt="CI" /></a>
  <img src="https://img.shields.io/badge/PHP-8.3%20%7C%208.4%20%7C%208.5-777BB4?logo=php&logoColor=white" alt="PHP 8.3 / 8.4 / 8.5" />
  <a href="LICENSE"><img src="https://img.shields.io/badge/License-MIT-green" alt="MIT License" /></a>
</p>

---

## What is this?

The reference implementation for the Displace local-first AI stack —
about 400 lines of PHP that index a folder of markdown and answer
questions about it with citations:

```text
$ bin/rag ask "What did we decide about the Q3 schema migration, and why?"

We decided to defer the user-table schema change to Q3 in favor of
shipping the redirect layer first.

Sources:
  0.549  deploy-notes.md
  0.403  release-checklist.md
```

Every stage runs in-process on CPU:

| Stage | Component | What it does |
|---|---|---|
| Chunk | [`displace/ai-toolkit`](https://github.com/DisplaceTech/ai-toolkit) | `RecursiveCharacterChunker` splits on document structure |
| Embed | [`ext-infer`](https://github.com/DisplaceTech/ext-infer) | Qwen3-Embedding-0.6B → packed float32 via `Embedding::packed()` |
| Index/search | [`ext-turbovec`](https://github.com/DisplaceTech/ext-turbovec) | quantized `IdMapIndex`, persisted with `write()`/`load()` |
| Rerank (optional) | ext-infer `RerankModel` | Qwen3-Reranker calibrated relevance over the candidates |
| Answer | ext-infer `Model::chat()` | grammar-constrained JSON: answer + confidence + citations |

Application code touches none of those classes directly — it talks to
the [`displace/ai-contracts`](https://github.com/DisplaceTech/ai-contracts)
interfaces through the thin adapters in
[`src/Adapter/`](src/Adapter/). Swap any stage without touching the
pipeline.

## Quick start

**1. Install the extensions** (prebuilt binaries via
[PIE](https://github.com/php/pie)):

```sh
php pie.phar install displace/ext-infer
php pie.phar install displace/ext-turbovec
```

**2. Install and grab the models** (~1.3GB for the required two):

```sh
composer install

mkdir -p models
curl -L -o models/Qwen3-Embedding-0.6B-Q8_0.gguf \
  https://huggingface.co/Qwen/Qwen3-Embedding-0.6B-GGUF/resolve/main/Qwen3-Embedding-0.6B-Q8_0.gguf
curl -L -o models/Qwen3-0.6B-Q8_0.gguf \
  https://huggingface.co/Qwen/Qwen3-0.6B-GGUF/resolve/main/Qwen3-0.6B-Q8_0.gguf

# optional, for --rerank:
curl -L -o models/Qwen3-Reranker-0.6B-Q8_0.gguf \
  https://huggingface.co/ggml-org/Qwen3-Reranker-0.6B-Q8_0-GGUF/resolve/main/qwen3-reranker-0.6b-q8_0.gguf
```

**3. Index and ask** (a sample corpus ships in [`corpus/`](corpus/)):

```sh
bin/rag index                       # corpus/ → data/index.tvim + chunks.json
bin/rag search "account lockout"    # top-k chunks with scores
bin/rag ask "How long does a lockout last?"
bin/rag ask "Which alert fires when FPM saturates?" --rerank
```

Point it at your own notes with `bin/rag index --corpus ~/notes`.

## How it works

**Indexing** walks the corpus, chunks each file (~1500 chars, structure
first), embeds chunks in batches, and persists two artifacts: the
quantized vector index (`data/index.tvim`) and a JSON sidecar mapping
chunk ids back to file + text. Embed once; queries reload read-only —
the same build-offline/serve-read-only shape ext-turbovec documents for
FPM.

**Asking** embeds the question (with Qwen3-Embedding's trained query
instruction prefix — documents don't get it; the asymmetry is part of
the model), searches the index, optionally reranks the candidates, and
hands the winners to the chat model. The answer is **grammar-
constrained** to a JSON schema — `{answer, confident, sources}` — so
citations are a machine-readable field and `json_decode` cannot fail.
When the corpus doesn't cover the question, `confident: false` comes
back instead of a hallucination.

Worth reading in order: [`Corpus`](src/Corpus.php) →
[`Indexer`](src/Indexer.php) → [`Retriever`](src/Retriever.php) →
[`Answerer`](src/Answerer.php), then the
[adapters](src/Adapter/) that bind the contracts to the extensions.

## Notes on models

- The 0.6B models are chosen so the whole demo runs on any 4GB box.
  Answers get noticeably better with a larger chat model
  (`RAG_CHAT_MODEL=models/Qwen3-4B-Instruct-2507-Q4_K_M.gguf`); the
  retrieval side is already strong at 0.6B.
- Qwen3-Embedding **requires last-token pooling** (the code sets it)
  and Q8_0/F16 quantization — quantizing embeddings below Q8 distorts
  the similarity geometry.
- Model paths are overridable via `RAG_EMBED_MODEL`, `RAG_CHAT_MODEL`,
  and `RAG_RERANK_MODEL`.

## Testing

```sh
composer test
```

The pipeline logic is tested **without models or extensions**: because
`Retriever` and `Indexer` depend on the
[ai-contracts](https://github.com/DisplaceTech/ai-contracts) interfaces,
their behavior (query-prefix handling, hit hydration, rerank over-fetch
and re-mapping, index↔sidecar drift detection) is proven against tiny
in-memory fakes in [`tests/Fake/`](tests/Fake/) — that's the
testability the contracts buy, demonstrated. A full end-to-end test
(index → search → ask over the sample corpus) runs automatically when
the extensions are loaded and `models/` is populated, and skips cleanly
otherwise; CI runs the fake-backed tier only.

## Deliberately out of scope

**Document formats beyond markdown** — PDF/HTML extraction is your
loader's job · **incremental re-indexing** — the corpus embeds in
seconds; rebuild is the feature · **a web UI** — this is plumbing,
shaped for reading · **multi-tenancy / auth** — see ext-turbovec's
allowlist filtering for where that hook goes · **streaming answers** —
when ext-infer ships streaming (v0.3+), this repo gets it for free.

## License

[MIT](LICENSE) &copy; 2026 Eric Mann / Displace Technologies
