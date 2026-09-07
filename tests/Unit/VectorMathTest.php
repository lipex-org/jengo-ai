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
}
