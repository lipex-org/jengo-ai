<?php

declare(strict_types=1);

namespace Tests\Unit;

use Jengo\Ai\Ai;
use Jengo\Ai\AiRequest;
use Jengo\Ai\Enums\FinishReason;
use Jengo\Ai\Support\Tool;
use Jengo\Ai\Testing\AiFake;
use Jengo\Ai\Testing\Concerns\AiTestAssertionsTrait;
use PHPUnit\Framework\TestCase;

class AiFakeTest extends TestCase
{
    use AiTestAssertionsTrait;

    protected function tearDown(): void
    {
        Ai::resetFake();
        parent::tearDown();
    }

    public function testAiFakeGeneratesQueuedResponses(): void
    {
        $fake = Ai::fake([
            'Response 1',
            'Response 2',
        ]);

        $r1 = Ai::prompt('First question')->text();
        $this->assertSame('Response 1', $r1);

        $r2 = Ai::prompt('Second question')->text();
        $this->assertSame('Response 2', $r2);

        $fake->assertCount(2);
        $fake->assertPromptSent('First question');
        $fake->assertPromptSent('Second question');
        $fake->assertPromptNotSent('Third question');
    }

    public function testAiFakeAssertionsTrait(): void
    {
        $this->aiFake(['{"summary":"AI generated summary"}']);

        $res = Ai::prompt('Summarize text')->asArray();
        $this->assertSame('AI generated summary', $res['summary']);

        $this->assertAiPromptSent('Summarize');
        $this->assertAiCount(1);
    }

    public function testAiFakeToolCallingLoop(): void
    {
        $tool = Tool::make('fetch_weather', 'Get weather for a city')
            ->parameter('city', 'string', 'City name')
            ->handler(fn(string $city) => ['city' => $city, 'temp' => '22C', 'condition' => 'Sunny']);

        $fake = Ai::fakeSequence()
            ->pushToolCall('fetch_weather', ['city' => 'Nairobi'])
            ->push('The weather in Nairobi is 22C and Sunny.');

        $response = Ai::prompt('What is the weather in Nairobi?')
            ->withTools([$tool])
            ->maxSteps(3)
            ->generate();

        $this->assertSame('The weather in Nairobi is 22C and Sunny.', $response->text());
        $fake->assertToolCalled('fetch_weather', fn($args) => $args['city'] === 'Nairobi');
        $fake->assertToolNotCalled('fetch_traffic');
    }

    public function testAiFakeStreaming(): void
    {
        $fake = Ai::fake();
        $fake->pushStream(['Hello ', 'from ', 'streaming!']);

        $collected = '';
        Ai::prompt('Stream test')->stream(function (string $chunk, string $acc) use (&$collected) {
            $collected .= $chunk;
        });

        $this->assertSame('Hello from streaming!', $collected);
        $fake->assertCount(1);
    }

    public function testAiFakeEmbedding(): void
    {
        $fake = Ai::fake();
        $fake->pushEmbedding([0.1, 0.2, 0.3, 0.4]);

        $vector = Ai::embed('Calculate embedding');
        $this->assertSame([0.1, 0.2, 0.3, 0.4], $vector);

        $fake->assertCount(0); // embed called directly on driver
    }
}
