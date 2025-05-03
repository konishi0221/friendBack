<?php
/* =======================================================================
   ChatService.php ― GPT-4o function-calling for personal AI assistant
   with Firestore vector database integration
   ======================================================================= */
declare(strict_types=1);

require_once dirname(__DIR__) . '/../public/core/FirestoreDB.php';
require_once dirname(__DIR__) . '/../public/core/VectorEmbedding.php';

class ChatService
{
    /* ------------ Properties ------------ */
    private array  $hist = [];          // user / assistant / function
    private array  $memory = [];        // compressed memories with vector embeddings
    private string $userId;
    private string $apiKey;             // OpenAI API key
    private FirestoreDB $db;            // Firestore database
    private VectorEmbedding $embedding; // Vector embedding generator

    /* ------------ Constructor ------------ */
    public function __construct(string $userId)
    {
        $this->userId = $userId;
        $this->apiKey = defined('OPENAI_API_KEY')
                        ? OPENAI_API_KEY
                        : (getenv('OPENAI_API_KEY') ?: 'sk-dummy-key-for-testing');

        $projectId = getenv('GOOGLE_CLOUD_PROJECT') ?: 'personal-ai-assistant';
        $keyFilePath = getenv('GOOGLE_APPLICATION_CREDENTIALS') ?: null;
        $this->db = FirestoreDB::getInstance($projectId, $keyFilePath);
        
        $this->embedding = VectorEmbedding::getInstance($this->apiKey);

        /* Load conversation history from Firestore (with session fallback) */
        $this->hist = $this->db->getConversationHistory($userId);
        
        /* Load compressed memories from Firestore (with session fallback) */
        $this->memory = $this->db->getMemory($userId);
    }

    /* ------------ History accessors ------------ */
    public function getHistory(): array { return $this->hist; }

    private function saveHistory(): void
    {
        if ($this->hist) {
            $success = $this->db->saveConversationHistory($this->userId, array_slice($this->hist, -40));
            
        }
    }

    private function saveMemory(): void
    {
        if ($this->memory) {
            $success = $this->db->saveMemory($this->userId, $this->memory);
            
        }
    }

    /* ------------ Build system prompt ------------ */
    private function buildSystemPrompt(): string
    {
        $path = dirname(__DIR__).'/prompts/chat_system.txt';

        $customPrompt = $this->db->getCustomPrompt($this->userId);
        
        $tmpl = '';
        if ($customPrompt) {
            $tmpl = $customPrompt;
        } else {
            $tmpl = is_file($path) ? file_get_contents($path) : '';
            if ($tmpl === false) $tmpl = '';
        }

        /* Get context from Firestore (with session fallback) */
        $ctx = $this->db->getContext($this->userId);
        
        /* Get relevant memories based on vector similarity */
        $relevantMemories = $this->getRelevantMemories();

        return $tmpl
            ."\n\n<!--memories-->\n".json_encode($relevantMemories, JSON_UNESCAPED_UNICODE)
            ."\n\n<!--user_ctx-->\n".json_encode($ctx, JSON_UNESCAPED_UNICODE);
    }
    
    /* ------------ Get relevant memories based on vector similarity ------------ */
    private function getRelevantMemories(int $limit = 5): array
    {
        if (empty($this->hist)) {
            return $this->memory;
        }
        
        $recentMessages = [];
        $count = 0;
        
        for ($i = count($this->hist) - 1; $i >= 0; $i--) {
            if ($this->hist[$i]['role'] === 'user') {
                $recentMessages[] = $this->hist[$i]['content'];
                $count++;
                
                if ($count >= 3) {
                    break;
                }
            }
        }
        
        if (empty($recentMessages)) {
            return $this->memory;
        }
        
        $queryText = implode("\n", $recentMessages);
        $queryEmbedding = $this->embedding->generateEmbedding($queryText);
        
        if ($queryEmbedding === null) {
            return $this->memory;
        }
        
        $memoriesWithEmbeddings = [];
        
        foreach ($this->memory as $memory) {
            if (!isset($memory['embedding'])) {
                $summary = $memory['summary'] ?? '';
                
                if ($summary) {
                    $memory['embedding'] = $this->embedding->generateEmbedding($summary);
                }
            }
            
            if (isset($memory['embedding'])) {
                $memoriesWithEmbeddings[] = $memory;
            }
        }
        
        $similarMemories = VectorEmbedding::findSimilarMemories(
            $memoriesWithEmbeddings,
            $queryEmbedding,
            $limit
        );
        
        foreach ($similarMemories as &$memory) {
            unset($memory['embedding']);
            unset($memory['similarity']);
        }
        
        return $similarMemories;
    }

