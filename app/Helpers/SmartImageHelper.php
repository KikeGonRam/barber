<?php

namespace App\Helpers;

use Illuminate\Support\Str;

/**
 * Resuelve una imagen "adivinada" de Unsplash para servicios y productos que
 * no tienen foto propia cargada, buscando palabras clave en el nombre (y
 * categoría, para productos) contra un mapa de coincidencias ordenado del
 * término más específico al más genérico. Se usa en las vistas de citas del
 * cliente, catálogo de servicios/productos e inventario (ver
 * resources/views/client/appointments/create.blade.php,
 * resources/views/inventory/products/index.blade.php,
 * resources/views/services/index.blade.php y public-index.blade.php) como
 * fallback cuando el modelo no trae una imagen real.
 */
class SmartImageHelper
{
    private static array $serviceMap = [
        // Taper Fade / Skin Fade
        ['kw' => ['taper', 'skin fade', 'skin-fade', 'alta fad', 'mid fad', 'low fad'],
            'id' => 'photo-1599351431202-1e0f0137899a'],
        // Degradado clásico
        ['kw' => ['degradado', 'classic', 'clásico', 'clasico', 'regular', 'scissor'],
            'id' => 'photo-1503951914875-452162b0f3f1'],
        // Pompadour
        ['kw' => ['pompadour', 'pompa'],
            'id' => 'photo-1534297635766-a262cdcb8ee4'],
        // Undercut / Desconectado
        ['kw' => ['undercut', 'under cut', 'desconectado'],
            'id' => 'photo-1605497787928-40e1c74e4e74'],
        // Mohicano / Mohawk
        ['kw' => ['mohicano', 'mohawk', 'cresta'],
            'id' => 'photo-1622286342621-4bd786c2447c'],
        // Corte infantil / Niños
        ['kw' => ['niño', 'infantil', 'kids', 'junior', 'children'],
            'id' => 'photo-1605497788044-5a32c7078486'],
        // Afeitado con navaja
        ['kw' => ['afeitado', 'shave', 'rasurado', 'navaja', 'straight'],
            'id' => 'photo-1583863788434-e58a36330cf0'],
        // Diseño / Arreglo de barba
        ['kw' => ['diseño de barba', 'perfilado', 'beard design', 'beard style'],
            'id' => 'photo-1621605815971-fbc98d665033'],
        // Arreglo / Recorte de barba
        ['kw' => ['arreglo', 'recorte', 'beard trim', 'bigote', 'mustache'],
            'id' => 'photo-1617450365226-9bf28c04e130'],
        // Combo / Paquete completo
        ['kw' => ['combo', 'completo', 'full', 'pack', 'paquete', 'todo incluido'],
            'id' => 'photo-1585747860715-2ba37e788b70'],
        // Tratamiento capilar / Keratina
        ['kw' => ['keratina', 'tratamiento', 'capilar', 'hidrat', 'nutrici', 'spa'],
            'id' => 'photo-1519823551278-64ac92734fb1'],
        // Tinte / Color
        ['kw' => ['color', 'tinte', 'tintura', 'rubio', 'decoloración', 'mechas'],
            'id' => 'photo-1552642762-f55d06580641'],
        // Cejas
        ['kw' => ['ceja', 'cejas', 'eyebrow'],
            'id' => 'photo-1570172619644-dfd03ed5d881'],
        // Barba (genérico)
        ['kw' => ['barba', 'beard'],
            'id' => 'photo-1580618672591-eb180b1a973f'],
        // Corte (genérico)
        ['kw' => ['corte', 'haircut', 'pelo', 'cabello', 'hair'],
            'id' => 'photo-1622286342621-4bd786c2447c'],
    ];

