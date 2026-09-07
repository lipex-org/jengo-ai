<?php

declare(strict_types=1);

namespace Tests\Unit;

use Generator;
use Jengo\Ai\Contracts\MessageInterface;
use Jengo\Ai\Enums\FinishReason;
use Jengo\Ai\Enums\Provider;
use Jengo\Ai\Enums\Role;
use Jengo\Ai\Messages\AssistantMessage;
use Jengo\Ai\Messages\Message;
use Jengo\Ai\Messages\SystemMessage;
use Jengo\Ai\Messages\ToolResultMessage;
use Jengo\Ai\Messages\UserMessage;
use Jengo\Ai\Responses\ChatResponse;
use Jengo\Ai\Responses\EmbeddingResponse;
use Jengo\Ai\Responses\StreamResponse;
use Jengo\Ai\Responses\Usage;
use Jengo\Ai\Support\ToolCall;
use PHPUnit\Framework\TestCase;

class ResponsesAndMessagesTest extends TestCase
{
    public function testEnums(): void
    {
        $this->assertSame('openai', Provider::OPENAI->value);
        $this->assertSame('anthropic', Provider::ANTHROPIC->value);
        $this->assertSame('gemini', Provider::GEMINI->value);
        $this->assertSame('deepseek', Provider::DEEPSEEK->value);
        $this->assertSame('groq', Provider::GROQ->value);
        $this->assertSame('openrouter', Provider::OPENROUTER->value);
        $this->assertSame('ollama', Provider::OLLAMA->value);
        $this->assertSame('fake', Provider::FAKE->value);

        $this->assertSame('system', Role::SYSTEM->value);
        $this->assertSame('user', Role::USER->value);
        $this->assertSame('assistant', Role::ASSISTANT->value);
        $this->assertSame('tool', Role::TOOL->value);

        $this->assertSame('stop', FinishReason::STOP->value);
        $this->assertSame('length', FinishReason::LENGTH->value);
        $this->assertSame('tool_calls', FinishReason::TOOL_CALLS->value);
        $this->assertSame('content_filter', FinishReason::CONTENT_FILTER->value);
    }

    public function testMessages(): void
    {
        $sys = new SystemMessage('System instruction');
        $this->assertSame(Role::SYSTEM, $sys->getRole());
        $this->assertSame('System instruction', $sys->getContent());

        $user = new UserMessage('User prompt');
        $this->assertSame(Role::USER, $user->getRole());
        $this->assertSame('User prompt', $user->getContent());

        $toolCall = new ToolCall('tc_1', 'calc', ['a' => 1]);
        $asst = new AssistantMessage('Thinking...', [$toolCall]);
        $this->assertSame(Role::ASSISTANT, $asst->getRole());
        $this->assertSame('Thinking...', $asst->getContent());
        $this->assertCount(1, $asst->toArray()['tool_calls']);

        $toolResult = new ToolResultMessage(['res' => 'ok'], 'tc_1', 'calc');
        $this->assertSame(Role::TOOL, $toolResult->getRole());
        $this->assertSame('tc_1', $toolResult->toArray()['tool_call_id']);

        $genericSys = Message::system('Hello Sys');
        $this->assertSame(Role::SYSTEM, $genericSys->getRole());

        $genericUser = Message::user('Hello User');
        $this->assertSame(Role::USER, $genericUser->getRole());

        $genericAsst = Message::assistant('Hello Asst');
        $this->assertSame(Role::ASSISTANT, $genericAsst->getRole());

        $genericTool = Message::tool(['status' => 'done'], 'tc_99');
        $this->assertSame(Role::TOOL, $genericTool->getRole());
    }

    public function testUsageMetrics(): void
    {
        $usage = new Usage(promptTokens: 100, completionTokens: 50);
        $this->assertSame(150, $usage->totalTokens);

        $array = $usage->toArray();
        $this->assertSame(100, $array['prompt_tokens']);
        $this->assertSame(50, $array['completion_tokens']);
        $this->assertSame(150, $array['total_tokens']);

        $fromArr = Usage::fromArray(['input_tokens' => 20, 'output_tokens' => 30]);
        $this->assertSame(20, $fromArr->promptTokens);
        $this->assertSame(30, $fromArr->completionTokens);
        $this->assertSame(50, $fromArr->totalTokens);
    }

    public function testChatResponsePropertiesAndSerialization(): void
    {
        $tc = new ToolCall('c_1', 'my_func', ['q' => 'search']);
        $usage = new Usage(10, 20, 30);
        $response = new ChatResponse(
            content: 'Search results',
            usage: $usage,
            finishReason: FinishReason::STOP,
            toolCalls: [$tc],
            rawResponse: ['ok' => true],
            model: 'gpt-4o',
            id: 'resp_123'
        );

        $this->assertSame('Search results', $response->text());
        $this->assertSame('Search results', (string) $response);
        $this->assertSame($usage, $response->usage());
        $this->assertSame(FinishReason::STOP, $response->finishReason());
        $this->assertTrue($response->hasToolCalls());
        $this->assertSame([$tc], $response->toolCalls());
        $this->assertSame(['ok' => true], $response->raw());
        $this->assertSame('gpt-4o', $response->model);
        $this->assertSame('resp_123', $response->id);

        $serialized = $response->jsonSerialize();
        $this->assertSame('resp_123', $serialized['id']);
        $this->assertSame('gpt-4o', $serialized['model']);
        $this->assertSame('stop', $serialized['finish_reason']);
    }

    public function testStreamResponseAccumulationAndIteration(): void
    {
        $generator = (function (): Generator {
            yield 'Part 1 ';
            yield 'Part 2 ';
            yield 'Part 3';
        })();

        $stream = new StreamResponse($generator, model: 'gpt-4o');

        $this->assertSame('Part 1 Part 2 Part 3', $stream->text());
        // Second call should return cached accumulated text
        $this->assertSame('Part 1 Part 2 Part 3', $stream->text());
    }

    public function testEmbeddingResponseJsonSerialize(): void
    {
        $emb = new EmbeddingResponse(
            embeddings: [[0.1, 0.2, 0.3]],
            usage: new Usage(5, 0, 5),
            model: 'text-embedding-3-small'
        );

        $this->assertSame(3, $emb->dimensions());
        $this->assertSame([0.1, 0.2, 0.3], $emb->first());

        $json = $emb->jsonSerialize();
        $this->assertSame('text-embedding-3-small', $json['model']);
        $this->assertSame(3, $json['dimensions']);
        $this->assertSame(1, $json['count']);
    }
}
