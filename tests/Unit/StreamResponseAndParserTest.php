<?php

declare(strict_types=1);

namespace Tests\Unit;

use Generator;
use Jengo\Ai\Responses\StreamResponse;
use Jengo\Ai\Responses\Usage;
use PHPUnit\Framework\TestCase;

class StreamResponseAndParserTest extends TestCase
{
    protected function createGenerator(array $chunks): Generator
    {
        foreach ($chunks as $chunk) {
            yield $chunk;
        }
    }

    public function testStreamResponseTextConsumesAllChunks(): void
    {
        $gen = $this->createGenerator(['Hello', ' ', 'world', '!']);
        $stream = new StreamResponse($gen, 'gpt-4o-mini', new Usage(promptTokens: 10, completionTokens: 4, totalTokens: 14));

        $this->assertSame('gpt-4o-mini', $stream->model);
        $this->assertSame(14, $stream->usage?->totalTokens);
        $this->assertSame('Hello world!', $stream->text());

        // Calling text() again returns cached accumulated string without re-iterating
        $this->assertSame('Hello world!', $stream->text());
    }

    public function testStreamResponseEachCallbackAccumulates(): void
    {
        $gen = $this->createGenerator(['Alpha', ' ', 'Beta', ' ', 'Gamma']);
        $stream = new StreamResponse($gen);

        $capturedChunks = [];
        $capturedAccumulated = [];

        $result = $stream->each(function (string $chunk, string $accumulated) use (&$capturedChunks, &$capturedAccumulated) {
            $capturedChunks[] = $chunk;
            $capturedAccumulated[] = $accumulated;
        });

        $this->assertSame('Alpha Beta Gamma', $result);
        $this->assertSame(['Alpha', ' ', 'Beta', ' ', 'Gamma'], $capturedChunks);
        $this->assertSame([
            'Alpha',
            'Alpha ',
            'Alpha Beta',
            'Alpha Beta ',
            'Alpha Beta Gamma',
        ], $capturedAccumulated);
    }

    public function testStreamResponseIteratorAggregate(): void
    {
        $gen = $this->createGenerator(['A', 'B', 'C']);
        $stream = new StreamResponse($gen);

        $collected = [];
        foreach ($stream as $chunk) {
            $collected[] = $chunk;
        }

        $this->assertSame(['A', 'B', 'C'], $collected);
    }

    public function testOpenAiStreamChunkParsingSimulation(): void
    {
        $rawSseLines = [
            'data: {"choices":[{"delta":{"content":"Let"}}]}',
            'data: {"choices":[{"delta":{"content":"\'s"}}]}',
            'data: {"choices":[{"delta":{"content":" build"}}]}',
            'data: [DONE]',
        ];

        $parserGenerator = (function () use ($rawSseLines): Generator {
            foreach ($rawSseLines as $line) {
                if (!str_starts_with($line, 'data:')) {
                    continue;
                }
                $payload = trim(substr($line, 5));
                if ($payload === '[DONE]' || $payload === '') {
                    continue;
                }
                $data = json_decode($payload, true);
                if (!is_array($data)) {
                    continue;
                }
                $delta = $data['choices'][0]['delta'] ?? [];
                if (isset($delta['content']) && $delta['content'] !== '') {
                    yield (string) $delta['content'];
                }
            }
        })();

        $stream = new StreamResponse($parserGenerator, 'gpt-4o');
        $this->assertSame("Let's build", $stream->text());
    }

    public function testAnthropicStreamChunkParsingSimulation(): void
    {
        $rawSseLines = [
            'event: content_block_delta',
            'data: {"type":"content_block_delta","delta":{"type":"text_delta","text":"The "}}',
            'event: content_block_delta',
            'data: {"type":"content_block_delta","delta":{"type":"text_delta","text":"future "}}',
            'event: content_block_delta',
            'data: {"type":"content_block_delta","delta":{"type":"text_delta","text":"is now."}}',
            'event: message_stop',
            'data: {"type":"message_stop"}',
        ];

        $parserGenerator = (function () use ($rawSseLines): Generator {
            foreach ($rawSseLines as $line) {
                if (!str_starts_with($line, 'data:')) {
                    continue;
                }
                $payload = trim(substr($line, 5));
                $data = json_decode($payload, true);
                if (!is_array($data)) {
                    continue;
                }
                if (($data['type'] ?? '') === 'content_block_delta' && isset($data['delta']['text'])) {
                    yield (string) $data['delta']['text'];
                }
            }
        })();

        $stream = new StreamResponse($parserGenerator, 'claude-3-5-sonnet');
        $this->assertSame('The future is now.', $stream->text());
    }

    public function testGeminiStreamChunkParsingSimulation(): void
    {
        $rawSseLines = [
            'data: {"candidates":[{"content":{"parts":[{"text":"Gemini "}]}}]}',
            'data: {"candidates":[{"content":{"parts":[{"text":"Stream"}]}}]}',
        ];

        $parserGenerator = (function () use ($rawSseLines): Generator {
            foreach ($rawSseLines as $line) {
                if (!str_starts_with($line, 'data:')) {
                    continue;
                }
                $payload = trim(substr($line, 5));
                $data = json_decode($payload, true);
                if (!is_array($data)) {
                    continue;
                }
                $parts = $data['candidates'][0]['content']['parts'] ?? [];
                foreach ($parts as $part) {
                    if (isset($part['text']) && $part['text'] !== '') {
                        yield (string) $part['text'];
                    }
                }
            }
        })();

        $stream = new StreamResponse($parserGenerator, 'gemini-1.5-pro');
        $this->assertSame('Gemini Stream', $stream->text());
    }

    public function testOllamaStreamChunkParsingSimulation(): void
    {
        $rawJsonLines = [
            '{"model":"llama3","message":{"role":"assistant","content":"Deep"},"done":false}',
            '{"model":"llama3","message":{"role":"assistant","content":" thought"},"done":false}',
            '{"model":"llama3","message":{"role":"assistant","content":""},"done":true}',
        ];

        $parserGenerator = (function () use ($rawJsonLines): Generator {
            foreach ($rawJsonLines as $line) {
                $data = json_decode($line, true);
                if (!is_array($data)) {
                    continue;
                }
                $content = $data['message']['content'] ?? '';
                if ($content !== '') {
                    yield (string) $content;
                }
            }
        })();

        $stream = new StreamResponse($parserGenerator, 'llama3');
        $this->assertSame('Deep thought', $stream->text());
    }
}
