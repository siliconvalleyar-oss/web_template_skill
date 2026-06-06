<?php
/**
 * Panel de soporte - Gestión de tickets
 */
session_start();
require_once __DIR__ . '/../config.php';

if (!isset($_SESSION['admin_id'])) {
    redirect('index.php');
}

$db = getDB();
if (!$db) die('Error de conexión');

// Respuesta a ticket (con verificación CSRF)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['responder_ticket'])) {
    if (!verificarCSRF()) {
        die('Error de seguridad: token CSRF inválido.');
    }
    $ticketId = (int)$_POST['ticket_id'];
    $mensaje = trim($_POST['respuesta'] ?? '');
    if ($mensaje) {
        $db->prepare("INSERT INTO respuestas_tickets (ticket_id, autor_tipo, autor_nombre, mensaje) VALUES (?, 'agente', ?, ?)")
           ->execute([$ticketId, $_SESSION['admin_nombre'], $mensaje]);
        $db->prepare("UPDATE tickets_soporte SET estado = 'en_proceso' WHERE id = ? AND estado = 'abierto'")->execute([$ticketId]);
        redirect('soporte-panel.php?ticket=' . $ticketId . '&msg=ok');
    }
}

// Cambiar estado (con verificación CSRF)
if (isset($_GET['cambiar_estado']) && isset($_GET['estado'])) {
    $token = $_GET['_csrf'] ?? '';
    if (!verificarCSRF($token)) {
        die('Error de seguridad: token CSRF inválido.');
    }
    $db->prepare("UPDATE tickets_soporte SET estado = ? WHERE id = ?")->execute([$_GET['estado'], $_GET['cambiar_estado']]);
    redirect('soporte-panel.php');
}

$search = $_GET['search'] ?? '';
$estado = $_GET['estado'] ?? '';

$sql = "SELECT t.*, e.nombre as emp_nombre FROM tickets_soporte t JOIN emprendimientos e ON e.id = t.emprendimiento_id WHERE 1=1";
$params = [];
if ($search) { $sql .= " AND (t.asunto LIKE ? OR t.cliente_nombre LIKE ?)"; $params[] = "%$search%"; $params[] = "%$search%"; }
if ($estado) { $sql .= " AND t.estado = ?"; $params[] = $estado; }
$sql .= " ORDER BY CASE WHEN t.estado = 'abierto' THEN 0 ELSE 1 END, t.fecha DESC";

$tickets = $db->prepare($sql);
$tickets->execute($params);
$tickets = $tickets->fetchAll();

$ticketId = isset($_GET['ticket']) ? (int)$_GET['ticket'] : 0;
$ticketDetalle = null;
$respuestas = [];
if ($ticketId) {
    $stmt = $db->prepare("SELECT t.*, e.nombre as emp_nombre FROM tickets_soporte t JOIN emprendimientos e ON e.id = t.emprendimiento_id WHERE t.id = ?");
    $stmt->execute([$ticketId]);
    $ticketDetalle = $stmt->fetch();
    if ($ticketDetalle) {
        $stmt = $db->prepare("SELECT * FROM respuestas_tickets WHERE ticket_id = ? ORDER BY fecha ASC");
        $stmt->execute([$ticketId]);
        $respuestas = $stmt->fetchAll();
    }
}

