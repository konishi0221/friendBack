<?php
require_once dirname(__DIR__).'/public/core/config.php';
require_once __DIR__.'/cros.php';
require_once __DIR__.'/chat/ChatService.php';

ini_set('upload_max_filesize', '10M');
ini_set('post_max_size', '10M');
ini_set('max_execution_time', '300');

$uploadsDir = dirname(__DIR__) . '/public/uploads';
if (!file_exists($uploadsDir)) {
    mkdir($uploadsDir, 0755, true);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $userId = $_POST['userId'] ?? '';
    $message = $_POST['message'] ?? '';
    $latitude = $_POST['latitude'] ?? null;
    $longitude = $_POST['longitude'] ?? null;
    
    if (!$userId) {
        http_response_code(400);
        echo json_encode(['error' => 'Missing userId parameter']);
        exit;
    }
    
    $chat = new ChatService($userId);
    
    if (!isset($_FILES['file']) || $_FILES['file']['error'] !== UPLOAD_ERR_OK) {
        http_response_code(400);
        $errorMessage = isset($_FILES['file']) ? uploadErrorMessage($_FILES['file']['error']) : 'No file uploaded';
        echo json_encode(['error' => $errorMessage]);
        exit;
    }
    
    $file = $_FILES['file'];
    $fileName = $file['name'];
    $fileTmpPath = $file['tmp_name'];
    $fileSize = $file['size'];
    $fileType = $file['type'];
    
    $fileExtension = pathinfo($fileName, PATHINFO_EXTENSION);
    $newFileName = uniqid('file_') . '_' . $userId . '.' . $fileExtension;
    $uploadFilePath = $uploadsDir . '/' . $newFileName;
    
    if (move_uploaded_file($fileTmpPath, $uploadFilePath)) {
        $fileUrl = '/uploads/' . $newFileName;
        $fileInfo = [
            'name' => $fileName,
            'type' => $fileType,
            'size' => $fileSize,
            'url' => $fileUrl,
            'path' => $uploadFilePath
        ];
        
        $context = [
            'file' => $fileInfo
        ];
        
        if ($latitude && $longitude) {
            $context['location'] = [
                'latitude' => $latitude,
                'longitude' => $longitude
            ];
        }
        
        $context['timestamp'] = date('c');
        
        $fileMessage = $message ? $message : "ファイルを分析してください: {$fileName}";
        $response = $chat->ask($fileMessage, $context);
        
        $response['file'] = $fileInfo;
        
        echo json_encode($response);
    } else {
        http_response_code(500);
        echo json_encode(['error' => 'Failed to save uploaded file']);
    }
} else {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
}

function uploadErrorMessage($errorCode) {
    $errors = [
        UPLOAD_ERR_INI_SIZE => 'The uploaded file exceeds the upload_max_filesize directive in php.ini',
        UPLOAD_ERR_FORM_SIZE => 'The uploaded file exceeds the MAX_FILE_SIZE directive in the HTML form',
        UPLOAD_ERR_PARTIAL => 'The uploaded file was only partially uploaded',
        UPLOAD_ERR_NO_FILE => 'No file was uploaded',
        UPLOAD_ERR_NO_TMP_DIR => 'Missing a temporary folder',
        UPLOAD_ERR_CANT_WRITE => 'Failed to write file to disk',
        UPLOAD_ERR_EXTENSION => 'A PHP extension stopped the file upload'
    ];
    
    return $errors[$errorCode] ?? 'Unknown upload error';
}
