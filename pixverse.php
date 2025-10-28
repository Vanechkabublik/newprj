<?php
require_once 'config.php';

header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit(0);
}

try {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        throw new Exception('Only POST method allowed');
    }

    if (empty($_FILES['image'])) {
        throw new Exception('Image file is required');
    }

    if (empty($_POST['prompt'])) {
        throw new Exception('Prompt is required');
    }

    // Валидация промпта
    $prompt = trim($_POST['prompt']);
    if (strlen($prompt) < 5) {
        throw new Exception('Prompt must be at least 5 characters');
    }
    if (strlen($prompt) > 1000) {
        throw new Exception('Prompt too long (max 1000 characters)');
    }

    // Загружаем в S3
    $s3Uploader = new S3Uploader();
    $s3Result = $s3Uploader->upload($_FILES['image']);

    // Отправляем в Replicate
    $service = new ReplicateService();
    $result = $service->createPrediction('pixverse', [
        'image' => $s3Result['url'],
        'prompt' => $prompt,
        'quality' => $service->formatQuality($_POST['quality'] ?? '720'),
        'aspect_ratio' => $_POST['aspect_ratio'] ?? '1:1',
        'duration' => intval($_POST['duration'] ?? 5)
    ]);

    jsonResponse(true, [
        'id' => $result['id'],
        'status' => $result['status'],
        'output_url' => $result['output'] ?? null,
        's3_url' => $s3Result['url']
    ]);

} catch (Exception $e) {
    jsonResponse(false, null, $e->getMessage(), 400);
}
?>