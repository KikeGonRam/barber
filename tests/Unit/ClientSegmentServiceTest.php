<?php

namespace Tests\Unit;

use App\Models\Client;
use App\Services\Client\ClientSegmentService;
use Carbon\Carbon;
use PHPUnit\Framework\TestCase;

class ClientSegmentServiceTest extends TestCase
{
    private ClientSegmentService $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->service = new ClientSegmentService;
    }

    private function clientJoinedDaysAgo(int $days): Client
    {
        $client = new Client;
        $client->created_at = Carbon::now()->subDays($days);

        return $client;
    }

    public function test_a_recently_joined_client_is_new_regardless_of_appointments(): void
    {
        $client = $this->clientJoinedDaysAgo(5);

        $this->assertSame('new', $this->service->segment($client, 0, null));
        $this->assertSame('new', $this->service->segment($client, 20, Carbon::now()->subDay()));
    }

    public function test_a_client_with_no_recent_appointment_is_inactive(): void
    {
        $client = $this->clientJoinedDaysAgo(200);

        $this->assertSame('inactive', $this->service->segment($client, 3, Carbon::now()->subDays(31)));
        $this->assertSame('inactive', $this->service->segment($client, 3, null)); // nunca tuvo cita
    }

    public function test_inactivity_wins_over_vip_status(): void
    {
        // Un VIP (muchas citas) que dejo de venir hace mas de 30 dias debe
        // contar como "en riesgo", no como VIP protegido — decision explicita
        // del dueno del proyecto: es a quien mas conviene recontactar.
        $client = $this->clientJoinedDaysAgo(400);

        $this->assertSame('inactive', $this->service->segment($client, 25, Carbon::now()->subDays(45)));
    }

    public function test_a_frequent_client_with_a_recent_visit_is_vip(): void
    {
        $client = $this->clientJoinedDaysAgo(400);

        $this->assertSame('vip', $this->service->segment($client, 11, Carbon::now()->subDays(5)));
    }

    public function test_a_regular_client_with_a_recent_visit_is_active(): void
    {
        $client = $this->clientJoinedDaysAgo(400);

        $this->assertSame('active', $this->service->segment($client, 3, Carbon::now()->subDays(5)));
    }

    public function test_boundaries_are_inclusive_at_the_edge(): void
    {
        $client = $this->clientJoinedDaysAgo(400);

        // Exactamente 30 dias todavia cuenta como activo, 31 ya es inactivo.
        $this->assertSame('active', $this->service->segment($client, 3, Carbon::now()->subDays(30)));
        $this->assertSame('inactive', $this->service->segment($client, 3, Carbon::now()->subDays(31)));

        // Exactamente 10 citas todavia no es VIP, 11 si.
        $this->assertSame('active', $this->service->segment($client, 10, Carbon::now()->subDays(5)));
        $this->assertSame('vip', $this->service->segment($client, 11, Carbon::now()->subDays(5)));
    }
}
