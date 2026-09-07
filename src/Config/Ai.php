<?php

declare(strict_types=1);

namespace Jengo\Ai\Config;

use CodeIgniter\Config\BaseConfig;

class Ai extends BaseConfig
{
    /**
     * Default AI Provider Driver ('openai', 'anthropic', 'gemini', 'deepseek', 'groq', 'ollama').
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

    public function __construct()
    {
        parent::__construct();

        // Populate credentials from environment variables if set
        if (isset($_ENV['OPENAI_API_KEY']) || isset($_SERVER['OPENAI_API_KEY'])) {
            $this->providers['openai']['key'] = (string) ($_ENV['OPENAI_API_KEY'] ?? $_SERVER['OPENAI_API_KEY']);
        }
        if (isset($_ENV['ANTHROPIC_API_KEY']) || isset($_SERVER['ANTHROPIC_API_KEY'])) {
            $this->providers['anthropic']['key'] = (string) ($_ENV['ANTHROPIC_API_KEY'] ?? $_SERVER['ANTHROPIC_API_KEY']);
        }
        if (isset($_ENV['GEMINI_API_KEY']) || isset($_SERVER['GEMINI_API_KEY'])) {
            $this->providers['gemini']['key'] = (string) ($_ENV['GEMINI_API_KEY'] ?? $_SERVER['GEMINI_API_KEY']);
        }
        if (isset($_ENV['DEEPSEEK_API_KEY']) || isset($_SERVER['DEEPSEEK_API_KEY'])) {
            $this->providers['deepseek']['key'] = (string) ($_ENV['DEEPSEEK_API_KEY'] ?? $_SERVER['DEEPSEEK_API_KEY']);
        }
        if (isset($_ENV['GROQ_API_KEY']) || isset($_SERVER['GROQ_API_KEY'])) {
            $this->providers['groq']['key'] = (string) ($_ENV['GROQ_API_KEY'] ?? $_SERVER['GROQ_API_KEY']);
        }
        if (isset($_ENV['OLLAMA_BASE_URL']) || isset($_SERVER['OLLAMA_BASE_URL'])) {
            $this->providers['ollama']['base_url'] = (string) ($_ENV['OLLAMA_BASE_URL'] ?? $_SERVER['OLLAMA_BASE_URL']);
        }
    }
}
