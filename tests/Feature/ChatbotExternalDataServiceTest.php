<?php

namespace Tests\Feature;

use App\Services\Chatbot\ChatbotExternalDataService;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * Regresión (encontrada en vivo probando el chat, 2026-09-06): las llaves
 * de $queries en getHairstyleInfo() nunca coincidían con lo que le manda
 * answerStyleQuestion() ('fade', 'undercut', minúsculas) -- así que
 * SIEMPRE caían al else y se usaba la palabra suelta como título literal
 * de artículo en la Wikipedia en español. Para "fade" eso resuelve a la
 * página de desambiguación de ingeniería de audio/cine, no al corte de
 * cabello -- "¿cuánto cuesta un fade?" devolvía un extracto sobre
 * "aumentar o disminuir progresivamente un volumen". Verificado además
 * contra la API real de Wikipedia que 3 de los 7 títulos originalmente
 * mapeados ('Quiff', 'Corte_militar', 'Afeitado_al_ras') tampoco existían.
 */
class ChatbotExternalDataServiceTest extends TestCase
{
    private ChatbotExternalDataService $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->service = app(ChatbotExternalDataService::class);
    }

    protected function tearDown(): void
    {
        Cache::flush();

        parent::tearDown();
    }

    public function test_get_hairstyle_info_queries_the_dedicated_fade_article_not_the_bare_word(): void
    {
        Http::fake([
            'es.wikipedia.org/*' => Http::response([
                'query' => ['pages' => ['1' => ['extract' => 'El hi-top fade es un corte graduado.']]],
            ]),
        ]);

        $info = $this->service->getHairstyleInfo('fade');

        Http::assertSent(fn ($request) => ($request->data()['titles'] ?? null) === 'Hi-top fade');
        $this->assertNotNull($info);
        $this->assertStringContainsString('hi-top fade', $info['description']);
    }

    /**
     * Antes del arreglo, 'undercut' era la única llave que por casualidad
     * coincidía con lo que mandaba answerStyleQuestion() -- pero
     * array_key_exists() en PHP es sensible a mayúsculas, así que ni
     * siquiera esa calzaba de verdad ('undercut' vs 'Undercut').
     */
    public function test_get_hairstyle_info_queries_the_correct_title_for_undercut(): void
    {
        Http::fake([
            'es.wikipedia.org/*' => Http::response([
                'query' => ['pages' => ['1' => ['extract' => 'El undercut es un corte con contraste marcado.']]],
            ]),
        ]);

        $this->service->getHairstyleInfo('undercut');

        Http::assertSent(fn ($request) => ($request->data()['titles'] ?? null) === 'Undercut');
    }
}
