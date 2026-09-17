<?php

declare(strict_types=1);

namespace Tests\Unit;

use Jengo\Ai\AiRequest;
use Jengo\Ai\Drivers\AnthropicDriver;
use Jengo\Ai\Drivers\GeminiDriver;
use Jengo\Ai\Drivers\OllamaDriver;
use Jengo\Ai\Drivers\OpenAiDriver;
use Jengo\Ai\Drivers\OpenRouterDriver;
use Jengo\Ai\Messages\AssistantMessage;
use Jengo\Ai\Messages\Message;
use Jengo\Ai\Messages\SystemMessage;
use Jengo\Ai\Messages\ToolResultMessage;
use Jengo\Ai\Messages\UserMessage;
use Jengo\Ai\Support\Tool;
use Jengo\Ai\Support\ToolCall;
use PHPUnit\Framework\TestCase;

class TestableOpenAiDriver extends OpenAiDriver
{
    public function exposedBuildRequestBody(AiRequest $request, bool $stream = false): array
    {
        return $this->buildRequestBody($request, $stream);
    }

    public function exposedFormatMessages(AiRequest $request): array
    {
        return $this->formatMessages($request);
    }
}

class TestableAnthropicDriver extends AnthropicDriver
{
    public function exposedBuildRequestBody(AiRequest $request, bool $stream = false): array
    {
        return $this->buildRequestBody($request, $stream);
    }

    public function exposedFormatMessages(AiRequest $request): array
    {
        return $this->formatMessages($request);
    }
}

class TestableGeminiDriver extends GeminiDriver
{
    public function exposedBuildRequestBody(AiRequest $request): array
    {
        return $this->buildRequestBody($request);
    }

    public function exposedFormatContents(AiRequest $request): array
    {
        return $this->formatContents($request);
    }
}

class TestableOllamaDriver extends OllamaDriver
{
    public function exposedBuildRequestBody(AiRequest $request, bool $stream = false): array
    {
        return $this->buildRequestBody($request, $stream);
    }
}

class TestableOpenRouterDriver extends OpenRouterDriver
{
    public function exposedBuildHeaders(): array
    {
        return $this->buildHeaders();
    }
}

class DriverPayloadFormattingTest extends TestCase
{
    public function testAnthropicMergesParallelToolResultsIntoSingleUserTurn(): void
    {
        $driver = new TestableAnthropicDriver(['key' => 'test-key']);

        $request = (new AiRequest())
            ->chat([
                new UserMessage('Check weather in Nairobi and London'),
                new AssistantMessage('', [
                    new ToolCall('call_1', 'weather', ['city' => 'Nairobi']),
                    new ToolCall('call_2', 'weather', ['city' => 'London']),
                ]),
                new ToolResultMessage('22C Sunny', 'call_1', 'weather'),
                new ToolResultMessage('15C Rainy', 'call_2', 'weather'),
            ]);

        $messages = $driver->exposedFormatMessages($request);

        // Crucial: Must be 3 turns (user, assistant, user), NOT 4 turns with consecutive user messages
        $this->assertCount(3, $messages);

        $this->assertSame('user', $messages[0]['role']);
        $this->assertSame('Check weather in Nairobi and London', $messages[0]['content']);

        $this->assertSame('assistant', $messages[1]['role']);
        $this->assertIsArray($messages[1]['content']);
        $this->assertCount(2, $messages[1]['content']);
        $this->assertSame('tool_use', $messages[1]['content'][0]['type']);
        $this->assertSame('call_1', $messages[1]['content'][0]['id']);
        $this->assertSame('tool_use', $messages[1]['content'][1]['type']);
        $this->assertSame('call_2', $messages[1]['content'][1]['id']);

        // Third turn merges both tool_result blocks in single user message
        $this->assertSame('user', $messages[2]['role']);
        $this->assertIsArray($messages[2]['content']);
        $this->assertCount(2, $messages[2]['content']);
        $this->assertSame('tool_result', $messages[2]['content'][0]['type']);
        $this->assertSame('call_1', $messages[2]['content'][0]['tool_use_id']);
        $this->assertSame('22C Sunny', $messages[2]['content'][0]['content']);
        $this->assertSame('tool_result', $messages[2]['content'][1]['type']);
        $this->assertSame('call_2', $messages[2]['content'][1]['tool_use_id']);
        $this->assertSame('15C Rainy', $messages[2]['content'][1]['content']);
    }

    public function testAnthropicExtractsSystemPromptFromChatMessages(): void
    {
        $driver = new TestableAnthropicDriver(['key' => 'test-key']);

        $request = (new AiRequest())
            ->chat([
                new SystemMessage('You are an expert coder.'),
                new UserMessage('Write quicksort algorithm.'),
            ]);

        $body = $driver->exposedBuildRequestBody($request);

        $this->assertSame('You are an expert coder.', $body['system']);
        $this->assertCount(1, $body['messages']);
        $this->assertSame('user', $body['messages'][0]['role']);
    }

