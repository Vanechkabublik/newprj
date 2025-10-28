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

    $format = $_POST['format'] ?? null;
    $supportedFormats = $replicateService->getSupportedFormats($model);
    if ($format && !in_array($format, $supportedFormats)) {
        jsonResponse(false, null, "Unsupported format. Supported: " . implode(', ', $supportedFormats), 400);
    }

    // Загружаем в S3
    $s3Uploader = new S3Uploader();
    $s3Result = $s3Uploader->upload($_FILES['image']);

    // Отправляем в Replicate
    $service = new ReplicateService();
    $result = $service->createPrediction('ddcolor', [
        'image' => $s3Result['url']
    ], $format);

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