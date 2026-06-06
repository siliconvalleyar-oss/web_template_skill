<?php
/**
 * API del Chatbot - Comunicación con DeepSeek 7B via Ollama
 * Endpoint: POST /chatbot-api.php
 * Recibe: { message, history[], slug }
 * Responde: { success, response }
 */
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

require_once __DIR__ . '/config.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Método no permitido']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);
$message = trim($input['message'] ?? '');
$history = $input['history'] ?? [];
$slug = trim($input['slug'] ?? '');

if (!$message || !$slug) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Mensaje y slug requeridos']);
    exit;
}

$db = getDB();
if (!$db) {
    echo json_encode(['success' => false, 'error' => 'Error de conexión a la base de datos']);
    exit;
}

// Obtener datos del emprendimiento
$stmt = $db->prepare("SELECT * FROM emprendimientos WHERE slug = ? AND activo = TRUE");
$stmt->execute([$slug]);
$emp = $stmt->fetch();

if (!$emp) {
    echo json_encode(['success' => false, 'error' => 'Emprendimiento no encontrado']);
    exit;
}

$empId = $emp['id'];

// Obtener contexto completo
$config = $db->prepare("SELECT * FROM config_visual WHERE emprendimiento_id = ?");
$config->execute([$empId]); $config = $config->fetch() ?: [];

$contacto = $db->prepare("SELECT * FROM contacto_redes WHERE emprendimiento_id = ?");
$contacto->execute([$empId]); $contacto = $contacto->fetch() ?: [];

$contenido = $db->prepare("SELECT * FROM contenido_texto WHERE emprendimiento_id = ?");
$contenido->execute([$empId]); $contenido = $contenido->fetch() ?: [];

$productos = $db->prepare("SELECT nombre, precio, stock, descripcion_corta FROM productos WHERE emprendimiento_id = ? AND activo = TRUE ORDER BY nombre");
$productos->execute([$empId]);
$productos = $productos->fetchAll();

$formasPago = $db->prepare("SELECT * FROM formas_pago WHERE emprendimiento_id = ? AND activo = TRUE");
$formasPago->execute([$empId]);
$formasPago = $formasPago->fetchAll();

// Construir System Prompt con contexto del negocio
$systemPrompt = "Eres un asistente virtual amable y profesional de la tienda '{$emp['nombre']}'. ";
$systemPrompt .= "Responde preguntas sobre productos, precios, stock, formas de pago, envíos y políticas. ";
$systemPrompt .= "Sé conciso, amable y útil. Si no sabes algo, sugiere contactar por WhatsApp.\n\n";

$systemPrompt .= "=== DATOS DEL NEGOCIO ===\n";
$systemPrompt .= "Nombre: {$emp['nombre']}\n";
if ($emp['eslogan']) $systemPrompt .= "Eslogan: {$emp['eslogan']}\n";
if ($contacto['whatsapp_numero']) $systemPrompt .= "WhatsApp: {$contacto['whatsapp_numero']}\n";
if ($contacto['whatsapp_horarios']) $systemPrompt .= "Horarios: {$contacto['whatsapp_horarios']}\n";
if ($contacto['email']) $systemPrompt .= "Email: {$contacto['email']}\n";
if ($contacto['telefono']) $systemPrompt .= "Teléfono: {$contacto['telefono']}\n";
if ($contacto['direccion']) $systemPrompt .= "Dirección: {$contacto['direccion']}\n";

if ($contenido && $contenido['texto_quienes_somos']) {
    $systemPrompt .= "\nSobre nosotros: " . strip_tags($contenido['texto_quienes_somos']) . "\n";
}
if ($contenido && $contenido['politicas_envio']) {
    $systemPrompt .= "\nPolíticas de envío: " . strip_tags($contenido['politicas_envio']) . "\n";
}
if ($contenido && $contenido['politicas_devolucion']) {
    $systemPrompt .= "\nPolíticas de devolución: " . strip_tags($contenido['politicas_devolucion']) . "\n";
}

// Productos
$systemPrompt .= "\n=== PRODUCTOS DISPONIBLES ===\n";
if (count($productos) > 0) {
    foreach ($productos as $p) {
        $systemPrompt .= "- {$p['nombre']}: \${$p['precio']} (Stock: {$p['stock']})";
        if ($p['descripcion_corta']) $systemPrompt .= " - {$p['descripcion_corta']}";
        $systemPrompt .= "\n";
    }
} else {
    $systemPrompt .= "No hay productos disponibles actualmente.\n";
}

// Formas de pago
$systemPrompt .= "\n=== FORMAS DE PAGO ===\n";
if (count($formasPago) > 0) {
    foreach ($formasPago as $fp) {
        $systemPrompt .= "- " . ucfirst($fp['tipo']);
        if ($fp['descripcion']) $systemPrompt .= ": {$fp['descripcion']}";
        if ($fp['datos_extra']) $systemPrompt .= " ({$fp['datos_extra']})";
        $systemPrompt .= "\n";
    }
} else {
    $systemPrompt .= "Consultar formas de pago disponibles.\n";
}

