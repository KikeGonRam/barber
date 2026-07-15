<?php

namespace Database\Seeders;

use App\Models\Service;
use Illuminate\Database\Seeder;

class ServiceSeeder extends Seeder
{
    private const SERVICES = [
        ['nombre' => 'Corte Clasico', 'categoria' => 'Cortes', 'precio' => 180, 'duracion_min' => 30, 'descripcion' => 'Corte de cabello tradicional con tijera y maquina, acabado prolijo.'],
        ['nombre' => 'Taper Fade', 'categoria' => 'Cortes', 'precio' => 220, 'duracion_min' => 40, 'descripcion' => 'Degradado progresivo en los laterales con transicion suave.'],
        ['nombre' => 'Skin Fade', 'categoria' => 'Cortes', 'precio' => 250, 'duracion_min' => 45, 'descripcion' => 'Degradado a piel, look moderno y muy definido.'],
        ['nombre' => 'Undercut', 'categoria' => 'Cortes', 'precio' => 230, 'duracion_min' => 40, 'descripcion' => 'Rapado en laterales con volumen conservado arriba.'],
        ['nombre' => 'Pompadour', 'categoria' => 'Cortes', 'precio' => 240, 'duracion_min' => 45, 'descripcion' => 'Corte con volumen frontal peinado hacia atras.'],
        ['nombre' => 'Corte Mohicano', 'categoria' => 'Cortes', 'precio' => 260, 'duracion_min' => 45, 'descripcion' => 'Cresta central marcada con laterales rapados.'],
        ['nombre' => 'Corte Infantil', 'categoria' => 'Cortes', 'precio' => 150, 'duracion_min' => 25, 'descripcion' => 'Corte para ninos, ambiente relajado y paciente.'],
        ['nombre' => 'Diseno de Barba', 'categoria' => 'Barba', 'precio' => 150, 'duracion_min' => 25, 'descripcion' => 'Perfilado y diseno de barba con navaja.'],
        ['nombre' => 'Arreglo de Barba', 'categoria' => 'Barba', 'precio' => 100, 'duracion_min' => 15, 'descripcion' => 'Recorte y perfilado rapido de barba.'],
        ['nombre' => 'Afeitado Clasico', 'categoria' => 'Barba', 'precio' => 180, 'duracion_min' => 30, 'descripcion' => 'Afeitado tradicional con navaja y toalla caliente.'],
        ['nombre' => 'Combo Corte + Barba', 'categoria' => 'Combos', 'precio' => 320, 'duracion_min' => 60, 'descripcion' => 'Paquete completo de corte y arreglo de barba.'],
        ['nombre' => 'Combo Premium', 'categoria' => 'Combos', 'precio' => 450, 'duracion_min' => 75, 'descripcion' => 'Corte, barba, tratamiento capilar y toalla caliente.'],
        ['nombre' => 'Tratamiento Capilar', 'categoria' => 'Tratamientos', 'precio' => 280, 'duracion_min' => 35, 'descripcion' => 'Hidratacion y nutricion profunda del cuero cabelludo.'],
        ['nombre' => 'Tinte / Color', 'categoria' => 'Tratamientos', 'precio' => 380, 'duracion_min' => 60, 'descripcion' => 'Coloracion completa o mechas segun preferencia.'],
        ['nombre' => 'Perfilado de Cejas', 'categoria' => 'Tratamientos', 'precio' => 90, 'duracion_min' => 15, 'descripcion' => 'Diseno y limpieza de cejas con navaja o pinza.'],
        ['nombre' => 'Keratina Express', 'categoria' => 'Tratamientos', 'precio' => 350, 'duracion_min' => 50, 'descripcion' => 'Alisado ligero con keratina, brillo inmediato.'],
        ['nombre' => 'Corte a Navaja', 'categoria' => 'Cortes', 'precio' => 260, 'duracion_min' => 40, 'descripcion' => 'Corte completo trabajado enteramente a navaja.'],
        ['nombre' => 'Rapado Total', 'categoria' => 'Cortes', 'precio' => 130, 'duracion_min' => 20, 'descripcion' => 'Rapado uniforme de toda la cabeza.'],
        ['nombre' => 'Spa Capilar', 'categoria' => 'Tratamientos', 'precio' => 300, 'duracion_min' => 40, 'descripcion' => 'Masaje, mascarilla y limpieza profunda del cabello.'],
        ['nombre' => 'Retoque Express', 'categoria' => 'Cortes', 'precio' => 120, 'duracion_min' => 15, 'descripcion' => 'Retoque rapido de contornos entre cortes.'],
    ];

    public function run(): void
    {
        foreach (self::SERVICES as $data) {
            Service::updateOrCreate(['nombre' => $data['nombre']], $data + ['activo' => true]);
        }

        $this->command->info('Servicios sembrados: '.count(self::SERVICES));
    }
}
