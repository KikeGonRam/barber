<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    public function run(): void
    {
        $this->call([
            RolePermissionSeeder::class,  // Roles y permisos primero
            AdminUserSeeder::class,        // Usuario administrador
            TestUsersSeeder::class,        // Usuarios de prueba por rol
            InventorySeeder::class,        // Productos del inventario
            DemoDataSeeder::class,         // Barberos, clientes, citas, pagos
            WorkSeeder::class,             // Portfolio de trabajos de barberos
            WorkImageSeeder::class,        // Imágenes de portfolio
            CommentSeeder::class,          // Comentarios y ratings
            ReactionSeeder::class,         // Reacciones (likes)
        ]);
    }
}
