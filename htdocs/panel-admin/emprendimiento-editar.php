<?php
/**
 * Crear / Editar Emprendimiento (formulario completo con pestañas)
 */
session_start();
require_once __DIR__ . '/../config.php';

if (!isset($_SESSION['admin_id'])) {
    redirect('index.php');
}

$db = getDB();
if (!$db) {
    die('Error de conexión a la base de datos.');
}

// Cargar temas predefinidos
require_once __DIR__ . '/themes.php';

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$isEditing = $id > 0;
$emprendimiento = null;
$configVisual = null;
$contactoRedes = null;
$contenidoTexto = null;
$imagenesCarrusel = [];
$productos = [];
$formasPago = [];

// Cargar datos existentes si estamos editando
if ($isEditing) {
    $stmt = $db->prepare("SELECT * FROM emprendimientos WHERE id = ?");
    $stmt->execute([$id]);
    $emprendimiento = $stmt->fetch();
    
    if (!$emprendimiento) {
        redirect('emprendimientos.php');
    }
    
    $configVisual = $db->prepare("SELECT * FROM config_visual WHERE emprendimiento_id = ?");
    $configVisual->execute([$id]);
    $configVisual = $configVisual->fetch() ?: [];
    
    $contactoRedes = $db->prepare("SELECT * FROM contacto_redes WHERE emprendimiento_id = ?");
    $contactoRedes->execute([$id]);
    $contactoRedes = $contactoRedes->fetch() ?: [];
    
    $contenidoTexto = $db->prepare("SELECT * FROM contenido_texto WHERE emprendimiento_id = ?");
    $contenidoTexto->execute([$id]);
    $contenidoTexto = $contenidoTexto->fetch() ?: [];
    
    $carruselStmt = $db->prepare("SELECT * FROM imagenes_carrusel WHERE emprendimiento_id = ? ORDER BY orden ASC");
    $carruselStmt->execute([$id]);
    $imagenesCarrusel = $carruselStmt->fetchAll();
    
    $prodStmt = $db->prepare("SELECT * FROM productos WHERE emprendimiento_id = ? ORDER BY nombre ASC");
    $prodStmt->execute([$id]);
    $productos = $prodStmt->fetchAll();
    
    $pagoStmt = $db->prepare("SELECT * FROM formas_pago WHERE emprendimiento_id = ?");
    $pagoStmt->execute([$id]);
    $formasPago = $pagoStmt->fetchAll();
    // Indexar por tipo
    $formasPagoIndexed = [];
    foreach ($formasPago as $fp) {
        $formasPagoIndexed[$fp['tipo']] = $fp;
    }
    $formasPago = $formasPagoIndexed;
}

