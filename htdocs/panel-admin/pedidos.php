<?php
/**
 * Gestión de pedidos/ventas
 */
session_start();
require_once __DIR__ . '/../config.php';

if (!isset($_SESSION['admin_id'])) {
    redirect('index.php');
}

$db = getDB();
if (!$db) die('Error de conexión');

// Cambiar estado de pedido (con verificación CSRF)
if (isset($_GET['cambiar_estado']) && isset($_GET['estado'])) {
    $token = $_GET['_csrf'] ?? '';
    if (!verificarCSRF($token)) {
        die('Error de seguridad: token CSRF inválido.');
    }
    $stmt = $db->prepare("UPDATE ventas SET estado = ? WHERE id = ?");
    $stmt->execute([$_GET['estado'], $_GET['cambiar_estado']]);
    redirect('pedidos.php?msg=ok');
}

$search = $_GET['search'] ?? '';
$estado = $_GET['estado'] ?? '';
$emprendimiento = $_GET['emprendimiento'] ?? '';

$sql = "SELECT v.*, e.nombre as emp_nombre, e.slug as emp_slug 
        FROM ventas v 
        JOIN emprendimientos e ON e.id = v.emprendimiento_id 
        WHERE 1=1";
$params = [];

if ($search) { $sql .= " AND (v.id LIKE ? OR e.nombre LIKE ?)"; $params[] = "%$search%"; $params[] = "%$search%"; }
if ($estado) { $sql .= " AND v.estado = ?"; $params[] = $estado; }
if ($emprendimiento) { $sql .= " AND v.emprendimiento_id = ?"; $params[] = $emprendimiento; }

$sql .= " ORDER BY v.fecha DESC LIMIT 100";
$ventas = $db->prepare($sql);
$ventas->execute($params);
$ventas = $ventas->fetchAll();

$emprendimientos = $db->query("SELECT id, nombre FROM emprendimientos ORDER BY nombre")->fetchAll();
$adminNombre = $_SESSION['admin_nombre'];
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Pedidos - Panel Admin</title>
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
                <a href="emprendimiento-editar.php"><i class="fas fa-plus-circle"></i> Nuevo Sitio</a>
                <a href="pedidos.php" class="active"><i class="fas fa-truck"></i> Pedidos</a>
                <a href="soporte-panel.php"><i class="fas fa-headset"></i> Tickets</a>
                <hr class="my-2 opacity-25">
                <a href="?logout" class="text-danger"><i class="fas fa-sign-out-alt"></i> Cerrar Sesión</a>
            </nav>
            <div class="sidebar-footer"><i class="fas fa-user me-1"></i> <?= h($adminNombre) ?></div>
        </div>

        <div class="main-content">
            <h2 class="fw-bold mb-4"><i class="fas fa-truck me-2 text-primary"></i> Pedidos</h2>
            
            <?php if (isset($_GET['msg'])): ?>
                <div class="alert alert-success">Estado actualizado correctamente.</div>
            <?php endif; ?>

            <div class="card border-0 shadow-sm mb-4">
                <div class="card-body">
                    <form class="row g-2">
                        <div class="col-md-4">
                            <input type="text" name="search" class="form-control" placeholder="Buscar pedido o emprendimiento..." value="<?= h($search) ?>">
                        </div>
                        <div class="col-md-3">
                            <select name="estado" class="form-select">
                                <option value="">Todos los estados</option>
                                <option value="pendiente" <?= $estado === 'pendiente' ? 'selected' : '' ?>>Pendiente</option>
                                <option value="pagado" <?= $estado === 'pagado' ? 'selected' : '' ?>>Pagado</option>
                                <option value="enviado" <?= $estado === 'enviado' ? 'selected' : '' ?>>Enviado</option>
                                <option value="completado" <?= $estado === 'completado' ? 'selected' : '' ?>>Completado</option>
                                <option value="cancelado" <?= $estado === 'cancelado' ? 'selected' : '' ?>>Cancelado</option>
                            </select>
                        </div>
                        <div class="col-md-3">
                            <select name="emprendimiento" class="form-select">
                                <option value="">Todos los emprendimientos</option>
                                <?php foreach ($emprendimientos as $emp): ?>
                                    <option value="<?= $emp['id'] ?>" <?= $emprendimiento == $emp['id'] ? 'selected' : '' ?>><?= h($emp['nombre']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-auto">
                            <button class="btn btn-primary"><i class="fas fa-search me-1"></i> Filtrar</button>
                        </div>
                    </form>
                </div>
            </div>

            <div class="card border-0 shadow-sm">
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th>#</th>
                                    <th>Emprendimiento</th>
                                    <th>Total</th>
                                    <th>Forma de Pago</th>
                                    <th>Fecha</th>
                                    <th>Estado</th>
                                    <th>Acciones</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (count($ventas) === 0): ?>
                                    <tr><td colspan="7" class="text-center py-4 text-muted">No hay pedidos registrados.</td></tr>
                                <?php else: foreach ($ventas as $v): ?>
                                    <tr>
                                        <td>#<?= $v['id'] ?></td>
                                        <td><?= h($v['emp_nombre']) ?></td>
                                        <td class="fw-bold">$<?= number_format($v['total'], 2) ?></td>
                                        <td><?= h($v['forma_pago'] ?? '—') ?></td>
                                        <td class="small"><?= date('d/m/Y H:i', strtotime($v['fecha'])) ?></td>
                                        <td>
                                            <?php
                                            $badgeClass = match($v['estado']) {
                                                'pendiente' => 'bg-warning text-dark',
                                                'pagado' => 'bg-info',
                                                'enviado' => 'bg-primary',
                                                'completado' => 'bg-success',
                                                'cancelado' => 'bg-danger',
                                                default => 'bg-secondary'
                                            }; ?>
                                            <span class="badge <?= $badgeClass ?>"><?= $v['estado'] ?></span>
                                        </td>
                                        <td>
                                            <div class="dropdown">
                                                <button class="btn btn-sm btn-outline-secondary dropdown-toggle" data-bs-toggle="dropdown">
                                                    Cambiar estado
                                                </button>
                                                <ul class="dropdown-menu">
                                                    <?php $csrf = generarCSRF(); ?>
                                                    <li><a class="dropdown-item" href="?cambiar_estado=<?= $v['id'] ?>&estado=pendiente&_csrf=<?= $csrf ?>">Pendiente</a></li>
                                                    <li><a class="dropdown-item" href="?cambiar_estado=<?= $v['id'] ?>&estado=pagado&_csrf=<?= $csrf ?>">Pagado</a></li>
                                                    <li><a class="dropdown-item" href="?cambiar_estado=<?= $v['id'] ?>&estado=enviado&_csrf=<?= $csrf ?>">Enviado</a></li>
                                                    <li><a class="dropdown-item" href="?cambiar_estado=<?= $v['id'] ?>&estado=completado&_csrf=<?= $csrf ?>">Completado</a></li>
                                                    <li><a class="dropdown-item text-danger" href="?cambiar_estado=<?= $v['id'] ?>&estado=cancelado&_csrf=<?= $csrf ?>">Cancelar</a></li>
                                                </ul>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
