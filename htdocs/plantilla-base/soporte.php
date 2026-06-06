<?php
/**
 * Sistema de soporte al cliente - Crear tickets
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

$mensajeExito = '';
$ticketCreado = 0;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['crear_ticket'])) {
    $nombre = trim($_POST['nombre'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $asunto = trim($_POST['asunto'] ?? '');
    $mensaje = trim($_POST['mensaje'] ?? '');

    if ($nombre && $asunto && $mensaje) {
        $stmt = $db->prepare("INSERT INTO tickets_soporte (emprendimiento_id, cliente_nombre, cliente_email, asunto, mensaje, estado) VALUES (?, ?, ?, ?, ?, 'abierto')");
        $stmt->execute([$empId, $nombre, $email, $asunto, $mensaje]);
        $ticketCreado = $db->lastInsertId();
        $mensajeExito = "Ticket #{$ticketCreado} creado correctamente. Te responderemos a la brevedad.";
    } else {
        $mensajeExito = 'Por favor completa todos los campos requeridos.';
        $mensajeExito = 'error';
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Soporte - <?= h($emp['nombre']) ?></title>
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
                    <li class="nav-item"><a class="nav-link active" href="/<?= h($slug) ?>/soporte.php">Soporte</a></li>
                </ul>
            </div>
        </nav>
    </header>

    <div class="container py-5">
        <div class="row justify-content-center">
            <div class="col-lg-8">
                <div class="text-center mb-5">
                    <i class="fas fa-headset fa-3x text-primary mb-3"></i>
                    <h1 class="fw-bold">Centro de Soporte</h1>
                    <p class="text-muted">¿Tienes algún problema o consulta? Abre un ticket y te ayudaremos.</p>
                </div>

                <?php if ($mensajeExito === 'error'): ?>
                    <div class="alert alert-danger">Por favor completa todos los campos requeridos.</div>
                <?php elseif ($ticketCreado > 0): ?>
                    <div class="alert alert-success text-center">
                        <i class="fas fa-check-circle fa-2x mb-2"></i>
                        <h5>¡Ticket Creado!</h5>
                        <p class="mb-0"><?= h($mensajeExito) ?></p>
                        <p class="small mt-2">Guardá tu número de ticket: <strong>#<?= $ticketCreado ?></strong></p>
                        <a href="/<?= h($slug) ?>" class="btn btn-primary mt-2"><i class="fas fa-home me-1"></i> Volver al inicio</a>
                    </div>
                <?php else: ?>
                    <div class="card border-0 shadow-sm">
                        <div class="card-body p-4">
                            <h4 class="fw-bold mb-4"><i class="fas fa-ticket-alt me-2 text-primary"></i> Abrir Ticket de Soporte</h4>
                            <form method="POST">
                                <div class="row g-3">
                                    <div class="col-md-6">
                                        <label class="form-label">Nombre *</label>
                                        <input type="text" name="nombre" class="form-control" required>
                                    </div>
                                    <div class="col-md-6">
                                        <label class="form-label">Email</label>
                                        <input type="email" name="email" class="form-control">
                                    </div>
                                    <div class="col-12">
                                        <label class="form-label">Asunto *</label>
                                        <input type="text" name="asunto" class="form-control" required placeholder="Ej: Consulta sobre producto, problema con pedido...">
                                    </div>
                                    <div class="col-12">
                                        <label class="form-label">Mensaje *</label>
                                        <textarea name="mensaje" class="form-control" rows="6" required placeholder="Describe tu consulta o problema en detalle..."></textarea>
                                    </div>
                                    <div class="col-12">
                                        <button type="submit" name="crear_ticket" class="btn btn-primary btn-lg w-100">
                                            <i class="fas fa-paper-plane me-2"></i> Enviar Ticket
                                        </button>
                                    </div>
                                </div>
                            </form>
                        </div>
                    </div>

                    <div class="text-center mt-4">
                        <p class="text-muted">¿Prefieres hablar con nosotros?</p>
                        <?php if ($contacto['whatsapp_numero']): ?>
                            <a href="https://wa.me/<?= preg_replace('/[^0-9]/', '', $contacto['whatsapp_numero']) ?>?text=<?= urlencode($contacto['whatsapp_mensaje_auto'] ?: 'Hola, necesito soporte') ?>" target="_blank" class="btn btn-success btn-lg">
                                <i class="fab fa-whatsapp me-2"></i> Contactar por WhatsApp
                            </a>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <?php include 'chatbot.php'; ?>
    <?php if ($contacto['whatsapp_numero']): ?>
        <a href="https://wa.me/<?= preg_replace('/[^0-9]/', '', $contacto['whatsapp_numero']) ?>" target="_blank" class="whatsapp-float"><i class="fab fa-whatsapp"></i></a>
    <?php endif; ?>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="/plantilla-base/assets/js/chatbot.js"></script>
</body>
</html>
