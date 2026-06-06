<?php
/**
 * Manejador de subida de archivos
 */
session_start();
require_once __DIR__ . '/../config.php';

if (!isset($_SESSION['admin_id'])) {
    http_response_code(401);
    echo json_encode(['error' => 'No autorizado']);
    exit;
}

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !isset($_FILES['file'])) {
    http_response_code(400);
    echo json_encode(['error' => 'Solicitud inválida']);
    exit;
}

$slug = $_POST['slug'] ?? '';
$type = $_POST['type'] ?? 'general'; // logo, producto, carrusel, general

if (!$slug) {
    http_response_code(400);
    echo json_encode(['error' => 'Slug requerido']);
    exit;
}

// Crear directorio de subidas para el emprendimiento
$uploadDir = BASE_PATH . '/' . $slug . '/uploads';
if (!is_dir($uploadDir)) {
    @mkdir($uploadDir, 0755, true);
}

$file = $_FILES['file'];
$extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));

// Validar extensión
if (!in_array($extension, ALLOWED_EXTENSIONS)) {
    http_response_code(400);
    echo json_encode(['error' => 'Tipo de archivo no permitido. Extensiones: ' . implode(', ', ALLOWED_EXTENSIONS)]);
    exit;
}

// Validar tamaño
if ($file['size'] > MAX_FILE_SIZE) {
    http_response_code(400);
    echo json_encode(['error' => 'El archivo excede el límite de 5MB']);
    exit;
}

// Validar que sea una imagen
$finfo = finfo_open(FILEINFO_MIME_TYPE);
$mimeType = finfo_file($finfo, $file['tmp_name']);
finfo_close($finfo);

$allowedMimes = ['image/jpeg', 'image/png', 'image/gif', 'image/webp', 'image/svg+xml', 'image/x-icon'];
if (!in_array($mimeType, $allowedMimes)) {
    http_response_code(400);
    echo json_encode(['error' => 'El archivo debe ser una imagen válida']);
    exit;
}

// Generar nombre único
$uuid = generarUUID();
$filename = $uuid . '.' . $extension;
$filepath = $uploadDir . '/' . $filename;

if (move_uploaded_file($file['tmp_name'], $filepath)) {
    echo json_encode([
        'success' => true,
        'filename' => $filename,
        'url' => '/' . $slug . '/uploads/' . $filename,
        'path' => $filepath
    ]);
} else {
    http_response_code(500);
    echo json_encode(['error' => 'Error al guardar el archivo']);
}