$systemPrompt .= "\n=== INSTRUCCIONES ===\n";
$systemPrompt .= "1. Responde siempre en español, con tono amable y profesional.\n";
$systemPrompt .= "2. Si te preguntan por precios o productos, usa la información de arriba.\n";
$systemPrompt .= "3. Si algo no está en tu contexto, di 'No tengo esa información, pero puedes consultarnos por WhatsApp' y ofrece el número.\n";
$systemPrompt .= "4. No inventes precios ni información que no esté en tu contexto.\n";
$systemPrompt .= "5. Sé breve pero completo en tus respuestas.\n";

// Construir historial completo para Ollama (/api/chat)
// El system prompt va en el campo "system" de alto nivel (el template del modelo usa $.System)
$recentHistory = array_slice($history, -6);
$ollamaMessages = [];
foreach ($recentHistory as $h) {
    if (isset($h['role']) && isset($h['content']) && in_array($h['role'], ['user', 'assistant'])) {
        $ollamaMessages[] = ['role' => $h['role'], 'content' => $h['content']];
    }
}

// Agregar mensaje actual
$ollamaMessages[] = ['role' => 'user', 'content' => $message];

// Llamar a Ollama API
try {
    $payload = json_encode([
        'model' => OLLAMA_MODEL,
        'system' => $systemPrompt,  // Template usa $.System, no un mensaje con role system
        'messages' => $ollamaMessages,
        'stream' => false,
        'options' => [
            'temperature' => 0.7,
            'num_predict' => 500,
        ]
    ]);

    // Usar shell_exec + curl del sistema (PHP libcurl 7.53.1 no funciona con Ollama)
    $tmpFile = tempnam(sys_get_temp_dir(), 'ollama_');
    file_put_contents($tmpFile, $payload);
    // Usar proc_open con entorno limpio para evitar que curl cargue libcurl de XAMPP
    $descriptors = [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']];
    $env = ['LD_LIBRARY_PATH' => '/lib/x86_64-linux-gnu'];
    $cmd = sprintf(
        '/usr/bin/curl -s --max-time %d -X POST %s -H "Content-Type: application/json" -d %s',
        CHATBOT_TIMEOUT,
        OLLAMA_URL,
        escapeshellarg('@' . $tmpFile)
    );
    $process = @proc_open($cmd, $descriptors, $pipes, '/tmp', $env);
    
    if (!is_resource($process)) {
        unlink($tmpFile);
        throw new Exception('Error al ejecutar curl (proc_open falló)');
    }
    
    fclose($pipes[0]);
    $response = trim(stream_get_contents($pipes[1]));
    $stderr = stream_get_contents($pipes[2]);
    fclose($pipes[1]);
    fclose($pipes[2]);
    $returnCode = proc_close($process);
    
    if ($returnCode !== 0 && empty($response)) {
        error_log('[CHATBOT] curl stderr: ' . substr($stderr, 0, 300));
        unlink($tmpFile);
        throw new Exception('Error de conexión con Ollama (curl exit code: ' . $returnCode . ')');
    }
    unlink($tmpFile);

    if ($response === null || $response === false || $response === '') {
        throw new Exception('Error de conexión con Ollama (curl via shell_exec no devolvió respuesta)');
    }

    // Decodificar respuesta JSON de Ollama
    $ollamaResponse = json_decode($response, true);
    
    if (!$ollamaResponse || !isset($ollamaResponse['response'])) {
        error_log('[CHATBOT] DEBUG response (primeros 500): ' . substr($response, 0, 500));
        throw new Exception('Respuesta inválida de Ollama');
    }

    $respuesta = trim($ollamaResponse['response']);
    
    // Eliminar tags de pensamiento si existen (deepseek-r1 los incluye)
    $respuesta = preg_replace('/<think>.*?<\/think>/s', '', $respuesta);
    $respuesta = trim($respuesta);

    if (empty($respuesta)) {
        throw new Exception('Respuesta vacía del modelo');
    }

    // Guardar la conversación en BD
    try {
        $sessionId = session_id() ?: 'anon_' . substr(md5($_SERVER['REMOTE_ADDR'] . $slug), 0, 10);
        $stmt = $db->prepare("INSERT INTO conversaciones_chatbot (emprendimiento_id, session_id, rol, mensaje) VALUES (?, ?, 'user', ?), (?, ?, 'assistant', ?)");
        $stmt->execute([$empId, $sessionId, $message, $empId, $sessionId, $respuesta]);
    } catch (Exception $e) {
        // No bloquear la respuesta si falla el guardado
    }

    echo json_encode([
        'success' => true,
        'response' => $respuesta,
    ]);

} catch (Exception $e) {
    error_log("[CHATBOT] Error: " . $e->getMessage());
    // Fallback: sugerir WhatsApp
    $fallback = "Lo siento, tuve un problema para procesar tu consulta. 😅\n\n";
    if ($contacto['whatsapp_numero']) {
        $waNumero = preg_replace('/[^0-9]/', '', $contacto['whatsapp_numero']);
        $waLink = "https://wa.me/{$waNumero}?text=" . urlencode($contacto['whatsapp_mensaje_auto'] ?: 'Hola, necesito ayuda');
        $fallback .= "Por favor, escríbeme por <a href='{$waLink}' target='_blank' class='text-success fw-bold'>WhatsApp</a> y te atenderé personalmente.";
    } else {
        $fallback .= "Por favor, intenta de nuevo más tarde o contáctanos por otro medio.";
    }
    
    echo json_encode([
        'success' => true,
        'response' => $fallback,
        'fallback' => true
    ]);
}
