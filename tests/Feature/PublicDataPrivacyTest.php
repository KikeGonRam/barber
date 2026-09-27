<?php

namespace Tests\Feature;

use App\Support\PublicName;
use Tests\TestCase;

/**
 * Lo que se ve sin sesión no debe filtrar datos: nombres completos de clientes ni páginas HTML
 * de error (que dejaban ver el puerto interno del contenedor).
 */
class PublicDataPrivacyTest extends TestCase
{
    public function test_public_name_keeps_first_name_and_surname_initial(): void
    {
        $this->assertSame('Luis G.', PublicName::of('Luis Enrique González Ramírez'));
        $this->assertSame('Ana P.', PublicName::of('Ana Pérez'));
        // Tres palabras = nombre y dos apellidos (lo común en México).
        $this->assertSame('Luis G.', PublicName::of('Luis González Ramírez'));
        $this->assertSame('Kike', PublicName::of('  Kike  '));
        $this->assertNull(PublicName::of(null));
        $this->assertNull(PublicName::of('   '));
    }

    public function test_api_answers_json_even_without_accept_header(): void
    {
        // Sin «Accept: application/json» Laravel redirigía (302 a :8080) al fallar la validación.
        $response = $this->get('/api/v1/availability/slots');

        $response->assertStatus(422);
        $this->assertStringContainsString('application/json', (string) $response->headers->get('Content-Type'));
    }
}
