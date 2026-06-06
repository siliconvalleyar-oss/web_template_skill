<?php
/**
 * Configuración general del sistema de 30 emprendimientos
 * Conexión a base de datos MySQL y constantes globales
 */

// Configuración de la base de datos
// Se pueden sobreescribir via variables de entorno (útil para Docker)
define('DB_HOST', getenv('DB_HOST') ?: 'localhost');
define('DB_NAME', getenv('DB_NAME') ?: 'sistema_emprendimientos');
define('DB_USER', getenv('DB_USER') ?: 'root');
define('DB_PASS', getenv('DB_PASS') ?: '');

// Configuración de rutas
// En Docker, la raiz web es /var/www/html, y los archivos están en htdocs/
define('BASE_PATH', getenv('BASE_PATH') ?: dirname(__DIR__) . '/htdocs');
define('PANEL_PATH', BASE_PATH . '/panel-admin');
define('TEMPLATE_PATH', BASE_PATH . '/plantilla-base');
define('BACKUP_PATH', BASE_PATH . '/backups');

// Configuración de Ollama (DeepSeek 7B)
define('OLLAMA_URL', getenv('OLLAMA_URL') ?: 'http://localhost:11434/api/chat');
define('OLLAMA_MODEL', getenv('OLLAMA_MODEL') ?: 'deepseek-r1:1.5b');
define('CHATBOT_TIMEOUT', (int)(getenv('CHATBOT_TIMEOUT') ?: 600)); // 10 minutos

// Configuración de subida de archivos
define('MAX_FILE_SIZE', (int)(getenv('MAX_FILE_SIZE') ?: 5 * 1024 * 1024)); // 5MB
define('ALLOWED_EXTENSIONS', ['jpg', 'jpeg', 'png', 'gif', 'webp', 'svg', 'ico']);

// Configuración de sesión
if (session_status() === PHP_SESSION_NONE) {
    ini_set('session.cookie_httponly', 1);
    ini_set('session.use_only_cookies', 1);
    ini_set('session.cookie_samesite', 'Lax');
    session_start();
}

// Configuración de zona horaria
date_default_timezone_set('America/Argentina/Buenos_Aires');

/**
 * Obtiene una conexión PDO a la base de datos
 * @return PDO|null
 */
function getDB() {
    static $pdo = null;
    if ($pdo === null) {
        try {
            $pdo = new PDO(
                "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4",
                DB_USER,
                DB_PASS,
                [
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                    PDO::ATTR_EMULATE_PREPARES => false,
                ]
            );
        } catch (PDOException $e) {
            error_log("Error de conexión BD: " . $e->getMessage());
            return null;
        }
    }
    return $pdo;
}

/**
 * Escapa HTML de forma segura
 */
function h($text) {
    return htmlspecialchars($text, ENT_QUOTES, 'UTF-8');
}

/**
 * Genera un UUID v4
 */
function generarUUID() {
    $data = random_bytes(16);
    $data[6] = chr(ord($data[6]) & 0x0f | 0x40);
    $data[8] = chr(ord($data[8]) & 0x3f | 0x80);
    return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($data), 4));
}

/**
 * Verifica que Ollama esté corriendo
 */
function verificarOllama() {
    $ch = curl_init('http://localhost:11434/api/tags');
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 5);
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    return $httpCode === 200;
}

/**
 * Redirige a una URL
 */
function redirect($url) {
    header("Location: $url");
    exit;
}

/**
 * Muestra mensaje de alerta
 */
function alerta($mensaje, $tipo = 'success') {
    $_SESSION['alerta'] = ['mensaje' => $mensaje, 'tipo' => $tipo];
}

// ====================================================
// CSRF Protection
// ====================================================

/**
 * Genera o recupera el token CSRF de la sesión actual
 */
