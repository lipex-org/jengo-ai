<?php

declare(strict_types=1);

namespace Tests\Unit;

use Jengo\Ai\Exceptions\SchemaValidationException;
use Jengo\Ai\Responses\ChatResponse;
use Jengo\Ai\Schema\SchemaBuilder;
use Jengo\Ai\Schema\StructuredOutput;
use PHPUnit\Framework\TestCase;

class StructuredOutputTest extends TestCase
{
    public function testSchemaBuilderFromArray(): void
    {
        $schema = SchemaBuilder::fromArray([
            'name'       => 'string',
            'age'        => 'int',
            'email'      => 'email',
            'is_active'  => 'bool',
            'tags'       => 'array',
            'profile'    => [
                'bio'     => 'string',
                'website' => 'url',
            ],
            'bio_opt'    => 'string|nullable',
        ]);

        $this->assertSame('object', $schema['type']);
        $this->assertArrayHasKey('name', $schema['properties']);
        $this->assertSame('string', $schema['properties']['name']['type']);
        $this->assertSame('integer', $schema['properties']['age']['type']);
        $this->assertSame('boolean', $schema['properties']['is_active']['type']);
        $this->assertSame('array', $schema['properties']['tags']['type']);

        // Check required fields (bio_opt is nullable so not strictly required)
        $this->assertContains('name', $schema['required']);
        $this->assertContains('age', $schema['required']);
        $this->assertNotContains('bio_opt', $schema['required']);

        // Check nested object
        $this->assertSame('object', $schema['properties']['profile']['type']);
        $this->assertSame('string', $schema['properties']['profile']['properties']['bio']['type']);
    }

    public function testStructuredOutputExtractCleanJson(): void
    {
        $json = '{"name":"Alice","role":"Engineer"}';
        $extracted = StructuredOutput::extract($json);

        $this->assertSame(['name' => 'Alice', 'role' => 'Engineer'], $extracted);
    }

    public function testStructuredOutputExtractMarkdownFences(): void
    {
        $content = "```json\n{\n  \"city\": \"Nairobi\",\n  \"temp\": 24\n}\n```";
        $extracted = StructuredOutput::extract($content);

        $this->assertSame(['city' => 'Nairobi', 'temp' => 24], $extracted);
    }

    public function testStructuredOutputExtractEmbeddedJson(): void
    {
        $content = "Here is your requested output:\n{\"status\":\"success\",\"code\":200}\nHope this helps!";
        $extracted = StructuredOutput::extract($content);

        $this->assertSame(['status' => 'success', 'code' => 200], $extracted);
    }

    public function testStructuredOutputThrowsOnInvalidJson(): void
    {
        $this->expectException(SchemaValidationException::class);
        StructuredOutput::extract("Sorry, I could not generate that.");
    }

    public function testStructuredOutputValidatesRequiredFields(): void
    {
        $schema = [
            'type'       => 'object',
            'properties' => ['name' => ['type' => 'string'], 'age' => ['type' => 'integer']],
            'required'   => ['name', 'age'],
        ];

        $this->expectException(SchemaValidationException::class);
        StructuredOutput::extract('{"name":"Bob"}', $schema);
    }

    public function testChatResponseAsArrayAndAsObject(): void
    {
        $response = new ChatResponse('{"company":"Acme","employees":150}');

        $arr = $response->asArray();
        $this->assertSame(['company' => 'Acme', 'employees' => 150], $arr);

        $obj = $response->asObject();
        $this->assertSame('Acme', $obj->company);
        $this->assertSame(150, $obj->employees);
    }

    public function testStructuredOutputExtractsWithPrecedingNarrativeAndFences(): void
    {
        $raw = "Certainly! Below is the requested JSON payload:\n\n```json\n{\n  \"status\": \"active\",\n  \"count\": 42\n}\n```\n\nPlease let me know if you need anything else!";
        $extracted = StructuredOutput::extract($raw);

        $this->assertSame(['status' => 'active', 'count' => 42], $extracted);
    }

    public function testStructuredOutputToleratesTrailingCommas(): void
    {
        $raw = '{"name": "Alice", "tags": ["admin", "staff",],}';
        $extracted = StructuredOutput::extract($raw);

        $this->assertSame('Alice', $extracted['name']);
        $this->assertSame(['admin', 'staff'], $extracted['tags']);
    }

    public function testStructuredOutputExtractsRootJsonArray(): void
    {
        $raw = "Here are the results: [{\"id\": 1}, {\"id\": 2}]";
        $extracted = StructuredOutput::extract($raw);

        $this->assertCount(2, $extracted);
        $this->assertSame(1, $extracted[0]['id']);
        $this->assertSame(2, $extracted[1]['id']);
    }

    public function testStructuredOutputMissingFieldsExceptionContainsDetails(): void
    {
        $schema = [
            'type'       => 'object',
            'properties' => ['email' => ['type' => 'string']],
            'required'   => ['email', 'password'],
        ];

        try {
            StructuredOutput::extract('{"email": "test@example.com"}', $schema);
            $this->fail('Expected SchemaValidationException was not thrown');
        } catch (SchemaValidationException $e) {
            $this->assertSame(['password'], $e->getMissingFields());
            $this->assertArrayHasKey('email', $e->getPayload());
        }
    }
}
