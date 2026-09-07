<?php

declare(strict_types=1);

namespace Jengo\Ai\Responses;

use Jengo\Ai\Support\VectorMath;
use JsonSerializable;

class EmbeddingResponse implements JsonSerializable
{
    /**
     * @param array<int, array<int, float>> $embeddings List of float vectors
     */
    public function __construct(
        public array $embeddings = [],
        public Usage $usage = new Usage(),
        public ?string $model = null,
        public array $rawResponse = []
    ) {
    }

    /**
     * Get the single primary embedding vector (for single text inputs).
     *
     * @return array<int, float>
     */
    public function vector(): array
    {
        return $this->embeddings[0] ?? [];
    }

    /**
     * Alias for vector().
     *
     * @return array<int, float>
     */
    public function first(): array
    {
        return $this->vector();
    }

    /**
     * Get all embedding vectors.
     *
     * @return array<int, array<int, float>>
     */
    public function vectors(): array
    {
        return $this->embeddings;
    }

    /**
     * Alias for vectors().
     *
     * @return array<int, array<int, float>>
     */
    public function all(): array
    {
        return $this->vectors();
    }

    /**
     * Get the dimensionality of the vector space (e.g., 1536, 768, 3072).
     */
    public function dimensions(): int
    {
        return count($this->vector());
    }

    /**
     * Calculate cosine similarity between two vectors inside this batch.
     */
    public function similarity(int $indexA = 0, int $indexB = 1): float
    {
        $vecA = $this->embeddings[$indexA] ?? [];
        $vecB = $this->embeddings[$indexB] ?? [];

        return VectorMath::cosineSimilarity($vecA, $vecB);
    }

    public function jsonSerialize(): array
    {
        return [
            'model'      => $this->model,
            'dimensions' => $this->dimensions(),
            'count'      => count($this->embeddings),
            'embeddings' => $this->embeddings,
            'usage'      => $this->usage->toArray(),
        ];
    }
}
