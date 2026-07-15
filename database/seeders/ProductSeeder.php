<?php

namespace Database\Seeders;

use App\Models\Product;
use Illuminate\Database\Seeder;

class ProductSeeder extends Seeder
{
    private const PRODUCTS = [
        ['nombre' => 'Cera Mate Fijacion Fuerte', 'categoria' => 'Styling', 'tipo' => 'venta', 'precio_compra' => 65, 'precio_venta' => 150],
        ['nombre' => 'Pomada Brillante Clasica', 'categoria' => 'Styling', 'tipo' => 'venta', 'precio_compra' => 60, 'precio_venta' => 140],
        ['nombre' => 'Gel Fijador Extra Fuerte', 'categoria' => 'Styling', 'tipo' => 'venta', 'precio_compra' => 45, 'precio_venta' => 110],
        ['nombre' => 'Arcilla Modeladora', 'categoria' => 'Styling', 'tipo' => 'venta', 'precio_compra' => 70, 'precio_venta' => 160],
        ['nombre' => 'Spray Fijador Profesional', 'categoria' => 'Styling', 'tipo' => 'venta', 'precio_compra' => 55, 'precio_venta' => 130],
        ['nombre' => 'Shampoo Anticaida', 'categoria' => 'Cuidado Capilar', 'tipo' => 'venta', 'precio_compra' => 80, 'precio_venta' => 180],
        ['nombre' => 'Shampoo Carbon Activado', 'categoria' => 'Cuidado Capilar', 'tipo' => 'venta', 'precio_compra' => 75, 'precio_venta' => 170],
        ['nombre' => 'Acondicionador Hidratante', 'categoria' => 'Cuidado Capilar', 'tipo' => 'venta', 'precio_compra' => 70, 'precio_venta' => 160],
        ['nombre' => 'Mascarilla Capilar Nutritiva', 'categoria' => 'Cuidado Capilar', 'tipo' => 'venta', 'precio_compra' => 90, 'precio_venta' => 210],
        ['nombre' => 'Aceite de Barba Premium', 'categoria' => 'Barba', 'tipo' => 'venta', 'precio_compra' => 85, 'precio_venta' => 200],
        ['nombre' => 'Balsamo de Barba', 'categoria' => 'Barba', 'tipo' => 'venta', 'precio_compra' => 75, 'precio_venta' => 180],
        ['nombre' => 'Kit Aceite y Balsamo de Barba', 'categoria' => 'Barba', 'tipo' => 'venta', 'precio_compra' => 130, 'precio_venta' => 320],
        ['nombre' => 'Aftershave Refrescante', 'categoria' => 'Afeitado', 'tipo' => 'venta', 'precio_compra' => 60, 'precio_venta' => 140],
        ['nombre' => 'Crema de Afeitar Clasica', 'categoria' => 'Afeitado', 'tipo' => 'venta', 'precio_compra' => 50, 'precio_venta' => 120],
        ['nombre' => 'Locion Post Afeitado', 'categoria' => 'Afeitado', 'tipo' => 'venta', 'precio_compra' => 55, 'precio_venta' => 130],
        ['nombre' => 'Navajas de Afeitar (5 pzas)', 'categoria' => 'Afeitado', 'tipo' => 'venta', 'precio_compra' => 40, 'precio_venta' => 95],
        ['nombre' => 'Peine de Madera', 'categoria' => 'Accesorios', 'tipo' => 'venta', 'precio_compra' => 35, 'precio_venta' => 90],
        ['nombre' => 'Cepillo para Barba', 'categoria' => 'Accesorios', 'tipo' => 'venta', 'precio_compra' => 45, 'precio_venta' => 110],
        ['nombre' => 'Toalla Facial Premium', 'categoria' => 'Accesorios', 'tipo' => 'venta', 'precio_compra' => 60, 'precio_venta' => 140],
        ['nombre' => 'Capa de Corte Profesional', 'categoria' => 'Herramientas', 'tipo' => 'uso_interno', 'precio_compra' => 180, 'precio_venta' => 0],
        ['nombre' => 'Tijeras de Corte 6"', 'categoria' => 'Herramientas', 'tipo' => 'uso_interno', 'precio_compra' => 850, 'precio_venta' => 0],
        ['nombre' => 'Maquina Cortadora Profesional', 'categoria' => 'Herramientas', 'tipo' => 'uso_interno', 'precio_compra' => 1200, 'precio_venta' => 0],
        ['nombre' => 'Navaja de Barbero Clasica', 'categoria' => 'Herramientas', 'tipo' => 'uso_interno', 'precio_compra' => 450, 'precio_venta' => 0],
        ['nombre' => 'Talco Refrescante', 'categoria' => 'Afeitado', 'tipo' => 'venta', 'precio_compra' => 40, 'precio_venta' => 95],
        ['nombre' => 'Tonico Capilar Anticaspa', 'categoria' => 'Cuidado Capilar', 'tipo' => 'venta', 'precio_compra' => 65, 'precio_venta' => 150],
        ['nombre' => 'Serum Brillo Instantaneo', 'categoria' => 'Styling', 'tipo' => 'venta', 'precio_compra' => 70, 'precio_venta' => 165],
        ['nombre' => 'Jabon de Barba Artesanal', 'categoria' => 'Barba', 'tipo' => 'venta', 'precio_compra' => 55, 'precio_venta' => 125],
        ['nombre' => 'Set de Viaje Grooming', 'categoria' => 'Accesorios', 'tipo' => 'venta', 'precio_compra' => 150, 'precio_venta' => 350],
        ['nombre' => 'Colonia UrbanBlade Signature', 'categoria' => 'Fragancias', 'tipo' => 'venta', 'precio_compra' => 180, 'precio_venta' => 420],
        ['nombre' => 'Desinfectante para Herramientas', 'categoria' => 'Herramientas', 'tipo' => 'uso_interno', 'precio_compra' => 90, 'precio_venta' => 0],
    ];

    public function run(): void
    {
        foreach (self::PRODUCTS as $data) {
            $stockActual = random_int(3, 60);
            $stockMinimo = $data['tipo'] === 'uso_interno' ? random_int(1, 3) : random_int(5, 15);

            Product::updateOrCreate(['nombre' => $data['nombre']], $data + [
                'descripcion' => "{$data['nombre']} — producto profesional UrbanBlade.",
                'stock_actual' => $stockActual,
                'stock_minimo' => $stockMinimo,
            ]);
        }

        $this->command->info('Productos sembrados: '.count(self::PRODUCTS));
    }
}
