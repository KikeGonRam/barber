{{--
    smart-image.blade.php
    Componente reutilizable que devuelve la URL de Unsplash más relevante
    según el nombre de un servicio o producto de barbería.

    Props:
        $name      → nombre del servicio/producto
        $category  → categoria (opcional)
        $type      → 'service' | 'product'  (default: 'product')
        $size      → 'sm'(100px) | 'md'(400px) | 'lg'(800px)  (default: 'md')
--}}
@php
use Illuminate\Support\Str;

$type     = $type     ?? 'product';
$size     = $size     ?? 'md';
$name     = $name     ?? '';
$category = $category ?? '';
$n        = Str::lower($name . ' ' . $category);

$dims = match ($size) {
    'sm'  => 'w=120&h=120',
    'lg'  => 'w=900&h=600',
    default => 'w=500&h=400',
};

// ──────────────────────────────────────────────────────────────────────────────
//  SERVICIOS — mapeo por nombre exacto o keyword
// ──────────────────────────────────────────────────────────────────────────────
$serviceMap = [
    // Taper Fade / Skin Fade / Alta / Mid / Low
    ['kw' => ['taper', 'skin fade', 'skin-fade', 'alta fad', 'mid fad', 'low fad'],
     'id' => 'photo-1599351431202-1e0f0137899a'],

    // Degradado clásico / regular
    ['kw' => ['degradado', 'classic fade', 'clásico', 'clasico', 'regular', 'scissor'],
     'id' => 'photo-1503951914875-452162b0f3f1'],

    // Pompadour
    ['kw' => ['pompadour', 'pompa', 'elvis'],
     'id' => 'photo-1534297635766-a262cdcb8ee4'],

    // Undercut
    ['kw' => ['undercut', 'under cut', 'desconectado'],
     'id' => 'photo-1605497787928-40e1c74e4e74'],

    // Mohicano / Mohawk / Cresta
    ['kw' => ['mohicano', 'mohawk', 'cresta'],
     'id' => 'photo-1622286342621-4bd786c2447c'],

    // Corte infantil / niño / kids
    ['kw' => ['niño', 'infantil', 'kids', 'junior', 'children'],
     'id' => 'photo-1605497788044-5a32c7078486'],

    // Barba completa / diseño de barba
    ['kw' => ['barba completa', 'diseño de barba', 'perfilado', 'perfil barba', 'beard design', 'beard style'],
     'id' => 'photo-1621605815971-fbc98d665033'],

    // Afeitado clásico con navaja / straight razor shave
    ['kw' => ['afeitado', 'afeitad', 'shave', 'rasurado', 'navaja', 'straight'],
     'id' => 'photo-1583863788434-e58a36330cf0'],

    // Arreglo de barba / Recorte
    ['kw' => ['arreglo', 'recorte', 'beard trim', 'bigote', 'mustache'],
     'id' => 'photo-1617450365226-9bf28c04e130'],

    // Combo / Full service / Paquete completo
    ['kw' => ['combo', 'completo', 'full', 'pack', 'paquete', 'todo incluido'],
     'id' => 'photo-1585747860715-2ba37e788b70'],

    // Tratamiento capilar / Keratina
    ['kw' => ['keratina', 'tratamiento', 'capilar', 'hidrat', 'nutrici', 'spa'],
     'id' => 'photo-1519823551278-64ac92734fb1'],

    // Color / Tinte
    ['kw' => ['color', 'tinte', 'tintura', 'rubio', 'decoloración'],
     'id' => 'photo-1552642762-f55d06580641'],

    // Cejas
    ['kw' => ['ceja', 'cejas', 'eyebrow'],
     'id' => 'photo-1570172619644-dfd03ed5d881'],

    // Corte (genérico)
    ['kw' => ['corte', 'haircut', 'pelo', 'cabello', 'hair'],
     'id' => 'photo-1622286342621-4bd786c2447c'],

    // Barba (genérico)
    ['kw' => ['barba', 'beard'],
     'id' => 'photo-1580618672591-eb180b1a973f'],
];

