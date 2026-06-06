<?php
/**
 * Carrito de compras
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
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Carrito - <?= h($emp['nombre']) ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="/plantilla-base/assets/css/plantilla.css">
    <style>:root { --color-principal: <?= $config['color_principal'] ?? '#2563eb' ?>; --color-secundario: <?= $config['color_secundario'] ?? '#7c3aed' ?>; }</style>
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
                    <li class="nav-item"><a class="nav-link" href="/<?= h($slug) ?>/soporte.php">Soporte</a></li>
                </ul>
            </div>
        </nav>
    </header>

    <div class="container py-4">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h2 class="fw-bold"><i class="fas fa-shopping-cart me-2 text-primary"></i> Carrito de Compras</h2>
            <div>
                <button class="btn btn-outline-danger btn-sm" onclick="vaciarCarrito()"><i class="fas fa-trash me-1"></i> Vaciar</button>
            </div>
        </div>

        <div class="row">
            <div class="col-lg-8">
                <div class="card border-0 shadow-sm">
                    <div class="card-body" id="carritoContainer">
                        <!-- Se renderiza con JS -->
                    </div>
                </div>
            </div>
            <div class="col-lg-4">
                <div class="card border-0 shadow-sm">
                    <div class="card-body">
                        <h5 class="fw-bold mb-3">Resumen</h5>
                        <div class="d-flex justify-content-between mb-2">
                            <span>Subtotal</span>
                            <span id="totalCarrito" class="fw-bold">$0.00</span>
                        </div>
                        <hr>
                        <a href="/<?= h($slug) ?>/checkout.php" class="btn btn-primary w-100 btn-lg" id="btnCheckout">
                            <i class="fas fa-arrow-right me-1"></i> Finalizar Compra
                        </a>
                        <a href="/<?= h($slug) ?>/productos.php" class="btn btn-outline-primary w-100 mt-2">
                            <i class="fas fa-arrow-left me-1"></i> Seguir Comprando
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <?php include 'chatbot.php'; ?>
    <?php if ($contacto['whatsapp_numero']): ?>
        <a href="https://wa.me/<?= preg_replace('/[^0-9]/', '', $contacto['whatsapp_numero']) ?>" target="_blank" class="whatsapp-float"><i class="fab fa-whatsapp"></i></a>
    <?php endif; ?>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="/plantilla-base/assets/js/carrito.js"></script>
    <script src="/plantilla-base/assets/js/chatbot.js"></script>
</body>
</html>
