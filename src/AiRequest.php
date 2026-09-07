<?php

declare(strict_types=1);

namespace Jengo\Ai;

use Jengo\Ai\Attributes\AiParameter;
use Jengo\Ai\Attributes\AiTool;
use Jengo\Ai\Config\Services;
use Jengo\Ai\Contracts\MessageInterface;
use Jengo\Ai\Contracts\ToolInterface;
use Jengo\Ai\Enums\Role;
use Jengo\Ai\Messages\AssistantMessage;
use Jengo\Ai\Messages\Message;
use Jengo\Ai\Messages\SystemMessage;
use Jengo\Ai\Messages\ToolResultMessage;
use Jengo\Ai\Messages\UserMessage;
use Jengo\Ai\Responses\ChatResponse;
use Jengo\Ai\Responses\StreamResponse;
use Jengo\Ai\Schema\SchemaBuilder;
use Jengo\Ai\Support\Tool;
use ReflectionClass;
use ReflectionMethod;
use ReflectionNamedType;

class AiRequest
{
    protected ?string $driverName = null;
    protected ?string $model = null;
    protected ?string $systemPrompt = null;

    /** @var array<int, MessageInterface> */
    protected array $messages = [];

    protected ?float $temperature = null;
    protected ?int $maxTokens = null;
    protected ?float $topP = null;

    /** @var array<string, mixed>|null */
    protected ?array $schema = null;

    /** @var array<string, ToolInterface> */
    protected array $tools = [];

    protected int $maxSteps = 5;

    /** @var array<string, mixed> */
    protected array $options = [];

    public function __construct(
        protected ?AiClient $client = null
    ) {
    }

    public function driver(string $driver): static
    {
        $this->driverName = $driver;
        return $this;
    }

    public function model(string $model): static
    {
        $this->model = $model;
        return $this;
    }

    public function system(string $systemPrompt): static
    {
        $this->systemPrompt = $systemPrompt;
        return $this;
    }

    public function prompt(string $prompt): static
    {
        $this->messages[] = new UserMessage($prompt);
        return $this;
    }

    /**
     * Set or append conversation messages.
     *
     * @param array<int|string, mixed>|MessageInterface $messages
     */
    public function chat(array|MessageInterface $messages): static
    {
        if ($messages instanceof MessageInterface) {
            $this->messages[] = $messages;
            return $this;
        }

        // Check if single message associative array e.g. ['role' => 'user', 'content' => 'hello']
        if (isset($messages['role']) && isset($messages['content'])) {
            $this->messages[] = $this->parseMessageArray($messages);
            return $this;
        }

        foreach ($messages as $msg) {
            if ($msg instanceof MessageInterface) {
                $this->messages[] = $msg;
            } elseif (is_array($msg) && isset($msg['role']) && isset($msg['content'])) {
                $this->messages[] = $this->parseMessageArray($msg);
            }
        }

        return $this;
    }

    public function user(string|array $content): static
    {
        $this->messages[] = new UserMessage($content);
        return $this;
    }

    public function assistant(string $content, ?array $toolCalls = null): static
    {
        $this->messages[] = new AssistantMessage($content, $toolCalls);
        return $this;
    }

    public function toolResult(string|array $result, string $toolCallId, ?string $name = null): static
    {
        $this->messages[] = new ToolResultMessage($result, $toolCallId, $name);
        return $this;
    }

    public function temperature(float $temperature): static
    {
        $this->temperature = $temperature;
        return $this;
    }

    public function maxTokens(int $maxTokens): static
    {
        $this->maxTokens = $maxTokens;
        return $this;
    }

    public function topP(float $topP): static
    {
        $this->topP = $topP;
        return $this;
    }

    /**
     * Define the expected output JSON Schema.
     *
     * @param array<string, mixed> $schema
     */
    public function schema(array $schema): static
    {
        $this->schema = SchemaBuilder::fromArray($schema);
        return $this;
    }

    /**
     * Register tools for function calling.
     *
     * @param array<int, ToolInterface>|ToolInterface $tools
     */
    public function withTools(array|ToolInterface $tools): static
    {
        if ($tools instanceof ToolInterface) {
            $this->tools[$tools->getName()] = $tools;
        } else {
            foreach ($tools as $tool) {
                if ($tool instanceof ToolInterface) {
                    $this->tools[$tool->getName()] = $tool;
                }
            }
        }

        return $this;
    }

    /**
     * Automatically discover and register tools from an object or class using PHP 8 #[AiTool] attributes.
     */
    public function withToolsFrom(object|string $target): static
    {
        $reflection = new ReflectionClass($target);
        $instance = is_object($target) ? $target : new $target();

        foreach ($reflection->getMethods(ReflectionMethod::IS_PUBLIC) as $method) {
            $attributes = $method->getAttributes(AiTool::class);
            if (empty($attributes)) {
                continue;
            }

            /** @var AiTool $toolAttr */
            $toolAttr = $attributes[0]->newInstance();
            $toolName = $toolAttr->name ?? $method->getName();
            $toolDesc = $toolAttr->description;

            $tool = Tool::make($toolName, $toolDesc);

            foreach ($method->getParameters() as $param) {
                $paramName = $param->getName();
                $paramType = 'string';

                if ($param->hasType()) {
                    $type = $param->getType();
                    if ($type instanceof ReflectionNamedType) {
                        $paramType = match ($type->getName()) {
                            'int'     => 'integer',
                            'float'   => 'number',
                            'bool'    => 'boolean',
                            'array'   => 'array',
                            default   => 'string',
                        };
                    }
                }

                $paramDesc = '';
                $paramRequired = !$param->isOptional();

                $paramAttrs = $param->getAttributes(AiParameter::class);
                if (!empty($paramAttrs)) {
                    /** @var AiParameter $pAttr */
                    $pAttr = $paramAttrs[0]->newInstance();
                    $paramDesc = $pAttr->description;
                    if ($pAttr->type !== null) {
                        $paramType = $pAttr->type;
                    }
                    if ($pAttr->required !== null) {
                        $paramRequired = $pAttr->required;
                    }
                }

                $tool->parameter($paramName, $paramType, $paramDesc, $paramRequired);
            }

            $tool->handler(function (...$args) use ($instance, $method) {
                return $method->invokeArgs($instance, $args);
            });

            $this->tools[$toolName] = $tool;
        }

        return $this;
    }

