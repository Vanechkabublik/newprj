<?php
require_once 'config.php';

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');

try {
    // Только GET запросы
    if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
        throw new Exception('Only GET method allowed');
    }

    $predictionId = $_GET['id'] ?? '';
    
    if (empty($predictionId)) {
        throw new Exception('Prediction ID is required');
    }
    
    // Валидация ID
    if (!preg_match('/^[a-zA-Z0-9_\-]+$/', $predictionId)) {
        throw new Exception('Invalid prediction ID');
    }
    
    $service = new ReplicateService();
    $result = $service->getPrediction($predictionId);
    
    echo json_encode([
        'success' => true,
        'data' => [
            'id' => $result['id'],
            'status' => $result['status'],
            'output_url' => $result['output'] ?? null,
            'error' => $result['error'] ?? null
        ],
        'timestamp' => time()
    ]);
    
} catch (Exception $e) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'error' => [
            'code' => 'API_ERROR', 
            'message' => $e->getMessage()
        ],
        'timestamp' => time()
    ]);
}
?>