    /* =========================================================
       ask() ― GPT-4o + function-calling (3 turn limit, compressMemory not counted)
       ========================================================= */
    public function ask(string $userMessage, array $ctx = [], string $interactionType = 'chat'): array
    {
        if ($userMessage === '') {
            return ['message' => '', 'via_tool' => false];
        }

        /* ------ Merge additional history from ctx ------ */
        if (!empty($ctx['messages']) && is_array($ctx['messages'])) {
            $add = array_map(static function ($m) {
                if (isset($m['function_call'])) {
                    return ['role' => 'assistant', 'function_call' => $m['function_call']];
                }
                return [
                    'role'    => ($m['role'] === 'bot' ? 'assistant' : 'user'),
                    'content' => $m['text'] ?? ''
                ];
            }, $ctx['messages']);

            while ($add && $this->hist &&
                   end($add)['role']    === end($this->hist)['role'] &&
                   end($add)['content'] === end($this->hist)['content']) {
                array_pop($add);
            }
            $this->hist = array_merge($this->hist, $add);
        }

        /* ------ Add latest user message to history ------ */
        $this->hist[] = ['role' => 'user', 'content' => $userMessage];
        
        /* ------ Add interaction type to context ------ */
        $context = $this->db->getContext($this->userId);
        $context['interactionType'] = $interactionType;
        $this->db->saveContext($this->userId, $context);

        /* ------ 1st call ------ */
        $messages = [['role' => 'system', 'content' => $this->buildSystemPrompt()]];
        foreach (array_slice($this->hist, -20) as $m) $messages[] = $m;

        $first = json_decode($this->callOpenAI($messages, $this->toolDefinition()), true);
        if (!isset($first['choices'][0]['message'])) {
            return ['message' => 'API Error', 'via_tool' => false, 'error' => $first];
        }

        /* ------ Loop (max 3 turns) ------ */
        $botText     = '';
        $toolContent = '{}';
        $memoryCompressed = false;
        $loop        = 0;

        while ($loop < 3) {
            $msg = $first['choices'][0]['message'];

            /* If content is received, we're done */
            if (!isset($msg['function_call'])) {
                $botText = $msg['content'] ?? '';
                break;
            }

            /* ---------- Execute tool ---------- */
            $fn   = $msg['function_call']['name'] ?? '';
            $args = json_decode($msg['function_call']['arguments'] ?? '{}', true);
            $incrementLoop = true;   // compressMemory will be false

            switch ($fn) {
                case 'getMemory':
                    $toolContent = json_encode($this->memory, JSON_UNESCAPED_UNICODE);
                    break;

                case 'compressMemory':
                    $summary = $args['summary'] ?? '';
                    
                    $embedding = $this->embedding->generateEmbedding($summary);
                    
                    $this->memory[] = [
                        'summary' => $summary,
                        'timestamp' => date('Y-m-d H:i:s'),
                        'embedding' => $embedding
                    ];
                    
                    $this->saveMemory();
                    $memoryCompressed = true;
                    $toolContent = '{"status":"compressed"}';
                    $incrementLoop = false;              /* Don't count this turn */
                    break;

                case 'updateCtx':
                    $context = $this->db->getContext($this->userId);
                    $updatedContext = array_merge($context, $args);
                    $this->db->saveContext($this->userId, $updatedContext);
                    $toolContent = '{"status":"ok"}';
                    break;
                    
                case 'updateSystemPrompt':
                    $prompt = $args['prompt'] ?? '';
                    if ($prompt) {
                        $this->db->saveCustomPrompt($this->userId, $prompt);
                        $toolContent = '{"status":"prompt_updated"}';
                    } else {
                        $toolContent = '{"error":"empty_prompt"}';
                    }
                    break;

                default:
                    $toolContent = '{"error":"unknown_function"}';
            }

            /* Add assistant(function_call) → function(result) to history */
            $this->hist[] = ['role' => 'assistant', 'function_call' => $msg['function_call']];
            $this->hist[] = ['role' => 'function',  'name' => $fn, 'content' => $toolContent];

            if ($incrementLoop) $loop++;                 /* compressMemory doesn't count */

            /* Next turn */
            if ($loop >= 3) break;                       /* Exit if limit reached */

            $messages = array_merge($messages, array_slice($this->hist, -2));
            $first    = json_decode($this->callOpenAI($messages, $this->toolDefinition()), true);
        }

        /* ------ Add final response to history ------ */
        if ($botText === '') $botText = 'I need a moment to process that.';
        $this->hist[] = ['role' => 'assistant', 'content' => $botText];
        $this->saveHistory();

        return [
            'message'      => $botText,
            'via_tool'     => true,
            'memory_json'  => json_decode($toolContent, true),
            'memory_compressed' => $memoryCompressed
        ];
    }

