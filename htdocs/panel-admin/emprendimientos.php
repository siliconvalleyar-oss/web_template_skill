<?php
/**
 * Listado de emprendimientos
 * Features: Duplicar, Exportar/Importar JSON, Backup al eliminar, CSRF
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

// Procesar acciones POST (CSRF protegidas)
$message = '';
$messageType = 'success';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verificarCSRF()) {
        $message = 'Error de seguridad: token CSRF inválido.';
        $messageType = 'danger';
    } elseif (isset($_POST['action'])) {
        try {
            switch ($_POST['action']) {
                // ========== DUPLICAR ==========
                case 'duplicar':
                    $sourceId = (int)($_POST['id'] ?? 0);
                    $newName = trim($_POST['nuevo_nombre'] ?? '');
                    $newSlug = trim($_POST['nuevo_slug'] ?? '');
                    if (!$sourceId || !$newName || !$newSlug) {
                        throw new Exception('Faltan datos para duplicar.');
                    }
                    // Verificar slug único
                    $check = $db->prepare("SELECT id FROM emprendimientos WHERE slug = ?");
                    $check->execute([$newSlug]);
                    if ($check->fetch()) {
                        throw new Exception("El slug '$newSlug' ya está en uso.");
                    }
                    $newId = duplicarEmprendimiento($db, $sourceId, $newSlug, $newName);
                    $message = "Emprendimiento duplicado correctamente. <a href='emprendimiento-editar.php?id=$newId' class='alert-link'>Editar nuevo</a>";
                    break;

                // ========== IMPORTAR JSON ==========
                case 'importar':
                    if (!isset($_FILES['json_file']) || $_FILES['json_file']['error'] !== UPLOAD_ERR_OK) {
                        throw new Exception('Error al subir el archivo JSON.');
                    }
                    $jsonContent = file_get_contents($_FILES['json_file']['tmp_name']);
                    $data = json_decode($jsonContent, true);
                    if (!$data || !isset($data['export_version'], $data['emprendimiento'])) {
                        throw new Exception('El archivo JSON no tiene el formato válido de exportación.');
                    }
                    $newSlug = trim($_POST['import_slug'] ?? $data['emprendimiento']['slug'] . '-importado');
                    $newName = trim($_POST['import_nombre'] ?? $data['emprendimiento']['nombre'] . ' (Importado)');

                    // Verificar slug único
                    $check = $db->prepare("SELECT id FROM emprendimientos WHERE slug = ?");
                    $check->execute([$newSlug]);
                    if ($check->fetch()) {
                        throw new Exception("El slug '$newSlug' ya está en uso.");
                    }

                    $db->beginTransaction();
                    try {
                        // Crear emprendimiento
                        $stmt = $db->prepare("INSERT INTO emprendimientos (slug, nombre, eslogan, activo) VALUES (?, ?, ?, 0)");
                        $stmt->execute([$newSlug, $newName, $data['emprendimiento']['eslogan'] ?? '']);
                        $newId = $db->lastInsertId();

                        // Importar config_visual
                        $cv = $data['config_visual'] ?? [];
                        if (!empty($cv)) {
                            $db->prepare("INSERT INTO config_visual (emprendimiento_id, tema, color_principal, color_secundario, color_fondo, color_texto, logo, favicon, titulo_seo, meta_descripcion) VALUES (?,?,?,?,?,?,?,?,?,?)")
                               ->execute([$newId, $cv['tema'] ?? 'classic-blue', $cv['color_principal'] ?? '#2563eb', $cv['color_secundario'] ?? '#7c3aed', $cv['color_fondo'] ?? '#ffffff', $cv['color_texto'] ?? '#1f2937', '', '', $cv['titulo_seo'] ?? '', $cv['meta_descripcion'] ?? '']);
                        }

                        // Importar contacto_redes
                        $cr = $data['contacto_redes'] ?? [];
                        if (!empty($cr)) {
                            $db->prepare("INSERT INTO contacto_redes (emprendimiento_id, telefono, email, direccion, whatsapp_numero, whatsapp_mensaje_auto, whatsapp_horarios, instagram_activo, instagram_link, instagram_usuario, facebook_activo, facebook_link, tiktok_activo, tiktok_link, linkedin_activo, linkedin_link, twitter_activo, twitter_link) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)")
                               ->execute([$newId, $cr['telefono'] ?? '', $cr['email'] ?? '', $cr['direccion'] ?? '', $cr['whatsapp_numero'] ?? '', $cr['whatsapp_mensaje_auto'] ?? '', $cr['whatsapp_horarios'] ?? '', $cr['instagram_activo'] ?? 0, $cr['instagram_link'] ?? '', $cr['instagram_usuario'] ?? '', $cr['facebook_activo'] ?? 0, $cr['facebook_link'] ?? '', $cr['tiktok_activo'] ?? 0, $cr['tiktok_link'] ?? '', $cr['linkedin_activo'] ?? 0, $cr['linkedin_link'] ?? '', $cr['twitter_activo'] ?? 0, $cr['twitter_link'] ?? '']);
                        }

                        // Importar contenido_texto
                        $ct = $data['contenido_texto'] ?? [];
                        if (!empty($ct)) {
                            $db->prepare("INSERT INTO contenido_texto (emprendimiento_id, texto_quienes_somos, texto_bienvenida, politicas_envio, politicas_devolucion) VALUES (?,?,?,?,?)")
                               ->execute([$newId, $ct['texto_quienes_somos'] ?? '', $ct['texto_bienvenida'] ?? '', $ct['politicas_envio'] ?? '', $ct['politicas_devolucion'] ?? '']);
                        }

                        // Importar productos
                        foreach ($data['productos'] ?? [] as $p) {
                            $db->prepare("INSERT INTO productos (emprendimiento_id, nombre, precio, stock, imagen, descripcion_corta, destacado, activo) VALUES (?,?,?,?,?,?,?,?)")
                               ->execute([$newId, $p['nombre'], $p['precio'], $p['stock'], '', $p['descripcion_corta'] ?? '', $p['destacado'] ?? 0, $p['activo'] ?? 1]);
                        }

                        // Importar formas de pago
                        foreach ($data['formas_pago'] ?? [] as $fp) {
                            $db->prepare("INSERT INTO formas_pago (emprendimiento_id, tipo, descripcion, datos_extra, activo) VALUES (?,?,?,?,?)")
                               ->execute([$newId, $fp['tipo'], $fp['descripcion'] ?? '', $fp['datos_extra'] ?? '', $fp['activo'] ?? 1]);
                        }

                        // Importar imágenes carrusel
                        foreach ($data['imagenes_carrusel'] ?? [] as $img) {
                            $db->prepare("INSERT INTO imagenes_carrusel (emprendimiento_id, imagen, orden, titulo, subtitulo) VALUES (?,?,?,?,?)")
                               ->execute([$newId, '', $img['orden'] ?? 0, $img['titulo'] ?? '', $img['subtitulo'] ?? '']);
                        }

                        $db->commit();
                        $message = "Emprendimiento importado correctamente. <a href='emprendimiento-editar.php?id=$newId' class='alert-link'>Editar</a>";
                    } catch (Exception $e) {
                        $db->rollBack();
                        throw $e;
                    }
                    break;

                // ========== ELIMINAR CON BACKUP ==========
                case 'eliminar':
                    $deleteId = (int)($_POST['id'] ?? 0);
                    $hacerBackup = isset($_POST['hacer_backup']);
                    if (!$deleteId) throw new Exception('ID inválido.');

                    // Obtener datos antes de eliminar
                    $data = exportarEmprendimiento($db, $deleteId);
                    if (!$data) throw new Exception('Emprendimiento no encontrado.');

                    if ($hacerBackup) {
                        $backupPath = guardarBackup($data);
                    }

                    // Eliminar (CASCADE elimina todos los datos relacionados)
                    $stmt = $db->prepare("DELETE FROM emprendimientos WHERE id = ?");
                    $stmt->execute([$deleteId]);

                    $msg = 'Emprendimiento eliminado correctamente.';
                    if ($hacerBackup) {
                        $msg .= " Backup guardado en: <code>" . h(basename($backupPath)) . "</code>";
                    }
                    $message = $msg;
                    break;
            }
        } catch (Exception $e) {
            $message = 'Error: ' . $e->getMessage();
            $messageType = 'danger';
        }
    }
}

// Procesar acciones GET (toggle activo, descarga JSON export)
if (isset($_GET['toggle']) && is_numeric($_GET['toggle'])) {
    try {
        $stmt = $db->prepare("UPDATE emprendimientos SET activo = NOT activo WHERE id = ?");
        $stmt->execute([$_GET['toggle']]);
        $message = 'Estado cambiado correctamente.';
    } catch (Exception $e) {
        $message = 'Error al cambiar estado.';
        $messageType = 'danger';
    }
}

if (isset($_GET['export']) && is_numeric($_GET['export'])) {
    try {
        $data = exportarEmprendimiento($db, (int)$_GET['export']);
        if (!$data) throw new Exception('No encontrado');
        $filename = 'emprendimiento_' . ($data['emprendimiento']['slug'] ?? 'export') . '_' . date('Y-m-d') . '.json';
        header('Content-Type: application/json');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        echo json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
        exit;
    } catch (Exception $e) {
        $message = 'Error al exportar: ' . $e->getMessage();
        $messageType = 'danger';
    }
}

// Búsqueda y filtros
$search = $_GET['search'] ?? '';
$filter = $_GET['filter'] ?? '';

$sql = "SELECT e.*, 
               (SELECT COUNT(*) FROM productos p WHERE p.emprendimiento_id = e.id) as total_productos,
               (SELECT COUNT(*) FROM ventas v WHERE v.emprendimiento_id = e.id) as total_ventas
        FROM emprendimientos e WHERE 1=1";
$params = [];

if ($search) {
    $sql .= " AND (e.nombre LIKE ? OR e.slug LIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
}

if ($filter === 'activos') {
    $sql .= " AND e.activo = TRUE";
} elseif ($filter === 'inactivos') {
    $sql .= " AND e.activo = FALSE";
}

$sql .= " ORDER BY e.ultima_modificacion DESC";
$stmt = $db->prepare($sql);
$stmt->execute($params);
$emprendimientos = $stmt->fetchAll();

$adminNombre = $_SESSION['admin_nombre'];
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Emprendimientos - Panel Admin</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="assets/admin.css">
</head>
<body>
    <div class="d-flex">
        <div class="sidebar">
            <div class="sidebar-header">
                <i class="fas fa-store me-2"></i> <span>Multi-Admin</span>
            </div>
            <nav class="sidebar-nav">
                <a href="dashboard.php"><i class="fas fa-chart-pie"></i> Dashboard</a>
                <a href="emprendimientos.php" class="active"><i class="fas fa-globe"></i> Emprendimientos</a>
                <a href="emprendimiento-editar.php"><i class="fas fa-plus-circle"></i> Nuevo Sitio</a>
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
                    <h2 class="fw-bold mb-1">Emprendimientos</h2>
                    <p class="text-muted mb-0">Gestiona todos los sitios web (máximo 30)</p>
                </div>
                <div class="d-flex gap-2">
                    <button type="button" class="btn btn-outline-success" data-bs-toggle="modal" data-bs-target="#importModal">
                        <i class="fas fa-file-import me-1"></i> Importar JSON
                    </button>
                    <a href="emprendimiento-editar.php" class="btn btn-primary">
                        <i class="fas fa-plus me-1"></i> Nuevo Emprendimiento
                    </a>
                </div>
            </div>

            <?php if ($message): ?>
                <div class="alert alert-<?= $messageType ?> alert-dismissible fade show">
                    <?= $message ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>

            <!-- Filtros y búsqueda -->
            <div class="card border-0 shadow-sm mb-4">
                <div class="card-body">
                    <div class="row g-3 align-items-center">
                        <div class="col-md-5">
                            <div class="search-box">
                                <i class="fas fa-search"></i>
                                <input type="text" class="form-control" id="searchInput" placeholder="Buscar por nombre o slug..." value="<?= h($search) ?>">
                            </div>
                        </div>
                        <div class="col-md-3">
                            <select class="form-select" id="filterSelect">
                                <option value="">Todos</option>
                                <option value="activos" <?= $filter === 'activos' ? 'selected' : '' ?>>Activos</option>
                                <option value="inactivos" <?= $filter === 'inactivos' ? 'selected' : '' ?>>Inactivos</option>
                            </select>
                        </div>
                        <div class="col-md-2">
                            <span class="badge bg-primary fs-6 px-3 py-2"><?= count($emprendimientos) ?> / 30</span>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Tabla -->
            <div class="card border-0 shadow-sm">
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th>Nombre</th>
                                    <th>Slug / URL</th>
                                    <th>Productos</th>
                                    <th>Ventas</th>
                                    <th>Creado</th>
                                    <th>Estado</th>
                                    <th>Acciones</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (count($emprendimientos) === 0): ?>
                                    <tr>
                                        <td colspan="7" class="text-center py-5 text-muted">
                                            <i class="fas fa-inbox fa-3x mb-3 d-block"></i>
                                            No se encontraron emprendimientos.
                                            <a href="emprendimiento-editar.php" class="d-block mt-2">Crear el primero</a>
                                        </td>
                                    </tr>
                                <?php else:
                                    foreach ($emprendimientos as $emp): ?>
                                        <tr>
                                            <td class="fw-semibold"><?= h($emp['nombre']) ?></td>
                                            <td>
                                                <code><?= h($emp['slug']) ?></code>
                                                <br>
                                                <a href="/<?= h($emp['slug']) ?>" target="_blank" class="small text-primary">
                                                    <i class="fas fa-external-link-alt fa-xs"></i> Ver sitio
                                                </a>
                                            </td>
                                            <td><?= $emp['total_productos'] ?></td>
                                            <td><?= $emp['total_ventas'] ?></td>
                                            <td class="small"><?= date('d/m/Y', strtotime($emp['fecha_creacion'])) ?></td>
                                            <td>
                                                <?php if ($emp['activo']): ?>
                                                    <span class="badge bg-success">Activo</span>
                                                <?php else: ?>
                                                    <span class="badge bg-secondary">Inactivo</span>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <div class="btn-group btn-group-sm">
                                                    <a href="emprendimiento-editar.php?id=<?= $emp['id'] ?>" class="btn btn-outline-primary" title="Editar">
                                                        <i class="fas fa-edit"></i>
                                                    </a>
                                                    <a href="?toggle=<?= $emp['id'] ?>" class="btn btn-outline-warning" title="Activar/Desactivar">
                                                        <i class="fas fa-power-off"></i>
                                                    </a>
                                                    <button type="button" class="btn btn-outline-info" title="Duplicar"
                                                            onclick="abrirDuplicar(<?= $emp['id'] ?>, '<?= h(addslashes($emp['nombre'])) ?>', '<?= h($emp['slug']) ?>')">
                                                        <i class="fas fa-copy"></i>
                                                    </button>
                                                    <a href="?export=<?= $emp['id'] ?>" class="btn btn-outline-success" title="Exportar JSON">
                                                        <i class="fas fa-file-export"></i>
                                                    </a>
                                                    <button type="button" class="btn btn-outline-danger" title="Eliminar"
                                                            onclick="abrirEliminar(<?= $emp['id'] ?>, '<?= h(addslashes($emp['nombre'])) ?>')">
                                                        <i class="fas fa-trash"></i>
                                                    </button>
                                                </div>
                                            </td>
                                        </tr>
                                    <?php endforeach;
                                endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Modal: Duplicar -->
    <div class="modal fade" id="duplicarModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <form method="POST">
                    <?= csrfField() ?>
                    <input type="hidden" name="action" value="duplicar">
                    <input type="hidden" name="id" id="dup_id">
                    <div class="modal-header">
                        <h5 class="modal-title"><i class="fas fa-copy me-2 text-info"></i> Duplicar Emprendimiento</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <p class="text-muted">Se clonará toda la configuración excepto las imágenes.</p>
                        <div class="mb-3">
                            <label class="form-label fw-semibold">Nuevo Nombre *</label>
                            <input type="text" name="nuevo_nombre" id="dup_nombre" class="form-control" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label fw-semibold">Nuevo Slug *</label>
                            <div class="input-group">
                                <span class="input-group-text">/</span>
                                <input type="text" name="nuevo_slug" id="dup_slug" class="form-control" required>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                        <button type="submit" class="btn btn-info text-white"><i class="fas fa-copy me-1"></i> Duplicar</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Modal: Eliminar con backup -->
    <div class="modal fade" id="eliminarModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <form method="POST">
                    <?= csrfField() ?>
                    <input type="hidden" name="action" value="eliminar">
                    <input type="hidden" name="id" id="del_id">
                    <div class="modal-header">
                        <h5 class="modal-title text-danger"><i class="fas fa-exclamation-triangle me-2"></i> Eliminar Emprendimiento</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <p id="del_nombre_text" class="fw-bold"></p>
                        <div class="alert alert-warning">
                            <i class="fas fa-info-circle me-1"></i> Esta acción eliminará TODOS los datos (productos, ventas, tickets, etc.).
                        </div>
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" name="hacer_backup" id="hacerBackup" checked>
                            <label class="form-check-label" for="hacerBackup">
                                <strong>Guardar backup JSON</strong> antes de eliminar
                            </label>
                        </div>
                        <p class="text-muted small mt-2">El backup se guardará en la carpeta <code>htdocs/backups/</code></p>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                        <button type="submit" class="btn btn-danger"><i class="fas fa-trash me-1"></i> Eliminar</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Modal: Importar JSON -->
    <div class="modal fade" id="importModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <form method="POST" enctype="multipart/form-data">
                    <?= csrfField() ?>
                    <input type="hidden" name="action" value="importar">
                    <div class="modal-header">
                        <h5 class="modal-title"><i class="fas fa-file-import me-2 text-success"></i> Importar desde JSON</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <p class="text-muted">Subí un archivo JSON exportado previamente para crear un nuevo emprendimiento.</p>
                        <div class="mb-3">
                            <label class="form-label fw-semibold">Archivo JSON *</label>
                            <input type="file" name="json_file" class="form-control" accept=".json" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label fw-semibold">Nuevo Nombre (opcional)</label>
                            <input type="text" name="import_nombre" class="form-control" placeholder="Si se deja vacío, se usará el nombre original + '(Importado)'">
                        </div>
                        <div class="mb-3">
                            <label class="form-label fw-semibold">Nuevo Slug (opcional)</label>
                            <div class="input-group">
                                <span class="input-group-text">/</span>
                                <input type="text" name="import_slug" class="form-control" placeholder="Si se deja vacío, se usará el original + '-importado'">
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                        <button type="submit" class="btn btn-success"><i class="fas fa-upload me-1"></i> Importar</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
    // Filtro en vivo
    document.getElementById('searchInput').addEventListener('keyup', function(e) {
        if (e.key === 'Enter') filtrar();
    });
    document.getElementById('filterSelect').addEventListener('change', filtrar);
    
    function filtrar() {
        const search = document.getElementById('searchInput').value;
        const filter = document.getElementById('filterSelect').value;
        window.location.href = '?search=' + encodeURIComponent(search) + '&filter=' + filter;
    }

    // Modal Duplicar
    function abrirDuplicar(id, nombre, slug) {
        document.getElementById('dup_id').value = id;
        document.getElementById('dup_nombre').value = nombre + ' (Copia)';
        document.getElementById('dup_slug').value = slug + '-copia-' + Date.now();
        new bootstrap.Modal(document.getElementById('duplicarModal')).show();
    }

    // Auto-generar slug en modal duplicar
    document.getElementById('dup_nombre')?.addEventListener('input', function() {
        const slugInput = document.getElementById('dup_slug');
        if (!slugInput.dataset.manuallyEdited) {
            slugInput.value = this.value.toLowerCase()
                .replace(/[^a-z0-9áéíóúüñ\s-]/g, '')
                .replace(/[á]/g, 'a').replace(/[é]/g, 'e').replace(/[í]/g, 'i')
                .replace(/[ó]/g, 'o').replace(/[ú]/g, 'u').replace(/[ü]/g, 'u').replace(/[ñ]/g, 'n')
                .replace(/\s+/g, '-').replace(/-+/g, '-').replace(/^-|-$/g, '');
        }
    });
    document.getElementById('dup_slug')?.addEventListener('input', function() {
        this.dataset.manuallyEdited = 'true';
    });

    // Modal Eliminar
    function abrirEliminar(id, nombre) {
        document.getElementById('del_id').value = id;
        document.getElementById('del_nombre_text').textContent = '¿Eliminar "' + nombre + '"?';
        new bootstrap.Modal(document.getElementById('eliminarModal')).show();
    }
    </script>
</body>
</html>