// Guardar datos (POST)
$successMessage = '';
$errorMessage = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Verificar token CSRF
    if (!verificarCSRF()) {
        $errorMessage = 'Error de seguridad: token CSRF inválido. Recargá la página y volvé a intentar.';
    } else {
    try {
        $db->beginTransaction();
        
        $slug = trim($_POST['slug'] ?? '');
        $nombre = trim($_POST['nombre'] ?? '');
        $eslogan = trim($_POST['eslogan'] ?? '');
        $activo = isset($_POST['activo']) ? 1 : 0;
        
        if (!$slug || !$nombre) {
            throw new Exception('Nombre y Slug son obligatorios.');
        }
        
        // Verificar slug único
        $slugCheck = $db->prepare("SELECT id FROM emprendimientos WHERE slug = ? AND id != ?");
        $slugCheck->execute([$slug, $id]);
        if ($slugCheck->fetch()) {
            throw new Exception('El slug ya está en uso. Elija otro.');
        }
        
        // Crear o actualizar emprendimiento
        if ($isEditing) {
            $stmt = $db->prepare("UPDATE emprendimientos SET slug = ?, nombre = ?, eslogan = ?, activo = ? WHERE id = ?");
            $stmt->execute([$slug, $nombre, $eslogan, $activo, $id]);
        } else {
            $stmt = $db->prepare("INSERT INTO emprendimientos (slug, nombre, eslogan, activo) VALUES (?, ?, ?, ?)");
            $stmt->execute([$slug, $nombre, $eslogan, $activo]);
            $id = $db->lastInsertId();
            $isEditing = true;
        }
        
        // Guardar config_visual (incluyendo tema)
        $tema = $_POST['tema'] ?? 'classic-blue';
        if (!isset($TEMAS[$tema])) $tema = 'classic-blue';
        $db->prepare("REPLACE INTO config_visual (emprendimiento_id, tema, color_principal, color_secundario, color_fondo, color_texto, logo, favicon, titulo_seo, meta_descripcion) VALUES (?,?,?,?,?,?,?,?,?,?)")
           ->execute([$id, $tema, $_POST['color_principal'] ?? '#2563eb', $_POST['color_secundario'] ?? '#7c3aed', $_POST['color_fondo'] ?? '#ffffff', $_POST['color_texto'] ?? '#1f2937', $_POST['logo_actual'] ?? '', $_POST['favicon_actual'] ?? '', $_POST['titulo_seo'] ?? '', $_POST['meta_descripcion'] ?? '']);
        
        // Guardar contacto_redes
        $db->prepare("REPLACE INTO contacto_redes (emprendimiento_id, telefono, email, direccion, whatsapp_numero, whatsapp_mensaje_auto, whatsapp_horarios, instagram_activo, instagram_link, instagram_usuario, facebook_activo, facebook_link, tiktok_activo, tiktok_link, linkedin_activo, linkedin_link, twitter_activo, twitter_link) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)")
           ->execute([$id, $_POST['telefono'] ?? '', $_POST['email'] ?? '', $_POST['direccion'] ?? '', $_POST['whatsapp_numero'] ?? '', $_POST['whatsapp_mensaje_auto'] ?? '', $_POST['whatsapp_horarios'] ?? '', isset($_POST['instagram_activo']) ? 1 : 0, $_POST['instagram_link'] ?? '', $_POST['instagram_usuario'] ?? '', isset($_POST['facebook_activo']) ? 1 : 0, $_POST['facebook_link'] ?? '', isset($_POST['tiktok_activo']) ? 1 : 0, $_POST['tiktok_link'] ?? '', isset($_POST['linkedin_activo']) ? 1 : 0, $_POST['linkedin_link'] ?? '', isset($_POST['twitter_activo']) ? 1 : 0, $_POST['twitter_link'] ?? '']);
        
        // Guardar contenido_texto
        $db->prepare("REPLACE INTO contenido_texto (emprendimiento_id, texto_quienes_somos, texto_bienvenida, politicas_envio, politicas_devolucion) VALUES (?,?,?,?,?)")
           ->execute([$id, $_POST['texto_quienes_somos'] ?? '', $_POST['texto_bienvenida'] ?? '', $_POST['politicas_envio'] ?? '', $_POST['politicas_devolucion'] ?? '']);
        
        // Guardar imágenes del carrusel
        $db->prepare("DELETE FROM imagenes_carrusel WHERE emprendimiento_id = ?")->execute([$id]);
        for ($i = 1; $i <= 5; $i++) {
            $img = $_POST["carrusel_imagen_{$i}"] ?? '';
            if ($img) {
                $titulo = $_POST["carrusel_titulo_{$i}"] ?? '';
                $subtitulo = $_POST["carrusel_subtitulo_{$i}"] ?? '';
                $db->prepare("INSERT INTO imagenes_carrusel (emprendimiento_id, imagen, orden, titulo, subtitulo) VALUES (?, ?, ?, ?, ?)")
                   ->execute([$id, $img, $i, $titulo, $subtitulo]);
            }
        }
        
        // Guardar productos (UPDATE si tienen ID, INSERT si son nuevos)
        $productoIds = $_POST['producto_id'] ?? [];
        $nombres = $_POST['producto_nombre'] ?? [];
        $precios = $_POST['producto_precio'] ?? [];
        $stocks = $_POST['producto_stock'] ?? [];
        $imagenes = $_POST['producto_imagen'] ?? [];
        $descripciones = $_POST['producto_descripcion'] ?? [];
        $destacados = $_POST['producto_destacado'] ?? [];
        $activos = $_POST['producto_activo'] ?? [];
        
        $idsProcesados = [];
        foreach ($nombres as $i => $nombre) {
            $nombre = trim($nombre);
            if (!$nombre) continue;
            $prodId = (int)($productoIds[$i] ?? 0);
            $precio = (float)($precios[$i] ?? 0);
            $stock = (int)($stocks[$i] ?? 0);
            $imagen = $imagenes[$i] ?? '';
            $descripcion = $descripciones[$i] ?? '';
            $destacado = isset($destacados[$i]) ? 1 : 0;
            $activo = isset($activos[$i]) ? 1 : 0;
            
            if ($prodId > 0) {
                $db->prepare("UPDATE productos SET nombre=?, precio=?, stock=?, imagen=?, descripcion_corta=?, destacado=?, activo=? WHERE id=? AND emprendimiento_id=?")
                   ->execute([$nombre, $precio, $stock, $imagen, $descripcion, $destacado, $activo, $prodId, $id]);
                $idsProcesados[] = $prodId;
            } else {
                $db->prepare("INSERT INTO productos (emprendimiento_id, nombre, precio, stock, imagen, descripcion_corta, destacado, activo) VALUES (?,?,?,?,?,?,?,?)")
                   ->execute([$id, $nombre, $precio, $stock, $imagen, $descripcion, $destacado, $activo]);
            }
        }
        // Eliminar productos que ya no están en el formulario (solo si no tienen ventas)
        if (!empty($idsProcesados)) {
            $ph = implode(',', array_fill(0, count($idsProcesados), '?'));
            $db->prepare("DELETE FROM productos WHERE emprendimiento_id = ? AND id NOT IN ($ph) AND id NOT IN (SELECT DISTINCT producto_id FROM detalle_venta WHERE producto_id IS NOT NULL)")
               ->execute(array_merge([$id], $idsProcesados));
        }
        
        // Guardar formas de pago
        $tiposPago = ['transferencia', 'mercadopago', 'efectivo', 'tarjeta'];
        foreach ($tiposPago as $tipo) {
            $activoPago = isset($_POST["pago_{$tipo}_activo"]) ? 1 : 0;
            $descripcion = $_POST["pago_{$tipo}_descripcion"] ?? '';
            $datosExtra = $_POST["pago_{$tipo}_datos"] ?? '';
            
            $exists = $db->prepare("SELECT id FROM formas_pago WHERE emprendimiento_id = ? AND tipo = ?");
            $exists->execute([$id, $tipo]);
            if ($exists->fetch()) {
                $db->prepare("UPDATE formas_pago SET descripcion = ?, datos_extra = ?, activo = ? WHERE emprendimiento_id = ? AND tipo = ?")
                   ->execute([$descripcion, $datosExtra, $activoPago, $id, $tipo]);
            } else {
                $db->prepare("INSERT INTO formas_pago (emprendimiento_id, tipo, descripcion, datos_extra, activo) VALUES (?,?,?,?,?)")
                   ->execute([$id, $tipo, $descripcion, $datosExtra, $activoPago]);
            }
        }
        
        $db->commit();
        $successMessage = $isEditing ? 'Emprendimiento actualizado correctamente.' : 'Emprendimiento creado correctamente.';
        
        // Recargar datos
        $stmt = $db->prepare("SELECT * FROM emprendimientos WHERE id = ?");
        $stmt->execute([$id]);
        $emprendimiento = $stmt->fetch();
        
    } catch (Exception $e) {
        $db->rollBack();
        $errorMessage = 'Error: ' . $e->getMessage();
    }
    } // Cierra else de CSRF
}

