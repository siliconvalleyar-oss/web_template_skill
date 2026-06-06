<?php
/**
 * Página principal del emprendimiento
 * Lee datos dinámicamente desde la BD usando el slug de la URL
 */
require_once dirname(__DIR__) . '/config.php';

// Obtener slug de la URL
$slug = $_GET['slug'] ?? basename(dirname($_SERVER['SCRIPT_NAME']));
if (!$slug) {
    http_response_code(404);
    die('Emprendimiento no encontrado');
}

$db = getDB();
if (!$db) {
    http_response_code(500);
    die('Error del sistema');
}

// Obtener datos del emprendimiento
$stmt = $db->prepare("SELECT * FROM emprendimientos WHERE slug = ? AND activo = TRUE");
$stmt->execute([$slug]);
$emp = $stmt->fetch();

if (!$emp) {
    http_response_code(404);
    die('Emprendimiento no encontrado o inactivo');
}

$empId = $emp['id'];

// Cargar configuración
$config = $db->prepare("SELECT * FROM config_visual WHERE emprendimiento_id = ?");
$config->execute([$empId]);
$config = $config->fetch() ?: [];

$contacto = $db->prepare("SELECT * FROM contacto_redes WHERE emprendimiento_id = ?");
$contacto->execute([$empId]);
$contacto = $contacto->fetch() ?: [];

$contenido = $db->prepare("SELECT * FROM contenido_texto WHERE emprendimiento_id = ?");
$contenido->execute([$empId]);
$contenido = $contenido->fetch() ?: [];

$carrusel = $db->prepare("SELECT * FROM imagenes_carrusel WHERE emprendimiento_id = ? ORDER BY orden ASC");
$carrusel->execute([$empId]);
$carrusel = $carrusel->fetchAll();

$productosDestacados = $db->prepare("SELECT * FROM productos WHERE emprendimiento_id = ? AND destacado = TRUE AND activo = TRUE ORDER BY nombre ASC LIMIT 8");
$productosDestacados->execute([$empId]);
$productosDestacados = $productosDestacados->fetchAll();

// Configurar colores CSS
$colorPpal = $config['color_principal'] ?? '#2563eb';
$colorSec = $config['color_secundario'] ?? '#7c3aed';
$colorFondo = $config['color_fondo'] ?? '#ffffff';
$colorTexto = $config['color_texto'] ?? '#1f2937';
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= h($config['titulo_seo'] ?: $emp['nombre']) ?></title>
    <meta name="description" content="<?= h($config['meta_descripcion'] ?: $emp['eslogan']) ?>">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="/plantilla-base/assets/css/plantilla.css">
    <style>
        :root {
            --color-principal: <?= $colorPpal ?>;
            --color-secundario: <?= $colorSec ?>;
            --color-fondo: <?= $colorFondo ?>;
            --color-texto: <?= $colorTexto ?>;
        }
        body { background: var(--color-fondo); color: var(--color-texto); }
        <?php if ($config['logo']): ?>
        .navbar-brand { background: url('/<?= h($slug) ?>/uploads/<?= h($config['logo']) ?>') center/contain no-repeat; width: 160px; height: 50px; text-indent: -9999px; }
        <?php endif; ?>
        <?php if ($config['favicon']): ?>
        .favicon-custom { display: none; }
        <?php endif; ?>
    </style>
    <?php if ($config['favicon']): ?>
    <link rel="icon" href="/<?= h($slug) ?>/uploads/<?= h($config['favicon']) ?>" type="image/x-icon">
    <?php endif; ?>
