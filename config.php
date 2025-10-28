<?php
class Env {
    private static $data = [];
    
    public static function load($file = '.env') {
        if (!file_exists($file)) return;
        
        $lines = file($file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        foreach ($lines as $line) {
            if (strpos(trim($line), '#') === 0) continue;
            
            list($key, $value) = explode('=', $line, 2);
            self::$data[trim($key)] = trim($value);
        }
    }
    
    public static function get($key, $default = null) {
        return self::$data[$key] ?? $default;
    }
}

Env::load(__DIR__ . '/.env');

class S3Uploader {
    private $uploadDir;
    
    public function __construct() {
        // Папка для загрузки файлов
        $this->uploadDir = __DIR__ . '/uploads/';
        
        // Создаем папку если не существует
        if (!file_exists($this->uploadDir)) {
            mkdir($this->uploadDir, 0755, true);
        }
    }
    
    public function upload($file) {
        // Валидация файла
        if ($file['error'] !== UPLOAD_ERR_OK) {
            throw new Exception('File upload error: ' . $file['error']);
        }
        
        // Проверка размера файла (максимум 10MB)
        if ($file['size'] > 10 * 1024 * 1024) {
            throw new Exception('File too large. Max 10MB allowed');
        }
        
        // Проверка типа файла
        $allowedTypes = ['image/jpeg', 'image/png', 'image/jpg', 'image/webp'];
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mime = finfo_file($finfo, $file['tmp_name']);
        finfo_close($finfo);
        
        if (!in_array($mime, $allowedTypes)) {
            throw new Exception('Invalid file type. Allowed: JPEG, PNG, WebP');
        }
        
        // Дополнительная проверка по расширению
        $extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        $allowedExtensions = ['jpg', 'jpeg', 'png', 'webp'];
        if (!in_array($extension, $allowedExtensions)) {
            throw new Exception('Invalid file extension. Allowed: jpg, jpeg, png, webp');
        }
        
        // Генерируем уникальное имя
        $filename = 'image_' . uniqid() . '_' . time() . '.' . $extension;
        $filePath = $this->uploadDir . $filename;
        
        // Перемещаем файл
        if (!move_uploaded_file($file['tmp_name'], $filePath)) {
            throw new Exception('Failed to move uploaded file');
        }
        
        // Проверяем что файл действительно изображение
        if (!$this->isValidImage($filePath)) {
            unlink($filePath); // Удаляем если не валидное изображение
            throw new Exception('Invalid image file');
        }
        
        return [
            'filename' => $filename,
            'path' => $filePath,
            'url' => $this->getFileUrl($filename),
            'size' => $file['size'],
            'type' => $mime
        ];
    }
    
    public function uploadFromUrl($url, $filename = null) {
        // Скачиваем файл с увеличенными таймаутами
        $context = stream_context_create([
            'http' => [
                'timeout' => 120, // 120 секунд таймаут
            ],
            'ssl' => [
                'verify_peer' => false,
                'verify_peer_name' => false,
            ]
        ]);
        
        $fileContent = file_get_contents($url, false, $context);
        if ($fileContent === false) {
            throw new Exception('Failed to download file from URL: ' . $url);
        }
        
        // Сохраняем во временный файл для проверки
        $tempFile = tempnam(sys_get_temp_dir(), 'download_');
        file_put_contents($tempFile, $fileContent);
        
        // Проверяем тип файла
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mime = finfo_file($finfo, $tempFile);
        finfo_close($finfo);
        
        $allowedTypes = ['image/jpeg', 'image/png', 'image/jpg', 'image/webp'];
        if (!in_array($mime, $allowedTypes)) {
            unlink($tempFile);
            throw new Exception('Invalid file type from URL. Allowed: JPEG, PNG, WebP');
        }
        
        // Определяем расширение
        $extension = $this->getExtensionFromMime($mime);
        
        // Генерируем имя файла если не предоставлено
        if (!$filename) {
            $filename = 'image_' . uniqid() . '_' . time() . '.' . $extension;
        }
        
        $filePath = $this->uploadDir . $filename;
        
        // Перемещаем файл из временной папки
        if (!rename($tempFile, $filePath)) {
            unlink($tempFile);
            throw new Exception('Failed to save downloaded file');
        }
        
        // Финальная проверка изображения
        if (!$this->isValidImage($filePath)) {
            unlink($filePath);
            throw new Exception('Invalid image file from URL');
        }
        
        return [
            'filename' => $filename,
            'path' => $filePath,
            'url' => $this->getFileUrl($filename),
            'size' => filesize($filePath),
            'type' => $mime
        ];
    }
    
