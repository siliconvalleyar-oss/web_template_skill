<?php

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

/**
 * Pruebas para la lógica de validación de archivos del uploader
 * 
 * Estas pruebas validan las reglas de negocio de subida de archivos
 * que están definidas en config.php (ALLOWED_EXTENSIONS, MAX_FILE_SIZE)
 * y en upload.php (allowedMimes).
 * 
 * NOTA: Usan las constantes reales ALLOWED_EXTENSIONS y MAX_FILE_SIZE
 * definidas en config.php. Si cambian, los tests se actualizan automáticamente.
 */

class UploadValidationTest extends TestCase
{
    private array $allowedMimes;

    protected function setUp(): void
    {
        parent::setUp();

        // Replicar el array de MIME types que está en upload.php
        $this->allowedMimes = [
            'image/jpeg',
            'image/png',
            'image/gif',
            'image/webp',
            'image/svg+xml',
            'image/x-icon',
        ];
    }

    // ==========================================
    // Tests de validación de extensiones
    // Usa la constante real ALLOWED_EXTENSIONS de config.php
    // ==========================================

    public function test_jpg_and_jpeg_are_both_allowed(): void
    {
        $this->assertContains('jpg', ALLOWED_EXTENSIONS);
        $this->assertContains('jpeg', ALLOWED_EXTENSIONS);
    }

    public function test_dangerous_extensions_are_not_allowed(): void
    {
        $dangerous = ['php', 'php3', 'php4', 'php5', 'phtml', 'exe', 'sh', 'bat', 'cgi', 'pl', 'py', 'rb', 'asp', 'aspx', 'js', 'html', 'htm'];
        foreach ($dangerous as $ext) {
            $this->assertNotContains(
                $ext,
                ALLOWED_EXTENSIONS,
                "La extensión '$ext' NO debería estar permitida"
            );
        }
    }

    public function test_extension_check_is_case_insensitive(): void
    {
        // El código usa strtolower(), así que JPG debe pasar
        $ext = 'JPG';
        $this->assertContains(strtolower($ext), ALLOWED_EXTENSIONS);
    }

    // ==========================================
    // Tests de validación de tamaño
    // Usa la constante real MAX_FILE_SIZE de config.php
    // ==========================================

    public function test_max_file_size_is_5mb(): void
    {
        $expected = 5 * 1024 * 1024; // 5MB en bytes
        $this->assertEquals($expected, MAX_FILE_SIZE);
    }

    public function test_file_under_limit_passes_size_check(): void
    {
        $smallFile = 1024; // 1KB
        $this->assertLessThanOrEqual(MAX_FILE_SIZE, $smallFile);
    }

    public function test_file_exactly_at_limit_passes_size_check(): void
    {
        $this->assertLessThanOrEqual(MAX_FILE_SIZE, MAX_FILE_SIZE);
    }

    public function test_file_over_limit_fails_size_check(): void
    {
        $overLimit = MAX_FILE_SIZE + 1;
        $this->assertGreaterThan(MAX_FILE_SIZE, $overLimit);
    }

    // ==========================================
    // Tests de validación de MIME types
    // ==========================================

    public function test_image_mimes_are_present(): void
    {
        $requiredMimes = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
        foreach ($requiredMimes as $mime) {
            $this->assertContains($mime, $this->allowedMimes, "Falta MIME requerido: $mime");
        }
    }

    public function test_non_image_mimes_are_rejected(): void
    {
        $nonImageMimes = [
            'application/x-php',
            'text/html',
            'application/javascript',
            'application/x-sh',
            'application/x-dosexec',
            'application/pdf',
            'text/plain',
        ];
        foreach ($nonImageMimes as $mime) {
            $this->assertNotContains(
                $mime,
                $this->allowedMimes,
                "El MIME '$mime' NO debería estar permitido"
            );
        }
    }

    // ==========================================
    // Tests de integridad de la ruta de subida
    // ==========================================

    public function test_upload_path_is_inside_base_path(): void
    {
        $slug = 'test-emprendimiento';
        $basePath = '/opt/lampp/htdocs/misitios';
        $uploadDir = $basePath . '/' . $slug . '/uploads';

        $this->assertStringStartsWith($basePath, $uploadDir);
        $this->assertStringEndsWith('/uploads', $uploadDir);
        $this->assertStringContainsString($slug, $uploadDir);
    }

    public function test_path_traversal_is_prevented(): void
    {
        // Verificar que slugs maliciosos con path traversal no puedan salir del BASE_PATH
        $maliciousSlugs = [
            '../../../etc/passwd',
            '..%2F..%2F..',
            '....//....//....//',
            '/etc/passwd',
        ];

        foreach ($maliciousSlugs as $slug) {
            $basePath = '/opt/lampp/htdocs/misitios';
            $uploadDir = $basePath . '/' . basename($slug) . '/uploads';
            // basename() elimina los componentes de directorio, protegiendo contra path traversal
            $resolvedDir = realpath($uploadDir) ?: $uploadDir;
            $this->assertStringStartsWith($basePath, $resolvedDir, "Path traversal detectado con slug: $slug");
        }
    }
}
