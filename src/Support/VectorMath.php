<?php

declare(strict_types=1);

namespace Jengo\Ai\Support;

class VectorMath
{
    /**
     * Compute the cosine similarity between two float vectors (-1.0 to 1.0, typically 0.0 to 1.0 for text).
     *
     * @param array<int, float> $vecA
     * @param array<int, float> $vecB
     */
    public static function cosineSimilarity(array $vecA, array $vecB): float
    {
        $count = count($vecA);
        if ($count !== count($vecB) || $count === 0) {
            return 0.0;
        }

        $dotProduct = 0.0;
        $normA = 0.0;
        $normB = 0.0;

        for ($i = 0; $i < $count; $i++) {
            $a = (float) $vecA[$i];
            $b = (float) $vecB[$i];

            $dotProduct += $a * $b;
            $normA += $a * $a;
            $normB += $b * $b;
        }

        if ($normA <= 0.0 || $normB <= 0.0) {
            return 0.0;
        }

        return $dotProduct / (sqrt($normA) * sqrt($normB));
    }

    /**
     * Compute the dot product of two vectors.
     *
     * @param array<int, float> $vecA
     * @param array<int, float> $vecB
     */
    public static function dotProduct(array $vecA, array $vecB): float
    {
        $count = count($vecA);
        if ($count !== count($vecB)) {
            return 0.0;
        }

        $dot = 0.0;
        for ($i = 0; $i < $count; $i++) {
            $dot += ((float) $vecA[$i]) * ((float) $vecB[$i]);
        }

        return $dot;
    }

    /**
     * Compute the Euclidean distance between two vectors.
     *
     * @param array<int, float> $vecA
     * @param array<int, float> $vecB
     */
    public static function euclideanDistance(array $vecA, array $vecB): float
    {
        $count = count($vecA);
        if ($count !== count($vecB)) {
            return 0.0;
        }

        $sum = 0.0;
        for ($i = 0; $i < $count; $i++) {
            $diff = ((float) $vecA[$i]) - ((float) $vecB[$i]);
            $sum += $diff * $diff;
        }

        return sqrt($sum);
    }

    /**
     * Find the top K most similar candidate vectors to a query vector using cosine similarity.
     *
     * @param array<int, float> $queryVector
     * @param array<string|int, array<int, float>> $candidates
     * @return array<string|int, float> Sorted descending by score
     */
    public static function topK(array $queryVector, array $candidates, int $k = 5): array
    {
        $scores = [];
        foreach ($candidates as $key => $candidateVector) {
            $scores[$key] = static::cosineSimilarity($queryVector, $candidateVector);
        }

        arsort($scores);

        return array_slice($scores, 0, $k, preserve_keys: true);
    }

    /**
     * Split a long text into overlapping chunks suitable for embedding generation.
     *
     * @return array<int, string>
     */
    public static function chunkText(string $text, int $chunkSize = 1000, int $overlap = 100): array
    {
        $text = trim($text);
        if (mb_strlen($text) <= $chunkSize) {
            return [$text];
        }

        $chunks = [];
        $length = mb_strlen($text);
        $step = max(1, $chunkSize - $overlap);

        for ($start = 0; $start < $length; $start += $step) {
            $chunk = mb_substr($text, $start, $chunkSize);
            if (trim($chunk) !== '') {
                $chunks[] = trim($chunk);
            }
            if ($start + $chunkSize >= $length) {
                break;
            }
        }

        return $chunks;
    }
}
