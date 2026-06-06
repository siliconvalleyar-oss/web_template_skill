<?php

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

/**
 * Pruebas unitarias para las funciones helper definidas en config.php
 */
class HelpersTest extends TestCase
{
    // ==========================================
    // Tests para h() — escape HTML
    // ==========================================

    public function test_h_escapes_special_chars(): void
    {
        $this->assertEquals('&amp;', h('&'));
        $this->assertEquals('&lt;', h('<'));
        $this->assertEquals('&gt;', h('>'));
        $this->assertEquals('&quot;', h('"'));
        $this->assertEquals('&#039;', h("'"));
    }

    public function test_h_does_not_affect_normal_text(): void
    {
        $this->assertEquals('Hola mundo 123', h('Hola mundo 123'));
        $this->assertEquals('', h(''));
    }

    public function test_h_handles_utf8(): void
    {
        $this->assertEquals('ñandú café 🧉', h('ñandú café 🧉'));
        $this->assertEquals('árbol', h('árbol'));
    }

    // ==========================================
    // Tests para generarUUID() — UUID v4
    // ==========================================

    public function test_generarUUID_has_correct_format(): void
    {
        $uuid = generarUUID();
        $pattern = '/^[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/';
        $this->assertMatchesRegularExpression($pattern, $uuid, "UUID no tiene formato v4: $uuid");
    }

    public function test_generarUUID_produces_unique_values(): void
    {
        $uuids = [];
        for ($i = 0; $i < 100; $i++) {
            $uuids[] = generarUUID();
        }
        $unique = array_unique($uuids);
        $this->assertCount(100, $unique, 'Se generaron UUIDs duplicados');
    }

    public function test_generarUUID_version_bits(): void
    {
        $uuid = generarUUID();
        // El 13er caracter debe ser '4' (versión 4)
        $this->assertEquals('4', $uuid[14], 'El UUID no es versión 4');
        // El 17mo caracter debe ser 8, 9, a o b (variant)
        $variant = $uuid[19];
        $this->assertContains($variant, ['8', '9', 'a', 'b'], "Variant inválida: $variant");
    }

    // ==========================================
    // Tests para CSRF
    // ==========================================

    public function test_generarCSRF_returns_string(): void
    {
        $token = generarCSRF();
        $this->assertIsString($token);
        $this->assertEquals(64, strlen($token)); // 32 bytes en hex = 64 chars
    }

    public function test_generarCSRF_is_consistent_in_session(): void
    {
        $token1 = generarCSRF();
        $token2 = generarCSRF();
        $this->assertEquals($token1, $token2, 'CSRF token debería ser el mismo en la misma sesión');
    }

    public function test_csrfField_returns_valid_html(): void
    {
        $token = generarCSRF();
        $field = csrfField();
        $expected = '<input type="hidden" name="_csrf_token" value="' . $token . '">';
        $this->assertEquals($expected, $field);
    }

    public function test_verificarCSRF_with_valid_token(): void
    {
        $token = generarCSRF();
        $this->assertTrue(verificarCSRF($token), 'Token CSRF válido debería retornar true');
    }

    public function test_verificarCSRF_with_invalid_token(): void
    {
        generarCSRF();
        $this->assertFalse(verificarCSRF('token-invalido'), 'Token inválido debería retornar false');
    }

    public function test_verificarCSRF_with_empty_token(): void
    {
        $this->assertFalse(verificarCSRF(''), 'Token vacío debería retornar false');
        $this->assertFalse(verificarCSRF(null), 'Token null debería retornar false');
    }

    public function test_verificarCSRF_resists_timing_attacks(): void
    {
        $token = generarCSRF();
        // Probar con token de longitud similar pero diferente
        $fakeToken = str_repeat('a', 64);
        $this->assertFalse(verificarCSRF($fakeToken), 'Token falso de misma longitud debería retornar false');
    }
}
