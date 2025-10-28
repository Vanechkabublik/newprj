<?php
// Обработка загрузки файла
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['fileToUpload'])) {
    $uploadDir = 'uploads/';
    
    // Создаем папку если её нет
    if (!file_exists($uploadDir)) {
        mkdir($uploadDir, 0777, true);
    }
    
    $fileName = basename($_FILES['fileToUpload']['name']);
    $targetFile = $uploadDir . $fileName;
    $uploadOk = true;
    $message = '';
    
    // Проверяем размер файла (макс 5MB)
    if ($_FILES['fileToUpload']['size'] > 5000000) {
        $message = 'Файл слишком большой.';
        $uploadOk = false;
    }
    
    // Проверяем тип файла
    $allowedTypes = ['jpg', 'png', 'jpeg', 'gif', 'pdf', 'txt'];
    $fileType = strtolower(pathinfo($targetFile, PATHINFO_EXTENSION));
    if (!in_array($fileType, $allowedTypes)) {
        $message = 'Разрешены только JPG, PNG, JPEG, GIF, PDF, TXT файлы.';
        $uploadOk = false;
    }
    
    // Пытаемся загрузить файл
    if ($uploadOk) {
        if (move_uploaded_file($_FILES['fileToUpload']['tmp_name'], $targetFile)) {
            $message = "Файл " . htmlspecialchars($fileName) . " успешно загружен.";
        } else {
            $message = "Ошибка при загрузке файла.";
        }
    }
}
?>

<!DOCTYPE html>
<html>
<head>
    <title>Загрузка файла</title>
    <meta charset="utf-8">
</head>
<body>
    <h2>Загрузка файла</h2>
    
    <?php if (isset($message)): ?>
        <p style="color: <?php echo strpos($message, 'успешно') !== false ? 'green' : 'red'; ?>">
            <?php echo $message; ?>
        </p>
    <?php endif; ?>
    
    <form method="post" enctype="multipart/form-data">
        <input type="file" name="fileToUpload" required>
        <br><br>
        <input type="submit" value="Загрузить файл">
    </form>
    
    <?php
    // Показываем список загруженных файлов
    if (file_exists('uploads/') && is_dir('uploads/')) {
        $files = scandir('uploads/');
        if (count($files) > 2) {
            echo "<h3>Загруженные файлы:</h3>";
            echo "<ul>";
            foreach ($files as $file) {
                if ($file != '.' && $file != '..') {
                    echo "<li>$file</li>";
                }
            }
            echo "</ul>";
        }
    }
    ?>
</body>
</html>