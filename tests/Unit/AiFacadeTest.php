<?php

declare(strict_types=1);

namespace Tests\Unit;

use Jengo\Ai\Ai;
use Jengo\Ai\AiClient;
use Jengo\Ai\AiRequest;
use Jengo\Ai\Config\Services;
use Jengo\Ai\Messages\UserMessage;
use PHPUnit\Framework\TestCase;

class AiFacadeTest extends TestCase
{
    protected function tearDown(): void
    {
        Ai::resetFake();
        parent::tearDown();
    }

    public function testFacadePrompt(): void
    {
        $request = Ai::prompt('Hello AI');
        $this->assertInstanceOf(AiRequest::class, $request);

        $messages = $request->getMessages();
        $this->assertCount(1, $messages);
        $this->assertSame('Hello AI', $messages[0]->getContent());
    }

    public function testFacadeChat(): void
    {
        $request = Ai::chat([
            ['role' => 'system', 'content' => 'System instruction'],
            ['role' => 'user', 'content' => 'User message'],
        ]);

        $this->assertInstanceOf(AiRequest::class, $request);
        $this->assertCount(2, $request->getMessages());
    }

    public function testFacadeDriverAndModel(): void
    {
        $request = Ai::driver('anthropic')->model('claude-3-5-sonnet-20241022');

        $this->assertInstanceOf(AiRequest::class, $request);
        $this->assertSame('anthropic', $request->getDriver());
        $this->assertSame('claude-3-5-sonnet-20241022', $request->getModel());
    }

    public function testFacadeSimilarity(): void
    {
        $sim = Ai::similarity([1.0, 0.0], [1.0, 0.0]);
        $this->assertEqualsWithDelta(1.0, $sim, 0.0001);
    }

    public function testAiHelperFunction(): void
    {
        $client = ai();
        $this->assertInstanceOf(AiClient::class, $client);

        $promptReq = ai('Quick question');
        $this->assertInstanceOf(AiRequest::class, $promptReq);

        $chatReq = ai([['role' => 'user', 'content' => 'Hello']]);
        $this->assertInstanceOf(AiRequest::class, $chatReq);
    }
}
