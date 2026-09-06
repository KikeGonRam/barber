<?php

namespace App\Services\Chatbot;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Enriquece las respuestas del chatbot con datos de APIs externas
 * (Wikipedia, OpenStreetMap/Nominatim, Overpass) sobre estilos, tecnicas
 * y ubicacion. Todas las llamadas HTTP van cacheadas y protegidas con
 * try/catch: si la API externa falla, se devuelve null en vez de romper
 * la conversacion del chatbot.
 */
class ChatbotExternalDataService
{
    /**
     * Obtiene información de Wikipedia sobre barbería, estilos, técnicas.
     * Efecto secundario: llamada HTTP externa cacheada 24h por query.
     */
    public function getWikipediaInfo(string $query): ?string
    {
        // Cachear por 24 horas
        $cacheKey = 'wikipedia_'.md5($query);

        return Cache::remember($cacheKey, 86400, function () use ($query) {
            try {
                $response = Http::withHeaders([
                    'User-Agent' => 'BarberPro-Chatbot/1.0',
                ])
                    ->timeout(5)
                    ->get('https://es.wikipedia.org/w/api.php', [
                        'action' => 'query',
                        'format' => 'json',
                        'titles' => $query,
                        'prop' => 'extracts',
                        'exintro' => true,
                        'explaintext' => true,
                        'redirects' => 1,
                    ]);

                if ($response->failed()) {
                    return null;
                }

                $data = $response->json();
                $pages = $data['query']['pages'] ?? [];

                if (empty($pages)) {
                    return null;
                }

                $page = reset($pages);
                $extract = $page['extract'] ?? null;

                if ($extract) {
                    return substr($extract, 0, 300).'...';
                }

                return null;

            } catch (\Exception $e) {
                Log::warning('Wikipedia API Error: '.$e->getMessage());

                return null;
            }
        });
    }

    /**
     * Busca información sobre estilos de corte en Wikipedia, mapeando el
     * nombre coloquial del estilo al titulo real del articulo.
     */
    public function getHairstyleInfo(string $style): ?array
    {
        // Regresión encontrada en vivo (2026-09-06): estas llaves nunca
        // coincidían con lo que manda answerStyleQuestion() ('fade',
        // 'undercut', minúsculas, sin "Corte"/"Slick" delante), así que
        // array_key_exists() siempre daba false y $key caía al else de
        // abajo -- se le pasaba la palabra suelta ('fade') como TÍTULO de
        // artículo a la Wikipedia en español, que resuelve a la página de
        // desambiguación genérica (edición de audio/cine), no al corte de
        // cabello. Confirmado: "¿cuánto cuesta un fade?" devolvía un
        // extracto sobre ingeniería de sonido. Las llaves ahora coinciden
        // exactamente con $styles de abajo. Además, verificado uno por uno
        // contra la API real de Wikipedia: 3 de los 7 títulos originales
        // ('Quiff', 'Corte_militar', 'Afeitado_al_ras') tampoco existían
        // -- corregidos a los títulos reales confirmados.
        $queries = [
            'fade' => 'Hi-top fade',
            'undercut' => 'Undercut',
            'pompadour' => 'Pompadour',
            'quiff' => 'Tupé',
            'crew cut' => 'Corte de pelo militar',
            'buzz cut' => 'Rapado',
            'slick back' => 'Peinado',
        ];

        $key = array_key_exists($style, $queries) ? $queries[$style] : $style;
        $info = $this->getWikipediaInfo($key);

        if ($info) {
            return [
                'style' => $style,
                'description' => $info,
                'source' => 'Wikipedia',
            ];
        }

        return null;
    }

    /**
     * Obtiene información de tendencias de barbería desde Wikipedia
     * (misma cache/manejo de errores que getWikipediaInfo).
     */
    public function getBarberTrends(): ?string
    {
        $info = $this->getWikipediaInfo('Barbería moderna');

        if ($info) {
            return "TENDENCIAS EN BARBERÍA:\n{$info}";
        }

        return null;
    }

