<?php

declare(strict_types=1);

namespace Jengo\Ai\Enums;

enum Provider: string
{
    case OPENAI = 'openai';
    case ANTHROPIC = 'anthropic';
    case GEMINI = 'gemini';
    case DEEPSEEK = 'deepseek';
    case GROQ = 'groq';
    case MISTRAL = 'mistral';
    case OLLAMA = 'ollama';
    case FAKE = 'fake';

    public function label(): string
    {
        return match ($this) {
            self::OPENAI => 'OpenAI',
            self::ANTHROPIC => 'Anthropic Claude',
            self::GEMINI => 'Google Gemini',
            self::DEEPSEEK => 'DeepSeek',
            self::GROQ => 'Groq',
            self::MISTRAL => 'Mistral AI',
            self::OLLAMA => 'Ollama (Local)',
            self::FAKE => 'In-Memory Testing Fake',
        };
    }
}
