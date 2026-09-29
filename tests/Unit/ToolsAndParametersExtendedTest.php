<?php

declare(strict_types=1);

namespace Tests\Unit;

use Jengo\Ai\AiRequest;
use Jengo\Ai\Attributes\AiParameter;
use Jengo\Ai\Attributes\AiTool;
use Jengo\Ai\Contracts\ToolInterface;
use Jengo\Ai\Support\Tool;
use Jengo\Ai\Support\ToolCall;
use PHPUnit\Framework\TestCase;

class SampleCalculatorTool implements ToolInterface
{
    public function getName(): string
    {
        return 'calculator';
    }

    public function getDescription(): string
    {
        return 'Performs basic arithmetic operations.';
    }

    public function getParametersSchema(): array
    {
        return [
            'type'       => 'object',
            'properties' => [
                'operation' => ['type' => 'string', 'description' => 'add, subtract, multiply, divide'],
                'a'         => ['type' => 'number', 'description' => 'First number'],
                'b'         => ['type' => 'number', 'description' => 'Second number'],
            ],
            'required'   => ['operation', 'a', 'b'],
        ];
    }

    public function execute(array $arguments): mixed
    {
        $op = $arguments['operation'] ?? 'add';
        $a = (float) ($arguments['a'] ?? 0);
        $b = (float) ($arguments['b'] ?? 0);

        return match ($op) {
            'add'      => $a + $b,
            'subtract' => $a - $b,
            'multiply' => $a * $b,
            'divide'   => $b !== 0.0 ? $a / $b : 'Error: Division by zero',
            default    => 'Unknown operation',
        };
    }
}

class SampleAttributedService
{
    #[AiTool(name: 'convert_currency', description: 'Convert amount from one currency to another')]
    public function convertCurrency(
        #[AiParameter(description: 'Amount in base currency')] float $amount,
        #[AiParameter(description: 'Base ISO currency code')] string $from,
        #[AiParameter(description: 'Target ISO currency code')] string $to = 'USD'
    ): float {
        $rates = ['USD' => 1.0, 'EUR' => 1.1, 'KES' => 0.0077];
        $usdAmount = $amount * ($rates[$from] ?? 1.0);
        return $usdAmount / ($rates[$to] ?? 1.0);
    }
}

class ToolsAndParametersExtendedTest extends TestCase
{
    public function testToolInterfaceDefinitionAndExecution(): void
    {
        $tool = new SampleCalculatorTool();
        $this->assertSame('calculator', $tool->getName());
        $this->assertSame('Performs basic arithmetic operations.', $tool->getDescription());
        $this->assertArrayHasKey('operation', $tool->getParametersSchema()['properties']);

        $resultAdd = $tool->execute(['operation' => 'add', 'a' => 15, 'b' => 27]);
        $this->assertSame(42.0, $resultAdd);

        $resultDiv = $tool->execute(['operation' => 'divide', 'a' => 10, 'b' => 0]);
        $this->assertSame('Error: Division by zero', $resultDiv);
    }

    public function testToolCallCreationAndSerialization(): void
    {
        $toolCall = new ToolCall(
            id: 'call_abc123',
            name: 'convert_currency',
            arguments: ['amount' => 1000, 'from' => 'EUR', 'to' => 'USD']
        );

        $this->assertSame('call_abc123', $toolCall->id);
        $this->assertSame('convert_currency', $toolCall->name);
        $this->assertSame(1000, $toolCall->arguments['amount']);

        $array = $toolCall->toArray();
        $this->assertSame('call_abc123', $array['id']);
        $this->assertSame('function', $array['type']);
        $this->assertSame('convert_currency', $array['function']['name']);
        $this->assertIsString($array['function']['arguments']);

        // From array factory
        $reconstructed = ToolCall::fromArray($array);
        $this->assertSame('call_abc123', $reconstructed->id);
        $this->assertSame('convert_currency', $reconstructed->name);
        $this->assertSame(1000, $reconstructed->arguments['amount']);
    }

    public function testAiRequestWithToolsFromAttributes(): void
    {
        $request = (new AiRequest())->withToolsFrom(SampleAttributedService::class);
        $tools = $request->getTools();

        $this->assertArrayHasKey('convert_currency', $tools);
        $tool = $tools['convert_currency'];
        $this->assertSame('convert_currency', $tool->getName());
        $this->assertSame('Convert amount from one currency to another', $tool->getDescription());

        $params = $tool->getParametersSchema();
        $this->assertArrayHasKey('amount', $params['properties']);
        $this->assertArrayHasKey('from', $params['properties']);
        $this->assertContains('amount', $params['required']);
        $this->assertContains('from', $params['required']);

        $execResult = $tool->execute(['amount' => 100, 'from' => 'USD', 'to' => 'USD']);
        $this->assertSame(100.0, $execResult);
    }
}