// ──────────────────────────────────────────────────────────────────────────────
//  PRODUCTOS — mapeo por nombre/categoría
// ──────────────────────────────────────────────────────────────────────────────
$productMap = [
    // Cera / Pomada / Fijador / Wax — PRODUCTO ESTRELLA
    ['kw' => ['cera', 'wax', 'pomada', 'fijador', 'fibre', 'clay', 'arcilla', 'pasta'],
     'id' => 'photo-1626806819282-2c1dc01a5e0c'],

    // Gel
    ['kw' => ['gel'],
     'id' => 'photo-1571781418606-70265b9cce90'],

    // Spray fijador / laca / spray
    ['kw' => ['spray', 'laca', 'brillo', 'gloss'],
     'id' => 'photo-1571781418606-70265b9cce90'],

    // Shampoo / Champú
    ['kw' => ['shampoo', 'champu', 'champú', 'jabón', 'jabon', 'limpiador'],
     'id' => 'photo-1526947425960-945c6e72858f'],

    // Acondicionador / Bálsamo (cabello)
    ['kw' => ['acondicionador', 'conditioner', 'balsamo cabello', 'bálsamo de cabello', 'mascarilla capilar'],
     'id' => 'photo-1604914237800-1c9102c219da'],

    // Aceite de barba / Beard oil
    ['kw' => ['aceite de barba', 'beard oil', 'aceite barba', 'oil barba'],
     'id' => 'photo-1626808642875-0aa545482efb'],

    // Bálsamo de barba / Beard balm
    ['kw' => ['balsamo de barba', 'bálsamo de barba', 'beard balm', 'balm'],
     'id' => 'photo-1583863788434-e58a36330cf0'],

    // Aftershave / Loción
    ['kw' => ['aftershave', 'after shave', 'after-shave', 'locion afeitado', 'loción'],
     'id' => 'photo-1581583013345-e2e9b1cfcd90'],

    // Crema / Espuma de afeitar
    ['kw' => ['crema afeitar', 'espuma', 'foam', 'shaving cream', 'crema de afeitado'],
     'id' => 'photo-1617450365226-9bf28c04e130'],

    // Aceite (genérico)
    ['kw' => ['aceite', 'oil', 'serum', 'sérum'],
     'id' => 'photo-1626808642875-0aa545482efb'],

    // Tónico capilar
    ['kw' => ['tonico', 'tónico', 'toner', 'reconst'],
     'id' => 'photo-1571781418606-70265b9cce90'],

    // Tijeras de corte
    ['kw' => ['tijera', 'tijeras', 'scissors', 'cizalla'],
     'id' => 'photo-1598440947619-2c35fc9aa908'],

    // Maquinilla / Clipper / Trimmer
    ['kw' => ['maquina', 'máquina', 'clipper', 'cortadora', 'trimmer', 'afeitadora', 'wahl', 'andis', 'oster'],
     'id' => 'photo-1596495578065-6e0763fa1178'],

    // Navaja / Rastrillo
    ['kw' => ['navaja', 'razor', 'rastrillo', 'maquinilla', 'gillette', 'hoja'],
     'id' => 'photo-1542393545-10f5b85e14fc'],

    // Brocha de afeitar
    ['kw' => ['brocha', 'brush', 'pincél', 'shaving brush'],
     'id' => 'photo-1512496015851-a90fb38ba796'],

    // Peine / Cepillo
    ['kw' => ['peine', 'cepillo', 'comb', 'boar', 'paleta', 'paddle'],
     'id' => 'photo-1512496015851-a90fb38ba796'],

    // Toalla / Cape / Mandil
    ['kw' => ['toalla', 'capa', 'cape', 'mandil', 'bata'],
     'id' => 'photo-1585747860715-2ba37e788b70'],

    // Crema (genérico)
    ['kw' => ['crema', 'cream', 'bálsamo', 'balsamo', 'ungüento'],
     'id' => 'photo-1626806819282-2c1dc01a5e0c'],
];

// ──────────────────────────────────────────────────────────────────────────────
//  Resolver
// ──────────────────────────────────────────────────────────────────────────────
$resolvedId = null;

$map = $type === 'service' ? $serviceMap : $productMap;
foreach ($map as $entry) {
    foreach ($entry['kw'] as $kw) {
        if (Str::contains($n, $kw)) {
            $resolvedId = $entry['id'];
            break 2;
        }
    }
}

// Fallback por tipo
if (!$resolvedId) {
    $resolvedId = $type === 'service'
        ? 'photo-1585747860715-2ba37e788b70'
        : 'photo-1626806819282-2c1dc01a5e0c';
}

$smartUrl = "https://images.unsplash.com/{$resolvedId}?{$dims}&auto=format&fit=crop&q=85";
$fallback  = "https://images.unsplash.com/photo-1585747860715-2ba37e788b70?{$dims}&auto=format&fit=crop&q=80";
@endphp

{{-- El componente sólo expone la variable $smartUrl y $fallback al scope padre --}}
