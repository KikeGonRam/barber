<?php

namespace Tests\Feature;

use App\Http\Controllers\Chatbot\ChatbotController;
use ReflectionMethod;
use Tests\TestCase;

/**
 * Regresión (reportada por el usuario, 2026-09-06, "pierde el contexto y
 * el rol"): ChatbotController::matchesKeywords() compara por substring
 * (str_contains). La keyword suelta 'ayuda' de la categoría de FAQ
 * generales calzaba dentro de "ayudame" -- un seguimiento tan común como
 * "sí, ayúdame" (respondiendo a CUALQUIER pregunta anterior del bot, sin
 * importar el tema) secuestraba la conversación hacia el FAQ genérico de
 * bienvenida para clientes nuevos ("BIENVENIDO A URBANBLADE: 1.
 * Regístrate..."), sin importar que el usuario fuera admin ni de qué se
 * hablara un segundo antes. Reproducido exacto: "como gestionar usuarios"
 * (responde bien, rama de admin) seguido de "si ayudame" (antes del
 * arreglo: FAQ de bienvenida; después: cae correctamente hasta el
 * proveedor de IA, que sí tiene el contexto real de la conversación desde
 * el arreglo de ChatbotContextService del mismo día).
 *
 * Se probó primero cambiar matchesKeywords() a comparación por palabra
 * completa (\bword\b) -- eso SÍ evita que 'ayuda' calce dentro de
 * 'ayudame', pero rompe coincidencias plurales de las que otras
 * categorías sí dependen (p. ej. la keyword 'usuario' dejaba de calzar
 * con "usuarios"). El arreglo real fue quitar 'ayuda' como keyword suelta
 * de la categoría de FAQ, no cambiar cómo compara el método en general
 * (ver el segundo test de abajo, que confirma que el plural sigue
 * funcionando).
 */
class ChatbotKeywordMatchingTest extends TestCase
{
    private function matchesKeywords(string $message, array $keywords): bool
    {
        $controller = app(ChatbotController::class);
        $method = new ReflectionMethod($controller, 'matchesKeywords');

        return $method->invoke($controller, $message, $keywords);
    }

    public function test_generic_faq_keywords_do_not_hijack_a_short_affirmative_followup(): void
    {
        $faqKeywords = ['primer', 'primera vez', 'nuevo', 'cómo funciona', 'tutorial'];

        $this->assertFalse($this->matchesKeywords('si ayudame', $faqKeywords));
        $this->assertFalse($this->matchesKeywords('ok ayudame por favor', $faqKeywords));
    }

    public function test_admin_user_management_keywords_still_match_the_plural(): void
    {
        $adminKeywords = ['permiso', 'usuario', 'rol', 'acceso'];

        $this->assertTrue($this->matchesKeywords('como gestionar usuarios', $adminKeywords));
    }

    public function test_faq_keywords_still_match_genuine_onboarding_questions(): void
    {
        $faqKeywords = ['primer', 'primera vez', 'nuevo', 'cómo funciona', 'tutorial'];

        $this->assertTrue($this->matchesKeywords('es mi primera vez aqui', $faqKeywords));
        $this->assertTrue($this->matchesKeywords('como funciona esto', $faqKeywords));
    }
}
