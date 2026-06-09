<?php

namespace Database\Seeders;

use App\Models\Product;
use Illuminate\Database\Seeder;

class InventorySeeder extends Seeder
{
    public function run(): void
    {
        $products = [
            ['nombre' => 'Shampoo profesional', 'categoria' => 'cuidado', 'precio_compra' => 120, 'precio_venta' => 180, 'stock_actual' => 15, 'stock_minimo' => 3, 'tipo' => 'producto'],
            ['nombre' => 'Cera moldeadora', 'categoria' => 'estilismo', 'precio_compra' => 80, 'precio_venta' => 130, 'stock_actual' => 20, 'stock_minimo' => 5, 'tipo' => 'producto'],
            ['nombre' => 'Aceite de barba', 'categoria' => 'barba', 'precio_compra' => 150, 'precio_venta' => 220, 'stock_actual' => 10, 'stock_minimo' => 2, 'tipo' => 'producto'],
            ['nombre' => 'Navaja profesional', 'categoria' => 'herramienta', 'precio_compra' => 350, 'precio_venta' => 0, 'stock_actual' => 8, 'stock_minimo' => 2, 'tipo' => 'herramienta'],
            ['nombre' => 'Maquina cortadora', 'categoria' => 'herramienta', 'precio_compra' => 1200, 'precio_venta' => 0, 'stock_actual' => 4, 'stock_minimo' => 1, 'tipo' => 'herramienta'],
            ['nombre' => 'Gel fijador', 'categoria' => 'estilismo', 'precio_compra' => 60, 'precio_venta' => 95, 'stock_actual' => 25, 'stock_minimo' => 5, 'tipo' => 'producto'],
            ['nombre' => 'Crema de afeitar', 'categoria' => 'barba', 'precio_compra' => 90, 'precio_venta' => 140, 'stock_actual' => 12, 'stock_minimo' => 3, 'tipo' => 'producto'],
            ['nombre' => 'Tónico capilar', 'categoria' => 'cuidado', 'precio_compra' => 200, 'precio_venta' => 290, 'stock_actual' => 7, 'stock_minimo' => 2, 'tipo' => 'producto'],
            ['nombre' => 'Peine profesional', 'categoria' => 'herramienta', 'precio_compra' => 45, 'precio_venta' => 0, 'stock_actual' => 15, 'stock_minimo' => 5, 'tipo' => 'herramienta'],
            ['nombre' => 'Desinfectante de herramientas', 'categoria' => 'higiene', 'precio_compra' => 70, 'precio_venta' => 0, 'stock_actual' => 2, 'stock_minimo' => 3, 'tipo' => 'insumo'],
            ['nombre' => 'Toallas desechables', 'categoria' => 'higiene', 'precio_compra' => 30, 'precio_venta' => 0, 'stock_actual' => 100, 'stock_minimo' => 20, 'tipo' => 'insumo'],
            ['nombre' => 'Pomada brillante', 'categoria' => 'estilismo', 'precio_compra' => 95, 'precio_venta' => 150, 'stock_actual' => 18, 'stock_minimo' => 4, 'tipo' => 'producto'],
        ];

        foreach ($products as $data) {
            Product::firstOrCreate(
                ['nombre' => $data['nombre']],
                $data
            );
        }

        $this->command->info('InventorySeeder: '.count($products).' productos de inventario creados.');
    }
}
