<?php
/**
 * Finalizar compra - Checkout
 */
session_start();
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

$formasPago = $db->prepare("SELECT * FROM formas_pago WHERE emprendimiento_id = ? AND activo = TRUE");
$formasPago->execute([$empId]);
$formasPago = $formasPago->fetchAll();

// Procesar compra
$mensaje = '';
$compraExitosa = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['finalizar'])) {
    $nombre = trim($_POST['nombre'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $telefono = trim($_POST['telefono'] ?? '');
    $formaPago = $_POST['forma_pago'] ?? '';
    $carritoJson = $_POST['carrito_data'] ?? '[]';
    $carrito = json_decode($carritoJson, true) ?: [];

    if (!$nombre || !$formaPago || count($carrito) === 0) {
        $mensaje = 'Completa todos los campos requeridos.';
    } else {
        try {
            $db->beginTransaction();

            // Buscar o crear cliente
            $stmt = $db->prepare("SELECT id FROM clientes WHERE emprendimiento_id = ? AND email = ?");
            $stmt->execute([$empId, $email]);
            $cliente = $stmt->fetch();

            if (!$cliente) {
                $stmt = $db->prepare("INSERT INTO clientes (emprendimiento_id, nombre, email, telefono) VALUES (?,?,?,?)");
                $stmt->execute([$empId, $nombre, $email, $telefono]);
                $clienteId = $db->lastInsertId();
            } else {
                $clienteId = $cliente['id'];
            }

            // Verificar stock y crear ventas individuales
            foreach ($carrito as $item) {
                $prodStmt = $db->prepare("SELECT stock, precio FROM productos WHERE id = ? AND emprendimiento_id = ?");
                $prodStmt->execute([$item['id'], $empId]);
                $producto = $prodStmt->fetch();

                if (!$producto || $producto['stock'] < $item['cantidad']) {
                    throw new Exception("Stock insuficiente para: {$item['nombre']}");
                }

                $total = $producto['precio'] * $item['cantidad'];

                // Crear venta
                $stmt = $db->prepare("INSERT INTO ventas (emprendimiento_id, cliente_id, producto_id, cantidad, total, forma_pago, datos_cliente_json, estado) VALUES (?,?,?,?,?,?,?,'pendiente')");
                $stmt->execute([$empId, $clienteId, $item['id'], $item['cantidad'], $total, $formaPago, json_encode(['nombre' => $nombre, 'email' => $email, 'telefono' => $telefono])]);
                $ventaId = $db->lastInsertId();

                // Actualizar stock
                $stmt = $db->prepare("UPDATE productos SET stock = stock - ? WHERE id = ?");
                $stmt->execute([$item['cantidad'], $item['id']]);
            }

            $db->commit();
            $compraExitosa = true;

            // Enviar notificación WhatsApp al dueño (si tiene WhatsApp configurado)
            if ($contacto['whatsapp_numero']) {
                $mensajeWA = "🛒 *Nuevo Pedido!*\n\nCliente: {$nombre}\nTotal: $" . array_sum(array_map(function($i) { return $i['precio'] * $i['cantidad']; }, $carrito)) . "\nPago: {$formaPago}\n\nRevisa el panel de administración.";
                // Opcional: integrar con API de WhatsApp
            }

        } catch (Exception $e) {
            $db->rollBack();
            $mensaje = 'Error: ' . $e->getMessage();
        }
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Finalizar Compra - <?= h($emp['nombre']) ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="/plantilla-base/assets/css/plantilla.css">
    <style>:root { --color-principal: <?= $config['color_principal'] ?? '#2563eb' ?>; }</style>
</head>
<body>
    <header class="header">
        <nav class="navbar navbar-expand-lg">
            <div class="container">
                <a class="navbar-brand" href="/<?= h($slug) ?>"><?= h($emp['nombre']) ?></a>
                <ul class="navbar-nav ms-auto">
                    <li class="nav-item"><a class="nav-link" href="/<?= h($slug) ?>">Inicio</a></li>
                    <li class="nav-item"><a class="nav-link" href="/<?= h($slug) ?>/productos.php">Productos</a></li>
                </ul>
            </div>
        </nav>
    </header>

    <div class="container py-4">
        <?php if ($compraExitosa): ?>
            <div class="text-center py-5">
                <i class="fas fa-check-circle fa-4x text-success mb-3"></i>
                <h2 class="fw-bold">¡Compra Exitosa!</h2>
                <p class="text-muted">Tu pedido ha sido registrado. Te contactaremos pronto.</p>
                <p class="small text-muted">ID de transacción: #<?= $ventaId ?> - Fecha: <?= date('d/m/Y H:i') ?></p>
                <a href="/<?= h($slug) ?>" class="btn btn-primary mt-3"><i class="fas fa-home me-2"></i> Volver al Inicio</a>
                <button class="btn btn-outline-primary mt-3 ms-2" onclick="localStorage.removeItem('carrito_<?= $slug ?>')">
                    <i class="fas fa-shopping me-2"></i> Seguir Comprando
                </button>
            </div>
        <?php else: ?>
            <h2 class="fw-bold mb-4"><i class="fas fa-credit-card me-2 text-primary"></i> Finalizar Compra</h2>
            
            <?php if ($mensaje): ?>
                <div class="alert alert-danger"><?= h($mensaje) ?></div>
            <?php endif; ?>

            <div class="row g-4">
                <div class="col-lg-7">
                    <div class="card border-0 shadow-sm">
                        <div class="card-body">
                            <h5 class="fw-bold mb-3">Datos del Cliente</h5>
                            <form method="POST" id="checkoutForm">
                                <div class="row g-3">
                                    <div class="col-md-6">
                                        <label class="form-label">Nombre Completo *</label>
                                        <input type="text" name="nombre" class="form-control" required>
                                    </div>
                                    <div class="col-md-6">
                                        <label class="form-label">Email</label>
                                        <input type="email" name="email" class="form-control">
                                    </div>
                                    <div class="col-md-6">
                                        <label class="form-label">Teléfono *</label>
                                        <input type="text" name="telefono" class="form-control" required>
                                    </div>
                                </div>

                                <hr class="my-4">
                                <h5 class="fw-bold mb-3">Forma de Pago *</h5>
                                
                                <?php if (count($formasPago) === 0): ?>
                                    <p class="text-muted">No hay formas de pago configuradas aún.</p>
                                <?php else: ?>
                                    <div class="row g-2">
                                        <?php foreach ($formasPago as $fp): ?>
                                            <div class="col-md-6">
                                                <div class="card payment-option <?= $fp['tipo'] === 'transferencia' ? 'border-primary' : '' ?>">
                                                    <div class="card-body">
                                                        <div class="form-check">
                                                            <input class="form-check-input" type="radio" name="forma_pago" value="<?= $fp['tipo'] ?>" id="pago_<?= $fp['tipo'] ?>" <?= $fp['tipo'] === 'transferencia' ? 'checked' : '' ?> required>
                                                            <label class="form-check-label fw-semibold" for="pago_<?= $fp['tipo'] ?>">
                                                                <?php
                                                                $iconos = ['transferencia' => 'university', 'mercadopago' => 'credit-card', 'efectivo' => 'money-bill-wave', 'tarjeta' => 'id-card'];
                                                                $icono = $iconos[$fp['tipo']] ?? 'money';
                                                                ?>
                                                                <i class="fas fa-<?= $icono ?> me-1"></i>
                                                                <?= ucfirst($fp['tipo']) ?>
                                                            </label>
                                                        </div>
                                                        <?php if ($fp['descripcion']): ?>
                                                            <small class="text-muted d-block mt-1"><?= h($fp['descripcion']) ?></small>
                                                        <?php endif; ?>
                                                        <?php if ($fp['datos_extra']): ?>
                                                            <small class="d-block mt-1 text-primary"><strong><?= h($fp['datos_extra']) ?></strong></small>
                                                        <?php endif; ?>
                                                    </div>
                                                </div>
                                            </div>
                                        <?php endforeach; ?>
                                    </div>
                                <?php endif; ?>

                                <input type="hidden" name="carrito_data" id="carritoData">
                                
                                <div class="mt-4">
                                    <button type="submit" name="finalizar" class="btn btn-primary btn-lg w-100" <?= count($formasPago) === 0 ? 'disabled' : '' ?>>
                                        <i class="fas fa-check me-2"></i> Confirmar Pedido
                                    </button>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
                <div class="col-lg-5">
                    <div class="card border-0 shadow-sm">
                        <div class="card-body">
                            <h5 class="fw-bold mb-3">Resumen del Pedido</h5>
                            <div id="resumenCheckout">
                                <p class="text-muted text-center py-3">Cargando carrito...</p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        <?php endif; ?>
    </div>

    <?php include 'chatbot.php'; ?>
    <?php if ($contacto['whatsapp_numero']): ?>
        <a href="https://wa.me/<?= preg_replace('/[^0-9]/', '', $contacto['whatsapp_numero']) ?>" target="_blank" class="whatsapp-float"><i class="fab fa-whatsapp"></i></a>
    <?php endif; ?>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="/plantilla-base/assets/js/carrito.js"></script>
    <script src="/plantilla-base/assets/js/chatbot.js"></script>
    <script>
    document.addEventListener('DOMContentLoaded', function() {
        const carrito = getCarrito();
        const resumen = document.getElementById('resumenCheckout');
        const dataInput = document.getElementById('carritoData');

        if (carrito.length === 0) {
            resumen.innerHTML = '<p class="text-muted text-center py-3">Tu carrito está vacío. <a href="productos.php">Ver productos</a></p>';
            document.querySelector('button[name="finalizar"]')?.setAttribute('disabled', 'true');
            return;
        }

        dataInput.value = JSON.stringify(carrito);

        let html = '';
        let total = 0;
        carrito.forEach(item => {
            const subtotal = item.precio * item.cantidad;
            total += subtotal;
            html += `
                <div class="d-flex justify-content-between align-items-center mb-2">
                    <div>
                        <strong class="small">${item.nombre}</strong>
                        <br><small class="text-muted">x${item.cantidad}</small>
                    </div>
                    <span>$${subtotal.toFixed(2)}</span>
                </div>`;
        });

        html += `<hr><div class="d-flex justify-content-between fw-bold fs-5">
            <span>Total</span>
            <span>$${total.toFixed(2)}</span>
        </div>`;

        // Información de contacto WhatsApp
        const whatsapp = '<?= h($contacto['whatsapp_numero'] ?? '') ?>';
        if (whatsapp) {
            html += `<hr><small class="text-muted d-block">
                <i class="fab fa-whatsapp text-success me-1"></i> 
                Pagos también por WhatsApp: ${whatsapp}
            </small>`;
        }

        resumen.innerHTML = html;
    });
    </script>

    <style>
    .payment-option { cursor: pointer; transition: all 0.2s; border: 2px solid #e5e7eb; }
    .payment-option:hover { border-color: var(--color-principal); }
    .payment-option.has-success { border-color: var(--color-principal); }
    input[type="radio"]:checked + label { color: var(--color-principal) !important; }
    input[type="radio"]:checked ~ .card { border-color: var(--color-principal); }
    </style>
</body>
</html>