    /**
     * Obtiene coordenadas y detalles usando Nominatim (OpenStreetMap).
     * Efecto secundario: llamada HTTP externa, cacheada 7 dias (una
     * direccion fisica no cambia seguido).
     */
    public function getLocationInfo(string $address): ?array
    {
        $cacheKey = 'osm_'.md5($address);

        return Cache::remember($cacheKey, 86400 * 7, function () use ($address) {
            try {
                $response = Http::withHeaders([
                    'User-Agent' => 'BarberPro-Chatbot/1.0',
                ])
                    ->timeout(5)
                    ->get('https://nominatim.openstreetmap.org/search', [
                        'q' => $address,
                        'format' => 'json',
                        'limit' => 1,
                    ]);

                if ($response->failed()) {
                    return null;
                }

                $data = $response->json();

                if (empty($data)) {
                    return null;
                }

                $location = $data[0] ?? null;

                if ($location) {
                    return [
                        'address' => $location['display_name'],
                        'latitude' => $location['lat'],
                        'longitude' => $location['lon'],
                        'type' => $location['type'],
                    ];
                }

                return null;

            } catch (\Exception $e) {
                Log::warning('OpenStreetMap API Error: '.$e->getMessage());

                return null;
            }
        });
    }

    /**
     * Busca barbershops cercanas (requiere ubicación) via Overpass API
     * (consulta cruda de OpenStreetMap). Efecto secundario: llamada HTTP
     * externa cacheada 24h, limita el resultado a 5 barberias.
     */
    public function getNearbyBarbers(float $lat, float $lon, int $radius = 2): ?array
    {
        $cacheKey = "nearby_barbers_{$lat}_{$lon}_{$radius}";

        return Cache::remember($cacheKey, 86400, function () use ($lat, $lon, $radius) {
            try {
                // Usar Overpass API para buscar barbershops
                $query = <<<EOQ
[bbox:-90,-180,90,180];
(
  node["shop"="hairdresser"](around:{$radius}000,{$lat},{$lon});
  way["shop"="hairdresser"](around:{$radius}000,{$lat},{$lon});
  node["shop"="barber"](around:{$radius}000,{$lat},{$lon});
  way["shop"="barber"](around:{$radius}000,{$lat},{$lon});
);
out center;
EOQ;

                $response = Http::timeout(10)->post('https://overpass-api.de/api/interpreter', [
                    'data' => $query,
                ]);

                if ($response->failed()) {
                    return null;
                }

                $xml = simplexml_load_string($response->body());
                $barbers = [];

                foreach ($xml->node as $node) {
                    $barber = [];
                    foreach ($node->tag as $tag) {
                        $key = (string) $tag['k'];
                        $value = (string) $tag['v'];
                        $barber[$key] = $value;
                    }
                    $barber['lat'] = (float) $node['lat'];
                    $barber['lon'] = (float) $node['lon'];
                    $barbers[] = $barber;
                }

                return array_slice($barbers, 0, 5);

            } catch (\Exception $e) {
                Log::warning('Overpass API Error: '.$e->getMessage());

                return null;
            }
        });
    }

    /**
     * Genera URL de Google Maps para una dirección dada (sin llamada HTTP,
     * solo construye el link de busqueda).
     */
    public function getGoogleMapsUrl(string $address): string
    {
        return 'https://www.google.com/maps/search/'.urlencode($address);
    }

    /**
     * Arma un link de direcciones (routing) entre dos coordenadas en
     * OpenStreetMap. No hace llamada HTTP, solo construye la URL.
     */
    public function getDirections(float $fromLat, float $fromLon, float $toLat, float $toLon): ?string
    {
        return "https://www.openstreetmap.org/directions?engine=osrm_car&route={$fromLat}%2C{$fromLon}%3B{$toLat}%2C{$toLon}";
    }

    /**
     * Responde preguntas sobre ubicación del negocio (dirección, cómo
     * llegar, competencia cercana) por deteccion de palabras clave.
     */
    public function answerLocationQuestion(string $message): ?string
    {
        $message = strtolower($message);

        // NEARBY COMPETITION
        if (str_contains($message, 'cerca') || str_contains($message, 'competencia') || str_contains($message, 'otros barberos')) {
            // Para esto necesitaríamos coordenadas reales del negocio
            return 'Para encontrar barbershops cercanas, ingresa tu ubicación o consulta con nuestro equipo.';
        }

        // DIRECTIONS
        if (str_contains($message, 'cómo llego') || str_contains($message, 'ruta')) {
            // NOTA: direccion hardcodeada, no viene de BarbershopSetting.
            $mapsUrl = $this->getGoogleMapsUrl('Av. Reforma 123, CDMX');

            return "CÓMO LLEGAR:\nAv. Reforma 123, CDMX\nVer en Google Maps: {$mapsUrl}";
        }

        // LOCATION DETAILS
        if (str_contains($message, 'dónde estamos') || str_contains($message, 'ubicación')) {
            // NOTA: misma direccion hardcodeada que arriba.
            $location = $this->getLocationInfo('Av. Reforma 123, CDMX');

            if ($location) {
                $mapsUrl = $this->getGoogleMapsUrl($location['address']);

                return "UBICACIÓN EXACTA:\n{$location['address']}\nVer en mapa: {$mapsUrl}";
            }
        }

        return null;
    }