    public function testGeminiMergesParallelToolResultsIntoSingleUserTurn(): void
    {
        $driver = new TestableGeminiDriver(['key' => 'test-key']);

        $request = (new AiRequest())
            ->chat([
                new UserMessage('Find restaurants and hotels'),
                new AssistantMessage('', [
                    new ToolCall('gemini_call_1', 'search_places', ['query' => 'restaurants']),
                    new ToolCall('gemini_call_2', 'search_places', ['query' => 'hotels']),
                ]),
                new ToolResultMessage(['results' => ['Burger Bar']], 'gemini_call_1', 'search_places'),
                new ToolResultMessage(['results' => ['Hilton Hotel']], 'gemini_call_2', 'search_places'),
            ]);

        $contents = $driver->exposedFormatContents($request);

        // Crucial: Must be 3 turns (user, model, user), NOT 4 turns with consecutive user messages
        $this->assertCount(3, $contents);

        $this->assertSame('user', $contents[0]['role']);
        $this->assertSame('model', $contents[1]['role']);
        $this->assertCount(2, $contents[1]['parts']);
        $this->assertArrayHasKey('functionCall', $contents[1]['parts'][0]);
        $this->assertArrayHasKey('functionCall', $contents[1]['parts'][1]);

        // Third turn merges both functionResponse parts in single user message
        $this->assertSame('user', $contents[2]['role']);
        $this->assertCount(2, $contents[2]['parts']);
        $this->assertSame('search_places', $contents[2]['parts'][0]['functionResponse']['name']);
        $this->assertSame(['results' => ['Burger Bar']], $contents[2]['parts'][0]['functionResponse']['response']['content']);
        $this->assertSame('search_places', $contents[2]['parts'][1]['functionResponse']['name']);
        $this->assertSame(['results' => ['Hilton Hotel']], $contents[2]['parts'][1]['functionResponse']['response']['content']);
    }

    public function testGeminiExtractsSystemPromptFromChatMessages(): void
    {
        $driver = new TestableGeminiDriver(['key' => 'test-key']);

        $request = (new AiRequest())
            ->chat([
                new SystemMessage('Think step by step before answering.'),
                new UserMessage('What is 2+2?'),
            ]);

        $body = $driver->exposedBuildRequestBody($request);

        $this->assertArrayHasKey('system_instruction', $body);
        $this->assertSame('Think step by step before answering.', $body['system_instruction']['parts'][0]['text']);
        $this->assertCount(1, $body['contents']);
        $this->assertSame('user', $body['contents'][0]['role']);
    }

    public function testOpenAiFormatMessagesAndSystemPromptDeduplication(): void
    {
        $driver = new TestableOpenAiDriver(['key' => 'test-key']);

        $request = (new AiRequest())
            ->chat([
                new SystemMessage('System instruction from message list'),
                new UserMessage('Hello'),
            ]);

        $body = $driver->exposedBuildRequestBody($request);

        // System message placed first
        $this->assertSame('system', $body['messages'][0]['role']);
        $this->assertSame('System instruction from message list', $body['messages'][0]['content']);

        // User message second
        $this->assertSame('user', $body['messages'][1]['role']);
        $this->assertSame('Hello', $body['messages'][1]['content']);

        // No duplicate system message
        $this->assertCount(2, $body['messages']);
    }

    public function testOpenAiRequestBodyIncludesToolsAndSchema(): void
    {
        $driver = new TestableOpenAiDriver(['key' => 'test-key']);

        $tool = Tool::make('calc', 'Calculator')
            ->parameter('expr', 'string', 'Math expression');

        $request = (new AiRequest())
            ->prompt('Calculate 5*5')
            ->withTools([$tool])
            ->schema([
                'result' => 'int',
            ]);

        $body = $driver->exposedBuildRequestBody($request);

        $this->assertArrayHasKey('tools', $body);
        $this->assertSame('auto', $body['tool_choice']);
        $this->assertSame('calc', $body['tools'][0]['function']['name']);

        $this->assertArrayHasKey('response_format', $body);
        $this->assertSame('json_schema', $body['response_format']['type']);
        $this->assertSame('response_schema', $body['response_format']['json_schema']['name']);
        $this->assertArrayHasKey('properties', $body['response_format']['json_schema']['schema']);
    }

    public function testOllamaRequestBodyOptionsAndFormat(): void
    {
        $driver = new TestableOllamaDriver(['base_url' => 'http://localhost:11434']);

        $request = (new AiRequest())
            ->prompt('List 3 cities')
            ->temperature(0.7)
            ->maxTokens(300)
            ->topP(0.9)
            ->schema([
                'cities' => 'array',
            ]);

        $body = $driver->exposedBuildRequestBody($request);

        $this->assertSame('llama3.2', $body['model']);
        $this->assertSame(0.7, $body['options']['temperature']);
        $this->assertSame(300, $body['options']['num_predict']);
        $this->assertSame(0.9, $body['options']['top_p']);
        $this->assertArrayHasKey('format', $body);
        $this->assertSame('object', $body['format']['type']);
    }

    public function testOpenRouterHeadersIncludeRefererAndTitle(): void
    {
        $driver = new TestableOpenRouterDriver([
            'key'       => 'sk-or-test',
            'site_url'  => 'https://example.com/app',
            'site_name' => 'Custom AI Portal',
        ]);

        $headers = $driver->exposedBuildHeaders();

        $this->assertContains('Authorization: Bearer sk-or-test', $headers);
        $this->assertContains('HTTP-Referer: https://example.com/app', $headers);
        $this->assertContains('X-Title: Custom AI Portal', $headers);
    }
}
