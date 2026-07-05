<?php

namespace Database\Seeders;

use App\Models\Product;
use Illuminate\Database\Seeder;

/**
 * Catálogo real de productos UrbanBlade — 30 SKUs.
 *   • 15 venta_cliente  → se venden al público en mostrador
 *   • 15 insumo_trabajo → se consumen en los servicios (no se venden directamente)
 *
 * Precios en MXN. Imágenes de Unsplash, seleccionadas por producto.
 */
class ProductSeeder extends Seeder
{
    private const Q = '?auto=format&fit=crop&w=800&q=80';
    private const U = 'https://images.unsplash.com/photo-';

    // Foto por categoría/tipo de producto — cada constante corresponde al tipo exacto
    private const IMG = [
        // Pomadas / ceras / geles — tarro de producto de styling
        'pomada_mate'       => '1598440947619-2e2c9fef6c30',
        'pomada_brillante'  => '1585747860715-2ba37e788b70',
        'cera_fibra'        => '1597854736861-1b8cfcd59ede',
        'gel_fijador'       => '1597854736861-1b8cfcd59ede',

        // Shampoos / acondicionadores — frascos de cuidado capilar
        'shampoo'           => '1526947425960-945c6e72858f',
        'acondicionador'    => '1631729371254-42c2892f0e6e',

        // Productos para barba — goteros / tarros de aceite/bálsamo
        'aceite_barba'      => '1516975080664-ed2fc6a32937',
        'balsamo_barba'     => '1558618666-fcd25c85cd64',
        'serum_barba'       => '1587813369290-091c9d432daf',
        'kit_barba'         => '1624454558948-e46dad9888e7',

        // Spray de styling / sal marina — frasco spray sobre fondo neutro
        'spray_sal'         => '1544717297-fa95b6ee9643',
        'spray_fijador'     => '1612817288484-6f916006741a',
        'crema_peinar'      => '1589421333958-3d4e79f0d4d2',

        // Fragancias / colonias — botella de colonia masculina
        'colonia'           => '1519345182560-3f2917c472ef',
        'aftershave_locion' => '1544975000456-6d3b8ec93a07',

        // Afeitar profesional — navaja de seguridad, brocha, crema
        'navaja_seguridad'  => '1565193566173-7a0ee3dbe261',
        'crema_afeitar'     => '1612817288484-6f916006741a',
        'espuma_afeitar'    => '1585747860715-2ba37e788b70',
        'hojas_afeitar'     => '1587813369290-091c9d432daf',
        'aceite_preafeitar' => '1516975080664-ed2fc6a32937',

        // Coloración capilar — tubos de tinte y oxidante
        'tinte_cabello'     => '1522337360788-8b13dee7a37e',
        'oxidante'          => '1613171155029-a668bbc65556',
        'decolorante'       => '1592654655-3e50f51e70ff',

        // Tratamientos capilares — frascos de keratina / mascarilla
        'keratina'          => '1605497788044-5a32c7078486',
        'mascarilla'        => '1631729371254-42c2892f0e6e',

        // Higiene y desinfección — barbicide azul, spray, guantes, toallas
        'barbicide'         => '1600857544200-b2f468e78ec7',
        'spray_desinfect'   => '1589421333958-3d4e79f0d4d2',
        'toallas'           => '1584622650111-993a426fbf0a',
        'guantes'           => '1584308666744-06a9e1bd9efd',
    ];

