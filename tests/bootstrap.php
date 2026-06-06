<?php
/**
 * Bootstrap de pruebas unitarias
 * 
 * Carga el autoloader de Composer y configura el entorno
 * para que las pruebas puedan ejecutarse sin servidor web.
 */

// Cargar autoloader de Composer
$autoload = __DIR__ . '/../vendor/autoload.php';
if (!file_exists($autoload)) {
    echo "[ERROR] Ejecuta 'composer install' primero.\n";
    exit(1);
}
require_once $autoload;

// Las pruebas NO deben iniciar sesión real
// Configuramos session_start para que sea no-op en CLI
if (session_status() === PHP_SESSION_NONE) {
    // No crear sesión real en CLI
}