</head>
<body>
    <!-- Header -->
    <header class="header">
        <nav class="navbar navbar-expand-lg">
            <div class="container">
                <a class="navbar-brand" href="/<?= h($slug) ?>">
                    <?php if ($config['logo']): ?>
                        <span style="display:none"><?= h($emp['nombre']) ?></span>
                    <?php else: ?>
                        <?= h($emp['nombre']) ?>
                    <?php endif; ?>
                </a>
                <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarMain">
                    <span class="navbar-toggler-icon"></span>
                </button>
                <div class="collapse navbar-collapse" id="navbarMain">
                    <ul class="navbar-nav ms-auto">
                        <li class="nav-item"><a class="nav-link active" href="/<?= h($slug) ?>">Inicio</a></li>
                        <li class="nav-item"><a class="nav-link" href="/<?= h($slug) ?>/productos.php">Productos</a></li>
                        <li class="nav-item"><a class="nav-link" href="/<?= h($slug) ?>/contacto.php">Contacto</a></li>
                        <li class="nav-item"><a class="nav-link" href="/<?= h($slug) ?>/soporte.php">Soporte</a></li>
                    </ul>
                    <div class="d-flex align-items-center ms-3 gap-2">
                        <a href="/<?= h($slug) ?>/carrito.php" class="btn btn-carrito position-relative">
                            <i class="fas fa-shopping-cart"></i>
                            <span class="cart-count badge bg-danger rounded-pill">0</span>
                        </a>
                    </div>
                </div>
            </div>
        </nav>
    </header>

    <!-- Hero Carousel -->
    <?php if (count($carrusel) > 0): ?>
    <section class="hero-carousel">
        <div id="heroCarousel" class="carousel slide" data-bs-ride="carousel">
            <div class="carousel-indicators">
                <?php foreach ($carrusel as $i => $img): ?>
                    <button type="button" data-bs-target="#heroCarousel" data-bs-slide-to="<?= $i ?>" class="<?= $i === 0 ? 'active' : '' ?>"></button>
                <?php endforeach; ?>
            </div>
            <div class="carousel-inner">
                <?php foreach ($carrusel as $i => $img): ?>
                    <div class="carousel-item <?= $i === 0 ? 'active' : '' ?>">
                        <div class="carousel-slide" style="background-image: url('/<?= h($slug) ?>/uploads/<?= h($img['imagen']) ?>')">
                            <div class="container">
                                <div class="carousel-caption">
                                    <?php if ($img['titulo']): ?><h2><?= h($img['titulo']) ?></h2><?php endif; ?>
                                    <?php if ($img['subtitulo']): ?><p><?= h($img['subtitulo']) ?></p><?php endif; ?>
                                    <a href="/<?= h($slug) ?>/productos.php" class="btn btn-primary btn-lg">Ver Productos <i class="fas fa-arrow-right ms-2"></i></a>
                                </div>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
            <button class="carousel-control-prev" type="button" data-bs-target="#heroCarousel" data-bs-slide="prev">
                <span class="carousel-control-prev-icon"></span>
            </button>
            <button class="carousel-control-next" type="button" data-bs-target="#heroCarousel" data-bs-slide="next">
                <span class="carousel-control-next-icon"></span>
            </button>
        </div>
    </section>
    <?php else: ?>
    <section class="hero-simple">
        <div class="container text-center py-5">
            <h1 class="display-4 fw-bold"><?= h($emp['nombre']) ?></h1>
            <?php if ($emp['eslogan']): ?><p class="lead"><?= h($emp['eslogan']) ?></p><?php endif; ?>
            <a href="/<?= h($slug) ?>/productos.php" class="btn btn-primary btn-lg mt-3">Ver Productos</a>
        </div>
    </section>
    <?php endif; ?>

    <!-- Bienvenida -->
    <?php if ($contenido && $contenido['texto_bienvenida']): ?>
    <section class="py-5">
        <div class="container">
            <div class="row justify-content-center">
                <div class="col-lg-8 text-center">
                    <p class="lead"><?= nl2br(h($contenido['texto_bienvenida'])) ?></p>
                </div>
            </div>
        </div>
    </section>
    <?php endif; ?>

    <!-- Productos Destacados -->
    <?php if (count($productosDestacados) > 0): ?>
    <section class="py-5 bg-light">
        <div class="container">
            <div class="text-center mb-5">
                <h2 class="fw-bold">Productos Destacados</h2>
                <p class="text-muted">Lo más popular de <?= h($emp['nombre']) ?></p>
            </div>
            <div class="row g-4">
                <?php foreach ($productosDestacados as $prod): ?>
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
                                    <span class="badge bg-warning text-dark">Últimas <?= $prod['stock'] ?> unidades</span>
                                <?php endif; ?>
                            </div>
                            <div class="product-info">
                                <h6 class="product-title"><?= h($prod['nombre']) ?></h6>
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
                                    <i class="fas fa-cart-plus me-1"></i> Agregar al Carrito
                                </button>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
            <div class="text-center mt-4">
                <a href="/<?= h($slug) ?>/productos.php" class="btn btn-outline-primary btn-lg">Ver Todos los Productos <i class="fas fa-arrow-right ms-2"></i></a>
            </div>
        </div>
    </section>
    <?php endif; ?>

    <!-- Quiénes Somos -->
    <?php if ($contenido && $contenido['texto_quienes_somos']): ?>
    <section class="py-5">
        <div class="container">
            <div class="row justify-content-center">
                <div class="col-lg-10">
                    <h2 class="fw-bold text-center mb-4">¿Quiénes Somos?</h2>
                    <div class="quienes-somos"><?= $contenido['texto_quienes_somos'] ?></div>
                </div>
            </div>
        </div>
    </section>
    <?php endif; ?>

    <!-- Footer -->
    <footer class="footer">
        <div class="container">
            <div class="row g-4">
                <div class="col-md-4">
                    <h5><?= h($emp['nombre']) ?></h5>
                    <?php if ($emp['eslogan']): ?><p class="text-muted small"><?= h($emp['eslogan']) ?></p><?php endif; ?>
                </div>
                <div class="col-md-4">
                    <h5>Contacto</h5>
                    <ul class="list-unstyled small">
                        <?php if ($contacto['telefono']): ?><li><i class="fas fa-phone me-2"></i><?= h($contacto['telefono']) ?></li><?php endif; ?>
                        <?php if ($contacto['email']): ?><li><i class="fas fa-envelope me-2"></i><?= h($contacto['email']) ?></li><?php endif; ?>
                        <?php if ($contacto['direccion']): ?><li><i class="fas fa-map-marker-alt me-2"></i><?= h($contacto['direccion']) ?></li><?php endif; ?>
                    </ul>
                </div>
                <div class="col-md-4">
                    <h5>Síguenos</h5>
                    <div class="social-links">
                        <?php if (!empty($contacto['instagram_activo']) && $contacto['instagram_link']): ?>
                            <a href="<?= h($contacto['instagram_link']) ?>" target="_blank" class="social-link" title="Instagram"><i class="fab fa-instagram"></i></a>
                        <?php endif; ?>
                        <?php if (!empty($contacto['facebook_activo']) && $contacto['facebook_link']): ?>
                            <a href="<?= h($contacto['facebook_link']) ?>" target="_blank" class="social-link" title="Facebook"><i class="fab fa-facebook"></i></a>
                        <?php endif; ?>
                        <?php if (!empty($contacto['tiktok_activo']) && $contacto['tiktok_link']): ?>
                            <a href="<?= h($contacto['tiktok_link']) ?>" target="_blank" class="social-link" title="TikTok"><i class="fab fa-tiktok"></i></a>
                        <?php endif; ?>
                        <?php if (!empty($contacto['linkedin_activo']) && $contacto['linkedin_link']): ?>
                            <a href="<?= h($contacto['linkedin_link']) ?>" target="_blank" class="social-link" title="LinkedIn"><i class="fab fa-linkedin"></i></a>
                        <?php endif; ?>
                        <?php if (!empty($contacto['twitter_activo']) && $contacto['twitter_link']): ?>
                            <a href="<?= h($contacto['twitter_link']) ?>" target="_blank" class="social-link" title="Twitter"><i class="fab fa-twitter"></i></a>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            <hr class="my-3">
            <div class="row align-items-center">
                <div class="col-md-6">
                    <p class="small text-muted mb-0">&copy; <?= date('Y') ?> <?= h($emp['nombre']) ?>. Todos los derechos reservados.</p>
                </div>
                <div class="col-md-6 text-md-end">
                    <a href="/<?= h($slug) ?>/politicas.php" class="small text-muted me-3">Políticas</a>
                    <a href="/<?= h($slug) ?>/contacto.php" class="small text-muted">Contacto</a>
                </div>
            </div>
        </div>
    </footer>

    <!-- WhatsApp Float Button -->
    <?php if ($contacto['whatsapp_numero']): ?>
    <a href="https://wa.me/<?= preg_replace('/[^0-9]/', '', $contacto['whatsapp_numero']) ?>?text=<?= urlencode($contacto['whatsapp_mensaje_auto'] ?: '¡Hola! Quiero consultar...') ?>" 
       target="_blank" class="whatsapp-float" title="Chatear por WhatsApp">
        <i class="fab fa-whatsapp"></i>
    </a>
    <?php endif; ?>

    <!-- Chatbot Widget (DeepSeek 7B) -->
    <?php include 'chatbot.php'; ?>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="/plantilla-base/assets/js/carrito.js"></script>
    <script src="/plantilla-base/assets/js/chatbot.js"></script>
</body>
</html>
