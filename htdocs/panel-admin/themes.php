<?php
/**
 * Temas visuales predefinidos para emprendimientos
 * Cada tema define: color_principal, color_secundario, color_fondo, color_texto
 */

$TEMAS = [
    'classic-blue' => [
        'nombre' => 'Classic Blue',
        'descripcion' => 'Azul corporativo profesional',
        'icono' => '🔵',
        'color_principal' => '#2563eb',
        'color_secundario' => '#7c3aed',
        'color_fondo' => '#ffffff',
        'color_texto' => '#1f2937',
    ],
    'nature-green' => [
        'nombre' => 'Nature Green',
        'descripcion' => 'Verde natural y fresco',
        'icono' => '🌿',
        'color_principal' => '#059669',
        'color_secundario' => '#10b981',
        'color_fondo' => '#f0fdf4',
        'color_texto' => '#1f2937',
    ],
    'sunset-orange' => [
        'nombre' => 'Sunset Orange',
        'descripcion' => 'Naranja vibrante y cálido',
        'icono' => '🌅',
        'color_principal' => '#ea580c',
        'color_secundario' => '#f97316',
        'color_fondo' => '#fff7ed',
        'color_texto' => '#1f2937',
    ],
    'rose-pink' => [
        'nombre' => 'Rose Pink',
        'descripcion' => 'Rosa elegante y moderno',
        'icono' => '🌹',
        'color_principal' => '#e11d48',
        'color_secundario' => '#f43f5e',
        'color_fondo' => '#fff1f2',
        'color_texto' => '#1f2937',
    ],
    'dark-mode' => [
        'nombre' => 'Dark Mode',
        'descripcion' => 'Oscuro, ideal para tecnología',
        'icono' => '🌙',
        'color_principal' => '#3b82f6',
        'color_secundario' => '#8b5cf6',
        'color_fondo' => '#0f172a',
        'color_texto' => '#f1f5f9',
    ],
    'purple-haze' => [
        'nombre' => 'Purple Haze',
        'descripcion' => 'Púrpura creativo y único',
        'icono' => '💜',
        'color_principal' => '#7c3aed',
        'color_secundario' => '#a855f7',
        'color_fondo' => '#f5f3ff',
        'color_texto' => '#1f2937',
    ],
    'ocean-teal' => [
        'nombre' => 'Ocean Teal',
        'descripcion' => 'Turquesa oceánico y fresco',
        'icono' => '🌊',
        'color_principal' => '#0d9488',
        'color_secundario' => '#14b8a6',
        'color_fondo' => '#f0fdfa',
        'color_texto' => '#1f2937',
    ],
    'warm-amber' => [
        'nombre' => 'Warm Amber',
        'descripcion' => 'Ámbar acogedor y artesanal',
        'icono' => '🟠',
        'color_principal' => '#d97706',
        'color_secundario' => '#f59e0b',
        'color_fondo' => '#fffbeb',
        'color_texto' => '#1f2937',
    ],
];

/**
 * Obtiene los colores de un tema por su clave
 */
function obtenerTema($clave) {
    global $TEMAS;
    return $TEMAS[$clave] ?? $TEMAS['classic-blue'];
}

/**
 * Obtiene lista de temas para un <select>
 */
function obtenerListaTemas() {
    global $TEMAS;
    $lista = [];
    foreach ($TEMAS as $clave => $tema) {
        $lista[$clave] = $tema['icono'] . ' ' . $tema['nombre'] . ' — ' . $tema['descripcion'];
    }
    return $lista;
}
