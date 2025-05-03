<?php
require_once dirname(__DIR__).'/public/core/config.php';
require_once __DIR__.'/cros.php';
require_once __DIR__.'/chat/ChatService.php';

$data = json_decode(file_get_contents('php://input'), true) ?: [];
$userId = $_GET['userId'] ?? $data['userId'] ?? '';

if (!$userId) {
    http_response_code(400);
    echo json_encode(['error' => 'Missing userId parameter']);
    exit;
}

$chat = new ChatService($userId);

switch ($_SERVER['REQUEST_METHOD']) {
    case 'GET':
        echo json_encode([
            'messages' => $chat->getHistory(),
            'userId' => $userId
        ]);
        break;
        
    case 'POST':
        $message = $data['message'] ?? '';
        $context = $data['context'] ?? [];
        $interactionType = $data['interactionType'] ?? 'chat';
        $perspective = $data['perspective'] ?? '';
        
        if (!$message) {
            http_response_code(400);
            echo json_encode(['error' => 'Missing message parameter']);
            exit;
        }
        
        if ($perspective) {
            $context['perspective'] = $perspective;
        }
        
        $response = $chat->ask($message, $context, $interactionType);
        echo json_encode($response);
        break;
        
    default:
        http_response_code(405);
        echo json_encode(['error' => 'Method not allowed']);
}
