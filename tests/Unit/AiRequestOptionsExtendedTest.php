<?php

declare(strict_types=1);

namespace Tests\Unit;

use Jengo\Ai\Ai;
use Jengo\Ai\AiRequest;
use Jengo\Ai\Messages\AssistantMessage;
use Jengo\Ai\Messages\SystemMessage;
use Jengo\Ai\Messages\ToolResultMessage;
use Jengo\Ai\Messages\UserMessage;
use Jengo\Ai\Responses\ChatResponse;
use PHPUnit\Framework\TestCase;

class AiRequestOptionsExtendedTest extends TestCase
{
    protected function tearDown(): void
    {
        Ai::resetFake();
        parent::tearDown();
    }

    public function testAiRequestFluentConfiguration(): void
    {
        $request = (new AiRequest())
            ->driver('openai')
            ->model('gpt-4o')
            ->system('You are an expert PHP and CodeIgniter 4 engineer.')
            ->temperature(0.7)
            ->maxTokens(2048)
            ->topP(0.95)
            ->maxSteps(10)
            ->option('frequency_penalty', 0.5);

        $this->assertSame('openai', $request->getDriver());
        $this->assertSame('gpt-4o', $request->getModel());
        $this->assertSame('You are an expert PHP and CodeIgniter 4 engineer.', $request->getSystemPrompt());
        $this->assertSame(0.7, $request->getTemperature());
        $this->assertSame(2048, $request->getMaxTokens());
        $this->assertSame(0.95, $request->getTopP());
        $this->assertSame(10, $request->getMaxSteps());
        $this->assertSame(0.5, $request->getOptions()['frequency_penalty']);
    }

    public function testAiRequestMessageBuildingChain(): void
    {
        $request = (new AiRequest())
            ->user('What is Jengo?')
            ->assistant('Jengo is a modern web application toolkit for CodeIgniter 4.')
            ->toolResult('Status: OK', 'call_001', 'system_check')
            ->chat(new SystemMessage('Context updated'))
            ->chat(['role' => 'user', 'content' => 'Awesome!']);

        $messages = $request->getMessages();
        $this->assertCount(5, $messages);

        $this->assertInstanceOf(UserMessage::class, $messages[0]);
        $this->assertSame('What is Jengo?', $messages[0]->getContent());

        $this->assertInstanceOf(AssistantMessage::class, $messages[1]);
        $this->assertSame('Jengo is a modern web application toolkit for CodeIgniter 4.', $messages[1]->getContent());

        $this->assertInstanceOf(ToolResultMessage::class, $messages[2]);
        $this->assertSame('call_001', $messages[2]->toolCallId);
        $this->assertSame('system_check', $messages[2]->name);

        $this->assertInstanceOf(SystemMessage::class, $messages[3]);
        $this->assertSame('Context updated', $messages[3]->getContent());

        $this->assertInstanceOf(UserMessage::class, $messages[4]);
        $this->assertSame('Awesome!', $messages[4]->getContent());
    }

    public function testAiRequestExecutionWithFake(): void
    {
        $fake = Ai::fake([
            'What is Jengo?' => 'Jengo is an ecosystem for rapid CodeIgniter 4 development.',
        ]);

        $response = Ai::driver('fake')
            ->model('fake-model')
            ->prompt('What is Jengo?')
            ->generate();

        $this->assertInstanceOf(ChatResponse::class, $response);
        $this->assertSame('Jengo is an ecosystem for rapid CodeIgniter 4 development.', $response->text());

        $fake->assertPromptSent('What is Jengo?');
        $fake->assertModel('fake-model');
    }
}