    public function run(): void
    {
        $products = [

            // ══════════════════════════════════════════════════════════════
            //  VENTA AL CLIENTE — productos que el cliente compra en mostrador
            // ══════════════════════════════════════════════════════════════

            // ── Pomadas y Ceras ──────────────────────────────────────────
            [
                'nombre'       => 'Pomada Mate Clay',
                'categoria'    => 'pomadas y ceras',
                'tipo'         => 'venta_cliente',
                'descripcion'  => 'Pomada de arcilla con fijación fuerte y acabado opaco/mate. Ideal para looks texturizados, quiffs y peinados con volumen. Base de agua, fácil de lavar. Sin brillo, define perfectamente cada hebra sin rigidez. 100g.',
                'precio_compra'=> 90.00,
                'precio_venta' => 185.00,
                'stock_actual' => 24,
                'stock_minimo' => 6,
                'imagen'       => self::U . self::IMG['pomada_mate']      . self::Q,
            ],
            [
                'nombre'       => 'Pomada Brillante Water-Based',
                'categoria'    => 'pomadas y ceras',
                'tipo'         => 'venta_cliente',
                'descripcion'  => 'Pomada base agua de fijación media-alta con acabado muy brillante estilo años 50. Perfecta para pompadours y side-parts clásicos. Se peina con facilidad, se lava al primer enjuague. Libre de alcohol. 100g.',
                'precio_compra'=> 78.00,
                'precio_venta' => 165.00,
                'stock_actual' => 20,
                'stock_minimo' => 5,
                'imagen'       => self::U . self::IMG['pomada_brillante'] . self::Q,
            ],
            [
                'nombre'       => 'Cera de Fibra Fuerte',
                'categoria'    => 'pomadas y ceras',
                'tipo'         => 'venta_cliente',
                'descripcion'  => 'Cera de fibra con fijación extra-fuerte y acabado semimate. Crea textura, definición y movimiento en cortes cortos o medios. Ideal para crop tops, texturizados y faux hawks. No pesa el cabello. 85g.',
                'precio_compra'=> 100.00,
                'precio_venta' => 200.00,
                'stock_actual' => 18,
                'stock_minimo' => 5,
                'imagen'       => self::U . self::IMG['cera_fibra']       . self::Q,
            ],
            [
                'nombre'       => 'Gel Ultra Fuerte 24h',
                'categoria'    => 'pomadas y ceras',
                'tipo'         => 'venta_cliente',
                'descripcion'  => 'Gel de fijación máxima con control total durante 24 horas. Acabado cristalino sin residuo blanco. Para peinados formales, looks mojados o estilos que requieren fijación absoluta. Con vitamina E y pantenol. 250ml.',
                'precio_compra'=> 48.00,
                'precio_venta' => 115.00,
                'stock_actual' => 32,
                'stock_minimo' => 8,
                'imagen'       => self::U . self::IMG['gel_fijador']      . self::Q,
            ],

            // ── Shampoos y Acondicionadores ──────────────────────────────
            [
                'nombre'       => 'Shampoo Carbón Activo 250ml',
                'categoria'    => 'shampoos y acondicionadores',
                'tipo'         => 'venta_cliente',
                'descripcion'  => 'Shampoo purificante con carbón activado que elimina impurezas, exceso de sebo y residuo de productos de styling. Limpieza profunda sin resecar. Fórmula sin sulfatos ni parabenos. Con aceite de argán. Para uso diario o post-uso de pomada.',
                'precio_compra'=> 92.00,
                'precio_venta' => 210.00,
                'stock_actual' => 18,
                'stock_minimo' => 4,
                'imagen'       => self::U . self::IMG['shampoo']          . self::Q,
            ],
            [
                'nombre'       => 'Shampoo Cafeína + Biotina 300ml',
                'categoria'    => 'shampoos y acondicionadores',
                'tipo'         => 'venta_cliente',
                'descripcion'  => 'Shampoo fortalecedor con cafeína estimulante del cuero cabelludo y biotina para prevenir la caída del cabello. Activa la circulación folicular, aumenta el volumen y densidad. Sin parabenos. Apto para uso diario. Especial para cabello fino o con tendencia a caída.',
                'precio_compra'=> 87.00,
                'precio_venta' => 195.00,
                'stock_actual' => 22,
                'stock_minimo' => 5,
                'imagen'       => self::U . self::IMG['shampoo']          . self::Q,
            ],
            [
                'nombre'       => 'Acondicionador de Karité Profundo 300ml',
                'categoria'    => 'shampoos y acondicionadores',
                'tipo'         => 'venta_cliente',
                'descripcion'  => 'Acondicionador nutritivo con mantequilla de karité y proteína de seda. Hidratación profunda, desencrespado y suavidad desde la primera aplicación. Ideal para cabello seco, maltratado o con frizz. Se deja actuar 3 minutos. Sin silicones pesados.',
                'precio_compra'=> 82.00,
                'precio_venta' => 180.00,
                'stock_actual' => 16,
                'stock_minimo' => 4,
                'imagen'       => self::U . self::IMG['acondicionador']   . self::Q,
            ],

            // ── Barba ────────────────────────────────────────────────────
            [
                'nombre'       => 'Aceite de Barba Premium 30ml',
                'categoria'    => 'cuidado de barba',
                'tipo'         => 'venta_cliente',
                'descripcion'  => 'Aceite 100% natural con base de jojoba, argán y aceite de semilla de uva. Hidrata el vello y la piel subyacente, elimina la picazón, da brillo natural y aroma sutil a madera y cuero. Absorción rápida. No grasoso. Incluye pipeta dosificadora.',
                'precio_compra'=> 130.00,
                'precio_venta' => 280.00,
                'stock_actual' => 20,
                'stock_minimo' => 5,
                'imagen'       => self::U . self::IMG['aceite_barba']     . self::Q,
            ],
            [
                'nombre'       => 'Bálsamo Hidratante de Barba 60g',
                'categoria'    => 'cuidado de barba',
                'tipo'         => 'venta_cliente',
                'descripcion'  => 'Bálsamo en crema con manteca de cacao, cera de abeja y aceite de almendra dulce. Acondiciona y domina la barba rizada o desordenada, deja el vello suave y manejable. Fijación ligera para moldear sin rigidez. Fragancia amaderada sutil.',
                'precio_compra'=> 115.00,
                'precio_venta' => 255.00,
                'stock_actual' => 18,
                'stock_minimo' => 4,
                'imagen'       => self::U . self::IMG['balsamo_barba']    . self::Q,
            ],
            [
                'nombre'       => 'Sérum Crecimiento de Barba 50ml',
                'categoria'    => 'cuidado de barba',
                'tipo'         => 'venta_cliente',
                'descripcion'  => 'Sérum estimulante para zonas con barba escasa o crecimiento irregular. Con minoxidil al 3%, biotina y extracto de romero que activan los folículos dormidos. Aplicar 2 veces al día en la zona limpia y seca. Resultados visibles en 4-8 semanas.',
                'precio_compra'=> 155.00,
                'precio_venta' => 330.00,
                'stock_actual' => 12,
                'stock_minimo' => 3,
                'imagen'       => self::U . self::IMG['serum_barba']      . self::Q,
            ],
            [
                'nombre'       => 'Kit Completo de Barba',
                'categoria'    => 'cuidado de barba',
                'tipo'         => 'venta_cliente',
                'descripcion'  => 'Set completo para el cuidado diario de la barba: Aceite Premium 30ml + Bálsamo 60g + Peine de madera + Cepillo de cerdas naturales. Todo en estuche de cuero sintético. El regalo perfecto y el kit ideal para el hombre que cuida su imagen. Ahorro de $120 vs piezas sueltas.',
                'precio_compra'=> 235.00,
                'precio_venta' => 490.00,
                'stock_actual' => 8,
                'stock_minimo' => 2,
                'imagen'       => self::U . self::IMG['kit_barba']        . self::Q,
            ],

            // ── Styling ──────────────────────────────────────────────────
            [
                'nombre'       => 'Spray de Sal Marina 200ml',
                'categoria'    => 'styling y acabados',
                'tipo'         => 'venta_cliente',
                'descripcion'  => 'Spray texturizador con sal marina y algas. Crea ondas naturales, volumen y textura estilo playa en cabello seco o húmedo. Acabado mate con movimiento natural. Sin alcohol. Ideal para estilos desenfadados y cortes texturizados. No pesa ni reseca.',
                'precio_compra'=> 65.00,
                'precio_venta' => 158.00,
                'stock_actual' => 25,
                'stock_minimo' => 6,
                'imagen'       => self::U . self::IMG['spray_sal']        . self::Q,
            ],
            [
                'nombre'       => 'Spray Fijador Extra Fuerte 250ml',
                'categoria'    => 'styling y acabados',
                'tipo'         => 'venta_cliente',
                'descripcion'  => 'Laca de fijación extra-fuerte para mantener el peinado en su lugar todo el día. No apelmaza, no deja residuo blanco. Permite retocar el peinado sin agrietarse. Con filtro UV para proteger el color del cabello. Para looks de alta fijación.',
                'precio_compra'=> 72.00,
                'precio_venta' => 168.00,
                'stock_actual' => 22,
                'stock_minimo' => 5,
                'imagen'       => self::U . self::IMG['spray_fijador']    . self::Q,
            ],
            [
                'nombre'       => 'Crema de Peinar Sin Enjuague 150ml',
                'categoria'    => 'styling y acabados',
                'tipo'         => 'venta_cliente',
                'descripcion'  => 'Crema multifuncional sin enjuague para peinar, hidratar y controlar el frizz. Aplica en cabello húmedo o seco. Brillo natural suave, no pesado. Define el rizo o alisa según el método de peinado. Con keratina hidrolizada y aceite de coco.',
                'precio_compra'=> 78.00,
                'precio_venta' => 175.00,
                'stock_actual' => 20,
                'stock_minimo' => 5,
                'imagen'       => self::U . self::IMG['crema_peinar']     . self::Q,
            ],

            // ── Fragancias ───────────────────────────────────────────────
            [
                'nombre'       => 'Colonia Barbería Clásica 100ml',
                'categoria'    => 'fragancias',
                'tipo'         => 'venta_cliente',
                'descripcion'  => 'Colonia masculina con fragancia inspirada en las barberías clásicas: notas de madera de cedro, pachulí, almizcle y un toque de cuero. Larga duración (6-8 horas). Concentración eau de toilette. Presentación en frasco ámbar con tapón dorado. Edición exclusiva UrbanBlade.',
                'precio_compra'=> 182.00,
                'precio_venta' => 395.00,
                'stock_actual' => 10,
                'stock_minimo' => 2,
                'imagen'       => self::U . self::IMG['colonia']          . self::Q,
            ],
            [
                'nombre'       => 'Aftershave Loción Refrescante 100ml',
                'categoria'    => 'fragancias',
                'tipo'         => 'venta_cliente',
                'descripcion'  => 'Loción aftershave refrescante con alcohol, mentol y extracto de aloe vera. Cierra los poros instantáneamente, calma el ardor post-afeitada y deja una fragancia fresca y masculina. Apto para piel sensible. Con efecto antiséptico leve. Aroma cítrico con fondo amaderado.',
                'precio_compra'=> 95.00,
                'precio_venta' => 225.00,
                'stock_actual' => 15,
                'stock_minimo' => 4,
                'imagen'       => self::U . self::IMG['aftershave_locion']. self::Q,
            ],

            // ══════════════════════════════════════════════════════════════
            //  INSUMOS DE TRABAJO — materiales consumidos en los servicios
            // ══════════════════════════════════════════════════════════════

            // ── Afeitar ──────────────────────────────────────────────────
            [
                'nombre'       => 'Crema de Afeitar Profesional 500ml',
                'categoria'    => 'afeitar',
                'tipo'         => 'insumo_trabajo',
                'descripcion'  => 'Crema de afeitar concentrada para uso profesional en barbería. Fórmula espumante de alta densidad que suaviza el vello en segundos. Con glicerina y aceite de oliva para proteger la piel durante el deslizamiento de la navaja. Rinde para 200+ afeitadas.',
                'precio_compra'=> 185.00,
                'precio_venta' => 0.00,
                'stock_actual' => 8,
                'stock_minimo' => 2,
                'imagen'       => self::U . self::IMG['crema_afeitar']    . self::Q,
            ],
            [
                'nombre'       => 'Espuma de Afeitar Piel Sensible 400ml',
                'categoria'    => 'afeitar',
                'tipo'         => 'insumo_trabajo',
                'descripcion'  => 'Espuma de afeitar en aerosol formulada para pieles sensibles y reactivas. Con manzanilla, aloe vera y extracto de avena. Reduce el enrojecimiento y la irritación post-afeitada. Sin alcohol, sin mentol agresivo. Ideal para clientes con piel reactiva o cuperosis.',
                'precio_compra'=> 68.00,
                'precio_venta' => 0.00,
                'stock_actual' => 12,
                'stock_minimo' => 3,
                'imagen'       => self::U . self::IMG['espuma_afeitar']   . self::Q,
            ],
            [
                'nombre'       => 'Aceite Pre-Afeitar 100ml',
                'categoria'    => 'afeitar',
                'tipo'         => 'insumo_trabajo',
                'descripcion'  => 'Aceite pre-afeitar que se aplica antes de la crema para suavizar el vello, levantar cada pelo y crear una capa protectora entre la piel y la navaja. Reduce cortes y quemaduras. Con aceites de cáñamo, argán y vitamina E. Inodoro para no interferir con el aftershave.',
                'precio_compra'=> 125.00,
                'precio_venta' => 0.00,
                'stock_actual' => 6,
                'stock_minimo' => 2,
                'imagen'       => self::U . self::IMG['aceite_preafeitar']. self::Q,
            ],
            [
                'nombre'       => 'Hojas de Afeitar Feather (caja 100u)',
                'categoria'    => 'afeitar',
                'tipo'         => 'insumo_trabajo',
                'descripcion'  => 'Hojas de afeitar japonesas Feather, consideradas las más afiladas del mercado. Acero inoxidable de doble filo. Corte limpio y preciso con mínima presión. Cada hoja se usa una sola vez por cliente (protocolo de higiene). Caja de 100 hojas individuales selladas.',
                'precio_compra'=> 320.00,
                'precio_venta' => 0.00,
                'stock_actual' => 5,
                'stock_minimo' => 2,
                'imagen'       => self::U . self::IMG['hojas_afeitar']    . self::Q,
            ],
            [
                'nombre'       => 'Navaja de Afeitar Mango Madera (reemplazable)',
                'categoria'    => 'afeitar',
                'tipo'         => 'insumo_trabajo',
                'descripcion'  => 'Navaja profesional con mango ergonómico de madera oscura y sistema de cuchilla reemplazable (compatible con hojas estándar). Peso perfecto para el control al afeitar. Incluye 10 hojas. La navaja se esteriliza entre clientes; la hoja se reemplaza por cliente. Navaja durable y elegante.',
                'precio_compra'=> 285.00,
                'precio_venta' => 0.00,
                'stock_actual' => 6,
                'stock_minimo' => 2,
                'imagen'       => self::U . self::IMG['navaja_seguridad'] . self::Q,
            ],

            // ── Coloración ───────────────────────────────────────────────
            [
                'nombre'       => 'Tinte Cabello Negro Natural 1.0 (60ml)',
                'categoria'    => 'coloración',
                'tipo'         => 'insumo_trabajo',
                'descripcion'  => 'Tinte permanente color Negro Natural 1.0. Cobertura de canas al 100% desde la primera aplicación. Fórmula con aceite de argán que protege la fibra capilar durante el proceso. Sin amoniaco. Mezclar 1:1 con oxidante 20Vol. Rinde para cabello corto-medio. Sin olor agresivo.',
                'precio_compra'=> 48.00,
                'precio_venta' => 0.00,
                'stock_actual' => 20,
                'stock_minimo' => 5,
                'imagen'       => self::U . self::IMG['tinte_cabello']    . self::Q,
            ],
            [
                'nombre'       => 'Tinte Cabello Castaño Oscuro 3.0 (60ml)',
                'categoria'    => 'coloración',
                'tipo'         => 'insumo_trabajo',
                'descripcion'  => 'Tinte permanente color Castaño Oscuro 3.0. Tono frío con reflejos naturales. Cobertura de canas superior al 95%. Con complejo de keratina y aceite de maracuyá. El más solicitado para hombres maduros que quieren renovar su look discretamente. Mezclar con oxidante 20Vol.',
                'precio_compra'=> 48.00,
                'precio_venta' => 0.00,
                'stock_actual' => 18,
                'stock_minimo' => 4,
                'imagen'       => self::U . self::IMG['tinte_cabello']    . self::Q,
            ],
            [
                'nombre'       => 'Oxidante en Crema 20 Vol (1 Litro)',
                'categoria'    => 'coloración',
                'tipo'         => 'insumo_trabajo',
                'descripcion'  => 'Oxidante en crema al 6% (20 volúmenes) para mezclar con tintes permanentes. Textura cremosa de alta estabilidad, no gotea. Activa el color y asegura la penetración del pigmento en la corteza capilar. Compatible con todas las marcas de tinte. Litro rinde para ~16 aplicaciones.',
                'precio_compra'=> 125.00,
                'precio_venta' => 0.00,
                'stock_actual' => 4,
                'stock_minimo' => 1,
                'imagen'       => self::U . self::IMG['oxidante']         . self::Q,
            ],
            [
                'nombre'       => 'Polvo Decolorante Profesional 500g',
                'categoria'    => 'coloración',
                'tipo'         => 'insumo_trabajo',
                'descripcion'  => 'Polvo decolorante de grado profesional que aclara hasta 7 tonos sin dañar excesivamente la fibra capilar. Fórmula con acondicionadores integrados que minimizan el daño. Bajo olor. Textura cremosa al mezclar. Para mechas, balayage, degradados o decoloración total. Mezclar con oxidante 30Vol.',
                'precio_compra'=> 195.00,
                'precio_venta' => 0.00,
                'stock_actual' => 4,
                'stock_minimo' => 1,
                'imagen'       => self::U . self::IMG['decolorante']      . self::Q,
            ],

            // ── Tratamientos ─────────────────────────────────────────────
            [
                'nombre'       => 'Keratina Líquida Profesional 500ml',
                'categoria'    => 'tratamientos capilares',
                'tipo'         => 'insumo_trabajo',
                'descripcion'  => 'Keratina líquida de alisado térmico para el servicio Keratina Express. Sella la cutícula capilar con calor (secadora/plancha) dejando el cabello liso, brillante y resistente al frizz durante 3-4 semanas. Libre de formaldehído. Con proteínas hidrolizadas y mantequilla de karité.',
                'precio_compra'=> 385.00,
                'precio_venta' => 0.00,
                'stock_actual' => 3,
                'stock_minimo' => 1,
                'imagen'       => self::U . self::IMG['keratina']         . self::Q,
            ],
            [
                'nombre'       => 'Mascarilla Capilar Hidratación Intensiva 500ml',
                'categoria'    => 'tratamientos capilares',
                'tipo'         => 'insumo_trabajo',
                'descripcion'  => 'Mascarilla de tratamiento intensivo con aceite de argán, colágeno y proteína de seda. Restaura la fibra dañada por calor, color o química. Se aplica 10 minutos con calor para máxima penetración. Ideal para los servicios de masaje capilar o hidratación de barba. Resultados inmediatos.',
                'precio_compra'=> 168.00,
                'precio_venta' => 0.00,
                'stock_actual' => 5,
                'stock_minimo' => 2,
                'imagen'       => self::U . self::IMG['mascarilla']       . self::Q,
            ],

            // ── Higiene y Desinfección ───────────────────────────────────
            [
                'nombre'       => 'Barbicide Desinfectante Concentrado 1.9L',
                'categoria'    => 'higiene y desinfección',
                'tipo'         => 'insumo_trabajo',
                'descripcion'  => 'Desinfectante de grado hospitalario aprobado por la FDA para esterilización de herramientas de barbería: tijeras, peinillas, navajas y máquinas. El icónico líquido azul que no oxida el metal. Dilución 1:15 con agua. 1.9L rinde para ~3 meses de uso diario en barbería activa.',
                'precio_compra'=> 325.00,
                'precio_venta' => 0.00,
                'stock_actual' => 2,
                'stock_minimo' => 1,
                'imagen'       => self::U . self::IMG['barbicide']        . self::Q,
            ],
            [
                'nombre'       => 'Spray Esterilizante Herramientas 500ml',
                'categoria'    => 'higiene y desinfección',
                'tipo'         => 'insumo_trabajo',
                'descripcion'  => 'Spray desinfectante instantáneo para limpiar tijeras, peines, máquinas y superficies entre cliente y cliente. Elimina el 99.9% de bacterias y hongos en 30 segundos. Fórmula no corrosiva, no daña el metal. No requiere enjuague. Uso directo sobre la herramienta.',
                'precio_compra'=> 128.00,
                'precio_venta' => 0.00,
                'stock_actual' => 6,
                'stock_minimo' => 2,
                'imagen'       => self::U . self::IMG['spray_desinfect']  . self::Q,
            ],
            [
                'nombre'       => 'Toallas Desechables de Microfibra (200u)',
                'categoria'    => 'higiene y desinfección',
                'tipo'         => 'insumo_trabajo',
                'descripcion'  => 'Toallas de un solo uso en microfibra prensada. Alta absorbencia, suaves con la piel. Para toalla caliente en afeitado, secado de cuello, limpieza de nuca y tratamientos. Eliminan el riesgo de contaminación cruzada. Uso único por cliente. Biodegradables. 200 unidades/caja.',
                'precio_compra'=> 98.00,
                'precio_venta' => 0.00,
                'stock_actual' => 10,
                'stock_minimo' => 3,
                'imagen'       => self::U . self::IMG['toallas']          . self::Q,
            ],
            [
                'nombre'       => 'Guantes de Nitrilo Negro (caja 100u)',
                'categoria'    => 'higiene y desinfección',
                'tipo'         => 'insumo_trabajo',
                'descripcion'  => 'Guantes de nitrilo color negro, sin latex, sin polvo. Resistentes a químicos de coloración y desinfectantes. Tacto anatómico que no interfiere con la precisión al cortar o teñir. Certificado CE. Tallas S/M/L disponibles. 100 unidades por caja. Descartables, un par por servicio.',
                'precio_compra'=> 148.00,
                'precio_venta' => 0.00,
                'stock_actual' => 8,
                'stock_minimo' => 2,
                'imagen'       => self::U . self::IMG['guantes']          . self::Q,
            ],
        ];

        foreach ($products as $data) {
            Product::updateOrCreate(
                ['nombre' => $data['nombre']],
                $data
            );
        }

        $venta  = collect($products)->where('tipo', 'venta_cliente')->count();
        $insumo = collect($products)->where('tipo', 'insumo_trabajo')->count();

        $this->command->info("  ✓ {$venta} productos de venta + {$insumo} insumos de trabajo creados");
    }
}