    private function isValidImage($filePath) {
        $imageInfo = getimagesize($filePath);
        return $imageInfo !== false;
    }
    
    private function getExtensionFromMime($mime) {
        $mimeMap = [
            'image/jpeg' => 'jpg',
            'image/jpg' => 'jpg',
            'image/png' => 'png',
            'image/webp' => 'webp'
        ];
        
        return $mimeMap[$mime] ?? 'jpg';
    }
    
    private function getFileUrl($filename) {
        $baseUrl = isset($_SERVER['HTTPS']) ? 'https://' : 'http://';
        $baseUrl .= $_SERVER['HTTP_HOST'] . dirname($_SERVER['PHP_SELF']);
        $baseUrl = rtrim($baseUrl, '/');
        
        return $baseUrl . '/uploads/' . $filename;
    }
    
    // Метод для удаления файла
    public function deleteFile($filename) {
        $filePath = $this->uploadDir . $filename;
        if (file_exists($filePath)) {
            return unlink($filePath);
        }
        return false;
    }
}

class ReplicateService {
    private $api_key;
    
    public function __construct() {
        $this->api_key = Env::get('REPLICATE_API_KEY');
        if (!$this->api_key) {
            throw new Exception('Replicate API key not found');
        }
    }
    
    public function createPrediction($model, $input) {
        $ch = curl_init();
        
        $data = [
            'version' => $this->getModelVersion($model),
            'input' => $input
        ];
        
        curl_setopt_array($ch, [
            CURLOPT_URL => "https://api.replicate.com/v1/predictions",
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => json_encode($data),
            CURLOPT_HTTPHEADER => [
                'Authorization: Token ' . $this->api_key,
                'Content-Type: application/json',
            ],
        ]);
        
        $response = curl_exec($ch);
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        if ($http_code !== 201) {
            throw new Exception('Replicate API error: ' . $response);
        }
        
        return json_decode($response, true);
    }
    
    public function getPrediction($id) {
        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL => "https://api.replicate.com/v1/predictions/" . $id,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => [
                'Authorization: Token ' . $this->api_key,
                'Content-Type: application/json',
            ],
        ]);
        
        $response = curl_exec($ch);
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        if ($http_code !== 200) {
            throw new Exception('Prediction not found');
        }
        
        return json_decode($response, true);
    }
    
    public function formatQuality($quality) {
        $qualityMap = [
            '360' => '360p',
            '540' => '540p', 
            '720' => '720p',
            '1080' => '1080p'
        ];
        
        return $qualityMap[$quality] ?? '720p';
    }
    
    private function getModelVersion($model) {
        $versions = [
            'ddcolor' => 'ca494ba129e44e45f661d6ece83c4c98a9a7c774309beca01429b58fce8aa695',
            'restore' => 'flux-kontext-apps/restore-image', 
            'pixverse' => 'pixverse/pixverse-v5'
        ];
        
        return $versions[$model] ?? $model;
    }
}

function jsonResponse($success, $data = null, $error = null, $statusCode = 200) {
    http_response_code($statusCode);
    header('Content-Type: application/json');
    
    $response = [
        'success' => $success,
        'timestamp' => time()
    ];
    
    if ($success) {
        $response['data'] = $data;
    } else {
        $response['error'] = [
            'code' => 'API_ERROR',
            'message' => $error
        ];
    }
    
    echo json_encode($response);
    exit;
}
?>