// Slug por defecto para nuevo
$defaultSlug = $isEditing ? $emprendimiento['slug'] : 'mi-emprendimiento-' . rand(100, 999);
$adminNombre = $_SESSION['admin_nombre'];
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $isEditing ? 'Editar' : 'Nuevo' ?> Emprendimiento - Panel Admin</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="assets/admin.css">
</head>
<body>
    <div class="d-flex">
        <div class="sidebar">
            <div class="sidebar-header"><i class="fas fa-store me-2"></i> <span>Multi-Admin</span></div>
            <nav class="sidebar-nav">
                <a href="dashboard.php"><i class="fas fa-chart-pie"></i> Dashboard</a>
                <a href="emprendimientos.php"><i class="fas fa-globe"></i> Emprendimientos</a>
                <a href="emprendimiento-editar.php" class="active"><i class="fas fa-plus-circle"></i> <?= $isEditing ? 'Editar' : 'Nuevo' ?> Sitio</a>
                <a href="pedidos.php"><i class="fas fa-truck"></i> Pedidos</a>
                <a href="soporte-panel.php"><i class="fas fa-headset"></i> Tickets</a>
                <hr class="my-2 opacity-25">
                <a href="?logout" class="text-danger"><i class="fas fa-sign-out-alt"></i> Cerrar Sesión</a>
            </nav>
            <div class="sidebar-footer"><i class="fas fa-user me-1"></i> <?= h($adminNombre) ?></div>
        </div>

        <div class="main-content">
            <div class="d-flex justify-content-between align-items-center mb-4">
                <div>
                    <h2 class="fw-bold mb-1"><?= $isEditing ? 'Editar' : 'Nuevo' ?> Emprendimiento</h2>
                    <p class="text-muted mb-0"><?= $isEditing ? 'Editando: ' . h($emprendimiento['nombre']) : 'Completa los datos para crear un nuevo sitio web' ?></p>
                </div>
                <?php if ($isEditing): ?>
                    <a href="/<?= h($emprendimiento['slug']) ?>" target="_blank" class="btn btn-success">
                        <i class="fas fa-external-link-alt me-1"></i> Ver Sitio
                    </a>
                <?php endif; ?>
            </div>

            <?php if ($successMessage): ?>
                <div class="alert alert-success alert-dismissible fade show"><?= h($successMessage) ?><button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>
            <?php endif; ?>
            <?php if ($errorMessage): ?>
                <div class="alert alert-danger alert-dismissible fade show"><?= h($errorMessage) ?><button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>
            <?php endif; ?>

            <form method="POST" id="emprendimientoForm" enctype="multipart/form-data">
                <?= csrfField() ?>
                <!-- Tabs -->
                <ul class="nav nav-tabs mb-4" id="tabNav" role="tablist">
                    <li class="nav-item"><button class="nav-link active" data-tab="basicos">📋 Datos Básicos</button></li>
                    <li class="nav-item"><button class="nav-link" data-tab="visual">🎨 Identidad Visual</button></li>
                    <li class="nav-item"><button class="nav-link" data-tab="contacto">📞 Contacto</button></li>
                    <li class="nav-item"><button class="nav-link" data-tab="textos">📝 Textos</button></li>
                    <li class="nav-item"><button class="nav-link" data-tab="carrusel">🖼️ Carrusel</button></li>
                    <li class="nav-item"><button class="nav-link" data-tab="productos">📦 Productos</button></li>
                    <li class="nav-item"><button class="nav-link" data-tab="pagos">💳 Pagos</button></li>
                </ul>

                <!-- Tab 1: Datos Básicos -->
                <div class="tab-content" id="tab-basicos">
                    <div class="card border-0 shadow-sm">
                        <div class="card-body">
                            <div class="row g-3">
                                <div class="col-md-6">
                                    <label class="form-label fw-semibold">Nombre del Emprendimiento *</label>
                                    <input type="text" name="nombre" class="form-control" required value="<?= h($emprendimiento['nombre'] ?? '') ?>">
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label fw-semibold">Slug / URL *</label>
                                    <div class="input-group">
                                        <span class="input-group-text bg-light">/</span>
                                        <input type="text" name="slug" class="form-control" required value="<?= h($defaultSlug) ?>" id="slugInput">
                                    </div>
                                    <small class="text-muted">URL amigable: solo letras, números y guiones</small>
                                </div>
                                <div class="col-12">
                                    <label class="form-label fw-semibold">Eslogan</label>
                                    <input type="text" name="eslogan" class="form-control" value="<?= h($emprendimiento['eslogan'] ?? '') ?>">
                                </div>
                                <div class="col-12">
                                    <div class="form-check form-switch">
                                        <input class="form-check-input" type="checkbox" name="activo" id="activoCheck" <?= !$isEditing || $emprendimiento['activo'] ? 'checked' : '' ?>>
                                        <label class="form-check-label" for="activoCheck">Sitio activo (visible al público)</label>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Tab 2: Identidad Visual -->
                <div class="tab-content d-none" id="tab-visual">
                    <div class="card border-0 shadow-sm">
                        <div class="card-body">
                            <!-- Selector de temas predefinidos -->
                            <div class="mb-4 p-3 bg-light rounded-3">
                                <h5 class="fw-bold mb-3"><i class="fas fa-palette me-2 text-primary"></i> Temas Predefinidos</h5>
                                <p class="text-muted small mb-3">Seleccioná un tema y los colores se ajustarán automáticamente. También podés personalizar cada color manualmente.</p>
                                <div class="row g-2" id="themeSelector">
                                    <?php foreach ($TEMAS as $clave => $tema): 
                                        $seleccionado = ($configVisual['tema'] ?? 'classic-blue') === $clave;
                                    ?>
                                    <div class="col-6 col-md-3">
                                        <div class="theme-option card border <?= $seleccionado ? 'border-primary border-2' : '' ?>" 
                                             data-tema="<?= $clave ?>" 
                                             onclick="seleccionarTema('<?= $clave ?>')"
                                             style="cursor:pointer">
                                            <div class="card-body p-3 text-center">
                                                <div class="theme-preview mb-2">
                                                    <span class="badge" style="background:<?= $tema['color_principal'] ?>">&nbsp;&nbsp;&nbsp;</span>
                                                    <span class="badge" style="background:<?= $tema['color_secundario'] ?>">&nbsp;&nbsp;&nbsp;</span>
                                                    <span class="badge" style="background:<?= $tema['color_fondo'] ?>;border:1px solid #ddd">&nbsp;&nbsp;&nbsp;</span>
                                                    <span class="badge" style="background:<?= $tema['color_texto'] ?>">&nbsp;&nbsp;&nbsp;</span>
                                                </div>
                                                <strong class="small d-block"><?= $tema['icono'] ?> <?= h($tema['nombre']) ?></strong>
                                                <small class="text-muted"><?= h($tema['descripcion']) ?></small>
                                            </div>
                                        </div>
                                    </div>
                                    <?php endforeach; ?>
                                </div>
                                <input type="hidden" name="tema" id="temaInput" value="<?= h($configVisual['tema'] ?? 'classic-blue') ?>">
                            </div>

                            <h5 class="fw-bold mb-3"><i class="fas fa-sliders-h me-2"></i> Personalización Manual</h5>
                            <div class="row g-3">
                                <div class="col-md-6">
                                    <label class="form-label fw-semibold">Color Principal</label>
                                    <div class="d-flex align-items-center gap-3">
                                        <input type="color" name="color_principal" id="color_principal" value="<?= h($configVisual['color_principal'] ?? '#2563eb') ?>">
                                        <span class="text-muted small">Usado en botones, enlaces, header</span>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label fw-semibold">Color Secundario</label>
                                    <div class="d-flex align-items-center gap-3">
                                        <input type="color" name="color_secundario" id="color_secundario" value="<?= h($configVisual['color_secundario'] ?? '#7c3aed') ?>">
                                        <span class="text-muted small">Usado en acentos y hover</span>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label fw-semibold">Color de Fondo</label>
                                    <input type="color" name="color_fondo" id="color_fondo" value="<?= h($configVisual['color_fondo'] ?? '#ffffff') ?>">
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label fw-semibold">Color de Texto</label>
                                    <input type="color" name="color_texto" id="color_texto" value="<?= h($configVisual['color_texto'] ?? '#1f2937') ?>">
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label fw-semibold">Logo</label>
                                    <input type="file" class="form-control" accept="image/*" onchange="uploadFile(this, 'logo')">
                                    <input type="hidden" name="logo_actual" id="logo_actual" value="<?= h($configVisual['logo'] ?? '') ?>">
                                    <div id="logoPreview" class="mt-2">
                                        <?php if (!empty($configVisual['logo'])): ?>
                                            <img src="/<?= h($emprendimiento['slug']) ?>/uploads/<?= h($configVisual['logo']) ?>" class="upload-preview">
                                        <?php endif; ?>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label fw-semibold">Favicon</label>
                                    <input type="file" class="form-control" accept="image/*" onchange="uploadFile(this, 'favicon')">
                                    <input type="hidden" name="favicon_actual" id="favicon_actual" value="<?= h($configVisual['favicon'] ?? '') ?>">
                                    <div id="faviconPreview" class="mt-2">
                                        <?php if (!empty($configVisual['favicon'])): ?>
                                            <img src="/<?= h($emprendimiento['slug']) ?>/uploads/<?= h($configVisual['favicon']) ?>" class="upload-preview" style="width:48px;height:48px">
                                        <?php endif; ?>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label fw-semibold">Título SEO</label>
                                    <input type="text" name="titulo_seo" class="form-control" value="<?= h($configVisual['titulo_seo'] ?? '') ?>">
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label fw-semibold">Meta Descripción</label>
                                    <textarea name="meta_descripcion" class="form-control" rows="3"><?= h($configVisual['meta_descripcion'] ?? '') ?></textarea>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Tab 3: Contacto y Redes -->
                <div class="tab-content d-none" id="tab-contacto">
                    <div class="card border-0 shadow-sm">
                        <div class="card-body">
                            <h5 class="fw-bold mb-3"><i class="fas fa-phone text-primary me-2"></i> Información de Contacto</h5>
                            <div class="row g-3 mb-4">
                                <div class="col-md-4">
                                    <label class="form-label">Teléfono</label>
                                    <input type="text" name="telefono" class="form-control" value="<?= h($contactoRedes['telefono'] ?? '') ?>">
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label">Email</label>
                                    <input type="email" name="email" class="form-control" value="<?= h($contactoRedes['email'] ?? '') ?>">
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label">Dirección</label>
                                    <input type="text" name="direccion" class="form-control" value="<?= h($contactoRedes['direccion'] ?? '') ?>">
                                </div>
                            </div>

                            <h5 class="fw-bold mb-3"><i class="fab fa-whatsapp text-success me-2"></i> WhatsApp</h5>
                            <div class="row g-3 mb-4">
                                <div class="col-md-4">
                                    <label class="form-label">Número *</label>
                                    <input type="text" name="whatsapp_numero" class="form-control" value="<?= h($contactoRedes['whatsapp_numero'] ?? '') ?>" placeholder="+5491123456789">
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label">Mensaje automático</label>
                                    <input type="text" name="whatsapp_mensaje_auto" class="form-control" value="<?= h($contactoRedes['whatsapp_mensaje_auto'] ?? '') ?>" placeholder="¡Hola! Quiero consultar sobre...">
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label">Horarios de atención</label>
                                    <input type="text" name="whatsapp_horarios" class="form-control" value="<?= h($contactoRedes['whatsapp_horarios'] ?? '') ?>" placeholder="Lun a Vie 9-18hs">
                                </div>
                            </div>

                            <h5 class="fw-bold mb-3"><i class="fas fa-share-alt text-primary me-2"></i> Redes Sociales</h5>
                            <?php $redes = [
                                'instagram' => ['icon' => 'fab fa-instagram', 'color' => '#E4405F', 'label' => 'Instagram'],
                                'facebook' => ['icon' => 'fab fa-facebook', 'color' => '#1877F2', 'label' => 'Facebook'],
                                'tiktok' => ['icon' => 'fab fa-tiktok', 'color' => '#000000', 'label' => 'TikTok'],
                                'linkedin' => ['icon' => 'fab fa-linkedin', 'color' => '#0A66C2', 'label' => 'LinkedIn'],
                                'twitter' => ['icon' => 'fab fa-twitter', 'color' => '#1DA1F2', 'label' => 'Twitter / X']
                            ];
                            foreach ($redes as $key => $red): ?>
                                <div class="card mb-2 border">
                                    <div class="card-body py-2">
                                        <div class="row g-2 align-items-center">
                                            <div class="col-auto">
                                                <div class="form-check form-switch">
                                                    <input class="form-check-input" type="checkbox" name="<?= $key ?>_activo" id="<?= $key ?>_activo" <?= !empty($contactoRedes["{$key}_activo"]) ? 'checked' : '' ?>>
                                                </div>
                                            </div>
                                            <div class="col-auto">
                                                <i class="<?= $red['icon'] ?>" style="color:<?= $red['color'] ?>;font-size:20px"></i>
                                            </div>
                                            <div class="col">
                                                <label class="form-label mb-0 fw-semibold"><?= $red['label'] ?></label>
                                                <input type="text" name="<?= $key ?>_link" class="form-control form-control-sm" placeholder="Link completo" value="<?= h($contactoRedes["{$key}_link"] ?? '') ?>">
                                            </div>
                                            <?php if ($key === 'instagram'): ?>
                                                <div class="col">
                                                    <label class="form-label mb-0 small">@usuario</label>
                                                    <input type="text" name="instagram_usuario" class="form-control form-control-sm" placeholder="@usuario" value="<?= h($contactoRedes['instagram_usuario'] ?? '') ?>">
                                                </div>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>

                <!-- Tab 4: Textos -->
                <div class="tab-content d-none" id="tab-textos">
                    <div class="card border-0 shadow-sm">
                        <div class="card-body">
                            <div class="mb-3">
                                <label class="form-label fw-semibold">Texto de Bienvenida (Home)</label>
                                <textarea name="texto_bienvenida" class="form-control" rows="4"><?= h($contenidoTexto['texto_bienvenida'] ?? '') ?></textarea>
                            </div>
                            <div class="mb-3">
                                <label class="form-label fw-semibold">Quiénes Somos</label>
                                <textarea name="texto_quienes_somos" class="form-control" rows="6"><?= h($contenidoTexto['texto_quienes_somos'] ?? '') ?></textarea>
                                <small class="text-muted">Puedes usar HTML básico: &lt;strong&gt;, &lt;em&gt;, &lt;br&gt;</small>
                            </div>
                            <div class="mb-3">
                                <label class="form-label fw-semibold">Políticas de Envío</label>
                                <textarea name="politicas_envio" class="form-control" rows="4"><?= h($contenidoTexto['politicas_envio'] ?? '') ?></textarea>
                            </div>
                            <div class="mb-3">
                                <label class="form-label fw-semibold">Políticas de Devolución</label>
                                <textarea name="politicas_devolucion" class="form-control" rows="4"><?= h($contenidoTexto['politicas_devolucion'] ?? '') ?></textarea>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Tab 5: Carrusel -->
                <div class="tab-content d-none" id="tab-carrusel">
                    <div class="card border-0 shadow-sm">
                        <div class="card-body">
                            <p class="text-muted mb-3">Sube hasta 5 imágenes para el carrusel de la página principal. Arrastra para reordenar.</p>
                            <div id="carruselContainer">
                                <?php for ($i = 1; $i <= 5; $i++):
                                    $imgData = $imagenesCarrusel[$i-1] ?? null;
                                ?>
                                <div class="card mb-2 carrusel-item" data-index="<?= $i ?>">
                                    <div class="card-body py-2">
                                        <div class="row g-2 align-items-center">
                                            <div class="col-auto">
                                                <span class="badge bg-secondary"><?= $i ?></span>
                                            </div>
                                            <div class="col-md-3">
                                                <input type="file" class="form-control form-control-sm" accept="image/*" onchange="uploadCarrusel(this, <?= $i ?>)">
                                                <input type="hidden" name="carrusel_imagen_<?= $i ?>" id="carrusel_img_<?= $i ?>" value="<?= h($imgData['imagen'] ?? '') ?>">
                                                <div id="carruselPreview_<?= $i ?>">
                                                    <?php if ($imgData && !empty($imgData['imagen'])): ?>
                                                        <img src="/<?= h($emprendimiento['slug']) ?>/uploads/<?= h($imgData['imagen']) ?>" class="upload-preview mt-1">
                                                    <?php endif; ?>
                                                </div>
                                            </div>
                                            <div class="col-md-3">
                                                <label class="small">Título</label>
                                                <input type="text" name="carrusel_titulo_<?= $i ?>" class="form-control form-control-sm" value="<?= h($imgData['titulo'] ?? '') ?>">
                                            </div>
                                            <div class="col-md-4">
                                                <label class="small">Subtítulo</label>
                                                <input type="text" name="carrusel_subtitulo_<?= $i ?>" class="form-control form-control-sm" value="<?= h($imgData['subtitulo'] ?? '') ?>">
                                            </div>
                                            <div class="col-auto">
                                                <button type="button" class="btn btn-outline-danger btn-sm" onclick="clearCarrusel(<?= $i ?>)"><i class="fas fa-times"></i></button>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                <?php endfor; ?>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Tab 6: Productos -->
                <div class="tab-content d-none" id="tab-productos">
                    <div class="card border-0 shadow-sm">
                        <div class="card-body">
                            <div class="d-flex justify-content-between mb-3">
                                <h5 class="fw-bold mb-0"><i class="fas fa-box text-primary me-2"></i> Productos</h5>
                                <button type="button" class="btn btn-primary btn-sm" onclick="addProducto()">
                                    <i class="fas fa-plus me-1"></i> Agregar Producto
                                </button>
                            </div>
                            <div id="productosContainer">
                                <?php if ($isEditing): foreach ($productos as $i => $prod): ?>
                                    <div class="card mb-2 producto-item">
                                        <div class="card-body py-2">
                                            <div class="row g-2 align-items-center">
                                                <div class="col-md-3">
                                                    <label class="small">Nombre *</label>
                                                    <input type="text" name="producto_nombre[]" class="form-control form-control-sm" required value="<?= h($prod['nombre']) ?>">
                                                </div>
                                                <div class="col-md-2">
                                                    <label class="small">Precio *</label>
                                                    <input type="number" step="0.01" name="producto_precio[]" class="form-control form-control-sm" required value="<?= h($prod['precio']) ?>">
                                                </div>
                                                <div class="col-md-1">
                                                    <label class="small">Stock *</label>
                                                    <input type="number" name="producto_stock[]" class="form-control form-control-sm" required value="<?= h($prod['stock']) ?>">
                                                </div>
                                                <div class="col-md-2">
                                                    <label class="small">Imagen</label>
                                                    <input type="file" class="form-control form-control-sm" accept="image/*" onchange="uploadProducto(this, <?= $i ?>)">                                                        <input type="hidden" name="producto_id[]" value="<?= $prod['id'] ?>">
                                                        <input type="hidden" name="producto_imagen[]" class="producto_img_input" value="<?= h($prod['imagen'] ?? '') ?>">
                                                    <?php if ($prod['imagen']): ?>
                                                        <img src="/<?= h($emprendimiento['slug']) ?>/uploads/<?= h($prod['imagen']) ?>" class="upload-preview mt-1" style="width:64px;height:64px">
                                                    <?php endif; ?>
                                                </div>
                                                <div class="col-md-2">
                                                    <label class="small">Descripción</label>
                                                    <input type="text" name="producto_descripcion[]" class="form-control form-control-sm" value="<?= h($prod['descripcion_corta'] ?? '') ?>">
                                                </div>
                                                <div class="col-auto">
                                                    <div class="form-check">
                                                        <input class="form-check-input" type="checkbox" name="producto_destacado[]" value="1" <?= $prod['destacado'] ? 'checked' : '' ?>>
                                                        <label class="small">Destacado</label>
                                                    </div>
                                                    <div class="form-check">
                                                        <input class="form-check-input" type="checkbox" name="producto_activo[]" value="1" <?= $prod['activo'] ? 'checked' : '' ?>>
                                                        <label class="small">Activo</label>
                                                    </div>
                                                </div>
                                                <div class="col-auto">
                                                    <button type="button" class="btn btn-outline-danger btn-sm" onclick="this.closest('.producto-item').remove()"><i class="fas fa-trash"></i></button>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                <?php endforeach; endif; ?>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Tab 7: Formas de Pago -->
                <div class="tab-content d-none" id="tab-pagos">
                    <div class="card border-0 shadow-sm">
                        <div class="card-body">
                            <?php $pagos = [
                                'transferencia' => ['icon' => 'fas fa-university', 'label' => 'Transferencia Bancaria', 'color' => '#2563eb'],
                                'mercadopago' => ['icon' => 'fas fa-credit-card', 'label' => 'Mercado Pago', 'color' => '#00b1ea'],
                                'efectivo' => ['icon' => 'fas fa-money-bill-wave', 'label' => 'Efectivo', 'color' => '#10b981'],
                                'tarjeta' => ['icon' => 'fas fa-id-card', 'label' => 'Tarjeta', 'color' => '#7c3aed']
                            ];
                            foreach ($pagos as $key => $pago):
                                $fpData = $formasPago[$key] ?? []; ?>
                                <div class="card mb-3">
                                    <div class="card-body">
                                        <div class="d-flex align-items-center gap-3 mb-2">
                                            <div class="form-check form-switch">
                                                <input class="form-check-input" type="checkbox" name="pago_<?= $key ?>_activo" id="pago_<?= $key ?>_activo" <?= (!empty($fpData) && $fpData['activo']) ? 'checked' : '' ?>>
                                            </div>
                                            <i class="<?= $pago['icon'] ?>" style="color:<?= $pago['color'] ?>;font-size:24px"></i>
                                            <h6 class="mb-0 fw-bold"><?= $pago['label'] ?></h6>
                                        </div>
                                        <div class="row g-2">
                                            <div class="col-md-6">
                                                <label class="small">Descripción</label>
                                                <input type="text" name="pago_<?= $key ?>_descripcion" class="form-control form-control-sm" value="<?= h($fpData['descripcion'] ?? '') ?>" placeholder="Ej: Transferencia a CBU 0000000000...">
                                            </div>
                                            <div class="col-md-6">
                                                <label class="small">Datos adicionales</label>
                                                <input type="text" name="pago_<?= $key ?>_datos" class="form-control form-control-sm" value="<?= h($fpData['datos_extra'] ?? '') ?>" placeholder="Titular, CBU, Alias, etc.">
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>

                <!-- Botón guardar -->
                <div class="mt-4 d-flex justify-content-between">
                    <a href="emprendimientos.php" class="btn btn-outline-secondary">
                        <i class="fas fa-arrow-left me-1"></i> Volver
                    </a>
                    <button type="submit" class="btn btn-primary btn-lg px-5">
                        <i class="fas fa-save me-2"></i> Guardar
                        <?php if ($isEditing): ?><small class="opacity-75 ms-1">y Actualizar Sitio</small><?php endif; ?>
                    </button>
                </div>
            </form>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
    // Navegación por tabs
    document.querySelectorAll('[data-tab]').forEach(btn => {
        btn.addEventListener('click', function(e) {
            e.preventDefault();
            const tab = this.dataset.tab;
            document.querySelectorAll('.nav-tabs .nav-link').forEach(t => t.classList.remove('active'));
            this.classList.add('active');
            document.querySelectorAll('.tab-content').forEach(tc => tc.classList.add('d-none'));
            document.getElementById('tab-' + tab).classList.remove('d-none');
        });
    });

    // Auto-generar slug desde nombre
    document.querySelector('input[name="nombre"]')?.addEventListener('input', function() {
        const slugInput = document.getElementById('slugInput');
        if (!slugInput.dataset.manuallyEdited) {
            slugInput.value = this.value.toLowerCase()
                .replace(/[^a-z0-9áéíóúüñ\s-]/g, '')
                .replace(/[á]/g, 'a').replace(/[é]/g, 'e').replace(/[í]/g, 'i')
                .replace(/[ó]/g, 'o').replace(/[ú]/g, 'u').replace(/[ü]/g, 'u').replace(/[ñ]/g, 'n')
                .replace(/\s+/g, '-')
                .replace(/-+/g, '-')
                .replace(/^-|-$/g, '');
        }
    });

    document.getElementById('slugInput')?.addEventListener('input', function() {
        this.dataset.manuallyEdited = 'true';
    });

    // Subida de archivos
    function uploadFile(input, type) {
        const file = input.files[0];
        if (!file) return;

        const slug = document.querySelector('input[name="slug"]')?.value || 'temp';
        const formData = new FormData();
        formData.append('file', file);
        formData.append('slug', slug);
        formData.append('type', type);

        fetch('upload.php', { method: 'POST', body: formData })
            .then(r => r.json())
            .then(data => {
                if (data.success) {
                    const preview = document.getElementById(type + 'Preview');
                    const hidden = document.getElementById(type + '_actual');
                    hidden.value = data.filename;
                    preview.innerHTML = `<img src="${data.url}" class="upload-preview">`;
                } else {
                    alert('Error: ' + (data.error || 'Error al subir archivo'));
                }
            })
            .catch(e => alert('Error de conexión'));
    }

    // Carrusel de imágenes
    let carruselCounter = <?= count($imagenesCarrusel) + 1 ?>;

    function uploadCarrusel(input, index) {
        const file = input.files[0];
        if (!file) return;

        const slug = document.querySelector('input[name="slug"]')?.value || 'temp';
        const formData = new FormData();
        formData.append('file', file);
        formData.append('slug', slug);
        formData.append('type', 'carrusel');

        fetch('upload.php', { method: 'POST', body: formData })
            .then(r => r.json())
            .then(data => {
                if (data.success) {
                    const preview = document.getElementById('carruselPreview_' + index);
                    const hidden = document.getElementById('carrusel_img_' + index);
                    hidden.value = data.filename;
                    preview.innerHTML = `<img src="${data.url}" class="upload-preview mt-1">`;
                } else {
                    alert('Error: ' + (data.error || 'Error al subir imagen'));
                }
            })
            .catch(e => alert('Error de conexión'));
    }

    function clearCarrusel(index) {
        document.getElementById('carrusel_img_' + index).value = '';
        document.getElementById('carruselPreview_' + index).innerHTML = '';
        document.querySelector(`.carrusel-item[data-index="${index}"] input[type="file"]`).value = '';
    }

    // Temas predefinidos
    const TEMAS = <?= json_encode($TEMAS) ?>;
    
    function seleccionarTema(clave) {
        const tema = TEMAS[clave];
        if (!tema) return;
        
        // Actualizar hidden input
        document.getElementById('temaInput').value = clave;
        
        // Actualizar colores
        document.getElementById('color_principal').value = tema.color_principal;
        document.getElementById('color_secundario').value = tema.color_secundario;
        document.getElementById('color_fondo').value = tema.color_fondo;
        document.getElementById('color_texto').value = tema.color_texto;
        
        // Actualizar visual de selección
        document.querySelectorAll('.theme-option').forEach(el => {
            el.classList.remove('border-primary', 'border-2');
            if (el.dataset.tema === clave) {
                el.classList.add('border-primary', 'border-2');
            }
        });
    }
    
    // Productos dinámicos
    let productoCounter = <?= max(count($productos), 1) ?>;

    function addProducto() {
        const container = document.getElementById('productosContainer');
        const idx = productoCounter++;
        const html = `
            <div class="card mb-2 producto-item">
                <div class="card-body py-2">
                    <div class="row g-2 align-items-center">
                        <div class="col-md-3">
                            <label class="small">Nombre *</label>
                            <input type="text" name="producto_nombre[]" class="form-control form-control-sm" required>
                        </div>
                        <div class="col-md-2">
                            <label class="small">Precio *</label>
                            <input type="number" step="0.01" name="producto_precio[]" class="form-control form-control-sm" required>
                        </div>
                        <div class="col-md-1">
                            <label class="small">Stock *</label>
                            <input type="number" name="producto_stock[]" class="form-control form-control-sm" required value="0">
                        </div>
                        <div class="col-md-2">
                            <label class="small">Imagen</label>
                            <input type="file" class="form-control form-control-sm" accept="image/*">
                            <input type="hidden" name="producto_id[]" value="0">
                            <input type="hidden" name="producto_imagen[]" value="">
                        </div>
                        <div class="col-md-2">
                            <label class="small">Descripción</label>
                            <input type="text" name="producto_descripcion[]" class="form-control form-control-sm">
                        </div>
                        <div class="col-auto">
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" name="producto_destacado[]" value="1">
                                <label class="small">Destacado</label>
                            </div>
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" name="producto_activo[]" value="1" checked>
                                <label class="small">Activo</label>
                            </div>
                        </div>
                        <div class="col-auto">
                            <button type="button" class="btn btn-outline-danger btn-sm" onclick="this.closest('.producto-item').remove()"><i class="fas fa-trash"></i></button>
                        </div>
                    </div>
                </div>
            </div>`;
        container.insertAdjacentHTML('beforeend', html);
    }
    </script>
</body>
</html>
