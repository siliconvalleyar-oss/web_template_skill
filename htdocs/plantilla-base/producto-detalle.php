<?php
/**
 * Página de detalle de producto individual
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
$prodId = (int)($_GET['id'] ?? 0);

$stmt = $db->prepare("SELECT * FROM productos WHERE id = ? AND emprendimiento_id = ? AND activo = TRUE");
$stmt->execute([$prodId, $empId]);
$prod = $stmt->fetch();
if (!$prod) { http_response_code(404); die('Producto no encontrado'); }

$config = $db->prepare("SELECT * FROM config_visual WHERE emprendimiento_id = ?");
$config->execute([$empId]); $config = $config->fetch() ?: [];

$contacto = $db->prepare("SELECT * FROM contacto_redes WHERE emprendimiento_id = ?");
$contacto->execute([$empId]); $contacto = $contacto->fetch() ?: [];

// Productos relacionados
$relacionados = $db->prepare("SELECT * FROM productos WHERE emprendimiento_id = ? AND activo = TRUE AND id != ? ORDER BY RAND() LIMIT 4");
$relacionados->execute([$empId, $prodId]);
$relacionados = $relacionados->fetchAll();
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= h($prod['nombre']) ?> - <?= h($emp['nombre']) ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="/plantilla-base/assets/css/plantilla.css">
    <style>:root { --color-principal: <?= $config['color_principal'] ?? '#2563eb' ?>; --color-secundario: <?= $config['color_secundario'] ?? '#7c3aed' ?>; --color-fondo: <?= $config['color_fondo'] ?? '#ffffff' ?>; --color-texto: <?= $config['color_texto'] ?? '#1f2937' ?>; }</style>
</head>
<body>
    <header class="header">
        <nav class="navbar navbar-expand-lg">
            <div class="container">
                <a class="navbar-brand" href="/<?= h($slug) ?>"><?= h($emp['nombre']) ?></a>
                <div class="collapse navbar-collapse" id="navbarMain">
                    <ul class="navbar-nav ms-auto">
                        <li class="nav-item"><a class="nav-link" href="/<?= h($slug) ?>">Inicio</a></li>
                        <li class="nav-item"><a class="nav-link active" href="/<?= h($slug) ?>/productos.php">Productos</a></li>
                        <li class="nav-item"><a class="nav-link" href="/<?= h($slug) ?>/contacto.php">Contacto</a></li>
                        <li class="nav-item"><a class="nav-link" href="/<?= h($slug) ?>/soporte.php">Soporte</a></li>
                    </ul>
                    <a href="/<?= h($slug) ?>/carrito.php" class="btn btn-carrito ms-3 position-relative">
                        <i class="fas fa-shopping-cart"></i>
                        <span class="cart-count badge bg-danger rounded-pill">0</span>
                    </a>
                </div>
            </div>
        </nav>
    </header>

    <div class="container py-4">
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb">
                <li class="breadcrumb-item"><a href="/<?= h($slug) ?>">Inicio</a></li>
                <li class="breadcrumb-item"><a href="/<?= h($slug) ?>/productos.php">Productos</a></li>
                <li class="breadcrumb-item active"><?= h($prod['nombre']) ?></li>
            </ol>
        </nav>

        <div class="row g-4">
            <div class="col-md-6">
                <?php if ($prod['imagen']): ?>
                    <img src="/<?= h($slug) ?>/uploads/<?= h($prod['imagen']) ?>" class="img-fluid rounded" alt="<?= h($prod['nombre']) ?>" style="width:100%;max-height:450px;object-fit:contain;">
                <?php else: ?>
                    <div class="bg-light rounded d-flex align-items-center justify-content-center" style="height:400px">
                        <i class="fas fa-image fa-4x text-muted"></i>
                    </div>
                <?php endif; ?>
            </div>
            <div class="col-md-6">
                <h2 class="fw-bold"><?= h($prod['nombre']) ?></h2>
                <?php if ($prod['descripcion_corta']): ?>
                    <p class="text-muted"><?= nl2br(h($prod['descripcion_corta'])) ?></p>
                <?php endif; ?>
                <hr>
                <h3 class="display-6 fw-bold" style="color:var(--color-principal)">$<?= number_format($prod['precio'], 2) ?></h3>
                <div class="mt-3">
                    <?php if ($prod['stock'] > 0): ?>
                        <span class="badge bg-success fs-6"><i class="fas fa-check me-1"></i> <?= $prod['stock'] ?> en stock</span>
                    <?php else: ?>
                        <span class="badge bg-danger fs-6"><i class="fas fa-times me-1"></i> Sin stock</span>
                    <?php endif; ?>
                </div>
                <div class="mt-4 d-flex gap-2">
                    <div class="input-group" style="width:130px">
                        <button class="btn btn-outline-secondary" onclick="changeQty(-1)">-</button>
                        <input type="number" id="qtyInput" class="form-control text-center" value="1" min="1" max="<?= $prod['stock'] ?>">
                        <button class="btn btn-outline-secondary" onclick="changeQty(1)">+</button>
                    </div>
                    <button class="btn btn-primary btn-lg flex-grow-1 add-to-cart"
                            data-id="<?= $prod['id'] ?>"
                            data-nombre="<?= h($prod['nombre']) ?>"
                            data-precio="<?= $prod['precio'] ?>"
                            data-imagen="<?= h($prod['imagen']) ?>"
                            data-stock="<?= $prod['stock'] ?>"
                            <?= $prod['stock'] <= 0 ? 'disabled' : '' ?>>
                        <i class="fas fa-cart-plus me-2"></i> Agregar al Carrito
                    </button>
                </div>
                <?php if ($contacto['whatsapp_numero']): ?>
                    <div class="mt-3">
                        <a href="https://wa.me/<?= preg_replace('/[^0-9]/', '', $contacto['whatsapp_numero']) ?>?text=<?= urlencode('Hola, quiero consultar sobre ' . $prod['nombre']) ?>" target="_blank" class="btn btn-outline-success w-100">
                            <i class="fab fa-whatsapp me-2"></i> Consultar por WhatsApp
                        </a>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <?php if (count($relacionados) > 0): ?>
            <hr class="my-5">
            <h4 class="fw-bold mb-4">Productos Relacionados</h4>
            <div class="row g-3">
                <?php foreach ($relacionados as $rel): ?>
                    <div class="col-6 col-md-3">
                        <div class="product-card">
                            <div class="product-img">
                                <?php if ($rel['imagen']): ?>
                                    <img src="/<?= h($slug) ?>/uploads/<?= h($rel['imagen']) ?>" alt="<?= h($rel['nombre']) ?>">
                                <?php else: ?>
                                    <div class="product-placeholder"><i class="fas fa-box fa-2x text-muted"></i></div>
                                <?php endif; ?>
                            </div>
                            <div class="product-info">
                                <h6 class="product-title"><?= h($rel['nombre']) ?></h6>
                                <div class="product-price">$<?= number_format($rel['precio'], 2) ?></div>
                                <a href="/<?= h($slug) ?>/producto-detalle.php?id=<?= $rel['id'] ?>" class="btn btn-outline-primary btn-sm w-100">Ver</a>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>

    <?php include 'chatbot.php'; ?>
    <?php if ($contacto['whatsapp_numero']): ?>
        <a href="https://wa.me/<?= preg_replace('/[^0-9]/', '', $contacto['whatsapp_numero']) ?>?text=<?= urlencode($contacto['whatsapp_mensaje_auto'] ?: '') ?>" target="_blank" class="whatsapp-float"><i class="fab fa-whatsapp"></i></a>
    <?php endif; ?>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="/plantilla-base/assets/js/carrito.js"></script>
    <script src="/plantilla-base/assets/js/chatbot.js"></script>
    <script>
    function changeQty(delta) {
        const input = document.getElementById('qtyInput');
        let val = parseInt(input.value) + delta;
        if (val < 1) val = 1;
        if (val > <?= $prod['stock'] ?>) val = <?= $prod['stock'] ?>;
        input.value = val;
    }

    // Agregar con cantidad específica
    document.querySelector('.add-to-cart')?.addEventListener('click', function() {
        const qty = parseInt(document.getElementById('qtyInput')?.value || 1);
        const cart = JSON.parse(localStorage.getItem('carrito_' + '<?= $slug ?>') || '[]');
        const existing = cart.find(item => item.id === <?= $prod['id'] ?>);
        if (existing) {
            existing.cantidad = Math.min(existing.cantidad + qty, <?= $prod['stock'] ?>);
        } else {
            cart.push({
                id: <?= $prod['id'] ?>,
                nombre: '<?= h(addslashes($prod['nombre'])) ?>',
                precio: <?= $prod['precio'] ?>,
                imagen: '<?= h($prod['imagen']) ?>',
                stock: <?= $prod['stock'] ?>,
                cantidad: qty
            });
        }
        localStorage.setItem('carrito_' + '<?= $slug ?>', JSON.stringify(cart));
        actualizarContadorCarrito();
        mostrarNotificacion('Producto agregado al carrito');
    });

    function mostrarNotificacion(msg) {
        const toast = document.createElement('div');
        toast.className = 'toast-notification';
        toast.innerHTML = `<i class="fas fa-check-circle me-2 text-success"></i> ${msg}`;
        document.body.appendChild(toast);
        setTimeout(() => toast.remove(), 2500);
    }
    </script>
</body>
</html>
