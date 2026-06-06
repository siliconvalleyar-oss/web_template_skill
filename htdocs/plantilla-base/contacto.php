<?php
/**
 * Página de contacto con formulario + chatbot + WhatsApp
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
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['enviar_contacto'])) {
    $nombre = trim($_POST['nombre'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $mensaje = trim($_POST['mensaje'] ?? '');
    if ($nombre && $mensaje) {
        // Guardar como ticket de soporte
        $stmt = $db->prepare("INSERT INTO tickets_soporte (emprendimiento_id, cliente_nombre, cliente_email, asunto, mensaje) VALUES (?, ?, ?, 'Consulta desde formulario de contacto', ?)");
        $stmt->execute([$empId, $nombre, $email, $mensaje]);
        $mensajeExito = 'Gracias por contactarnos. Te responderemos a la brevedad.';
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Contacto - <?= h($emp['nombre']) ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="/plantilla-base/assets/css/plantilla.css">
    <style>
        :root { --color-principal: <?= $config['color_principal'] ?? '#2563eb' ?>; --color-secundario: <?= $config['color_secundario'] ?? '#7c3aed' ?>; }
        .contact-card { height: 100%; transition: transform 0.3s; }
        .contact-card:hover { transform: translateY(-4px); }
        .contact-icon { width: 60px; height: 60px; border-radius: 15px; display: flex; align-items: center; justify-content: center; font-size: 24px; margin-bottom: 16px; }
    </style>
</head>
<body>
    <header class="header">
        <nav class="navbar navbar-expand-lg">
            <div class="container">
                <a class="navbar-brand" href="/<?= h($slug) ?>"><?= h($emp['nombre']) ?></a>
                <div class="collapse navbar-collapse" id="navbarMain">
                    <ul class="navbar-nav ms-auto">
                        <li class="nav-item"><a class="nav-link" href="/<?= h($slug) ?>">Inicio</a></li>
                        <li class="nav-item"><a class="nav-link" href="/<?= h($slug) ?>/productos.php">Productos</a></li>
                        <li class="nav-item"><a class="nav-link active" href="/<?= h($slug) ?>/contacto.php">Contacto</a></li>
                        <li class="nav-item"><a class="nav-link" href="/<?= h($slug) ?>/soporte.php">Soporte</a></li>
                    </ul>
                </div>
            </div>
        </nav>
    </header>

    <section class="py-5">
        <div class="container">
            <div class="text-center mb-5">
                <h1 class="fw-bold">Contacto</h1>
                <p class="text-muted">Estamos para ayudarte. Elige el canal que prefieras.</p>
            </div>

            <?php if ($mensajeExito): ?>
                <div class="alert alert-success text-center"><?= h($mensajeExito) ?></div>
            <?php endif; ?>

            <div class="row g-4 mb-5">
                <?php if ($contacto['telefono']): ?>
                    <div class="col-md-4">
                        <div class="card contact-card border-0 shadow-sm text-center p-4">
                            <div class="contact-icon bg-primary-subtle text-primary mx-auto"><i class="fas fa-phone"></i></div>
                            <h5>Teléfono</h5>
                            <a href="tel:<?= h($contacto['telefono']) ?>" class="text-decoration-none"><?= h($contacto['telefono']) ?></a>
                        </div>
                    </div>
                <?php endif; ?>
                <?php if ($contacto['email']): ?>
                    <div class="col-md-4">
                        <div class="card contact-card border-0 shadow-sm text-center p-4">
                            <div class="contact-icon bg-success-subtle text-success mx-auto"><i class="fas fa-envelope"></i></div>
                            <h5>Email</h5>
                            <a href="mailto:<?= h($contacto['email']) ?>" class="text-decoration-none"><?= h($contacto['email']) ?></a>
                        </div>
                    </div>
                <?php endif; ?>
                <?php if ($contacto['whatsapp_numero']): ?>
                    <div class="col-md-4">
                        <div class="card contact-card border-0 shadow-sm text-center p-4">
                            <div class="contact-icon bg-success text-white mx-auto"><i class="fab fa-whatsapp"></i></div>
                            <h5>WhatsApp</h5>
                            <a href="https://wa.me/<?= preg_replace('/[^0-9]/', '', $contacto['whatsapp_numero']) ?>?text=<?= urlencode($contacto['whatsapp_mensaje_auto'] ?: '¡Hola!') ?>" target="_blank" class="text-decoration-none">
                                <?= h($contacto['whatsapp_numero']) ?>
                            </a>
                            <?php if ($contacto['whatsapp_horarios']): ?>
                                <br><small class="text-muted"><?= h($contacto['whatsapp_horarios']) ?></small>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endif; ?>
            </div>

            <div class="row g-4">
                <div class="col-lg-6">
                    <div class="card border-0 shadow-sm">
                        <div class="card-body p-4">
                            <h4 class="fw-bold mb-4"><i class="fas fa-paper-plane text-primary me-2"></i> Envíanos un Mensaje</h4>
                            <form method="POST">
                                <div class="mb-3">
                                    <label class="form-label">Nombre *</label>
                                    <input type="text" name="nombre" class="form-control" required>
                                </div>
                                <div class="mb-3">
                                    <label class="form-label">Email</label>
                                    <input type="email" name="email" class="form-control">
                                </div>
                                <div class="mb-3">
                                    <label class="form-label">Mensaje *</label>
                                    <textarea name="mensaje" class="form-control" rows="5" required></textarea>
                                </div>
                                <button type="submit" name="enviar_contacto" class="btn btn-primary">
                                    <i class="fas fa-paper-plane me-2"></i> Enviar Mensaje
                                </button>
                            </form>
                        </div>
                    </div>
                </div>
                <div class="col-lg-6">
                    <div class="card border-0 shadow-sm">
                        <div class="card-body p-4">
                            <h4 class="fw-bold mb-4"><i class="fas fa-robot text-primary me-2"></i> Chat IA (DeepSeek)</h4>
                            <p class="text-muted">Pregúntale a nuestro asistente inteligente sobre productos, stock, formas de pago y más.</p>
                            <!-- El chatbot se integra como widget flotante -->
                            <button class="btn btn-outline-primary" onclick="document.querySelector('.chatbot-toggle')?.click()">
                                <i class="fas fa-comment-dots me-2"></i> Abrir Chat
                            </button>
                        </div>
                    </div>
                </div>
            </div>

            <?php if ($contacto['direccion']): ?>
                <div class="card border-0 shadow-sm mt-4">
                    <div class="card-body p-4">
                        <h5><i class="fas fa-map-marker-alt text-danger me-2"></i> Dirección</h5>
                        <p class="mb-0"><?= nl2br(h($contacto['direccion'])) ?></p>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </section>

    <?php include 'chatbot.php'; ?>
    <?php if ($contacto['whatsapp_numero']): ?>
        <a href="https://wa.me/<?= preg_replace('/[^0-9]/', '', $contacto['whatsapp_numero']) ?>" target="_blank" class="whatsapp-float"><i class="fab fa-whatsapp"></i></a>
    <?php endif; ?>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="/plantilla-base/assets/js/carrito.js"></script>
    <script src="/plantilla-base/assets/js/chatbot.js"></script>
</body>
</html>