    /**
     * Responde preguntas sobre estilos de corte y tendencias, buscando
     * coincidencia de estilo o palabra de tendencia en el mensaje.
     */
    public function answerStyleQuestion(string $message): ?string
    {
        $message = strtolower($message);

        // STYLE QUERIES
        $styles = ['fade', 'undercut', 'pompadour', 'quiff', 'crew cut', 'buzz cut', 'slick back'];

        foreach ($styles as $style) {
            if (str_contains($message, $style)) {
                $info = $this->getHairstyleInfo($style);
                if ($info) {
                    return "{$info['style']}:\n{$info['description']}\n\n¿Te interesa este look?";
                }
            }
        }

        // TRENDS
        if (str_contains($message, 'tendencia') || str_contains($message, 'moda barbería') || str_contains($message, 'qué está en trend')) {
            $trends = $this->getBarberTrends();
            if ($trends) {
                return $trends;
            }
        }

        return null;
    }

    /**
     * Responde preguntas técnicas sobre barbería (clipper, tijera, etc.)
     * mapeando la palabra clave a un articulo especifico de Wikipedia.
     */
    public function answerTechniqueQuestion(string $message): ?string
    {
        $message = strtolower($message);
        $techniques = [
            'fade' => 'Desvanecimiento_(barbería)',
            'línea' => 'Barbería_moderna',
            'técnica' => 'Barbería',
            'clipper' => 'Máquina_cortadora',
            'tijera' => 'Tijera',
        ];

        foreach ($techniques as $keyword => $query) {
            if (str_contains($message, $keyword)) {
                $info = $this->getWikipediaInfo($query);
                if ($info) {
                    return "TÉCNICA: {$keyword}\n{$info}";
                }
            }
        }

        return null;
    }

    /**
     * Compila todos los datos externos relevantes (ubicacion, estilo,
     * tecnica, barberias cercanas) en un solo array de contexto para
     * pasarle al chatbot. Puede disparar varias llamadas HTTP cacheadas.
     */
    public function getEnhancedContext(string $message, ?array $userLocation = null): array
    {
        $context = [
            'location_info' => null,
            'style_info' => null,
            'trend_info' => null,
            'technique_info' => null,
            'nearby_barbers' => null,
        ];

        // Intentar get location info
        $context['location_info'] = $this->answerLocationQuestion($message);

        // Intentar get style info
        $context['style_info'] = $this->answerStyleQuestion($message);

        // Intentar get technique info
        $context['technique_info'] = $this->answerTechniqueQuestion($message);

        // Si tenemos ubicación del usuario, buscar cercanos
        if ($userLocation && isset($userLocation['lat']) && isset($userLocation['lon'])) {
            $context['nearby_barbers'] = $this->getNearbyBarbers(
                $userLocation['lat'],
                $userLocation['lon']
            );
        }

        return $context;
    }

    /**
     * Intenta responder directamente con datos externos (ubicacion, estilo
     * o tecnica), en ese orden de prioridad; null si ninguno aplica.
     */
    public function getExternalResponse(string $message): ?string
    {
        // Location questions
        $locationResponse = $this->answerLocationQuestion($message);
        if ($locationResponse) {
            return $locationResponse;
        }

        // Style questions
        $styleResponse = $this->answerStyleQuestion($message);
        if ($styleResponse) {
            return $styleResponse;
        }

        // Technique questions
        $techniqueResponse = $this->answerTechniqueQuestion($message);
        if ($techniqueResponse) {
            return $techniqueResponse;
        }

        return null;
    }
}
