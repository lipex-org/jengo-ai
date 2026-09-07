# Jengo AI (`jengo/ai`)

[![Latest Version](https://img.shields.io/badge/release-v1.0.0-blue.svg)](https://github.com/jengo-php/ai)
[![PHP Version](https://img.shields.io/badge/php-%5E8.2-777bb4.svg)](https://php.net)
[![CodeIgniter 4](https://img.shields.io/badge/CodeIgniter-4.6%2B-ef4444.svg)](https://codeigniter.com)
[![Tests Passing](https://img.shields.io/badge/tests-100%25%20passing-brightgreen.svg)]()
[![License: MIT](https://img.shields.io/badge/license-MIT-green.svg)](LICENSE)

A unified, multi-provider AI SDK and Agentic Engine for **CodeIgniter 4** and the **Jengo Framework**. Bringing first-class parity with the modern Laravel AI ecosystem (Laravel AI SDK / Prism) to CodeIgniter 4 applications.

---

## 🌟 Key Capabilities

- 🤖 **Multi-Provider Drivers**: Seamlessly switch between **OpenAI**, **Anthropic Claude**, **Google Gemini**, **DeepSeek**, **Groq**, **OpenRouter (300+ models gateway)**, and **Ollama (Local Self-Hosted)**.
- 📐 **Schema-First Structured Outputs**: Enforce strict JSON output schemas and hydrate directly into validated PHP associative arrays or objects (`->schema([...])->asArray()`).
- 🛠️ **Tool Calling & Agentic Execution Loops**: Let LLMs call PHP methods, database queries, and business logic with built-in multi-step autonomous execution loops (`->maxSteps(5)`).
- 🏷️ **PHP 8 `#[AiTool]` Attribute Auto-Discovery**: Automatically convert any PHP service class into LLM tools via attributes.
- ⚡ **Real-Time Token Streaming**: Built-in Server-Sent Events (SSE) response generator for real-time frontend streaming (Inertia.js, Vue, React, HTMX).
- 🧭 **Vector Embeddings & Semantic Search**: Generate embeddings, batch vectors, and compute cosine similarity / top-K ranking (`VectorMath`).
- 📝 **Parameterized Prompt Templates**: Reusable prompt templates with variable interpolation (`PromptTemplate`).
- 🧪 **Zero-Cost Testing Double (`Ai::fake()`)**: Fully featured in-memory mock double with prompt assertions, model assertions, sequence queuing, and tool verification.

---

## 📦 Installation

Install via Composer:

```bash
composer require jengo/ai
```

Publish the configuration file using the Jengo Spark CLI:

```bash
php spark jengo:install ai
```

This publishes `app/Config/Ai.php`.

---

## ⚙️ Configuration

Configure your AI settings and API credentials in your `.env` file using standard CodeIgniter 4 dot notation:

```dotenv
# Default Provider Driver ('openai', 'anthropic', 'gemini', 'deepseek', 'groq', 'openrouter', 'ollama')
ai.default = 'openai'

# OpenAI Configuration
ai.providers.openai.key = 'sk-...'
ai.providers.openai.organization = 'org-...'
ai.providers.openai.model = 'gpt-4o-mini'

# Anthropic Claude Configuration
ai.providers.anthropic.key = 'sk-ant-...'
ai.providers.anthropic.model = 'claude-3-5-sonnet-20241022'

# Google Gemini Configuration
ai.providers.gemini.key = 'AIzaSy...'
ai.providers.gemini.model = 'gemini-2.0-flash'

# DeepSeek Configuration
ai.providers.deepseek.key = 'sk-...'
ai.providers.deepseek.model = 'deepseek-chat'

# Groq Configuration
ai.providers.groq.key = 'gsk_...'
ai.providers.groq.model = 'llama-3.3-70b-versatile'

# OpenRouter (Unified 300+ Model Gateway)
ai.providers.openrouter.key = 'sk-or-...'
ai.providers.openrouter.model = 'meta-llama/llama-3.3-70b-instruct'
ai.providers.openrouter.site_url = 'https://myapp.com'
ai.providers.openrouter.site_name = 'My CI4 App'

# Ollama Local Configuration
ai.providers.ollama.base_url = 'http://localhost:11434'
ai.providers.ollama.model = 'llama3.2'

# Global Generation Defaults
ai.defaults.temperature = 0.7
ai.defaults.max_tokens = 2048
ai.defaults.max_steps = 5
```

---

## 🚀 Quick Start

### 1. Simple 1-Liner Prompt

```php
use Jengo\Ai\Ai;

$summary = Ai::prompt('Summarize this quarterly financial report in 3 bullets: ...')->text();
```

### 2. Multi-Turn Chat Conversation

```php
$response = Ai::chat([
    ['role' => 'system', 'content' => 'You are a senior CodeIgniter 4 architect.'],
    ['role' => 'user', 'content' => 'How do I optimize FrankenPHP worker mode?'],
])
->temperature(0.2)
->maxTokens(1500)
->generate();

echo $response->text();
echo "Tokens used: " . $response->usage->totalTokens;
```

### 3. Switching Providers on the Fly

```php
// Claude 3.5 Sonnet
$audit = Ai::driver('anthropic')
    ->model('claude-3-5-sonnet-20241022')
    ->prompt('Perform a deep security audit on this code...')
    ->text();

// Google Gemini 2.0 Flash
$fastSummary = Ai::driver('gemini')
    ->model('gemini-2.0-flash')
    ->prompt('Extract key topics: ...')
    ->text();

// DeepSeek R1 Reasoning
$math = Ai::driver('deepseek')
    ->model('deepseek-reasoner')
    ->prompt('Solve this optimization proof...')
    ->text();

// Groq Ultra-Fast Llama 3.3
$fast = Ai::driver('groq')
    ->model('llama-3.3-70b-versatile')
    ->prompt('Summarize the text...')
    ->text();

// OpenRouter Gateway (Access 300+ models with 1 key)
$routed = Ai::driver('openrouter')
    ->model('meta-llama/llama-3.3-70b-instruct')
    ->prompt('Analyze market trends: ...')
    ->text();

// Local Ollama (Offline, zero cost)
$tags = Ai::driver('ollama')
    ->model('llama3.2')
    ->prompt('Generate 5 SEO tags for...')
    ->text();
```

---

## 📐 Structured JSON Outputs

Enforce strict JSON schema validation and receive clean PHP associative arrays or objects:

```php
$lead = Ai::prompt('Extract lead info from email: John Doe, VP at Acme Corp, john@acme.com, +1-555-0199')
    ->schema([
        'name'              => 'string',
        'company'           => 'string',
        'email'             => 'email',
        'phone'             => 'string',
        'is_decision_maker' => 'bool',
    ])
    ->asArray();

// Returns typed PHP array:
// [
//     'name' => 'John Doe',
//     'company' => 'Acme Corp',
//     'email' => 'john@acme.com',
//     'phone' => '+1-555-0199',
//     'is_decision_maker' => true
// ]
```

Or decode into a `stdClass` object:

```php
$leadObj = Ai::prompt('...')->schema([...])->asObject();
echo $leadObj->company;
```

---

## 🛠️ Tool Calling & Autonomous Agents

### Fluent Tool Builder

```php
use Jengo\Ai\Ai;
use Jengo\Ai\Support\Tool;
use App\Models\InventoryModel;

$response = Ai::prompt('Check stock for SKU-8842 and calculate total reorder cost for 50 units')
    ->withTools([
        Tool::make('check_stock', 'Check current stock level for an inventory SKU')
            ->parameter('sku', 'string', 'The inventory item SKU code', required: true)
            ->handler(function (string $sku) {
                return InventoryModel::findBySku($sku);
            }),

        Tool::make('calculate_reorder_cost', 'Calculate reorder cost with discounts')
            ->parameter('sku', 'string', 'Item SKU code', required: true)
            ->parameter('quantity', 'integer', 'Quantity to reorder', required: true)
            ->handler(function (string $sku, int $quantity) {
                return InventoryModel::calculateReorder($sku, $quantity);
            }),
    ])
    ->maxSteps(5) // Multi-step autonomous execution loop
    ->generate();

echo $response->text();
```

### Attribute-Based Tool Discovery (`#[AiTool]`)

Decorate methods on any service class with `#[AiTool]` and `#[AiParameter]`:

```php
use Jengo\Ai\Attributes\AiTool;
use Jengo\Ai\Attributes\AiParameter;

class OrderService
{
    #[AiTool('Get current shipping tracking status')]
    public function getTrackingStatus(
        #[AiParameter('Courier tracking number')] string $trackingNumber
    ): array {
        return ['status' => 'Out for delivery', 'eta' => '2:00 PM'];
    }
}

// Automatically registers all #[AiTool] methods from the service:
$ai = Ai::prompt('Where is package TRK-9912?')
    ->withToolsFrom(new OrderService())
    ->generate();
```

---

## ⚡ Real-Time Streaming & Server-Sent Events (SSE)

Stream tokens live to the frontend in a CodeIgniter 4 controller:

```php
namespace App\Controllers;

use CodeIgniter\Controller;
use Jengo\Ai\Ai;

class ChatController extends Controller
{
    public function stream()
    {
        $prompt = $this->request->getPost('message');

        return Ai::chat($prompt)
            ->stream()
            ->toSseResponse(); // Returns Response with text/event-stream headers
    }
}
```

Or consume tokens programmatically in PHP:

```php
Ai::prompt('Write an essay on AI')->stream(function (string $chunk, string $accumulated) {
    echo $chunk;
    flush();
});
```

---

## 🧭 Vector Embeddings & Semantic Search (RAG)

```php
use Jengo\Ai\Ai;
use Jengo\Ai\Support\VectorMath;

// Generate 1536-dimensional vector
$vectorA = Ai::embed('High-performance PDF generation with CodeIgniter 4');
$vectorB = Ai::embed('Document templating with Dompdf');

// Compute cosine similarity (0.0 to 1.0)
$similarity = Ai::similarity($vectorA, $vectorB);

// Batch embeddings
$vectors = Ai::embedMany([
    'First article text...',
    'Second article text...',
]);

// Top-K vector semantic search
$candidates = [
    'doc_1' => $vectorA,
    'doc_2' => $vectorB,
];
$topResults = VectorMath::topK($queryVector, $candidates, k: 5);
```

---

## 📝 Parameterized Prompt Templates

```php
use Jengo\Ai\Support\PromptTemplate;
use Jengo\Ai\Ai;

$template = PromptTemplate::make('Translate the following text into {target_lang}: "{text}"');

$prompt = $template->render([
    'target_lang' => 'Swahili',
    'text'        => 'Welcome to the Jengo Framework',
]);

$response = Ai::prompt($prompt)->text();
```

---

## 🧪 Testing with `Ai::fake()`

Test your AI features with zero token costs and zero network latency:

```php
use Jengo\Ai\Ai;
use Tests\TestCase;

class SupportBotTest extends TestCase
{
    public function testChatBot(): void
    {
        // 1. Activate fake AI double with queued response
        $ai = Ai::fake([
            'Hello! How can I assist you today?',
        ]);

        // 2. Perform controller action
        $this->post('/api/support/chat', ['message' => 'Hi']);

        // 3. Assertions
        $ai->assertPromptSent('Hi');
        $ai->assertDriver('openai');
        $ai->assertCount(1);
    }

    public function testAgenticToolCalling(): void
    {
        $ai = Ai::fakeSequence()
            ->pushToolCall('check_stock', ['sku' => 'SKU-001'])
            ->push('We have 25 units in stock.');

        $this->post('/api/inventory/ai-query', ['query' => 'Do we have SKU-001?']);

        $ai->assertToolCalled('check_stock', fn($args) => $args['sku'] === 'SKU-001');
    }
}
```

### Optional Test Case Trait

```php
use Jengo\Ai\Testing\Concerns\AiTestAssertionsTrait;
use Tests\TestCase;

class LeadTest extends TestCase
{
    use AiTestAssertionsTrait;

    public function testStructuredLeadExtraction(): void
    {
        $this->aiFake(['{"name":"Alice","company":"Acme"}']);

        $this->post('/api/leads/parse', ['text' => '...']);

        $this->assertAiPromptSent('lead');
        $this->assertAiCount(1);
    }
}
```

---

## 🌐 Global Helper

The `ai()` helper is autoloaded globally:

```php
// Quick prompt
$text = ai('Write a haiku about code')->text();

// Access AiClient coordinator
$driver = ai()->driver('anthropic');
```

---

## 📄 License

The `jengo/ai` package is open-sourced software licensed under the [MIT license](LICENSE).
