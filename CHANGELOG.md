# Changelog

All notable changes to rag-over-markdown are documented here. The format
is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/).
Dates are tag dates.

## [0.1.1] - Unreleased

### Changed

- **Requires ext-infer 0.2.1 or later** (`composer.json`, README). On
  ext-infer 0.2.0, an answer prompt over 2048 tokens aborts the PHP
  process inside llama.cpp: `bin/rag ask --k 8` over a ~30 KB corpus
  reproduces it. 0.2.1 fixes the abort.
- Documented the ext-turbovec 0.2 upgrade: re-run `bin/rag index`, since
  0.1 index files can't be loaded by 0.2. Both versions pass the
  end-to-end tier.
- PIE install lines pin the tested minor versions.
- The end-to-end test honours `RAG_EMBED_MODEL` / `RAG_CHAT_MODEL`, like
  `bin/rag`, so models can live outside the checkout.
- Bundled IDE stubs refreshed from ext-infer 0.2.1 / ext-turbovec 0.2.0.
- CI runs weekly as well as on push; Dependabot keeps the dev toolchain
  and actions current. `.phpunit.cache/` is no longer tracked.

## [0.1.0] - 2026-06-11

First release: chunk (ai-toolkit) → embed (ext-infer, packed) →
`IdMapIndex` (ext-turbovec) → optional rerank → schema-constrained
answer with citations, wired through the ai-contracts adapters;
`bin/rag index|search|ask`; a sample corpus; fake-backed and end-to-end
test tiers.

[0.1.1]: https://github.com/DisplaceTech/rag-over-markdown/compare/v0.1.0...v0.1.1
[0.1.0]: https://github.com/DisplaceTech/rag-over-markdown/releases/tag/v0.1.0
