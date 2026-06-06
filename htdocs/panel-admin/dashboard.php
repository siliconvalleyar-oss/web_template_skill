<?php
/**
 * Dashboard del panel de administración
 */
session_start();
require_once __DIR__ . '/../config.php';

// Verificar autenticación
if (!isset($_SESSION['admin_id'])) {
    redirect('index.php');
}

$db = getDB();
if (!$db) {
    die('Error de conexión a la base de datos.');
}

// Estadísticas
$totalEmprendimientos = $db->query("SELECT COUNT(*) FROM emprendimientos")->fetchColumn();
$activos = $db->query("SELECT COUNT(*) FROM emprendimientos WHERE activo = TRUE")->fetchColumn();
$totalProductos = $db->query("SELECT COUNT(*) FROM productos")->fetchColumn();
$totalVentas = $db->query("SELECT COUNT(*) FROM ventas")->fetchColumn();
$ventasPendientes = $db->query("SELECT COUNT(*) FROM ventas WHERE estado = 'pendiente'")->fetchColumn();
$ticketsAbiertos = $db->query("SELECT COUNT(*) FROM tickets_soporte WHERE estado = 'abierto'")->fetchColumn();

$adminNombre = $_SESSION['admin_nombre'];
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard - Panel Admin</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="assets/admin.css">
</head>
<body>
    <div class="d-flex">
        <!-- Sidebar -->
        <div class="sidebar">
            <div class="sidebar-header">
                <i class="fas fa-store me-2"></i>
                <span>Multi-Admin</span>
            </div>
            <nav class="sidebar-nav">
                <a href="dashboard.php" class="active">
                    <i class="fas fa-chart-pie"></i> Dashboard
                </a>
                <a href="emprendimientos.php">
                    <i class="fas fa-globe"></i> Emprendimientos
                </a>
                <a href="emprendimiento-editar.php">
                    <i class="fas fa-plus-circle"></i> Nuevo Sitio
                </a>
                <a href="pedidos.php">
                    <i class="fas fa-truck"></i> Pedidos
                </a>
                <a href="soporte-panel.php">
                    <i class="fas fa-headset"></i> Tickets
                </a>
                <hr class="my-2 opacity-25">
                <a href="?logout" class="text-danger">
                    <i class="fas fa-sign-out-alt"></i> Cerrar Sesión
                </a>
            </nav>
            <div class="sidebar-footer">
                <i class="fas fa-user me-1"></i> <?= h($adminNombre) ?>
            </div>
        </div>

        <!-- Main Content -->
        <div class="main-content">
            <div class="d-flex justify-content-between align-items-center mb-4">
                <div>
                    <h2 class="fw-bold mb-1">Dashboard</h2>
                    <p class="text-muted mb-0">Resumen general del sistema multi-emprendimiento</p>
                </div>
                <div class="text-end">
                    <span class="badge bg-success"><i class="fas fa-circle me-1"></i> Sistema Activo</span>
                    <span class="badge bg-info"><i class="fas fa-robot me-1"></i> DeepSeek 7B</span>
                </div>
            </div>

            <!-- Stats Cards -->
            <div class="row g-3 mb-4">
                <div class="col-md-3">
                    <div class="stat-card">
                        <div class="stat-icon bg-primary-subtle text-primary">
                            <i class="fas fa-globe"></i>
                        </div>
                        <div class="stat-info">
                            <h3><?= $totalEmprendimientos ?></h3>
                            <span>Emprendimientos</span>
                            <small class="text-success"><?= $activos ?> activos</small>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="stat-card">
                        <div class="stat-icon bg-success-subtle text-success">
                            <i class="fas fa-box"></i>
                        </div>
                        <div class="stat-info">
                            <h3><?= $totalProductos ?></h3>
                            <span>Productos</span>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="stat-card">
                        <div class="stat-icon bg-warning-subtle text-warning">
                            <i class="fas fa-shopping-cart"></i>
                        </div>
                        <div class="stat-info">
                            <h3><?= $totalVentas ?></h3>
                            <span>Ventas</span>
                            <small class="text-warning"><?= $ventasPendientes ?> pendientes</small>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="stat-card">
                        <div class="stat-icon bg-danger-subtle text-danger">
                            <i class="fas fa-headset"></i>
                        </div>
                        <div class="stat-info">
                            <h3><?= $ticketsAbiertos ?></h3>
                            <span>Tickets Abiertos</span>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Últimos emprendimientos -->
            <div class="card border-0 shadow-sm mb-4">
                <div class="card-header bg-white d-flex justify-content-between align-items-center py-3">
                    <h5 class="mb-0 fw-bold"><i class="fas fa-list me-2 text-primary"></i> Últimos Emprendimientos</h5>
                    <a href="emprendimientos.php" class="btn btn-sm btn-outline-primary">Ver todos</a>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th>Nombre</th>
                                    <th>Slug</th>
                                    <th>Productos</th>
                                    <th>Ventas</th>
                                    <th>Estado</th>
                                    <th>Acciones</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php
                                $emprendimientos = $db->query("
                                    SELECT e.*, 
                                           (SELECT COUNT(*) FROM productos p WHERE p.emprendimiento_id = e.id) as total_productos,
                                           (SELECT COUNT(*) FROM ventas v WHERE v.emprendimiento_id = e.id) as total_ventas
                                    FROM emprendimientos e
                                    ORDER BY e.ultima_modificacion DESC
                                    LIMIT 10
                                ")->fetchAll();

                                if (count($emprendimientos) === 0): ?>
                                    <tr>
                                        <td colspan="6" class="text-center py-4 text-muted">
                                            <i class="fas fa-inbox fa-2x mb-2 d-block"></i>
                                            No hay emprendimientos aún. 
                                            <a href="emprendimiento-editar.php" class="text-primary">Crear el primero</a>
                                        </td>
                                    </tr>
                                <?php else:
                                    foreach ($emprendimientos as $emp): ?>
                                        <tr>
                                            <td class="fw-semibold"><?= h($emp['nombre']) ?></td>
                                            <td><code><?= h($emp['slug']) ?></code></td>
                                            <td><?= $emp['total_productos'] ?></td>
                                            <td><?= $emp['total_ventas'] ?></td>
                                            <td>
                                                <?php if ($emp['activo']): ?>
                                                    <span class="badge bg-success">Activo</span>
                                                <?php else: ?>
                                                    <span class="badge bg-secondary">Inactivo</span>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <a href="emprendimiento-editar.php?id=<?= $emp['id'] ?>" class="btn btn-sm btn-outline-primary" title="Editar">
                                                    <i class="fas fa-edit"></i>
                                                </a>
                                                <a href="/<?= h($emp['slug']) ?>" target="_blank" class="btn btn-sm btn-outline-success" title="Ver sitio">
                                                    <i class="fas fa-external-link-alt"></i>
                                                </a>
                                            </td>
                                        </tr>
                                    <?php endforeach;
                                endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <!-- Acciones rápidas -->
            <div class="row g-3">
                <div class="col-md-6">
                    <div class="card border-0 shadow-sm h-100">
                        <div class="card-body text-center py-5">
                            <i class="fas fa-plus-circle fa-3x text-primary mb-3"></i>
                            <h5>Crear Nuevo Emprendimiento</h5>
                            <p class="text-muted small">Agrega un nuevo sitio web para un emprendimiento</p>
                            <a href="emprendimiento-editar.php" class="btn btn-primary">
                                <i class="fas fa-plus me-1"></i> Nuevo
                            </a>
                        </div>
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="card border-0 shadow-sm h-100">
                        <div class="card-body text-center py-5">
                            <i class="fas fa-headset fa-3x text-danger mb-3"></i>
                            <h5>Soporte y Tickets</h5>
                            <p class="text-muted small">Gestiona los tickets de soporte de los clientes</p>
                            <a href="soporte-panel.php" class="btn btn-danger">
                                <i class="fas fa-ticket-alt me-1"></i> Ir a Tickets
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
