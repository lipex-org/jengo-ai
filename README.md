<p align="center">
  <a href="https://lipex-org.github.io/jengophp.com/">
    <img src="https://raw.githubusercontent.com/lipex-org/docs/main/public/logo-full.png" width="220" alt="Jengo Logo">
  </a>
</p>

<h1 align="center">Jengo AI</h1>

<p align="center">
  <strong>An enterprise-grade, multi-provider generative AI SDK, autonomous agent engine, and vector search toolkit for CodeIgniter 4 and the Jengo Framework.</strong>
</p>

<p align="center">
  <a href="https://lipex-org.github.io/jengophp.com/packages/ai"><strong>Documentation</strong></a> •
  <a href="https://github.com/lipex-org/ai/blob/main/LICENSE"><strong>License</strong></a> •
  <a href="https://github.com/lipex-org/ai/issues"><strong>Issues</strong></a>
</p>

---

## Installation

```bash
composer require jengo/ai
php spark jengo:install ai
```

## Quick Start

```php
use Jengo\Ai\Ai;

// Simple prompt via default provider
$answer = ai('Explain quantum computing in one sentence.')->text();

// Explicit provider & model selection
$summary = ai('Summarize this document...')
    ->driver('openrouter')
    ->model('anthropic/claude-3.5-sonnet')
    ->text();

// Structured JSON output
$data = ai('Extract user: Alice, 29, designer from Nairobi.')
    ->schema(['name' => 'string', 'age' => 'int', 'city' => 'string'])
    ->asArray();
```

## Documentation

For comprehensive guides on multi-provider drivers, tool calling, PHP 8 attributes, Server-Sent Events (SSE) streaming, embeddings, and testing doubles, visit https://lipex-org.github.io/jengophp.com/packages/ai.

## License

Released under the MIT License.