$adminNombre = $_SESSION['admin_nombre'];
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Soporte - Panel Admin</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="assets/admin.css">
    <style>
        .chat-msg { max-width: 75%; margin-bottom: 12px; padding: 12px 16px; border-radius: 12px; }
        .chat-msg.cliente { background: #f3f4f6; }
        .chat-msg.agente { background: #2563eb; color: white; margin-left: auto; }
        .chat-msg .small { font-size: 11px; opacity: 0.7; }
    </style>
</head>
<body>
    <div class="d-flex">
        <div class="sidebar">
            <div class="sidebar-header"><i class="fas fa-store me-2"></i> <span>Multi-Admin</span></div>
            <nav class="sidebar-nav">
                <a href="dashboard.php"><i class="fas fa-chart-pie"></i> Dashboard</a>
                <a href="emprendimientos.php"><i class="fas fa-globe"></i> Emprendimientos</a>
                <a href="emprendimiento-editar.php"><i class="fas fa-plus-circle"></i> Nuevo Sitio</a>
                <a href="pedidos.php"><i class="fas fa-truck"></i> Pedidos</a>
                <a href="soporte-panel.php" class="active"><i class="fas fa-headset"></i> Tickets</a>
                <hr class="my-2 opacity-25">
                <a href="?logout" class="text-danger"><i class="fas fa-sign-out-alt"></i> Cerrar Sesión</a>
            </nav>
            <div class="sidebar-footer"><i class="fas fa-user me-1"></i> <?= h($adminNombre) ?></div>
        </div>

        <div class="main-content">
            <?php if (isset($_GET['msg'])): ?>
                <div class="alert alert-success">Respuesta enviada correctamente.</div>
            <?php endif; ?>

            <div class="row">
                <!-- Lista de tickets -->
                <div class="col-md-<?= $ticketDetalle ? 5 : 12 ?>">
                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <h4 class="fw-bold"><i class="fas fa-headset me-2 text-primary"></i> Tickets de Soporte</h4>
                    </div>

                    <div class="card border-0 shadow-sm mb-3">
                        <div class="card-body">
                            <form class="row g-2">
                                <div class="col">
                                    <input type="text" name="search" class="form-control" placeholder="Buscar tickets..." value="<?= h($search) ?>">
                                </div>
                                <div class="col-auto">
                                    <select name="estado" class="form-select" onchange="this.form.submit()">
                                        <option value="">Todos</option>
                                        <option value="abierto" <?= $estado === 'abierto' ? 'selected' : '' ?>>Abiertos</option>
                                        <option value="en_proceso" <?= $estado === 'en_proceso' ? 'selected' : '' ?>>En Proceso</option>
                                        <option value="cerrado" <?= $estado === 'cerrado' ? 'selected' : '' ?>>Cerrados</option>
                                    </select>
                                </div>
                            </form>
                        </div>
                    </div>

                    <?php if (count($tickets) === 0): ?>
                        <div class="text-center py-5 text-muted"><i class="fas fa-inbox fa-3x mb-3 d-block"></i>No hay tickets.</div>
                    <?php else: ?>
                        <div class="list-group">
                            <?php foreach ($tickets as $t): 
                                $active = $ticketId === (int)$t['id']; ?>
                                <a href="?ticket=<?= $t['id'] ?>" class="list-group-item list-group-item-action <?= $active ? 'active' : '' ?>">
                                    <div class="d-flex justify-content-between">
                                        <small class="text-<?= $active ? 'white' : 'muted' ?>"><?= h($t['emp_nombre']) ?></small>
                                        <small class="text-<?= $active ? 'white-50' : 'muted' ?>"><?= date('d/m H:i', strtotime($t['fecha'])) ?></small>
                                    </div>
                                    <strong class="d-block text-truncate"><?= h($t['asunto']) ?></strong>
                                    <div class="d-flex justify-content-between align-items-center mt-1">
                                        <small class="text-<?= $active ? 'white-50' : 'muted' ?>"><?= h($t['cliente_nombre'] ?? 'Anónimo') ?></small>
                                        <?php
                                        $badge = match($t['estado']) {
                                            'abierto' => 'bg-warning text-dark',
                                            'en_proceso' => 'bg-info',
                                            'cerrado' => 'bg-secondary',
                                            default => 'bg-secondary'
                                        }; ?>
                                        <span class="badge <?= $badge ?>"><?= $t['estado'] ?></span>
                                    </div>
                                </a>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>

                <!-- Detalle del ticket -->
                <?php if ($ticketDetalle): ?>
                    <div class="col-md-7">
                        <div class="card border-0 shadow-sm">
                            <div class="card-header bg-white d-flex justify-content-between align-items-center py-3">
                                <div>
                                    <h5 class="mb-0 fw-bold"><?= h($ticketDetalle['asunto']) ?></h5>
                                    <small class="text-muted">
                                        <?= h($ticketDetalle['emp_nombre']) ?> · 
                                        <?= h($ticketDetalle['cliente_nombre'] ?? 'Anónimo') ?> 
                                        <?= $ticketDetalle['cliente_email'] ? '· ' . h($ticketDetalle['cliente_email']) : '' ?>
                                    </small>
                                </div>
                                <div class="btn-group btn-group-sm">
                                    <?php if ($ticketDetalle['estado'] !== 'cerrado'): 
                                        $csrf = generarCSRF(); ?>
                                        <a href="?cambiar_estado=<?= $ticketDetalle['id'] ?>&estado=cerrado&_csrf=<?= $csrf ?>" class="btn btn-outline-success">
                                            <i class="fas fa-check me-1"></i> Cerrar
                                        </a>
                                    <?php endif; ?>
                                    <a href="?ticket=<?= $ticketDetalle['id'] ?>" class="btn btn-outline-secondary"><i class="fas fa-sync"></i></a>
                                </div>
                            </div>
                            <div class="card-body" style="max-height: 500px; overflow-y: auto;" id="chatContainer">
                                <!-- Mensaje inicial del ticket -->
                                <div class="chat-msg cliente">
                                    <div><?= nl2br(h($ticketDetalle['mensaje'])) ?></div>
                                    <div class="small mt-1"><?= date('d/m/Y H:i', strtotime($ticketDetalle['fecha'])) ?></div>
                                </div>
                                
                                <?php foreach ($respuestas as $r): ?>
                                    <div class="chat-msg <?= $r['autor_tipo'] === 'agente' ? 'agente' : 'cliente' ?>">
                                        <div><?= nl2br(h($r['mensaje'])) ?></div>
                                        <div class="small mt-1"><?= h($r['autor_nombre']) ?> · <?= date('d/m H:i', strtotime($r['fecha'])) ?></div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                            
                            <?php if ($ticketDetalle['estado'] !== 'cerrado'): ?>
                                <div class="card-footer bg-white">
                                    <form method="POST">
                                        <?= csrfField() ?>
                                        <input type="hidden" name="ticket_id" value="<?= $ticketDetalle['id'] ?>">
                                        <div class="input-group">
                                            <textarea name="respuesta" class="form-control" rows="2" placeholder="Escribe tu respuesta..." required></textarea>
                                            <button type="submit" name="responder_ticket" class="btn btn-primary">
                                                <i class="fas fa-paper-plane"></i>
                                            </button>
                                        </div>
                                    </form>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
    // Auto-scroll al último mensaje
    const chat = document.getElementById('chatContainer');
    if (chat) chat.scrollTop = chat.scrollHeight;
    </script>
</body>
</html>
