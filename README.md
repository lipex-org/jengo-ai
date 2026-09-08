# Jengo AI

An enterprise-grade, multi-provider generative AI SDK, autonomous agent engine, and vector search toolkit for CodeIgniter 4 and the Jengo Framework.

Documentation: https://lipex-org.github.io/jengophp.com/packages/ai

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
