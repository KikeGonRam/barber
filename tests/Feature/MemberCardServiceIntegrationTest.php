<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\Member\MemberCardService;
use Carbon\Carbon;
use Tests\TestCase;

/**
 * Feature test (no Unit) porque memberNumber()/memberSince() necesitan un
 * User real persistido (id de Mongo, created_at) y qrDataUri()/
 * qrPngDataUri() generan un QR real vía endroid/qr-code — sin mocks, para
 * probar que la generación real funciona en este entorno (requiere GD para
 * el PNG, ya instalado en el contenedor).
 */
class MemberCardServiceIntegrationTest extends TestCase
{
    private MemberCardService $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->service = app(MemberCardService::class);
    }

    protected function tearDown(): void
    {
        User::query()->delete();

        parent::tearDown();
    }

    private function makeUser(): User
    {
        return User::create([
            'name' => 'Cliente de prueba',
            'email' => 'membercard@test.local',
            'password' => 'password',
        ]);
    }

    public function test_member_number_has_the_expected_format(): void
    {
        $user = $this->makeUser();

        $number = $this->service->memberNumber($user);

        $this->assertMatchesRegularExpression('/^UB · [0-9A-F]{4} [0-9A-F]{4}$/', $number);
    }

    public function test_member_number_is_deterministic_for_the_same_user(): void
    {
        $user = $this->makeUser();

        $this->assertSame($this->service->memberNumber($user), $this->service->memberNumber($user));
    }

    public function test_member_since_reflects_the_creation_year(): void
    {
        $user = $this->makeUser();
        $user->forceFill(['created_at' => Carbon::create(2023, 5, 1)])->save();

        $this->assertSame('2023', $this->service->memberSince($user->fresh()));
    }

    public function test_qr_data_uri_returns_a_valid_svg_data_uri(): void
    {
        $user = $this->makeUser();

        $uri = $this->service->qrDataUri($user);

        $this->assertNotNull($uri);
        $this->assertStringStartsWith('data:image/svg+xml', $uri);
    }

    public function test_qr_png_data_uri_returns_a_valid_png_data_uri(): void
    {
        $user = $this->makeUser();

        $uri = $this->service->qrPngDataUri($user);

        $this->assertNotNull($uri);
        $this->assertStringStartsWith('data:image/png', $uri);
    }
}
