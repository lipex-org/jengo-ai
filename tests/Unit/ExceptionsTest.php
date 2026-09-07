<?php

declare(strict_types=1);

namespace Tests\Unit;

use Jengo\Ai\Exceptions\AiException;
use Jengo\Ai\Exceptions\AuthenticationException;
use Jengo\Ai\Exceptions\DriverException;
use Jengo\Ai\Exceptions\RateLimitException;
use Jengo\Ai\Exceptions\SchemaValidationException;
use PHPUnit\Framework\TestCase;

class ExceptionsTest extends TestCase
{
    public function testAiExceptionHierarchy(): void
    {
        $e = new AuthenticationException('Invalid key');
        $this->assertInstanceOf(AiException::class, $e);

        $authEx = AuthenticationException::invalidKey('Bad API key');
        $this->assertSame('Bad API key', $authEx->getMessage());

        $rateEx = RateLimitException::providerLimit('429 Too Many Requests', retryAfter: 30);
        $this->assertSame('429 Too Many Requests', $rateEx->getMessage());
        $this->assertSame(30, $rateEx->getRetryAfter());

        $driverEx = DriverException::networkError('Connection timeout');
        $this->assertSame('Connection timeout', $driverEx->getMessage());

        $schemaEx = SchemaValidationException::missingFields(['email', 'name']);
        $this->assertStringContainsString('email', $schemaEx->getMessage());
    }
}
