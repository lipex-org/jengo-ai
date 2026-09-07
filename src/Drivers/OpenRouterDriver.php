<?php

declare(strict_types=1);

namespace Jengo\Ai\Drivers;

class OpenRouterDriver extends OpenAiDriver
{
    /**
     * @param array<string, mixed> $config
     */
    public function __construct(array $config = [])
    {
        $defaultConfig = [
            'base_url'  => 'https://openrouter.ai/api/v1',
            'model'     => 'openai/gpt-4o-mini',
            'site_url'  => '',
            'site_name' => 'Jengo AI',
        ];

        parent::__construct(array_merge($defaultConfig, $config));
    }

    /**
     * @return array<string, string>
     */
    protected function buildHeaders(): array
    {
        $headers = parent::buildHeaders();

        $siteUrl = (string) ($this->config['site_url'] ?? $this->config['http_referer'] ?? '');
        if ($siteUrl !== '') {
            $headers[] = "HTTP-Referer: {$siteUrl}";
        }

        $siteName = (string) ($this->config['site_name'] ?? $this->config['app_name'] ?? '');
        if ($siteName !== '') {
            $headers[] = "X-Title: {$siteName}";
        }

        return $headers;
    }
}
