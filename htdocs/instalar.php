<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Instalador - Sistema Multi-Emprendimiento</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        body { background: #f3f4f6; min-height: 100vh; display: flex; align-items: center; }
        .installer-card { max-width: 700px; margin: 0 auto; border: none; border-radius: 16px; box-shadow: 0 10px 40px rgba(0,0,0,0.1); }
        .step-indicator { display: flex; gap: 8px; margin-bottom: 24px; }
        .step-dot { width: 32px; height: 32px; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-size: 14px; font-weight: 600; }
        .step-dot.active { background: #2563eb; color: white; }
        .step-dot.pending { background: #e5e7eb; color: #6b7280; }
        .step-dot.success { background: #10b981; color: white; }
        .step-dot.error { background: #ef4444; color: white; }
        .check-result { padding: 12px 16px; border-radius: 8px; margin-bottom: 8px; }
        .check-result.pass { background: #ecfdf5; border-left: 4px solid #10b981; }
        .check-result.fail { background: #fef2f2; border-left: 4px solid #ef4444; }
        .check-result.warning { background: #fffbeb; border-left: 4px solid #f59e0b; }
        .logo-icon { font-size: 48px; color: #2563eb; }
    </style>
</head>
<body>
    <div class="container py-5">
        <div class="card installer-card p-5">
            <?php
            // Función helper para escapar HTML (definida localmente porque este archivo no incluye config.php)
            function h($text) { return htmlspecialchars($text, ENT_QUOTES, 'UTF-8'); }
            
            $step = isset($_GET['step']) ? (int)$_GET['step'] : 0;

            if ($step === 0) {
                // Paso 0: Pantalla de bienvenida
                ?>
                <div class="text-center mb-4">
                    <i class="fas fa-store logo-icon mb-3"></i>
                    <h2 class="fw-bold">Sistema Multi-Emprendimiento</h2>
                    <p class="text-muted">Generador de 30 sitios web con chatbot offline DeepSeek 7B</p>
                </div>
                <hr>
                <div class="mb-4">
                    <h5><i class="fas fa-clipboard-list me-2 text-primary"></i> Este instalador verificará:</h5>
                    <ul class="list-unstyled mt-3">
                        <li class="mb-2"><i class="fas fa-check-circle text-success me-2"></i> XAMPP corriendo (Apache + MySQL)</li>
                        <li class="mb-2"><i class="fas fa-check-circle text-success me-2"></i> Conexión a MySQL</li>
                        <li class="mb-2"><i class="fas fa-check-circle text-success me-2"></i> Ollama y DeepSeek 7B disponibles</li>
                        <li class="mb-2"><i class="fas fa-check-circle text-success me-2"></i> PHP 8+ con extensiones requeridas</li>
                    </ul>
                    <p class="text-muted small mt-3">
                        <i class="fas fa-info-circle me-1"></i>
                        Luego creará la base de datos, tablas, panel admin y plantilla base.
                    </p>
                </div>
                <div class="d-grid gap-2">
                    <a href="?step=1" class="btn btn-primary btn-lg">
                        <i class="fas fa-play me-2"></i> Iniciar Instalación
                    </a>
                </div>
                <?php
            } elseif ($step === 1) {
                // Paso 1: Verificaciones del sistema
                ?>
                <div class="text-center mb-4">
                    <i class="fas fa-search text-primary logo-icon mb-3"></i>
                    <h3 class="fw-bold">Verificando Sistema...</h3>
                </div>

                <div class="step-indicator justify-content-center mb-4">
                    <span class="step-dot active">1</span>
                    <span class="step-dot pending">2</span>
                    <span class="step-dot pending">3</span>
                </div>
                <?php

                $allPass = true;
                $checks = [];

                // 1. Verificar PHP versión
                $phpVersion = phpversion();
                $phpPass = version_compare($phpVersion, '8.0', '>=');
                $checks[] = [
                    'name' => 'PHP 8.0+',
                    'detail' => "Versión detectada: PHP $phpVersion",
                    'pass' => $phpPass
                ];
                if (!$phpPass) $allPass = false;

                // 2. Extensiones PHP requeridas
                $extensions = ['pdo', 'pdo_mysql', 'curl', 'gd', 'mbstring', 'json', 'session'];
                foreach ($extensions as $ext) {
                    $loaded = extension_loaded($ext);
                    $checks[] = [
                        'name' => "Extensión: $ext",
                        'detail' => $loaded ? 'Disponible' : 'NO DISPONIBLE',
                        'pass' => $loaded
                    ];
                    if (!$loaded) $allPass = false;
                }

                // 3. Conexión MySQL
                $mysqlOk = false;
                try {
                    $testPdo = new PDO("mysql:host=localhost", 'root', '', [
                        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                        PDO::ATTR_TIMEOUT => 3
                    ]);
                    $mysqlOk = true;
                } catch (Exception $e) {
                    // Ignorar
                }
                $checks[] = [
                    'name' => 'MySQL (XAMPP)',
                    'detail' => $mysqlOk ? 'Conexión exitosa en localhost:3306' : 'No se pudo conectar. ¿XAMPP está corriendo?',
                    'pass' => $mysqlOk
                ];
                if (!$mysqlOk) $allPass = false;

                // 4. Verificar Ollama
                $ollamaOk = false;
                $deepseekAvailable = false;
                try {
                    $ch = curl_init('http://localhost:11434/api/tags');
                    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                    curl_setopt($ch, CURLOPT_TIMEOUT, 5);
                    $response = curl_exec($ch);
                    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
                    curl_close($ch);
                    if ($httpCode === 200) {
                        $ollamaOk = true;
                        $models = json_decode($response, true);
                        if ($models && isset($models['models'])) {
                            foreach ($models['models'] as $model) {
                                if (strpos($model['name'], 'deepseek') !== false) {
                                    $deepseekAvailable = true;
                                    break;
                                }
                            }
                        }
                    }
                } catch (Exception $e) {
                    // Ignorar
                }
                $checks[] = [
                    'name' => 'Ollama (localhost:11434)',
                    'detail' => $ollamaOk ? 'Servicio Ollama detectado' : 'No responde. ¿Ollama está corriendo?',
                    'pass' => $ollamaOk
                ];
                $checks[] = [
                    'name' => 'Modelo DeepSeek',
                    'detail' => $deepseekAvailable ? 'Modelo deepseek disponible (' . $model['name'] . ')' : 'No hay modelo deepseek. Ejecuta: ollama pull deepseek-r1:1.5b',
                    'pass' => $deepseekAvailable,
                    'warning' => !$deepseekAvailable
                ];
                if (!$ollamaOk) $allPass = false;

                // 5. Permisos de escritura
                $writableDirs = [
                    __DIR__ . '/plantilla-base/uploads',
                    __DIR__ . '/panel-admin',
                ];
                foreach ($writableDirs as $dir) {
                    $exists = is_dir($dir) || @mkdir($dir, 0755, true);
                    $writable = $exists && is_writable($dir);
                    $checks[] = [
                        'name' => "Permiso: " . basename($dir),
                        'detail' => $writable ? 'Directorio con permisos de escritura' : 'Sin permisos de escritura',
                        'pass' => $writable
                    ];
                    if (!$writable) $allPass = false;
                }

                // Mostrar resultados
                foreach ($checks as $check): ?>
                    <div class="check-result <?= $check['pass'] ? 'pass' : (isset($check['warning']) ? 'warning' : 'fail') ?>">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <strong><?= h($check['name']) ?></strong>
                                <br><small><?= h($check['detail']) ?></small>
                            </div>
                            <div>
                                <?php if ($check['pass']): ?>
                                    <i class="fas fa-check-circle text-success fa-lg"></i>
                                <?php elseif (isset($check['warning'])): ?>
                                    <i class="fas fa-exclamation-triangle text-warning fa-lg"></i>
                                <?php else: ?>
                                    <i class="fas fa-times-circle text-danger fa-lg"></i>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>

                <div class="mt-4 text-center">
                    <?php if ($allPass): ?>
                        <div class="alert alert-success">
                            <i class="fas fa-check-circle me-2"></i>
                            Todas las verificaciones pasaron. Continuemos con la instalación.
                        </div>
                        <a href="?step=2" class="btn btn-primary btn-lg">
                            <i class="fas fa-arrow-right me-2"></i> Continuar con la Instalación
                        </a>
                    <?php else: ?>
                        <div class="alert alert-danger">
                            <i class="fas fa-exclamation-circle me-2"></i>
                            Hay problemas que deben resolverse antes de continuar.
                        </div>
                        <a href="?step=1" class="btn btn-warning btn-lg">
                            <i class="fas fa-redo me-2"></i> Reintentar Verificaciones
                        </a>
                    <?php endif; ?>
                </div>
                <?php
            } elseif ($step === 2) {
                // Paso 2: Crear BD, tablas y estructura
                ?>
                <div class="text-center mb-4">
                    <i class="fas fa-database text-primary logo-icon mb-3"></i>
                    <h3 class="fw-bold">Instalando Sistema...</h3>
                </div>
                <div class="step-indicator justify-content-center mb-4">
                    <span class="step-dot success">1</span>
                    <span class="step-dot active">2</span>
                    <span class="step-dot pending">3</span>
                </div>
                <?php

                $errors = [];
                $success = [];

                try {
                    // Conectar a MySQL sin BD específica
                    $pdo = new PDO("mysql:host=localhost", 'root', '', [
                        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION
                    ]);

                    // 1. Crear BD
                    $pdo->exec("CREATE DATABASE IF NOT EXISTS sistema_emprendimientos DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
                    $pdo->exec("USE sistema_emprendimientos");
                    $success[] = "Base de datos 'sistema_emprendimientos' creada.";

                    // 2. Leer y ejecutar el schema SQL
                    $sqlFile = __DIR__ . '/sistema_emprendimientos.sql';
                    $sql = file_get_contents($sqlFile);

                    // El SQL ya no contiene CREATE DATABASE/USE (se manejan arriba en PHP), ejecutar directamente
                    // Ejecutar sentencias una por una
                    $statements = explode(';', $sql);
                    foreach ($statements as $statement) {
                        $statement = trim($statement);
                        if (!empty($statement)) {
                            $pdo->exec($statement);
                        }
                    }
                    // Migración: agregar columna tema si no existe
                    try {
                        $pdo->exec("ALTER TABLE config_visual ADD COLUMN tema VARCHAR(50) DEFAULT 'classic-blue' AFTER emprendimiento_id");
                    } catch (Exception $e) {
                        // La columna ya existe, ignorar
                    }
                    $success[] = "Todas las tablas creadas correctamente.";

                    // 3. Crear usuario admin
                    $passwordHash = password_hash('admin123', PASSWORD_DEFAULT);
                    $stmt = $pdo->prepare("INSERT IGNORE INTO usuarios_admin (nombre, email, password_hash, rol) VALUES (?, ?, ?, ?)");
                    $stmt->execute(['Administrador', 'admin@admin.com', $passwordHash, 'superadmin']);
                    $success[] = "Usuario admin creado: admin@admin.com / admin123";

                } catch (Exception $e) {
                    $errors[] = "Error durante la instalación: " . $e->getMessage();
                }

                // Mostrar resultados
                foreach ($success as $msg): ?>
                    <div class="check-result pass">
                        <i class="fas fa-check-circle text-success me-2"></i>
                        <?= h($msg) ?>
                    </div>
                <?php endforeach;
                foreach ($errors as $msg): ?>
                    <div class="check-result fail">
                        <i class="fas fa-times-circle text-danger me-2"></i>
                        <?= h($msg) ?>
                    </div>
                <?php endforeach; ?>

                <div class="mt-4 text-center">
                    <?php if (empty($errors)): ?>
                        <a href="?step=3" class="btn btn-primary btn-lg">
                            <i class="fas fa-flag-checkered me-2"></i> Finalizar Instalación
                        </a>
                    <?php else: ?>
                        <a href="?step=2" class="btn btn-warning btn-lg">
                            <i class="fas fa-redo me-2"></i> Reintentar
                        </a>
                    <?php endif; ?>
                </div>
                <?php
            } elseif ($step === 3) {
                // Paso 3: Finalización
                ?>
                <div class="text-center mb-4">
                    <i class="fas fa-check-circle text-success logo-icon mb-3"></i>
                    <h3 class="fw-bold">¡Instalación Completa!</h3>
                </div>
                <div class="step-indicator justify-content-center mb-4">
                    <span class="step-dot success">1</span>
                    <span class="step-dot success">2</span>
                    <span class="step-dot success">3</span>
                </div>

                <div class="alert alert-success text-center">
                    <i class="fas fa-check-circle fa-2x mb-2"></i>
                    <h5>El sistema está listo para usar</h5>
                </div>

                <div class="row g-3 mt-3">
                    <div class="col-md-6">
                        <div class="card h-100 border-primary">
                            <div class="card-body text-center">
                                <i class="fas fa-cog fa-2x text-primary mb-2"></i>
                                <h5>Panel de Administración</h5>
                                <a href="/panel-admin/" class="btn btn-primary">
                                    <i class="fas fa-external-link-alt me-1"></i> Ir al Panel
                                </a>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="card h-100 border-success">
                            <div class="card-body text-center">
                                <i class="fas fa-file-alt fa-2x text-success mb-2"></i>
                                <h5>Documentación</h5>
                                <a href="/README.md" class="btn btn-success">
                                    <i class="fas fa-book me-1"></i> Ver Manual
                                </a>
                            </div>
                        </div>
                    </div>
                </div>

                <hr class="my-4">
                <h6 class="text-center text-muted mb-3">Accesos directos:</h6>
                <div class="table-responsive">
                    <table class="table table-sm">
                        <tr><td><strong>Panel Admin:</strong></td><td><a href="/panel-admin/">http://localhost/panel-admin/</a></td></tr>
                        <tr><td><strong>Email:</strong></td><td>admin@admin.com</td></tr>
                        <tr><td><strong>Contraseña:</strong></td><td>admin123</td></tr>
                        <tr><td><strong>Ollama API:</strong></td><td>http://localhost:11434</td></tr>
                    </table>
                </div>
                <?php
            }
            ?>
        </div>
        <p class="text-center text-muted mt-3 small">
            <i class="fas fa-code me-1"></i> Sistema Multi-Emprendimiento con DeepSeek 7B
        </p>
    </div>
</body>
</html>