    private static array $productMap = [
        // Cera / Pomada / Wax — el más específico primero
        ['kw' => ['cera', 'wax', 'pomada', 'fijador', 'fibre', 'clay', 'arcilla', 'pasta cabello'],
            'id' => 'photo-1626806819282-2c1dc01a5e0c'],
        // Gel
        ['kw' => ['gel'],
            'id' => 'photo-1571781418606-70265b9cce90'],
        // Shampoo / Champú
        ['kw' => ['shampoo', 'champu', 'champú', 'jabón cabello', 'jabon cabello'],
            'id' => 'photo-1526947425960-945c6e72858f'],
        // Acondicionador / Mascarilla capilar
        ['kw' => ['acondicionador', 'conditioner', 'mascarilla capilar', 'balsamo cabello'],
            'id' => 'photo-1604914237800-1c9102c219da'],
        // Aceite de barba
        ['kw' => ['aceite de barba', 'beard oil', 'oil barba', 'aceite barba'],
            'id' => 'photo-1626808642875-0aa545482efb'],
        // Bálsamo de barba
        ['kw' => ['balsamo de barba', 'bálsamo de barba', 'beard balm'],
            'id' => 'photo-1583863788434-e58a36330cf0'],
        // Aftershave / Loción post afeitado
        ['kw' => ['aftershave', 'after shave', 'after-shave', 'locion afeitado', 'post afeitado'],
            'id' => 'photo-1581583013345-e2e9b1cfcd90'],
        // Crema / Espuma de afeitar
        ['kw' => ['crema afeitar', 'espuma', 'foam', 'shaving cream'],
            'id' => 'photo-1617450365226-9bf28c04e130'],
        // Aceite (genérico)
        ['kw' => ['aceite', 'oil', 'serum', 'sérum'],
            'id' => 'photo-1626808642875-0aa545482efb'],
        // Tónico capilar
        ['kw' => ['tonico', 'tónico', 'toner', 'spray', 'laca'],
            'id' => 'photo-1571781418606-70265b9cce90'],
        // Tijeras
        ['kw' => ['tijera', 'scissors', 'cizalla'],
            'id' => 'photo-1598440947619-2c35fc9aa908'],
        // Maquinilla / Clipper / Trimmer
        ['kw' => ['maquina', 'máquina', 'clipper', 'cortadora', 'trimmer', 'wahl', 'andis', 'oster', 'babyliss', 'remington'],
            'id' => 'photo-1596495578065-6e0763fa1178'],
        // Navaja / Rastrillo
        ['kw' => ['navaja', 'razor', 'rastrillo', 'hoja', 'gillette', 'mach'],
            'id' => 'photo-1542393545-10f5b85e14fc'],
        // Brocha / Cepillo / Peine
        ['kw' => ['brocha', 'peine', 'cepillo', 'brush', 'comb', 'paddle'],
            'id' => 'photo-1512496015851-a90fb38ba796'],
        // Crema (genérico) / Bálsamo
        ['kw' => ['crema', 'cream', 'bálsamo', 'balsamo'],
            'id' => 'photo-1626806819282-2c1dc01a5e0c'],
        // Jabón
        ['kw' => ['jabón', 'jabon', 'soap', 'gel de baño'],
            'id' => 'photo-1526947425960-945c6e72858f'],
    ];

    /**
     * Devuelve la URL de imagen sugerida para un servicio, según su nombre.
     */
    public static function forService(string $nombre, string $size = 'md'): string
    {
        return self::resolve($nombre, '', self::$serviceMap, $size);
    }

    /**
     * Devuelve la URL de imagen sugerida para un producto, combinando
     * nombre y categoría para mejorar la coincidencia de palabras clave.
     */
    public static function forProduct(string $nombre, string $categoria = '', string $size = 'md'): string
    {
        return self::resolve($nombre, $categoria, self::$productMap, $size);
    }

    /**
     * Busca la primera palabra clave del mapa que aparezca en "nombre +
     * extra" y arma la URL de Unsplash con las dimensiones pedidas. El
     * orden del mapa importa: las entradas más específicas van primero
     * para que no las "tape" una coincidencia genérica (p.ej. "aceite de
     * barba" debe matchear antes que el "aceite" genérico).
     */
    private static function resolve(string $nombre, string $extra, array $map, string $size): string
    {
        $dims = match ($size) {
            'sm' => 'w=120&h=120',
            'lg' => 'w=900&h=600',
            default => 'w=500&h=400',
        };

        $n = Str::lower($nombre.' '.$extra);
        foreach ($map as $entry) {
            foreach ($entry['kw'] as $kw) {
                if (Str::contains($n, $kw)) {
                    return "https://images.unsplash.com/{$entry['id']}?{$dims}&auto=format&fit=crop&q=85";
                }
            }
        }

        // Ninguna palabra clave coincidió: imagen genérica de "combo/paquete"
        // como último recurso para no dejar el <img> roto.
        return "https://images.unsplash.com/photo-1585747860715-2ba37e788b70?{$dims}&auto=format&fit=crop&q=80";
    }
}
