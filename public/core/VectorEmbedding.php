<?php
declare(strict_types=1);

require_once __DIR__ . '/../../vendor/autoload.php';

use OpenAI\Client;
use OpenAI\Factory;

class VectorEmbedding
{
    private Client $client;
    private static ?VectorEmbedding $instance = null;
    private string $apiKey;
    private string $model = 'text-embedding-3-small';

    /**
     * Constructor - initializes OpenAI client
     * 
     * @param string $apiKey OpenAI API key
     */
    private function __construct(string $apiKey)
    {
        $this->apiKey = $apiKey;
        
        try {
            $this->client = (new Factory())->withApiKey($apiKey)->make();
        } catch (\Exception $e) {
            error_log("OpenAI client initialization error: " . $e->getMessage());
        }
    }
    
    /**
     * Get singleton instance
     * 
     * @param string $apiKey OpenAI API key
     * @return VectorEmbedding
     */
    public static function getInstance(string $apiKey): VectorEmbedding
    {
        if (self::$instance === null) {
            self::$instance = new self($apiKey);
        }
        
        return self::$instance;
    }
    
    /**
     * Generate embedding for text
     * 
     * @param string $text
     * @return array|null
     */
    public function generateEmbedding(string $text): ?array
    {
        if ($this->apiKey === 'sk-dummy-key-for-testing') {
            return array_fill(0, 1536, 0.0);
        }
        
        try {
            $response = $this->client->embeddings()->create([
                'model' => $this->model,
                'input' => $text,
            ]);
            
            return $response->embeddings[0]->embedding;
        } catch (\Exception $e) {
            error_log("OpenAI embedding error: " . $e->getMessage());
            return null;
        }
    }
    
    /**
     * Calculate cosine similarity between two vectors
     * 
     * @param array $a
     * @param array $b
     * @return float
     */
    public static function cosineSimilarity(array $a, array $b): float
    {
        if (count($a) !== count($b)) {
            return 0.0;
        }
        
        $dotProduct = 0.0;
        $normA = 0.0;
        $normB = 0.0;
        
        for ($i = 0; $i < count($a); $i++) {
            $dotProduct += $a[$i] * $b[$i];
            $normA += $a[$i] * $a[$i];
            $normB += $b[$i] * $b[$i];
        }
        
        if ($normA === 0.0 || $normB === 0.0) {
            return 0.0;
        }
        
        return $dotProduct / (sqrt($normA) * sqrt($normB));
    }
    
    /**
     * Find similar memories based on vector similarity
     * 
     * @param array $memories Array of memory objects with 'summary' and 'embedding' fields
     * @param array $queryEmbedding
     * @param int $limit
     * @return array
     */
    public static function findSimilarMemories(array $memories, array $queryEmbedding, int $limit = 5): array
    {
        if (empty($memories) || empty($queryEmbedding)) {
            return [];
        }
        
        $similarities = [];
        
        foreach ($memories as $index => $memory) {
            if (!isset($memory['embedding']) || !is_array($memory['embedding'])) {
                continue;
            }
            
            $similarity = self::cosineSimilarity($memory['embedding'], $queryEmbedding);
            $similarities[$index] = $similarity;
        }
        
        arsort($similarities);
        
        $result = [];
        $count = 0;
        
        foreach ($similarities as $index => $similarity) {
            if ($count >= $limit) {
                break;
            }
            
            $memory = $memories[$index];
            $memory['similarity'] = $similarity;
            $result[] = $memory;
            $count++;
        }
        
        return $result;
    }
}
