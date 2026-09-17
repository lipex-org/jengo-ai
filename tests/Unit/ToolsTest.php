<?php

declare(strict_types=1);

namespace Tests\Unit;

use Jengo\Ai\AiRequest;
use Jengo\Ai\Attributes\AiParameter;
use Jengo\Ai\Attributes\AiTool;
use Jengo\Ai\Support\Tool;
use PHPUnit\Framework\TestCase;

class DummyOrderService
{
    #[AiTool('Get current status of an order by its ID')]
    public function getOrderStatus(
        #[AiParameter('The unique order ID')] string $orderId
    ): array {
        return ['order_id' => $orderId, 'status' => 'Shipped', 'carrier' => 'DHL'];
    }

    #[AiTool('Calculate tax for a given amount')]
    public function calculateTax(
        #[AiParameter('Subtotal amount in dollars')] float $amount,
        #[AiParameter('Tax percentage rate')] float $rate = 0.16
    ): float {
        return $amount * $rate;
    }

    public function helperWithoutAttribute(): string
    {
        return 'not a tool';
    }
}

class ToolsTest extends TestCase
{
    public function testToolBuilderAndExecution(): void
    {
        $tool = Tool::make('calculate_discount', 'Calculate discount for a user')
            ->parameter('amount', 'number', 'Total cart amount', required: true)
            ->parameter('coupon', 'string', 'Promo code', required: false, default: 'NONE')
            ->handler(function (float $amount, string $coupon = 'NONE'): array {
                $discount = $coupon === 'VIP50' ? $amount * 0.5 : 0.0;
                return ['discount' => $discount, 'final_total' => $amount - $discount];
            });

        $this->assertSame('calculate_discount', $tool->getName());
        $this->assertSame('Calculate discount for a user', $tool->getDescription());

        $schema = $tool->getParametersSchema();
        $this->assertSame('object', $schema['type']);
        $this->assertArrayHasKey('amount', $schema['properties']);
        $this->assertArrayHasKey('coupon', $schema['properties']);
        $this->assertContains('amount', $schema['required']);
        $this->assertNotContains('coupon', $schema['required']);

        $result = $tool->execute(['amount' => 100.0, 'coupon' => 'VIP50']);
        $this->assertSame(50.0, $result['discount']);
        $this->assertSame(50.0, $result['final_total']);
    }

    public function testAttributeDiscoveryWithToolsFrom(): void
    {
        $request = new AiRequest();
        $service = new DummyOrderService();

        $request->withToolsFrom($service);

        $this->assertTrue($request->hasTools());
        $tools = $request->getTools();

        $this->assertArrayHasKey('getOrderStatus', $tools);
        $this->assertArrayHasKey('calculateTax', $tools);
        $this->assertArrayNotHasKey('helperWithoutAttribute', $tools);

        $orderTool = $tools['getOrderStatus'];
        $this->assertSame('Get current status of an order by its ID', $orderTool->getDescription());

        $result = $orderTool->execute(['orderId' => 'ORD-123']);
        $this->assertSame(['order_id' => 'ORD-123', 'status' => 'Shipped', 'carrier' => 'DHL'], $result);

        $taxTool = $tools['calculateTax'];
        $tax = $taxTool->execute(['amount' => 100.0, 'rate' => 0.2]);
        $this->assertSame(20.0, $tax);
    }

    public function testToolExecutionIgnoresUnknownParameters(): void
    {
        $tool = Tool::make('greet', 'Greet a user')
            ->parameter('name', 'string', 'User name')
            ->handler(function (string $name): string {
                return "Hello, {$name}!";
            });

        // Calling with hallucinated extra arguments from LLM
        $result = $tool->execute([
            'name'       => 'Alice',
            'extra_arg'  => 'should_be_ignored',
            'confidence' => 0.98,
        ]);

        $this->assertSame('Hello, Alice!', $result);
    }

    public function testToolExecutionWithVariadicHandler(): void
    {
        $tool = Tool::make('variadic_test', 'Accepts any arguments')
            ->handler(function (...$args): array {
                return $args;
            });

        $result = $tool->execute(['a' => 1, 'b' => 2]);
        $this->assertSame(['a' => 1, 'b' => 2], $result);
    }

    public function testToolExecutionThrowsWhenNoHandler(): void
    {
        $tool = Tool::make('no_handler', 'Unconfigured tool');
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Tool [no_handler] has no execution handler defined.');

        $tool->execute(['foo' => 'bar']);
    }

    public function testAttributeDiscoveryTypeCoercionAndDefaults(): void
    {
        $service = new class {
            #[AiTool('Process order quantity')]
            public function process(
                #[AiParameter('Item count')] int $count,
                #[AiParameter('Item price')] float $price,
                #[AiParameter('Tax exempt')] bool $isExempt = false
            ): array {
                return [
                    'count'     => $count,
                    'price'     => $price,
                    'is_exempt' => $isExempt,
                ];
            }
        };

        $request = new AiRequest();
        $request->withToolsFrom($service);

        $tool = $request->getTools()['process'];

        // Pass strings that should be coerced to int, float, bool, and omit isExempt default
        $result = $tool->execute([
            'count' => '10',
            'price' => '49.95',
        ]);

        $this->assertSame(10, $result['count']);
        $this->assertSame(49.95, $result['price']);
        $this->assertFalse($result['is_exempt']);

        // Test boolean string coercion ("true")
        $result2 = $tool->execute([
            'count'     => '3',
            'price'     => '10.0',
            'isExempt'  => 'true',
        ]);

        $this->assertSame(3, $result2['count']);
        $this->assertSame(10.0, $result2['price']);
        $this->assertTrue($result2['is_exempt']);
    }
}
