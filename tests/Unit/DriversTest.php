<?php

declare(strict_types=1);

namespace Tests\Unit;

use Jengo\Ai\AiClient;
use Jengo\Ai\Config\Ai as AiConfig;
use Jengo\Ai\Contracts\DriverInterface;
use Jengo\Ai\Drivers\AnthropicDriver;
use Jengo\Ai\Drivers\DeepSeekDriver;
use Jengo\Ai\Drivers\FakeDriver;
use Jengo\Ai\Drivers\GeminiDriver;
use Jengo\Ai\Drivers\GroqDriver;
use Jengo\Ai\Drivers\OllamaDriver;
use Jengo\Ai\Drivers\OpenAiDriver;
use Jengo\Ai\Testing\AiFake;
use PHPUnit\Framework\TestCase;

class DriversTest extends TestCase
{
    public function testDriverResolution(): void
    {
        $config = new AiConfig();
        $client = new AiClient($config);

        $openai = $client->driver('openai');
        $this->assertInstanceOf(OpenAiDriver::class, $openai);

        $anthropic = $client->driver('anthropic');
        $this->assertInstanceOf(AnthropicDriver::class, $anthropic);

        $gemini = $client->driver('gemini');
        $this->assertInstanceOf(GeminiDriver::class, $gemini);

        $deepseek = $client->driver('deepseek');
        $this->assertInstanceOf(DeepSeekDriver::class, $deepseek);

        $groq = $client->driver('groq');
        $this->assertInstanceOf(GroqDriver::class, $groq);

        $ollama = $client->driver('ollama');
        $this->assertInstanceOf(OllamaDriver::class, $ollama);
    }

    public function testDefaultDriverSwitching(): void
    {
        $config = new AiConfig();
        $config->default = 'anthropic';
        $client = new AiClient($config);

        $this->assertSame('anthropic', $client->getDefaultDriver());
        $this->assertInstanceOf(AnthropicDriver::class, $client->driver());

        $client->setDefaultDriver('gemini');
        $this->assertSame('gemini', $client->getDefaultDriver());
        $this->assertInstanceOf(GeminiDriver::class, $client->driver());
    }

    public function testCustomDriverExtension(): void
    {
        $client = new AiClient();
        $client->extend('custom_llm', function () {
            return new FakeDriver(new AiFake('custom response'));
        });

        $driver = $client->driver('custom_llm');
        $this->assertInstanceOf(DriverInterface::class, $driver);
    }

    public function testFakeDriverReturnsAiFake(): void
    {
        $fake = new AiFake('Hello Fake');
        $driver = new FakeDriver($fake);

        $this->assertSame($fake, $driver->getFake());
    }

    public function testDotNotationEnvLoading(): void
    {
        $_ENV['ai.providers.openai.key'] = 'sk-test-dot-notation';
        $_ENV['ai.providers.openai.model'] = 'gpt-4o-custom';
        $_ENV['ai.defaults.temperature'] = '0.35';

        $config = new AiConfig();

        $this->assertSame('sk-test-dot-notation', $config->providers['openai']['key']);
        $this->assertSame('gpt-4o-custom', $config->providers['openai']['model']);
        $this->assertSame(0.35, $config->defaults['temperature']);

        unset($_ENV['ai.providers.openai.key'], $_ENV['ai.providers.openai.model'], $_ENV['ai.defaults.temperature']);
    }
}
