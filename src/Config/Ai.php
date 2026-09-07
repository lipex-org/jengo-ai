<?php

declare(strict_types=1);

namespace Jengo\Ai\Config;

use CodeIgniter\Config\BaseConfig;

class Ai extends BaseConfig
{
    /**
     * Default AI Provider Driver ('openai', 'anthropic', 'gemini', 'deepseek', 'groq', 'openrouter', 'ollama').
     */
    public string $default = 'openai';

    /**
     * Provider API Credentials and Options.
     *
     * @var array<string, array<string, mixed>>
     */
    public array $providers = [
        'openai' => [
            'key'          => '',
            'organization' => null,
            'model'        => 'gpt-4o-mini',
            'base_url'     => 'https://api.openai.com/v1',
            'timeout'      => 30,
            'retry'        => 3,
        ],
        'anthropic' => [
            'key'      => '',
            'model'    => 'claude-3-5-sonnet-20241022',
            'base_url' => 'https://api.anthropic.com/v1',
            'timeout'  => 30,
            'retry'    => 2,
        ],
        'gemini' => [
            'key'      => '',
            'model'    => 'gemini-2.0-flash',
            'base_url' => 'https://generativelanguage.googleapis.com/v1beta',
            'timeout'  => 30,
            'retry'    => 2,
        ],
        'deepseek' => [
            'key'      => '',
            'model'    => 'deepseek-chat',
            'base_url' => 'https://api.deepseek.com/v1',
            'timeout'  => 60,
            'retry'    => 2,
        ],
        'groq' => [
            'key'      => '',
            'model'    => 'llama-3.3-70b-versatile',
            'base_url' => 'https://api.groq.com/openai/v1',
            'timeout'  => 15,
            'retry'    => 2,
        ],
        'openrouter' => [
            'key'       => '',
            'model'     => 'openai/gpt-4o-mini',
            'base_url'  => 'https://openrouter.ai/api/v1',
            'site_url'  => '',
            'site_name' => 'Jengo AI',
            'timeout'   => 60,
            'retry'     => 2,
        ],
        'ollama' => [
            'base_url' => 'http://localhost:11434',
            'model'    => 'llama3.2',
            'timeout'  => 120,
            'retry'    => 1,
        ],
    ];

    /**
     * Global generation defaults.
     *
     * @var array<string, mixed>
     */
    public array $defaults = [
        'temperature' => 0.7,
        'max_tokens'  => 2048,
        'max_steps'   => 5, // Maximum recursive tool-call steps in agentic mode
    ];
}
