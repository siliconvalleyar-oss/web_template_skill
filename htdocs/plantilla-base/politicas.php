<?php
/**
 * Políticas de envío y devolución
 */
require_once dirname(__DIR__) . '/config.php';

$slug = $_GET['slug'] ?? basename(dirname($_SERVER['SCRIPT_NAME']));
$db = getDB();
if (!$db) die('Error del sistema');

$stmt = $db->prepare("SELECT * FROM emprendimientos WHERE slug = ? AND activo = TRUE");
$stmt->execute([$slug]);
$emp = $stmt->fetch();
if (!$emp) { http_response_code(404); die('No encontrado'); }

$empId = $emp['id'];
$config = $db->prepare("SELECT * FROM config_visual WHERE emprendimiento_id = ?");
$config->execute([$empId]); $config = $config->fetch() ?: [];

$contacto = $db->prepare("SELECT * FROM contacto_redes WHERE emprendimiento_id = ?");
$contacto->execute([$empId]); $contacto = $contacto->fetch() ?: [];

$contenido = $db->prepare("SELECT * FROM contenido_texto WHERE emprendimiento_id = ?");
$contenido->execute([$empId]); $contenido = $contenido->fetch() ?: [];
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Políticas - <?= h($emp['nombre']) ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="/plantilla-base/assets/css/plantilla.css">
    <style>:root { --color-principal: <?= $config['color_principal'] ?? '#2563eb' ?>; }</style>
</head>
<body>
    <header class="header">
        <nav class="navbar navbar-expand-lg">
            <div class="container">
                <a class="navbar-brand" href="/<?= h($slug) ?>"><?= h($emp['nombre']) ?></a>
                <ul class="navbar-nav ms-auto">
                    <li class="nav-item"><a class="nav-link" href="/<?= h($slug) ?>">Inicio</a></li>
                    <li class="nav-item"><a class="nav-link" href="/<?= h($slug) ?>/productos.php">Productos</a></li>
                    <li class="nav-item"><a class="nav-link" href="/<?= h($slug) ?>/contacto.php">Contacto</a></li>
                </ul>
            </div>
        </nav>
    </header>

    <div class="container py-5">
        <h1 class="fw-bold mb-4">Políticas</h1>
        
        <div class="row g-4">
            <div class="col-lg-6">
                <div class="card border-0 shadow-sm h-100">
                    <div class="card-body p-4">
                        <h4 class="fw-bold mb-3"><i class="fas fa-truck text-primary me-2"></i> Políticas de Envío</h4>
                        <?php if ($contenido && $contenido['politicas_envio']): ?>
                            <div><?= nl2br(h($contenido['politicas_envio'])) ?></div>
                        <?php else: ?>
                            <p class="text-muted">Las políticas de envío están siendo actualizadas. Por favor, contáctanos para más información.</p>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            <div class="col-lg-6">
                <div class="card border-0 shadow-sm h-100">
                    <div class="card-body p-4">
                        <h4 class="fw-bold mb-3"><i class="fas fa-undo text-danger me-2"></i> Políticas de Devolución</h4>
                        <?php if ($contenido && $contenido['politicas_devolucion']): ?>
                            <div><?= nl2br(h($contenido['politicas_devolucion'])) ?></div>
                        <?php else: ?>
                            <p class="text-muted">Las políticas de devolución están siendo actualizadas. Por favor, contáctanos para más información.</p>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
        
        <?php if ($contacto['whatsapp_numero']): ?>
            <div class="text-center mt-4">
                <p>¿Tienes dudas? <a href="https://wa.me/<?= preg_replace('/[^0-9]/', '', $contacto['whatsapp_numero']) ?>" target="_blank" class="text-success fw-bold"><i class="fab fa-whatsapp me-1"></i> Chatea con nosotros</a></p>
            </div>
        <?php endif; ?>
    </div>

    <?php include 'chatbot.php'; ?>
    <?php if ($contacto['whatsapp_numero']): ?>
        <a href="https://wa.me/<?= preg_replace('/[^0-9]/', '', $contacto['whatsapp_numero']) ?>" target="_blank" class="whatsapp-float"><i class="fab fa-whatsapp"></i></a>
    <?php endif; ?>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="/plantilla-base/assets/js/chatbot.js"></script>
</body>
</html>
