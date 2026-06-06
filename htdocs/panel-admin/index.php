<?php
/**
 * Panel de Administración - Login y Dashboard
 * Sistema Multi-Emprendimiento con DeepSeek 7B
 */
session_start();
require_once __DIR__ . '/../config.php';

// Logout
if (isset($_GET['logout'])) {
    session_destroy();
    redirect('index.php');
}

// Procesar login
$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['login'])) {
    // Verificar token CSRF
    if (!verificarCSRF()) {
        $error = 'Error de seguridad: token inválido. Recargá la página.';
    } else {
        $email = trim($_POST['email'] ?? '');
        $password = trim($_POST['password'] ?? '');

        if ($email && $password) {
            $db = getDB();
            if ($db) {
                $stmt = $db->prepare("SELECT * FROM usuarios_admin WHERE email = ?");
                $stmt->execute([$email]);
                $user = $stmt->fetch();

                if ($user && password_verify($password, $user['password_hash'])) {
                    $_SESSION['admin_id'] = $user['id'];
                    $_SESSION['admin_nombre'] = $user['nombre'];
                    $_SESSION['admin_email'] = $user['email'];
                    $_SESSION['admin_rol'] = $user['rol'];

                    // Actualizar último acceso
                    $stmt = $db->prepare("UPDATE usuarios_admin SET ultimo_acceso = NOW() WHERE id = ?");
                    $stmt->execute([$user['id']]);

                    // Redirigir al dashboard
                    redirect('dashboard.php');
                } else {
                    $error = 'Credenciales inválidas.';
                }
            } else {
                $error = 'Error de conexión a la base de datos.';
            }
        } else {
            $error = 'Por favor ingrese email y contraseña.';
        }
    }
}

// Si ya está logueado, ir al dashboard
if (isset($_SESSION['admin_id'])) {
    redirect('dashboard.php');
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Panel Admin - Multi-Emprendimiento</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        body { background: linear-gradient(135deg, #1e3a5f 0%, #2563eb 100%); min-height: 100vh; font-family: 'Segoe UI', system-ui, sans-serif; }
        .login-card { border: none; border-radius: 20px; box-shadow: 0 20px 60px rgba(0,0,0,0.3); overflow: hidden; }
        .login-header { background: linear-gradient(135deg, #2563eb, #7c3aed); padding: 40px; text-align: center; }
        .login-header h2 { color: white; font-weight: 700; margin: 0; }
        .login-header p { color: rgba(255,255,255,0.8); margin: 8px 0 0; }
        .login-body { padding: 40px; background: white; }
        .login-icon { width: 64px; height: 64px; background: rgba(255,255,255,0.2); border-radius: 50%; display: flex; align-items: center; justify-content: center; margin: 0 auto 16px; }
        .login-icon i { font-size: 28px; color: white; }
        .form-control { border-radius: 12px; padding: 12px 16px; border: 2px solid #e5e7eb; font-size: 15px; }
        .form-control:focus { border-color: #2563eb; box-shadow: 0 0 0 3px rgba(37,99,235,0.1); }
        .btn-login { border-radius: 12px; padding: 12px; font-weight: 600; background: linear-gradient(135deg, #2563eb, #7c3aed); border: none; transition: transform 0.2s; }
        .btn-login:hover { transform: translateY(-1px); box-shadow: 0 8px 25px rgba(37,99,235,0.3); }
        .footer-text { color: rgba(255,255,255,0.6); text-align: center; margin-top: 24px; font-size: 14px; }
    </style>
</head>
<body>
    <div class="container">
        <div class="row justify-content-center align-items-center min-vh-100">
            <div class="col-md-5">
                <div class="card login-card">
                    <div class="login-header">
                        <div class="login-icon">
                            <i class="fas fa-store"></i>
                        </div>
                        <h2>Panel de Administración</h2>
                        <p>Sistema Multi-Emprendimiento</p>
                    </div>
                    <div class="login-body">
                        <?php if ($error): ?>
                            <div class="alert alert-danger"><?= h($error) ?></div>
                        <?php endif; ?>
                        <form method="POST" autocomplete="off">
                            <?= csrfField() ?>
                            <div class="mb-3">
                                <label class="form-label fw-semibold">Email</label>
                                <div class="input-group">
                                    <span class="input-group-text bg-light border-0"><i class="fas fa-envelope text-muted"></i></span>
                                    <input type="email" name="email" class="form-control" placeholder="admin@admin.com" required>
                                </div>
                            </div>
                            <div class="mb-4">
                                <label class="form-label fw-semibold">Contraseña</label>
                                <div class="input-group">
                                    <span class="input-group-text bg-light border-0"><i class="fas fa-lock text-muted"></i></span>
                                    <input type="password" name="password" class="form-control" placeholder="••••••••" required>
                                </div>
                            </div>
                            <button type="submit" name="login" class="btn btn-primary btn-login w-100">
                                <i class="fas fa-arrow-right me-2"></i> Ingresar
                            </button>
                        </form>
                    </div>
                </div>
                <p class="footer-text">
                    <i class="fas fa-robot me-1"></i> Powered by DeepSeek 7B · Ollama
                </p>
            </div>
        </div>
    </div>
</body>
</html>
