<?php

namespace App\Services\Chatbot;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class ChatbotExternalDataService
{
    /**
     * Obtiene información de Wikipedia sobre barbería, estilos, técnicas
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
     * Busca información sobre estilos de corte en Wikipedia
     */
    public function getHairstyleInfo(string $style): ?array
    {
        $queries = [
            'Corte fade' => 'Desvanecimiento_(barbería)',
            'Undercut' => 'Undercut',
            'Pompadour' => 'Pompadour',
            'Quiff' => 'Quiff',
            'Crew cut' => 'Corte_militar',
            'Buzz cut' => 'Afeitado_al_ras',
            'Slick back' => 'Peinado',
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
     * Obtiene información de tendencias de barbería
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
     * Obtiene coordenadas y detalles usando Nominatim (OpenStreetMap)
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
     * Busca barbershops cercanas (requiere ubicación)
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
     * Genera URL de Google Maps
     */
    public function getGoogleMapsUrl(string $address): string
    {
        return 'https://www.google.com/maps/search/'.urlencode($address);
    }

    /**
     * Obtiene direcciones entre dos puntos
     */
    public function getDirections(float $fromLat, float $fromLon, float $toLat, float $toLon): ?string
    {
        return "https://www.openstreetmap.org/directions?engine=osrm_car&route={$fromLat}%2C{$fromLon}%3B{$toLat}%2C{$toLon}";
    }

    /**
     * Responde preguntas sobre ubicación
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
            $mapsUrl = $this->getGoogleMapsUrl('Av. Reforma 123, CDMX');

            return "CÓMO LLEGAR:\nAv. Reforma 123, CDMX\nVer en Google Maps: {$mapsUrl}";
        }

        // LOCATION DETAILS
        if (str_contains($message, 'dónde estamos') || str_contains($message, 'ubicación')) {
            $location = $this->getLocationInfo('Av. Reforma 123, CDMX');

            if ($location) {
                $mapsUrl = $this->getGoogleMapsUrl($location['address']);

                return "UBICACIÓN EXACTA:\n{$location['address']}\nVer en mapa: {$mapsUrl}";
            }
        }

        return null;
    }

    /**
     * Responde preguntas sobre estilos y tendencias
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
     * Responde preguntas técnicas sobre barbería
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
     * Compilador de datos externos para contexto aumentado
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
     * Contextual response usando datos externos
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