    /* ------------ OpenAI API call ------------ */
    private function callOpenAI(array $messages, array $tools): string
    {
        $isValidKey = (strpos($this->apiKey, 'sk-') === 0) && ($this->apiKey !== 'sk-dummy-key-for-testing');
        
        error_log("API Key Status: " . ($isValidKey ? "Valid key found" : "Using dummy key"));
        
        if (!$isValidKey) {
            return json_encode([
                'choices' => [
                    [
                        'message' => [
                            'content' => 'This is a simulated response since we are using a dummy API key. In production, this would be a real response from the selected model. To use the real API, set OPENAI_API_KEY in your .env file.'
                        ]
                    ]
                ]
            ]);
        }
        
        $context = $this->db->getContext($this->userId);
        $model = $context['model'] ?? 'gpt-4o'; // Default to GPT-4o if not specified
        
        if ($model !== 'gpt-3.5-turbo' && $model !== 'gpt-4o') {
            $model = 'gpt-4o'; // Default to GPT-4o if invalid model
        }
        
        $body = [
            'model'       => $model,
            'messages'    => $messages,
            'temperature' => 0.7
        ];
        if ($tools) {
            $body['functions']     = array_column($tools, 'function');
            $body['function_call'] = 'auto';
        }

        error_log("Sending request to OpenAI API. Model: " . $model);
        
        $ch = curl_init('https://api.openai.com/v1/chat/completions');
        curl_setopt_array($ch, [
            CURLOPT_HTTPHEADER     => [
                'Content-Type: application/json',
                'Authorization: Bearer '.$this->apiKey
            ],
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => json_encode($body, JSON_UNESCAPED_UNICODE)
        ]);

        $response = curl_exec($ch);
        $err      = curl_error($ch);
        $info     = curl_getinfo($ch);
        curl_close($ch);

        /* ---- Debug logging ---- */
        if ($err) {
            error_log("CURL_ERR: ".$err);
            return json_encode(['error'=>'CURL error: '.$err]);
        }
        if ($info['http_code'] !== 200) {
            error_log("HTTP=".$info['http_code']." BODY=".$response);
            return json_encode(['error'=>'API error: '.$response]);
        }
        if (!$response) {
            error_log("EMPTY response");
            return json_encode(['error'=>'Empty response']);
        }

        error_log("Received successful response from OpenAI API");
        return $response;
    }

    /* ------------ Tool definitions ------------ */
    private function toolDefinition(): array
    {
        return [
            [ 'type'=>'function','function'=>[
                'name'=>'getMemory',
                'description'=>'Retrieve compressed memories from previous conversations',
                'parameters'=>[
                    'type'=>'object',
                    'properties'=>[
                        'limit'=>[
                            'type'=>'integer',
                            'description'=>'Maximum number of memories to retrieve',
                            'default'=>10
                        ]
                    ]
                ]
            ]],
            [ 'type'=>'function','function'=>[
                'name'=>'compressMemory',
                'description'=>'Compress and store important information from the conversation',
                'parameters'=>[
                    'type'=>'object',
                    'properties'=>[
                        'summary'=>['type'=>'string']
                    ],
                    'required'=>['summary']
                ]
            ]],
            [ 'type'=>'function','function'=>[
                'name'=>'updateCtx',
                'description'=>'Update user context information',
                'parameters'=>[
                    'type'=>'object',
                    'properties'=>[
                        'name'      =>['type'=>'string','maxLength'=>30],
                        'preferences'=>['type'=>'string']
                    ]
                ]
            ]],
            [ 'type'=>'function','function'=>[
                'name'=>'updateSystemPrompt',
                'description'=>'Update the system prompt to better understand the user. Use this to customize how you interact with the user based on their personality, preferences, and communication style.',
                'parameters'=>[
                    'type'=>'object',
                    'properties'=>[
                        'prompt'=>['type'=>'string', 'description'=>'The new system prompt to use for future interactions']
                    ],
                    'required'=>['prompt']
                ]
            ]]
        ];
    }

    /* ------------ Utility methods ------------ */
    private function isJson(string $s): bool
    {
        json_decode($s);
        return json_last_error() === JSON_ERROR_NONE;
    }
}
