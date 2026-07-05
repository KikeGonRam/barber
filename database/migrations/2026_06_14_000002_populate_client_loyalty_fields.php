<?php

use App\Models\Appointment;
use App\Models\Client;
use App\Services\Loyalty\LoyaltyService;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    public function up(): void
    {
        Client::get()->each(function (Client $client) {
            $completadas = Appointment::where('client_id', (string) $client->id)
                ->where('estado', 'completada')
                ->count();

            $nivel  = LoyaltyService::nivelFromCitas($completadas);
            $puntos = $completadas * 10;

            $client->update([
                'total_citas' => $completadas,
                'puntos'      => $puntos,
                'nivel'       => $nivel,
            ]);
        });
    }

    public function down(): void
    {
        Client::get()->each(function (Client $client) {
            $client->unset(['nivel', 'puntos', 'total_citas']);
        });
    }
};
