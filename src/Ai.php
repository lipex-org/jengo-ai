<?php

declare(strict_types=1);

namespace Jengo\Ai;

use Jengo\Ai\Config\Services;
use Jengo\Ai\Contracts\MessageInterface;
use Jengo\Ai\Testing\AiFake;

/**
 * @method static \Jengo\Ai\Contracts\DriverInterface getDriver(?string $name = null)
 */
class Ai
{
    /**
     * Start a new prompt request.
     */
    public static function prompt(string $prompt): AiRequest
    {
        return static::getClient()->prompt($prompt);
    }

    /**
     * Start a new multi-turn chat request.
     *
     * @param array<int|string, mixed>|MessageInterface $messages
     */
    public static function chat(array|MessageInterface $messages): AiRequest
    {
        return static::getClient()->chat($messages);
    }

    /**
     * Start a new request configured with a specific driver.
     */
    public static function driver(string $driver): AiRequest
    {
        return (new AiRequest(static::getClient()))->driver($driver);
    }

    /**
     * Start a new request configured with a specific model.
     */
    public static function model(string $model): AiRequest
    {
        return (new AiRequest(static::getClient()))->model($model);
    }

    /**
     * Generate vector embedding for a single text.
     *
     * @return array<int, float>
     */
    public static function embed(string $text, ?string $model = null, ?string $driver = null): array
    {
        return static::getClient()->embed($text, $model, $driver);
    }

    /**
     * Generate vector embeddings for multiple texts.
     *
     * @param array<int, string> $texts
     * @return array<int, array<int, float>>
     */
    public static function embedMany(array $texts, ?string $model = null, ?string $driver = null): array
    {
        return static::getClient()->embedMany($texts, $model, $driver);
    }

    /**
     * Calculate cosine similarity between two vector embeddings.
     *
     * @param array<int, float> $a
     * @param array<int, float> $b
     */
    public static function similarity(array $a, array $b): float
    {
        return static::getClient()->similarity($a, $b);
    }

    /**
     * Swap the active AI client with an in-memory testing double.
     *
     * @param array<int|string, mixed>|string|null $responses
     */
    public static function fake(mixed $responses = null): AiFake
    {
        return static::getClient()->fake($responses);
    }

    /**
     * Start a fake sequence double.
     */
    public static function fakeSequence(): AiFake
    {
        return static::getClient()->fakeSequence();
    }

    /**
     * Reset active fake double.
     */
    public static function resetFake(): void
    {
        static::getClient()->resetFake();
    }

    /**
     * Retrieve the shared AiClient instance.
     */
    public static function getClient(): AiClient
    {
        return Services::ai();
    }

    /**
     * Dynamically forward static calls to the shared client instance.
     */
    public static function __callStatic(string $method, array $arguments): mixed
    {
        return static::getClient()->$method(...$arguments);
    }
}
