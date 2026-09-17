<?php

declare(strict_types=1);

namespace Tests\Unit;

use Jengo\Ai\Enums\Role;
use Jengo\Ai\Support\PromptTemplate;
use PHPUnit\Framework\TestCase;

class PromptTemplateTest extends TestCase
{
    public function testRenderPromptTemplate(): void
    {
        $template = PromptTemplate::make('Hello {name}, welcome to {place}!');
        $rendered = $template->render(['name' => 'Ian', 'place' => 'Jengo AI']);

        $this->assertSame('Hello Ian, welcome to Jengo AI!', $rendered);
    }

    public function testRenderDoubleBraces(): void
    {
        $template = PromptTemplate::make('Translate "{{ text }}" into {{ target_lang }}.');
        $rendered = $template->render(['text' => 'Good morning', 'target_lang' => 'French']);

        $this->assertSame('Translate "Good morning" into French.', $rendered);
    }

    public function testToMessage(): void
    {
        $template = PromptTemplate::make('System prompt: {role_desc}');
        $message = $template->toMessage(['role_desc' => 'Act as a developer.'], Role::SYSTEM);

        $this->assertSame(Role::SYSTEM, $message->getRole());
        $this->assertSame('System prompt: Act as a developer.', $message->getContent());
    }

    public function testRenderWithArrayVariable(): void
    {
        $template = PromptTemplate::make("Analyze the following items:\n{items}");
        $rendered = $template->render(['items' => ['id' => 1, 'name' => 'Widget']]);

        $this->assertStringContainsString('"id": 1', $rendered);
        $this->assertStringContainsString('"name": "Widget"', $rendered);
    }

    public function testRenderLeavesUnmatchedPlaceholders(): void
    {
        $template = PromptTemplate::make('Hello {name}, your role is {role}.');
        $rendered = $template->render(['name' => 'Bob']);

        $this->assertSame('Hello Bob, your role is {role}.', $rendered);
    }
}
