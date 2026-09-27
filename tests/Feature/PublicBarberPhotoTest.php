<?php

namespace Tests\Feature;

use App\Models\Barber;
use App\Models\User;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * La foto pública del barbero ("Los Maestros", su ficha y la app): la de su perfil de barbero
 * o, si no subió una, la de su cuenta. Antes la ficha /barbers/{slug} mandaba la ruta interna
 * del archivo en lugar de una URL, y sin foto de barbero solo se veían iniciales.
 */
class PublicBarberPhotoTest extends TestCase
{
    protected function tearDown(): void
    {
        Barber::query()->delete();
        User::withTrashed()->forceDelete();

        parent::tearDown();
    }

    public function test_barber_without_own_photo_shows_the_account_photo(): void
    {
        $user = User::create(['name' => 'Nava Panther', 'email' => 'nava-foto@test.local', 'password' => 'password']);
        $user->forceFill(['avatar_url' => 'https://cdn.example.com/avatars/nava.jpg'])->save();
        $barber = Barber::create(['user_id' => (string) $user->id, 'nombre' => 'Nava', 'activo' => true]);

        $this->getJson('/api/v1/barbers')->assertOk()
            ->assertJsonPath('data.0.foto', 'https://cdn.example.com/avatars/nava.jpg');
        $this->getJson('/api/v1/barbers/'.$barber->getRouteKey())->assertOk()
            ->assertJsonPath('barber.foto', 'https://cdn.example.com/avatars/nava.jpg');
    }

    public function test_the_barber_photo_is_a_public_url_and_wins_over_the_account_photo(): void
    {
        $user = User::create(['name' => 'Bruno Díaz', 'email' => 'bruno-foto@test.local', 'password' => 'password']);
        $user->forceFill(['avatar_url' => 'https://cdn.example.com/avatars/bruno.jpg'])->save();
        $barber = Barber::create(['user_id' => (string) $user->id, 'nombre' => 'Bruno', 'activo' => true, 'foto' => 'barbers/bruno.jpg']);
        $url = Storage::disk('public')->url('barbers/bruno.jpg');

        $this->getJson('/api/v1/barbers')->assertOk()->assertJsonPath('data.0.foto', $url);
        $this->getJson('/api/v1/barbers/'.$barber->getRouteKey())->assertOk()->assertJsonPath('barber.foto', $url);
    }
}
