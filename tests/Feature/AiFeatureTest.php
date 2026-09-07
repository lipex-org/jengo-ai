<?php

declare(strict_types=1);

namespace Tests\Feature;

use Jengo\Ai\Ai;
use Jengo\Ai\Attributes\AiParameter;
use Jengo\Ai\Attributes\AiTool;
use Jengo\Ai\Installers\AiInstaller;
use Jengo\Ai\Responses\StreamResponse;
use Jengo\Ai\Testing\Concerns\AiTestAssertionsTrait;
use PHPUnit\Framework\TestCase;

class LeadParserService
{
    #[AiTool('Lookup CRM company by domain')]
    public function lookupCompany(
        #[AiParameter('Domain name')] string $domain
    ): array {
        return [
            'domain'   => $domain,
            'company'  => 'Acme Global',
            'industry' => 'Technology',
        ];
    }
}

class AiFeatureTest extends TestCase
{
    use AiTestAssertionsTrait;

    protected function tearDown(): void
    {
        Ai::resetFake();
        parent::tearDown();
    }

    public function testEndToEndStructuredOutputExtraction(): void
    {
        $fake = Ai::fake([
            json_encode([
                'name'              => 'Jane Doe',
                'company'           => 'Acme Corp',
                'email'             => 'jane@acme.com',
                'is_decision_maker' => true,
            ]),
        ]);

        $lead = Ai::prompt('Extract lead info from email signature: Jane Doe, VP Engineering at Acme Corp, jane@acme.com')
            ->schema([
                'name'              => 'string',
                'company'           => 'string',
                'email'             => 'email',
                'is_decision_maker' => 'bool',
            ])
            ->asArray();

        $this->assertSame('Jane Doe', $lead['name']);
        $this->assertSame('Acme Corp', $lead['company']);
        $this->assertSame('jane@acme.com', $lead['email']);
        $this->assertTrue($lead['is_decision_maker']);

        $fake->assertPromptSent('Extract lead info');
        $fake->assertCount(1);
    }

    public function testEndToEndAutonomousAgentToolLoop(): void
    {
        $fake = Ai::fakeSequence()
            ->pushToolCall('lookupCompany', ['domain' => 'acmeglobal.com'])
            ->push('Acme Global is in the Technology industry.');

        $service = new LeadParserService();

        $response = Ai::prompt('Find information for company domain acmeglobal.com')
            ->withToolsFrom($service)
            ->maxSteps(5)
            ->generate();

        $this->assertSame('Acme Global is in the Technology industry.', $response->text());
        $fake->assertToolCalled('lookupCompany', fn($args) => $args['domain'] === 'acmeglobal.com');
    }

    public function testEndToEndEmbeddingAndCosineSimilarity(): void
    {
        $fake = Ai::fake();
        $fake->pushEmbedding([1.0, 0.0, 0.0]);
        $fake->pushEmbedding([0.8, 0.6, 0.0]);

        $vec1 = Ai::embed('PHP framework architecture');
        $vec2 = Ai::embed('CodeIgniter 4 fullstack design');

        $similarity = Ai::similarity($vec1, $vec2);
        $this->assertEqualsWithDelta(0.8, $similarity, 0.01);
    }

    public function testAiInstallerMetadata(): void
    {
        $this->assertSame('ai', AiInstaller::name());
        $this->assertNotEmpty(AiInstaller::description());
        $this->assertNotEmpty(AiInstaller::reasonForSkipping());
    }
}
