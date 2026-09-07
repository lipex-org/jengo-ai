<?php

declare(strict_types=1);

namespace Jengo\Ai;

use Closure;
use Jengo\Ai\Config\Ai as AiConfig;
use Jengo\Ai\Contracts\DriverInterface;
use Jengo\Ai\Contracts\MessageInterface;
use Jengo\Ai\Drivers\AnthropicDriver;
use Jengo\Ai\Drivers\DeepSeekDriver;
use Jengo\Ai\Drivers\FakeDriver;
use Jengo\Ai\Drivers\GeminiDriver;
use Jengo\Ai\Drivers\GroqDriver;
use Jengo\Ai\Drivers\OllamaDriver;
use Jengo\Ai\Drivers\OpenAiDriver;
use Jengo\Ai\Drivers\OpenRouterDriver;
use Jengo\Ai\Exceptions\DriverException;
use Jengo\Ai\Support\VectorMath;
use Jengo\Ai\Testing\AiFake;

class AiClient
{
    protected AiConfig $config;

    /** @var array<string, DriverInterface> */
    protected array $drivers = [];

    /** @var array<string, Closure> */
    protected array $customCreators = [];

    protected ?AiFake $fake = null;

    public function __construct(?AiConfig $config = null)
    {
        $this->config = $config ?? config('Ai') ?? new AiConfig();
    }

    /**
     * Resolve a driver instance by name, or default driver if null.
     * If fake is active, returns FakeDriver.
     */
    public function driver(?string $name = null): DriverInterface
    {
        if ($this->fake !== null) {
            return $this->fake->createDriver();
        }

        $name = $name ?? $this->getDefaultDriver();

        if (isset($this->drivers[$name])) {
            return $this->drivers[$name];
        }

        $this->drivers[$name] = $this->createDriver($name);
        return $this->drivers[$name];
    }

    /**
     * Get default driver name from config.
     */
    public function getDefaultDriver(): string
    {
        return $this->config->default ?? 'openai';
    }

    /**
     * Set default driver name.
     */
    public function setDefaultDriver(string $name): self
    {
        $this->config->default = $name;
        return $this;
    }

    /**
     * Register a custom driver creator callback.
     */
    public function extend(string $name, Closure $callback): self
    {
        $this->customCreators[$name] = $callback;
        return $this;
    }

    /**
     * Initiate a single prompt request.
     */
    public function prompt(string $prompt): AiRequest
    {
        return (new AiRequest($this))->prompt($prompt);
    }

    /**
     * Initiate a multi-turn chat request.
     *
     * @param array<int|string, mixed>|MessageInterface $messages
     */
    public function chat(array|MessageInterface $messages): AiRequest
    {
        return (new AiRequest($this))->chat($messages);
    }

    /**
     * Generate vector embedding for a single text.
     *
     * @return array<int, float>
     */
    public function embed(string $text, ?string $model = null, ?string $driver = null): array
    {
        $response = $this->driver($driver)->embed([$text], $model);
        return $response->first() ?? [];
    }

    /**
     * Generate vector embeddings for multiple texts.
     *
     * @param array<int, string> $texts
     * @return array<int, array<int, float>>
     */
    public function embedMany(array $texts, ?string $model = null, ?string $driver = null): array
    {
        $response = $this->driver($driver)->embed($texts, $model);
        return $response->all();
    }

    /**
     * Calculate cosine similarity between two vector embeddings.
     *
     * @param array<int, float> $a
     * @param array<int, float> $b
     */
    public function similarity(array $a, array $b): float
    {
        return VectorMath::cosineSimilarity($a, $b);
    }

    /**
     * Swap the active driver with an in-memory testing double (AiFake).
     *
     * @param array<int|string, mixed>|string|null $responses
     */
    public function fake(mixed $responses = null): AiFake
    {
        $this->fake = new AiFake($responses);
        return $this->fake;
    }

    /**
     * Activate testing fake with sequence builder.
     */
    public function fakeSequence(): AiFake
    {
        $this->fake = new AiFake();
        return $this->fake;
    }

    public function isFake(): bool
    {
        return $this->fake !== null;
    }

    public function getFake(): ?AiFake
    {
        return $this->fake;
    }

    public function resetFake(): self
    {
        $this->fake = null;
        return $this;
    }

    /**
     * Instantiate driver instance.
     */
    protected function createDriver(string $name): DriverInterface
    {
        if (isset($this->customCreators[$name])) {
            return ($this->customCreators[$name])($this->config);
        }

        $providerConfig = $this->config->providers[$name] ?? [];

        return match ($name) {
            'openai'    => new OpenAiDriver($providerConfig),
            'anthropic' => new AnthropicDriver($providerConfig),
            'gemini'    => new GeminiDriver($providerConfig),
            'deepseek'   => new DeepSeekDriver($providerConfig),
            'groq'       => new GroqDriver($providerConfig),
            'openrouter' => new OpenRouterDriver($providerConfig),
            'ollama'     => new OllamaDriver($providerConfig),
            'fake'      => new FakeDriver($this->fake ?? new AiFake()),
            default     => throw DriverException::unsupported("Unsupported AI driver: [{$name}]."),
        };
    }
}
