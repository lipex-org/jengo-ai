<?php

declare(strict_types=1);

namespace Jengo\Ai\Drivers;

class GroqDriver extends OpenAiDriver
{
    /**
     * @param array<string, mixed> $config
     */
    public function __construct(array $config = [])
    {
        $defaultConfig = [
            'base_url' => 'https://api.groq.com/openai/v1',
            'model'    => 'llama-3.3-70b-versatile',
        ];

        parent::__construct(array_merge($defaultConfig, $config));
    }
}
