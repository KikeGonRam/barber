<?php

namespace Database\Seeders;

use App\Models\Service;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

/**
 * Catálogo real de servicios de barbería premium UrbanBlade.
 * 20 servicios en 5 categorías, precios en MXN, imágenes de Unsplash.
 */
class ServiceSeeder extends Seeder
{
    // Unsplash CDN — fotos curadas de barbería/grooming masculino
    private const IMG = 'https://images.unsplash.com/photo-';
    private const Q   = '?auto=format&fit=crop&w=800&q=80';

    public function run(): void
    {
        $services = [

            // ── CORTES ──────────────────────────────────────────────────────
            [
                'nombre'      => 'Fade Clásico',
                'categoria'   => 'corte',
                'precio'      => 320.0,
                'duracion_min'=> 35,
                'descripcion' => 'Degradado suave de mayor a menor longitud desde laterales y nuca hasta la coronilla. Se trabaja con máquinas de números progresivos (#4→#2→#1) creando una fusión sin líneas visibles. El resultado es un look limpio, moderno y versátil que sienta bien a todo tipo de rostro.',
                'imagen'      => self::IMG . '1599351564783-3f3d1fb2c3e5' . self::Q,
            ],
            [
                'nombre'      => 'Skin Fade',
                'categoria'   => 'corte',
                'precio'      => 360.0,
                'duracion_min'=> 40,
                'descripcion' => 'El fade más extremo: laterales y nuca afeitadas completamente a piel (#0) con transición gradual hacia una corona de mayor longitud. Máximo contraste y definición. Requiere navaja de precisión y expertise técnica avanzada. Look urbano audaz.',
                'imagen'      => self::IMG . '1621605815971-fbc98d665033' . self::Q,
            ],
            [
                'nombre'      => 'Undercut',
                'categoria'   => 'corte',
                'precio'      => 350.0,
                'duracion_min'=> 40,
                'descripcion' => 'Máximo contraste entre corona larga (5-8 cm) y laterales muy cortos o afeitados (#0.5–#1). La línea divisoria queda nítida a la altura de las orejas. Perfecto para peinados con pomada o gel. Corte de alto mantenimiento que requiere visita cada 3-4 semanas.',
                'imagen'      => self::IMG . '1503951914875-452162b0f3f1' . self::Q,
            ],
            [
                'nombre'      => 'Pompadour',
                'categoria'   => 'corte',
                'precio'      => 400.0,
                'duracion_min'=> 45,
                'descripcion' => 'Corona elevada y voluminosa (8-10 cm) barrida hacia atrás con laterales en fade. La técnica requiere corte con tijera en corona para crear textura y movimiento naturales. Inspirado en el estilo rockabilly clásico, actualizado para el hombre contemporáneo.',
                'imagen'      => self::IMG . '1519345182560-3f2917c472ef' . self::Q,
            ],
            [
                'nombre'      => 'Quiff Moderno',
                'categoria'   => 'corte',
                'precio'      => 370.0,
                'duracion_min'=> 40,
                'descripcion' => 'Variante del pompadour con barrida lateral que crea una onda natural. Corona con altura controlada (6-7 cm) y laterales con fade suave. Híbrido elegante entre pompadour y side-part: muy versátil para ocasiones formales e informales. Idealmente peinado con crema de fijación media.',
                'imagen'      => self::IMG . '1605497788044-5a32c7078486' . self::Q,
            ],
            [
                'nombre'      => 'Corte Texturizado',
                'categoria'   => 'corte',
                'precio'      => 300.0,
                'duracion_min'=> 30,
                'descripcion' => 'Largo medio-corto (2-4 cm) con textura desordenada intencional conseguida con tijeras en movimiento cruzado y técnica de point-cutting. Sin fade definido. Corona con volumen natural y aspecto desaliñado-elegante, tendencia actual muy popular entre millennials y generación Z.',
                'imagen'      => self::IMG . '1622286342621-4bd786c2447c' . self::Q,
            ],
            [
                'nombre'      => 'Corte Clásico',
                'categoria'   => 'corte',
                'precio'      => 300.0,
                'duracion_min'=> 35,
                'descripcion' => 'Corte tradicional de negocios con taper suave en laterales, largo superior moderado (2-3 cm) y línea de cabello definida. Peinado hacia atrás o con raya lateral limpia. Aspecto conservador, profesional y atemporal. El corte favorito del ejecutivo moderno.',
                'imagen'      => self::IMG . '1503951914875-452162b0f3f1' . self::Q,
            ],
            [
                'nombre'      => 'Buzz Cut',
                'categoria'   => 'corte',
                'precio'      => 250.0,
                'duracion_min'=> 20,
                'descripcion' => 'Todo el cabello a la misma longitud muy corta (#0.5 a #2) con máquina de corte. Máxima practicidad y mínimo mantenimiento. Incluye ajuste fino con navaja en nuca y sienes para líneas impecables. El look que nunca pasa de moda y que revela la estructura natural del rostro.',
                'imagen'      => self::IMG . '1560250097-0b93528c311a' . self::Q,
            ],
            [
                'nombre'      => 'Faux Hawk',
                'categoria'   => 'corte',
                'precio'      => 370.0,
                'duracion_min'=> 40,
                'descripcion' => 'Versión wearable del mohicano clásico: corona ligeramente más larga peinada al centro, laterales con fade cerrado para máximo contraste. Requiere styling con gel fuerte o cera. Un corte con carácter y personalidad, perfecto para el hombre que no tiene miedo de destacar.',
                'imagen'      => self::IMG . '1621605815971-fbc98d665033' . self::Q,
            ],

            // ── BARBA ───────────────────────────────────────────────────────
            [
                'nombre'      => 'Perfilado de Barba',
                'categoria'   => 'barba',
                'precio'      => 200.0,
                'duracion_min'=> 20,
                'descripcion' => 'Limpieza y definición de las líneas de la barba: mejillas, cuello e inferior del mentón. Se trabaja con tijeras de precisión y navaja para líneas perfectamente limpias. Se mantiene el largo original, solo se eliminan los pelos desordenados. Indispensable para mantener una barba siempre impecable.',
                'imagen'      => self::IMG . '1560250097-0b93528c311a' . self::Q,
            ],
            [
                'nombre'      => 'Afeitado Clásico con Toalla Caliente',
                'categoria'   => 'barba',
                'precio'      => 320.0,
                'duracion_min'=> 30,
                'descripcion' => 'El ritual ancestral de la barbería: toalla caliente húmeda (3-5 min) para abrir poros y suavizar el vello, pre-afeitado con crema artesanal, afeitada en múltiples pasadas con navaja recta, enjuague con agua fría para cerrar poros, y cierre con aftershave y bálsamo hidratante. Experiencia de lujo y relajación total.',
                'imagen'      => self::IMG . '1585747860715-2ba37e788b70' . self::Q,
            ],
            [
                'nombre'      => 'Arreglo Completo de Barba',
                'categoria'   => 'barba',
                'precio'      => 350.0,
                'duracion_min'=> 40,
                'descripcion' => 'Servicio de transformación completa: definición de forma (ducktail, Van Dyke, barba redonda o cuadrada), arreglo de largo y densidad con tijeras y máquinas especializadas, perfilado fino con navaja. Resultado: una barba con carácter y forma definida que complementa perfectamente los rasgos del rostro.',
                'imagen'      => self::IMG . '1622286342621-4bd786c2447c' . self::Q,
            ],
            [
                'nombre'      => 'Tinte de Barba',
                'categoria'   => 'barba',
                'precio'      => 250.0,
                'duracion_min'=> 30,
                'descripcion' => 'Aplicación de tinte semi-permanente profesional para cubrir canas de manera natural o para cambiar el tono de la barba. El tinte se mezcla a medida, se aplica con brocha y se deja reposar 15-20 minutos, se enjuaga y se sella con acondicionador. Resultado inmediato que dura 4-6 semanas.',
                'imagen'      => self::IMG . '1560250097-0b93528c311a' . self::Q,
            ],
            [
                'nombre'      => 'Hidratación de Barba',
                'categoria'   => 'barba',
                'precio'      => 180.0,
                'duracion_min'=> 15,
                'descripcion' => 'Tratamiento nutritivo con mascarilla de mantequilla de karité, aceite de argán y colágeno aplicada sobre el vello y la piel subyacente. Masaje de 10 minutos para potenciar la absorción. Barba visiblemente más suave, menos resequedad y picazón, aspecto saludable y brillante.',
                'imagen'      => self::IMG . '1585747860715-2ba37e788b70' . self::Q,
            ],

            // ── COMBOS ──────────────────────────────────────────────────────
            [
                'nombre'      => 'Combo Clásico',
                'categoria'   => 'combo',
                'precio'      => 480.0,
                'duracion_min'=> 50,
                'descripcion' => 'La dupla perfecta: Corte Fade Clásico + Arreglo Completo de Barba. Saldrás renovado de los pies a la cabeza con cabello y barba impecables. Ahorro de $190 respecto a los servicios por separado. El combo más solicitado en UrbanBlade, perfecto para reuniones, eventos o simplemente verte al 100.',
                'imagen'      => self::IMG . '1599351564783-3f3d1fb2c3e5' . self::Q,
            ],
            [
                'nombre'      => 'Combo Premium',
                'categoria'   => 'combo',
                'precio'      => 820.0,
                'duracion_min'=> 75,
                'descripcion' => 'La experiencia UrbanBlade completa: Corte a elección + Afeitado Clásico con Toalla Caliente + Hidratación de Barba + Masaje de Cuero Cabelludo. 75 minutos de ritual de cuidado masculino premium. Incluye café de cortesía y aplicación de productos de acabado profesionales.',
                'imagen'      => self::IMG . '1503951914875-452162b0f3f1' . self::Q,
            ],
            [
                'nombre'      => 'Combo Express',
                'categoria'   => 'combo',
                'precio'      => 460.0,
                'duracion_min'=> 50,
                'descripcion' => 'Para el hombre que cuida su imagen pero tiene el tiempo contado: Corte Clásico o Texturizado + Perfilado de Barba en 50 minutos exactos. Protocolo optimizado para máxima eficiencia sin sacrificar calidad. Ideal para visitas entre semana en horario de almuerzo.',
                'imagen'      => self::IMG . '1621605815971-fbc98d665033' . self::Q,
            ],

            // ── TRATAMIENTOS ────────────────────────────────────────────────
            [
                'nombre'      => 'Masaje de Cuero Cabelludo',
                'categoria'   => 'tratamiento',
                'precio'      => 250.0,
                'duracion_min'=> 20,
                'descripcion' => 'Masaje profundo de 20 minutos en cuero cabelludo con técnicas de digitopresión y movimientos circulares. Estimula la circulación sanguínea y los folículos pilosos, relaja la tensión cervical acumulada y reduce el estrés. Se realiza con aceites esenciales de menta, eucalipto o lavanda según preferencia.',
                'imagen'      => self::IMG . '1503951914875-452162b0f3f1' . self::Q,
            ],
            [
                'nombre'      => 'Keratina Express',
                'categoria'   => 'tratamiento',
                'precio'      => 420.0,
                'duracion_min'=> 45,
                'descripcion' => 'Alisado y fortalecimiento con queratina líquida premium aplicada post-ducha caliente y sellada con secadora profesional (técnica de alisado térmico). Cabello liso, brillante, resistente al frizz y con movimiento natural por 3-4 semanas. No altera el color. Sin compromiso, sin permanente.',
                'imagen'      => self::IMG . '1519345182560-3f2917c472ef' . self::Q,
            ],
            [
                'nombre'      => 'Coloración Capilar',
                'categoria'   => 'tratamiento',
                'precio'      => 380.0,
                'duracion_min'=> 60,
                'descripcion' => 'Tinte profesional para cabello con pigmentos de larga duración. Ideal para cubrir canas, refrescar el tono natural o crear un cambio audaz. La coloración se aplica siempre ANTES del corte para evaluar el tono resultante. Incluye enjuague, acondicionador y un pequeño corte de mantenimiento.',
                'imagen'      => self::IMG . '1605497788044-5a32c7078486' . self::Q,
            ],
        ];

        foreach ($services as $data) {
            $slug = Str::slug($data['nombre']);

            Service::updateOrCreate(
                ['slug' => $slug],
                array_merge($data, [
                    'slug'  => $slug,
                    'activo'=> true,
                ])
            );
        }

        $this->command->info('  ✓ ' . count($services) . ' servicios de UrbanBlade creados');
    }
}