    public function maxSteps(int $maxSteps): static
    {
        $this->maxSteps = $maxSteps;
        return $this;
    }

    public function option(string $key, mixed $value): static
    {
        $this->options[$key] = $value;
        return $this;
    }

    /**
     * @param array<string, mixed> $options
     */
    public function options(array $options): static
    {
        $this->options = array_merge($this->options, $options);
        return $this;
    }

    /**
     * Execute synchronous generation with automatic multi-step agentic tool calling loop.
     */
    public function generate(): ChatResponse
    {
        $client = $this->client ?? Services::ai();
        $driver = $client->driver($this->driverName);

        $step = 0;

        while ($step < $this->maxSteps) {
            $step++;
            $response = $driver->generate($this);

            if (!$response->hasToolCalls() || empty($this->tools)) {
                return $response;
            }

            // Append assistant response with tool calls
            $this->messages[] = new AssistantMessage($response->text(), $response->toolCalls());

            // Execute each tool call and record tool result messages
            foreach ($response->toolCalls() as $toolCall) {
                if (isset($this->tools[$toolCall->name])) {
                    $tool = $this->tools[$toolCall->name];
                    try {
                        $result = $tool->execute($toolCall->arguments);
                    } catch (\Throwable $e) {
                        $result = ['error' => $e->getMessage()];
                    }
                    $this->messages[] = new ToolResultMessage($result, $toolCall->id, $toolCall->name);
                } else {
                    $this->messages[] = new ToolResultMessage(
                        ['error' => "Tool [{$toolCall->name}] not found."],
                        $toolCall->id,
                        $toolCall->name
                    );
                }
            }
        }

        return $driver->generate($this);
    }

    /**
     * Shortcut to generate completion and return output text.
     */
    public function text(): string
    {
        return $this->generate()->text();
    }

    /**
     * Shortcut to generate completion and decode JSON structured output array.
     */
    public function asArray(): array
    {
        return $this->generate()->asArray();
    }

    /**
     * Shortcut to generate completion and decode JSON structured output object.
     */
    public function asObject(): object
    {
        return $this->generate()->asObject();
    }

    /**
     * Execute real-time streaming response.
     *
     * @param callable(string $chunk, string $accumulated): void|null $callback
     */
    public function stream(?callable $callback = null): StreamResponse
    {
        $client = $this->client ?? Services::ai();
        $driver = $client->driver($this->driverName);

        $streamResponse = $driver->stream($this);

        if ($callback !== null) {
            $streamResponse->each($callback);
        }

        return $streamResponse;
    }

    // ==========================================
    // Getters
    // ==========================================

    /**
     * @return array<int, MessageInterface>
     */
    public function getMessages(): array
    {
        return $this->messages;
    }

    public function getSystemPrompt(): ?string
    {
        return $this->systemPrompt;
    }

    public function getModel(): ?string
    {
        return $this->model;
    }

    public function getDriver(): ?string
    {
        return $this->driverName;
    }

    public function getTemperature(): ?float
    {
        return $this->temperature;
    }

    public function getMaxTokens(): ?int
    {
        return $this->maxTokens;
    }

    public function getTopP(): ?float
    {
        return $this->topP;
    }

    /**
     * @return array<string, mixed>|null
     */
    public function getSchema(): ?array
    {
        return $this->schema;
    }

    public function hasSchema(): bool
    {
        return $this->schema !== null;
    }

    /**
     * @return array<string, ToolInterface>
     */
    public function getTools(): array
    {
        return $this->tools;
    }

    public function hasTools(): bool
    {
        return !empty($this->tools);
    }

    public function getMaxSteps(): int
    {
        return $this->maxSteps;
    }

    /**
     * @return array<string, mixed>
     */
    public function getOptions(): array
    {
        return $this->options;
    }

    /**
     * Get a combined text representation of all messages and system prompt.
     */
    public function getFormattedPrompt(): string
    {
        $parts = [];
        if ($this->systemPrompt !== null) {
            $parts[] = $this->systemPrompt;
        }

        foreach ($this->messages as $msg) {
            $content = $msg->getContent();
            $parts[] = is_array($content) ? json_encode($content) : (string) $content;
        }

        return implode("\n", $parts);
    }

    /**
     * @param array<string, mixed> $msg
     */
    protected function parseMessageArray(array $msg): MessageInterface
    {
        $roleStr = strtolower((string) ($msg['role'] ?? 'user'));
        $role = match ($roleStr) {
            'system'    => Role::SYSTEM,
            'assistant' => Role::ASSISTANT,
            'tool'      => Role::TOOL,
            default     => Role::USER,
        };

        return match ($role) {
            Role::SYSTEM    => new SystemMessage((string) ($msg['content'] ?? '')),
            Role::USER      => new UserMessage($msg['content'] ?? ''),
            Role::ASSISTANT => new AssistantMessage((string) ($msg['content'] ?? ''), $msg['tool_calls'] ?? null),
            Role::TOOL      => new ToolResultMessage($msg['content'] ?? '', (string) ($msg['tool_call_id'] ?? ''), $msg['name'] ?? null),
        };
    }
}
