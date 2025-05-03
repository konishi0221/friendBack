<?php
declare(strict_types=1);

class FirestoreDB
{
    private static ?FirestoreDB $instance = null;

    private function __construct()
    {
        // Simplified constructor with session storage only
    }
    
    public static function getInstance(string $projectId = 'personal-ai-assistant', ?string $keyFilePath = null): FirestoreDB
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        
        return self::$instance;
    }
    
    public function isAvailable(): bool
    {
        return false; // Always use session storage
    }
    
    public function getContext(string $userId): array
    {
        return $_SESSION["ctx_{$userId}"] ?? [
            'name' => 'ユーザー',
            'personality' => '親切で思いやりがある',
            'interests' => ['テクノロジー', '旅行', 'ビジネス']
        ];
    }
    
    public function getCustomPrompt(string $userId): ?string
    {
        return $_SESSION["custom_prompt_{$userId}"] ?? "あなたは、役立ち、親しみやすく、会話的であるように設計されたパーソナルAIアシスタントです。ユーザーの質問に丁寧に答え、必要な情報を提供してください。";
    }
    
    public function saveConversationHistory(string $userId, array $history): bool
    {
        $_SESSION["chat_{$userId}"] = array_slice($history, -40);
        return true;
    }
    
    public function getConversationHistory(string $userId): array
    {
        return $_SESSION["chat_{$userId}"] ?? [];
    }
    
    public function saveMemory(string $userId, array $memory): bool
    {
        $_SESSION["memory_{$userId}"] = $memory;
        return true;
    }
    
    public function getMemory(string $userId): array
    {
        return $_SESSION["memory_{$userId}"] ?? [];
    }
    
    public function saveContext(string $userId, array $context): bool
    {
        $_SESSION["ctx_{$userId}"] = $context;
        return true;
    }
    
    public function saveCustomPrompt(string $userId, string $prompt): bool
    {
        $_SESSION["custom_prompt_{$userId}"] = $prompt;
        return true;
    }
}
