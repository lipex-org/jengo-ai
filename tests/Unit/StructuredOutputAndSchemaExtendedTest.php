<?php

declare(strict_types=1);

namespace Tests\Unit;

use Jengo\Ai\Exceptions\SchemaValidationException;
use Jengo\Ai\Schema\SchemaBuilder;
use Jengo\Ai\Schema\StructuredOutput;
use PHPUnit\Framework\TestCase;

class StructuredOutputAndSchemaExtendedTest extends TestCase
{
    public function testSchemaBuilderSupportsAllPrimitiveAndSpecialTypes(): void
    {
        $schema = SchemaBuilder::fromArray([
            'intVal'      => 'integer',
            'floatVal'    => 'float',
            'numericVal'  => 'numeric',
            'numberVal'   => 'number',
            'boolVal'     => 'boolean',
            'emailVal'    => 'email',
            'urlVal'      => 'url',
            'dateVal'     => 'date',
            'datetimeVal' => 'datetime',
            'listVal'     => 'list',
            'arrayVal'    => 'array',
        ], 'AllTypesSchema');

        $this->assertSame('AllTypesSchema', $schema['name']);
        $this->assertTrue($schema['strict']);
        $this->assertSame('object', $schema['type']);
        $this->assertFalse($schema['additionalProperties']);

        $props = $schema['properties'];
        $this->assertSame('integer', $props['intVal']['type']);
        $this->assertSame('number', $props['floatVal']['type']);
        $this->assertSame('number', $props['numericVal']['type']);
        $this->assertSame('number', $props['numberVal']['type']);
        $this->assertSame('boolean', $props['boolVal']['type']);
        $this->assertSame('string', $props['emailVal']['type']);
        $this->assertSame('string', $props['urlVal']['type']);
        $this->assertSame('string', $props['dateVal']['type']);
        $this->assertSame('string', $props['datetimeVal']['type']);
        $this->assertSame('array', $props['listVal']['type']);
        $this->assertSame('array', $props['arrayVal']['type']);
    }

    public function testSchemaBuilderPassesThroughPrebuiltObjectSchema(): void
    {
        $prebuilt = [
            'type'       => 'object',
            'properties' => [
                'custom' => ['type' => 'string'],
            ],
            'required'   => ['custom'],
        ];

        $result = SchemaBuilder::fromArray($prebuilt, 'CustomTitle', strict: true);
        $this->assertSame('object', $result['type']);
        $this->assertFalse($result['additionalProperties']);
    }

    public function testStructuredOutputExtractSanitizesTrailingCommas(): void
    {
        // Trailing comma in JSON object and array
        $llmOutput = '{"title": "Summary", "tags": ["php", "ci4",], "count": 10,}';
        $extracted = StructuredOutput::extract($llmOutput);

        $this->assertSame([
            'title' => 'Summary',
            'tags'  => ['php', 'ci4'],
            'count' => 10,
        ], $extracted);
    }

    public function testStructuredOutputThrowsOnInvalidJson(): void
    {
        $this->expectException(SchemaValidationException::class);
        $this->expectExceptionMessage('Failed to parse structured JSON output from model response');

        StructuredOutput::extract('This is completely non-JSON plain text with no brackets.');
    }

    public function testStructuredOutputValidatesRequiredFields(): void
    {
        $schema = [
            'required' => ['userId', 'status'],
        ];

        $this->expectException(SchemaValidationException::class);
        $this->expectExceptionMessage('Missing required field(s): [status]');

        StructuredOutput::extract('{"userId": 123}', $schema);
    }
}
