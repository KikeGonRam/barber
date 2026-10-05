<?php

namespace App\Support\Docs;

use Knuckles\Camel\Output\OutputEndpointData;
use Knuckles\Scribe\Writing\OpenApiSpecGenerators\OpenApiGenerator;

/**
 * Marca como `required` todas las propiedades de las respuestas que provienen de
 * `docs/contrato/*.json`.
 *
 * Scribe infiere el schema de un ejemplo pero nunca emite `required`, así que
 * `openapi-typescript` tipa cada campo como opcional (`id?: string`) y los tipos
 * del frontend quedan inservibles. Aquí sí es seguro afirmar que todas las
 * claves documentadas están siempre presentes: `ApiContractTest` compara esos
 * mismos archivos contra la respuesta real y exige el mismo conjunto de claves.
 *
 * Solo se toca una respuesta si TODOS los ejemplos de ese status en el endpoint
 * son archivos de contrato; los ejemplos escritos a mano no tienen esa garantía.
 * Se registra en `config/scribe.php` (`openapi.generators`).
 */
class ContractRequiredFieldsGenerator extends OpenApiGenerator
{
    /** @var array<int, mixed>|null */
    private static ?array $contractExamples = null;

    public function pathItem(array $pathItem, array $groupedEndpoints, OutputEndpointData $endpoint): array
    {
        // Sin respuestas, Scribe deja un stdClass vacío (para serializarlo como `{}`), no un array.
        $responses = is_array($pathItem['responses'] ?? null) ? $pathItem['responses'] : [];

        foreach (array_keys($responses) as $status) {
            if (! $this->allExamplesAreContractFiles($endpoint, (string) $status)) {
                continue;
            }

            $schema = $responses[$status]['content']['application/json']['schema'] ?? null;
            if (is_array($schema)) {
                $pathItem['responses'][$status]['content']['application/json']['schema'] = self::markRequired($schema);
            }
        }

        return $pathItem;
    }

    /**
     * Agrega `required` (todas las claves de `properties`) a cada objeto del schema,
     * incluidos los anidados en `properties`, `items` y `oneOf`.
     *
     * @param  array<string, mixed>  $schema
     * @return array<string, mixed>
     */
    public static function markRequired(array $schema): array
    {
        if (isset($schema['properties']) && is_array($schema['properties']) && $schema['properties'] !== []) {
            foreach ($schema['properties'] as $name => $property) {
                if (is_array($property)) {
                    $schema['properties'][$name] = self::markRequired($property);
                }
            }
            $schema['required'] = array_map('strval', array_keys($schema['properties']));
        }

        if (isset($schema['items']) && is_array($schema['items'])) {
            $schema['items'] = self::markRequired($schema['items']);
        }

        foreach ($schema['oneOf'] ?? [] as $index => $variant) {
            if (is_array($variant)) {
                $schema['oneOf'][$index] = self::markRequired($variant);
            }
        }

        return $schema;
    }

    private function allExamplesAreContractFiles(OutputEndpointData $endpoint, string $status): bool
    {
        $examples = collect($endpoint->responses)->filter(fn ($response) => (string) $response->status === $status);
        if ($examples->isEmpty()) {
            return false;
        }

        return $examples->every(fn ($response) => in_array(json_decode((string) $response->content, true), self::contractExamples(), true));
    }

    /** @return array<int, mixed> */
    private static function contractExamples(): array
    {
        return self::$contractExamples ??= array_map(
            fn (string $file) => json_decode((string) file_get_contents($file), true),
            glob(base_path('docs/contrato/*.json')) ?: [],
        );
    }
}
