<?php

declare(strict_types=1);

namespace Tests\Unit;

use Jengo\Ai\Support\VectorMath;
use PHPUnit\Framework\TestCase;

class VectorMathTest extends TestCase
{
    public function testCosineSimilarityIdenticalVectors(): void
    {
        $a = [1.0, 2.0, 3.0];
        $b = [1.0, 2.0, 3.0];

        $similarity = VectorMath::cosineSimilarity($a, $b);
        $this->assertEqualsWithDelta(1.0, $similarity, 0.0001);
    }

    public function testCosineSimilarityOrthogonalVectors(): void
    {
        $a = [1.0, 0.0];
        $b = [0.0, 1.0];

        $similarity = VectorMath::cosineSimilarity($a, $b);
        $this->assertEqualsWithDelta(0.0, $similarity, 0.0001);
    }

    public function testCosineSimilarityOppositeVectors(): void
    {
        $a = [1.0, 0.0];
        $b = [-1.0, 0.0];

        $similarity = VectorMath::cosineSimilarity($a, $b);
        $this->assertEqualsWithDelta(-1.0, $similarity, 0.0001);
    }

    public function testDotProduct(): void
    {
        $a = [1.0, 2.0, 3.0];
        $b = [4.0, 5.0, 6.0];

        $dot = VectorMath::dotProduct($a, $b);
        $this->assertSame(32.0, $dot); // 1*4 + 2*5 + 3*6 = 4 + 10 + 18 = 32
    }

    public function testEuclideanDistance(): void
    {
        $a = [0.0, 0.0];
        $b = [3.0, 4.0];

        $dist = VectorMath::euclideanDistance($a, $b);
        $this->assertEqualsWithDelta(5.0, $dist, 0.0001);
    }

    public function testTopKSearch(): void
    {
        $query = [1.0, 0.0];
        $candidates = [
            'doc_a' => [0.9, 0.1],
            'doc_b' => [0.0, 1.0],
            'doc_c' => [0.99, 0.01],
            'doc_d' => [-0.5, 0.5],
        ];

        $top = VectorMath::topK($query, $candidates, k: 2);

        $this->assertCount(2, $top);
        $keys = array_keys($top);
        $this->assertSame('doc_c', $keys[0]);
        $this->assertSame('doc_a', $keys[1]);
    }

    public function testZeroMagnitudeAndMismatchedVectors(): void
    {
        // Zero magnitude
        $zero = [0.0, 0.0, 0.0];
        $vec = [1.0, 2.0, 3.0];
        $this->assertSame(0.0, VectorMath::cosineSimilarity($zero, $vec));
        $this->assertSame(0.0, VectorMath::cosineSimilarity($vec, $zero));

        // Mismatched dimensions
        $this->assertSame(0.0, VectorMath::cosineSimilarity([1.0], [1.0, 2.0]));
        $this->assertSame(0.0, VectorMath::dotProduct([1.0], [1.0, 2.0]));
        $this->assertSame(0.0, VectorMath::euclideanDistance([1.0], [1.0, 2.0]));
    }

    public function testChunkTextEdgeCases(): void
    {
        // Empty string
        $this->assertSame([], VectorMath::chunkText(''));
        $this->assertSame([], VectorMath::chunkText('   '));

        // Short string fits in one chunk
        $short = 'Hello world';
        $this->assertSame(['Hello world'], VectorMath::chunkText($short, chunkSize: 50));

        // Long text chunking with overlap
        $long = str_repeat('ABCDEFGHIJ ', 20); // 220 chars
        $chunks = VectorMath::chunkText($long, chunkSize: 50, overlap: 10);
        $this->assertGreaterThan(1, count($chunks));
        foreach ($chunks as $c) {
            $this->assertLessThanOrEqual(50, mb_strlen($c));
        }
    }
}
