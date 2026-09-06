<?php

namespace Tests\Feature;

use App\Models\Appointment;
use App\Models\Barber;
use App\Models\BarberReview;
use App\Models\BarberSchedule;
use App\Models\Client;
use App\Models\Comment;
use App\Models\MobileApiToken;
use App\Models\Permission;
use App\Models\Reaction;
use App\Models\Role;
use App\Models\SavedWork;
use App\Models\Service;
use App\Models\User;
use App\Models\Work;
use App\Models\WorkImage;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class BarberWorkspaceApiTest extends TestCase
{
    private string $token = 'test-barber-workspace-token';

    private User $barberUser;

    private Barber $barber;

    protected function setUp(): void
    {
        parent::setUp();

        app(PermissionRegistrar::class)->forgetCachedPermissions();
        $this->seed(RolePermissionSeeder::class);

        $role = Role::where('name', 'barbero')->where('guard_name', 'web')->firstOrFail();
        $this->barberUser = User::create([
            'name' => 'Barbero Workspace API',
            'email' => 'barber-workspace-api@test.local',
            'password' => 'password',
        ]);
        $this->barberUser->forceFill(['email_verified_at' => now(), 'role_id' => [(string) $role->id]])->save();
        $this->barber = Barber::create([
            'user_id' => (string) $this->barberUser->id,
            'nombre' => $this->barberUser->name,
            'activo' => true,
        ]);
        MobileApiToken::create([
            'user_id' => (string) $this->barberUser->id,
            'name' => 'test',
            'token_hash' => hash('sha256', $this->token),
        ]);
    }

    protected function tearDown(): void
    {
        Appointment::withTrashed()->forceDelete();
        BarberReview::query()->delete();
        BarberSchedule::query()->delete();
        Comment::query()->delete();
        Reaction::query()->delete();
        SavedWork::query()->delete();
        WorkImage::query()->delete();
        Work::query()->delete();
        Service::query()->delete();
        Client::query()->delete();
        Barber::query()->delete();
        MobileApiToken::query()->delete();
        User::withTrashed()->forceDelete();
        Role::query()->delete();
        Permission::query()->delete();
        \DB::connection('mongodb')->table(config('permission.table_names.role_has_permissions'))->delete();
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        parent::tearDown();
    }

    public function test_barber_agenda_is_scoped_and_returns_period_stats(): void
    {
        $clientUser = User::create(['name' => 'Cliente Agenda', 'email' => 'client-agenda@test.local', 'password' => 'password']);
        $client = Client::create(['user_id' => (string) $clientUser->id, 'telefono' => '5550001111']);
        $service = Service::create(['nombre' => 'Corte Agenda', 'precio' => 250, 'duracion_min' => 30, 'activo' => true]);
        $appointment = Appointment::create([
            'client_id' => (string) $client->id,
            'barber_id' => (string) $this->barber->id,
            'service_id' => (string) $service->id,
            'fecha' => now()->toDateString(),
            'hora_inicio' => '10:00:00',
            'hora_fin' => '10:30:00',
            'estado' => 'confirmada',
        ]);

        $response = $this->withToken($this->token)->getJson('/api/v1/barber/agenda?period=day');

        $response->assertOk()
            ->assertJsonPath('data.0.code', (string) $appointment->getAttribute('code'))
            ->assertJsonPath('data.0.client.user.name', 'Cliente Agenda')
            ->assertJsonPath('stats.total_period', 1)
            ->assertJsonPath('stats.confirmed_period', 1)
            ->assertJsonStructure(['range' => ['start', 'end', 'label']]);
    }

    public function test_barber_can_manage_schedule_portfolio_and_profile(): void
    {
        Storage::fake('public');
        $png = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNk+A8AAQUBAScY42YAAAAASUVORK5CYII=');

        $schedules = collect(['monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday', 'sunday'])
            ->map(fn (string $day) => [
                'day_of_week' => $day,
                'start_time' => '09:00',
                'end_time' => '18:00',
                'is_active' => $day !== 'sunday',
            ])->all();

        $this->withToken($this->token)->putJson('/api/v1/barber/schedule', ['schedules' => $schedules])
            ->assertOk();
        $this->withToken($this->token)->getJson('/api/v1/barber/schedule')
            ->assertOk()
            ->assertJsonCount(7, 'schedules')
            ->assertJsonPath('schedules.0.is_active', true);

        $created = $this->withToken($this->token)->post('/api/v1/barber/works', [
            'title' => 'Fade de prueba',
            'description' => 'Trabajo temporal de la suite.',
            'media' => [UploadedFile::fake()->createWithContent('fade.png', $png)],
        ]);
        $created->assertCreated()->assertJsonPath('work.title', 'Fade de prueba');
        $this->withToken($this->token)->getJson('/api/v1/barber/portfolio')
            ->assertOk()
            ->assertJsonPath('stats.total_works', 1)
            ->assertJsonPath('works.0.media.0.type', 'image');

        $profile = $this->withToken($this->token)->post('/api/v1/barber/profile', [
            'especialidades' => 'Fade, Barba',
            'descripcion' => 'Barbero de prueba',
            'foto' => UploadedFile::fake()->createWithContent('perfil.png', $png),
        ]);
        $profile->assertOk()->assertJsonPath('especialidades', 'Fade, Barba');

        $this->withToken($this->token)->getJson('/api/v1/barber/me')
            ->assertOk()
            ->assertJsonPath('portfolio_total', 1)
            ->assertJsonPath('especialidades', 'Fade, Barba')
            ->assertJsonStructure(['foto_url', 'member_since', 'years_experience', 'stats']);
    }
}
