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

        // Support CodeIgniter 4's standard dot-notation environment overrides (.env)
        // e.g., ai.providers.openai.key, ai.providers.gemini.model, ai.defaults.temperature
        $envVars = array_merge($_SERVER, $_ENV);
        foreach ($envVars as $key => $value) {
            $lowerKey = strtolower((string) $key);
            if (!str_starts_with($lowerKey, 'ai.')) {
                continue;
            }

            $parts = explode('.', $lowerKey);

            // ai.providers.<provider>.<key>
            if (count($parts) === 4 && $parts[1] === 'providers') {
                $provider = $parts[2];
                $configKey = $parts[3];
                $this->providers[$provider][$configKey] = $this->castEnvValue($value);
            }
            // ai.defaults.<key>
            elseif (count($parts) === 3 && $parts[1] === 'defaults') {
                $defaultKey = $parts[2];
                $this->defaults[$defaultKey] = $this->castEnvValue($value);
            }
        }
    }

    protected function castEnvValue(mixed $value): mixed
    {
        if (!is_string($value)) {
            return $value;
        }

        $trimmed = trim($value);
        $lower = strtolower($trimmed);

        if ($lower === 'true' || $lower === '(true)') {
            return true;
        }
        if ($lower === 'false' || $lower === '(false)') {
            return false;
        }
        if ($lower === 'null' || $lower === '(null)') {
            return null;
        }
        if ($lower === 'empty' || $lower === '(empty)') {
            return '';
        }
        if (is_numeric($trimmed)) {
            return str_contains($trimmed, '.') ? (float) $trimmed : (int) $trimmed;
        }

        return $trimmed;
    }
}
