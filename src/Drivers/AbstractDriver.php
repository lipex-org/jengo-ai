<?php

declare(strict_types=1);

namespace Jengo\Ai\Drivers;

use Config\Services;
use Generator;
use Jengo\Ai\Contracts\DriverInterface;
use Jengo\Ai\Exceptions\AuthenticationException;
use Jengo\Ai\Exceptions\DriverException;
use Jengo\Ai\Exceptions\RateLimitException;
use Throwable;

abstract class AbstractDriver implements DriverInterface
{
    /**
     * @param array<string, mixed> $config
     */
    public function __construct(
        protected array $config = []
    ) {
    }

    /**
     * Get the driver configuration or a specific key.
     */
    public function getConfig(?string $key = null, mixed $default = null): mixed
    {
        if ($key === null) {
            return $this->config;
        }

        return $this->config[$key] ?? $default;
    }

    /**
     * Execute a JSON HTTP request with retries and exponential backoff.
     *
     * @param array<string, string> $headers
     * @param array<string, mixed> $body
     * @return array<string, mixed>
     *
     * @throws AuthenticationException
     * @throws RateLimitException
     * @throws DriverException
     */
    protected function postJson(string $url, array $headers = [], array $body = []): array
    {
        $maxRetries = (int) ($this->config['retry'] ?? 2);
        $timeout = (int) ($this->config['timeout'] ?? 30);
        $attempt = 0;

        $payload = !empty($body) ? (string) json_encode($body, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) : '{}';

        $normalizedHeaders = [
            'Content-Type' => 'application/json',
            'Accept'       => 'application/json',
        ];

        foreach ($headers as $key => $val) {
            if (is_int($key)) {
                $parts = explode(':', (string) $val, 2);
                if (count($parts) === 2) {
                    $normalizedHeaders[trim($parts[0])] = trim($parts[1]);
                }
            } else {
                $normalizedHeaders[$key] = (string) $val;
            }
        }

        while (true) {
            try {
                $client = Services::curlrequest([
                    'timeout'         => (float) $timeout,
                    'connect_timeout' => 10.0,
                    'http_errors'     => false,
                ]);

                $response = $client->post($url, [
                    'headers' => $normalizedHeaders,
                    'body'    => $payload,
                ]);

                $httpCode    = $response->getStatusCode();
                $rawResponse = (string) $response->getBody();
            } catch (Throwable $e) {
                if ($attempt < $maxRetries) {
                    $attempt++;
                    usleep((int) (pow(2, $attempt) * 100000)); // Exponential backoff (0.2s, 0.4s...)
                    continue;
                }
                throw DriverException::networkError("cURL Request Error: " . $e->getMessage());
            }

            if ($httpCode >= 200 && $httpCode < 300) {
                $decoded = json_decode($rawResponse, true);
                if (json_last_error() !== JSON_ERROR_NONE || !is_array($decoded)) {
                    throw DriverException::invalidResponse("Invalid JSON response from driver: " . substr($rawResponse, 0, 200));
                }
                return $decoded;
            }

            // Handle Rate Limit (429)
            if ($httpCode === 429) {
                if ($attempt < $maxRetries) {
                    $attempt++;
                    usleep((int) (pow(2, $attempt) * 200000));
                    continue;
                }
                throw RateLimitException::providerLimit("Rate limit exceeded (HTTP 429): " . $rawResponse);
            }

            // Handle Authentication Failure (401, 403)
            if ($httpCode === 401 || $httpCode === 403) {
                throw AuthenticationException::invalidKey("Authentication failed (HTTP {$httpCode}): " . $rawResponse);
            }

            // Transient server errors (500, 502, 503, 504)
            if ($httpCode >= 500 && $attempt < $maxRetries) {
                $attempt++;
                usleep((int) (pow(2, $attempt) * 150000));
                continue;
            }

            throw DriverException::apiError("API request to {$url} failed with HTTP {$httpCode}: " . $rawResponse, $httpCode);
        }
    }

    /**
     * Execute a streaming HTTP request yielding raw SSE data lines or chunks.
     * Note: Low-level cURL multi-handle is retained here as CodeIgniter's CURLRequest
     * buffers the complete HTTP response body synchronously and does not support
     * generator-driven chunked SSE streaming.
     *
     * @param array<string, string> $headers
     * @param array<string, mixed> $body
     * @return Generator<int, string>
     */
    protected function postStream(string $url, array $headers = [], array $body = []): Generator
    {
        $timeout = (int) ($this->config['timeout'] ?? 120);
        $payload = (string) json_encode($body, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

        $defaultHeaders = [
            'Content-Type: application/json',
            'Accept: text/event-stream',
        ];
        $allHeaders = array_merge($defaultHeaders, $headers);

        $buffer = '';
        /** @var array<int, string> $queue */
        $queue = [];

        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL            => $url,
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => $payload,
            CURLOPT_HTTPHEADER     => $allHeaders,
            CURLOPT_RETURNTRANSFER => false,
            CURLOPT_TIMEOUT        => $timeout,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_SSL_VERIFYHOST => 2,
            CURLOPT_WRITEFUNCTION  => function ($ch, string $data) use (&$buffer, &$queue): int {
                $buffer .= $data;
                while (($pos = strpos($buffer, "\n")) !== false) {
                    $line = substr($buffer, 0, $pos);
                    $buffer = substr($buffer, $pos + 1);
                    $line = trim($line);
                    if ($line !== '') {
                        $queue[] = $line;
                    }
                }
                return strlen($data);
            },
        ]);

        // Run curl asynchronously using curl_multi to yield generator lines
        $mh = curl_multi_init();
        curl_multi_add_handle($mh, $ch);

        $active = null;
        do {
            $mrc = curl_multi_exec($mh, $active);
            while (!empty($queue)) {
                $line = array_shift($queue);
                yield $line;
            }
            if ($active) {
                curl_multi_select($mh, 0.05);
            }
        } while ($active && $mrc === CURLM_OK);

        if (!empty($buffer)) {
            $line = trim($buffer);
            if ($line !== '') {
                yield $line;
            }
        }

        curl_multi_remove_handle($mh, $ch);
        curl_multi_close($mh);
        curl_close($ch);
    }
}
