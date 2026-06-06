<?php
/**
 * Catálogo de productos con filtros
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

// Búsqueda
$search = $_GET['search'] ?? '';
$sql = "SELECT * FROM productos WHERE emprendimiento_id = ? AND activo = TRUE";
$params = [$empId];
if ($search) { $sql .= " AND nombre LIKE ?"; $params[] = "%$search%"; }
$sql .= " ORDER BY nombre ASC";

$productos = $db->prepare($sql);
$productos->execute($params);
$productos = $productos->fetchAll();
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Productos - <?= h($emp['nombre']) ?></title>
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

    <section class="py-4">
        <div class="container">
            <div class="d-flex justify-content-between align-items-center mb-4">
                <h2 class="fw-bold mb-0">Productos</h2>
                <form method="GET" class="d-flex gap-2">
                    <input type="text" name="search" class="form-control" placeholder="Buscar producto..." value="<?= h($search) ?>">
                    <button class="btn btn-primary"><i class="fas fa-search"></i></button>
                </form>
            </div>

            <?php if (count($productos) === 0): ?>
                <div class="text-center py-5">
                    <i class="fas fa-box-open fa-4x text-muted mb-3"></i>
                    <h5>No hay productos disponibles</h5>
                    <?php if ($search): ?><p class="text-muted">No se encontraron resultados para "<?= h($search) ?>"</p><?php endif; ?>
                </div>
            <?php else: ?>
                <div class="row g-4">
                    <?php foreach ($productos as $prod): ?>
                        <div class="col-6 col-md-4 col-lg-3">
                            <div class="product-card">
                                <div class="product-img">
                                    <?php if ($prod['imagen']): ?>
                                        <img src="/<?= h($slug) ?>/uploads/<?= h($prod['imagen']) ?>" alt="<?= h($prod['nombre']) ?>">
                                    <?php else: ?>
                                        <div class="product-placeholder"><i class="fas fa-box fa-3x text-muted"></i></div>
                                    <?php endif; ?>
                                    <?php if ($prod['stock'] <= 0): ?>
                                        <span class="badge bg-danger out-of-stock">Sin stock</span>
                                    <?php elseif ($prod['stock'] <= 5): ?>
                                        <span class="badge bg-warning text-dark">Últimas <?= $prod['stock'] ?> uds.</span>
                                    <?php endif; ?>
                                </div>
                                <div class="product-info">
                                    <a href="/<?= h($slug) ?>/producto-detalle.php?id=<?= $prod['id'] ?>" class="text-decoration-none">
                                        <h6 class="product-title"><?= h($prod['nombre']) ?></h6>
                                    </a>
                                    <?php if ($prod['descripcion_corta']): ?>
                                        <p class="product-desc"><?= h(substr($prod['descripcion_corta'], 0, 60)) ?>...</p>
                                    <?php endif; ?>
                                    <div class="product-price">$<?= number_format($prod['precio'], 2) ?></div>
                                    <button class="btn btn-primary btn-sm w-100 add-to-cart" 
                                            data-id="<?= $prod['id'] ?>"
                                            data-nombre="<?= h($prod['nombre']) ?>"
                                            data-precio="<?= $prod['precio'] ?>"
                                            data-imagen="<?= h($prod['imagen']) ?>"
                                            data-stock="<?= $prod['stock'] ?>"
                                            <?= $prod['stock'] <= 0 ? 'disabled' : '' ?>>
                                        <i class="fas fa-cart-plus me-1"></i> Agregar
                                    </button>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </section>

    <?php include 'chatbot.php'; ?>
    <?php if ($contacto['whatsapp_numero']): ?>
        <a href="https://wa.me/<?= preg_replace('/[^0-9]/', '', $contacto['whatsapp_numero']) ?>?text=<?= urlencode($contacto['whatsapp_mensaje_auto'] ?: '') ?>" target="_blank" class="whatsapp-float"><i class="fab fa-whatsapp"></i></a>
    <?php endif; ?>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="/plantilla-base/assets/js/carrito.js"></script>
    <script src="/plantilla-base/assets/js/chatbot.js"></script>
</body>
</html>