function generarCSRF() {
    if (empty($_SESSION['_csrf_token'])) {
        $_SESSION['_csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['_csrf_token'];
}

/**
 * Renderiza un campo hidden con el token CSRF
 */
function csrfField() {
    return '<input type="hidden" name="_csrf_token" value="' . generarCSRF() . '">';
}

/**
 * Verifica que el token CSRF recibido sea válido
 * @param string|null $token Token recibido (opcional, usa $_POST por defecto)
 * @return bool
 */
function verificarCSRF($token = null) {
    if ($token === null) {
        $token = $_POST['_csrf_token'] ?? '';
    }
    if (empty($token) || empty($_SESSION['_csrf_token'])) {
        return false;
    }
    // Comparación segura en tiempo constante
    return hash_equals($_SESSION['_csrf_token'], $token);
}

// ====================================================
// Backup / Export helpers
// ====================================================

/**
 * Exporta un emprendimiento completo a un array (para JSON o backup)
 * @param PDO $db
 * @param int $id ID del emprendimiento
 * @return array|null Datos completos o null si no existe
 */
function exportarEmprendimiento($db, $id) {
    $stmt = $db->prepare("SELECT * FROM emprendimientos WHERE id = ?");
    $stmt->execute([$id]);
    $emp = $stmt->fetch();
    if (!$emp) return null;

    // Obtener cada sección por separado
    $cvStmt = $db->prepare("SELECT * FROM config_visual WHERE emprendimiento_id = ?");
    $cvStmt->execute([$id]);
    $configVisual = $cvStmt->fetch() ?: [];

    $crStmt = $db->prepare("SELECT * FROM contacto_redes WHERE emprendimiento_id = ?");
    $crStmt->execute([$id]);
    $contactoRedes = $crStmt->fetch() ?: [];

    $ctStmt = $db->prepare("SELECT * FROM contenido_texto WHERE emprendimiento_id = ?");
    $ctStmt->execute([$id]);
    $contenidoTexto = $ctStmt->fetch() ?: [];

    $icStmt = $db->prepare("SELECT * FROM imagenes_carrusel WHERE emprendimiento_id = ? ORDER BY orden");
    $icStmt->execute([$id]);
    $imagenesCarrusel = $icStmt->fetchAll();

    $pStmt = $db->prepare("SELECT * FROM productos WHERE emprendimiento_id = ? ORDER BY nombre");
    $pStmt->execute([$id]);
    $productos = $pStmt->fetchAll();

    $fpStmt = $db->prepare("SELECT * FROM formas_pago WHERE emprendimiento_id = ?");
    $fpStmt->execute([$id]);
    $formasPago = $fpStmt->fetchAll();

    return [
        'export_version' => '1.0',
        'export_date' => date('Y-m-d H:i:s'),
        'emprendimiento' => $emp,
        'config_visual' => $configVisual,
        'contacto_redes' => $contactoRedes,
        'contenido_texto' => $contenidoTexto,
        'imagenes_carrusel' => $imagenesCarrusel,
        'productos' => $productos,
        'formas_pago' => $formasPago,
    ];
}

/**
 * Guarda un backup JSON de un emprendimiento en /backups/
 * @param array $data Datos del emprendimiento (de exportarEmprendimiento)
 * @return string Ruta del archivo backup
 */
function guardarBackup($data) {
    $backupDir = BACKUP_PATH;
    if (!is_dir($backupDir)) {
        @mkdir($backupDir, 0755, true);
    }
    $slug = $data['emprendimiento']['slug'] ?? 'unknown';
    $fecha = date('Y-m-d_H-i-s');
    $filename = "backup_{$slug}_{$fecha}.json";
    $filepath = "$backupDir/$filename";
    file_put_contents($filepath, json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
    return $filepath;
}

/**
 * Clona (duplica) un emprendimiento completo con nuevo slug y nombre
 * @param PDO $db
 * @param int $sourceId ID del emprendimiento a clonar
 * @param string $newSlug Nuevo slug
 * @param string $newName Nuevo nombre
 * @return int ID del nuevo emprendimiento
 */
function duplicarEmprendimiento($db, $sourceId, $newSlug, $newName) {
    $data = exportarEmprendimiento($db, $sourceId);
    if (!$data) throw new Exception('Emprendimiento origen no encontrado');

    $db->beginTransaction();
    try {
        // Crear nuevo emprendimiento
        $stmt = $db->prepare("INSERT INTO emprendimientos (slug, nombre, eslogan, activo) VALUES (?, ?, ?, 0)");
        $stmt->execute([$newSlug, $newName, $data['emprendimiento']['eslogan']]);
        $newId = $db->lastInsertId();

        // Copiar config_visual
        $cv = $data['config_visual'];
        if ($cv) {
            $db->prepare("INSERT INTO config_visual (emprendimiento_id, tema, color_principal, color_secundario, color_fondo, color_texto, logo, favicon, titulo_seo, meta_descripcion) VALUES (?,?,?,?,?,?,?,?,?,?)")
               ->execute([$newId, $cv['tema'] ?? 'classic-blue', $cv['color_principal'] ?? '#2563eb', $cv['color_secundario'] ?? '#7c3aed', $cv['color_fondo'] ?? '#ffffff', $cv['color_texto'] ?? '#1f2937', '', '', $cv['titulo_seo'] ?? '', $cv['meta_descripcion'] ?? '']);
        }

        // Copiar contacto_redes
        $cr = $data['contacto_redes'];
        if ($cr) {
            $db->prepare("INSERT INTO contacto_redes (emprendimiento_id, telefono, email, direccion, whatsapp_numero, whatsapp_mensaje_auto, whatsapp_horarios, instagram_activo, instagram_link, instagram_usuario, facebook_activo, facebook_link, tiktok_activo, tiktok_link, linkedin_activo, linkedin_link, twitter_activo, twitter_link) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)")
               ->execute([$newId, $cr['telefono'] ?? '', $cr['email'] ?? '', $cr['direccion'] ?? '', $cr['whatsapp_numero'] ?? '', $cr['whatsapp_mensaje_auto'] ?? '', $cr['whatsapp_horarios'] ?? '', $cr['instagram_activo'] ?? 0, $cr['instagram_link'] ?? '', $cr['instagram_usuario'] ?? '', $cr['facebook_activo'] ?? 0, $cr['facebook_link'] ?? '', $cr['tiktok_activo'] ?? 0, $cr['tiktok_link'] ?? '', $cr['linkedin_activo'] ?? 0, $cr['linkedin_link'] ?? '', $cr['twitter_activo'] ?? 0, $cr['twitter_link'] ?? '']);
        }

        // Copiar contenido_texto
        $ct = $data['contenido_texto'];
        if ($ct) {
            $db->prepare("INSERT INTO contenido_texto (emprendimiento_id, texto_quienes_somos, texto_bienvenida, politicas_envio, politicas_devolucion) VALUES (?,?,?,?,?)")
               ->execute([$newId, $ct['texto_quienes_somos'] ?? '', $ct['texto_bienvenida'] ?? '', $ct['politicas_envio'] ?? '', $ct['politicas_devolucion'] ?? '']);
        }

        // Copiar productos
        foreach ($data['productos'] as $p) {
            $db->prepare("INSERT INTO productos (emprendimiento_id, nombre, precio, stock, imagen, descripcion_corta, destacado, activo) VALUES (?,?,?,?,?,?,?,?)")
               ->execute([$newId, $p['nombre'], $p['precio'], $p['stock'], '', $p['descripcion_corta'] ?? '', $p['destacado'] ?? 0, $p['activo'] ?? 1]);
        }

        // Copiar formas de pago
        foreach ($data['formas_pago'] as $fp) {
            $db->prepare("INSERT INTO formas_pago (emprendimiento_id, tipo, descripcion, datos_extra, activo) VALUES (?,?,?,?,?)")
               ->execute([$newId, $fp['tipo'], $fp['descripcion'] ?? '', $fp['datos_extra'] ?? '', $fp['activo'] ?? 1]);
        }

        // Copiar imágenes del carrusel (sin las imágenes físicas)
        foreach ($data['imagenes_carrusel'] as $img) {
            $db->prepare("INSERT INTO imagenes_carrusel (emprendimiento_id, imagen, orden, titulo, subtitulo) VALUES (?,?,?,?,?)")
               ->execute([$newId, '', $img['orden'] ?? 0, $img['titulo'] ?? '', $img['subtitulo'] ?? '']);
        }

        $db->commit();
        return $newId;
    } catch (Exception $e) {
        $db->rollBack();
        throw $e;
    }
}
