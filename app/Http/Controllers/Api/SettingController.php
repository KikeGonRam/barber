<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\BarbershopSetting;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SettingController extends Controller
{
    public function show(Request $request): JsonResponse
    {
        $this->authorizeAdmin($request);

        $setting = $this->getSetting();

        return response()->json([
            'data' => $this->payload($setting),
        ]);
    }

    public function update(Request $request): JsonResponse
    {
        $this->authorizeAdmin($request);

        $validated = $request->validate([
            'nombre' => ['required', 'string', 'max:255'],
            'direccion' => ['nullable', 'string', 'max:255'],
            'telefono' => ['nullable', 'string', 'max:30'],
            'horario_apertura' => ['nullable', 'date_format:H:i'],
            'horario_cierre' => ['nullable', 'date_format:H:i', 'after:horario_apertura'],
            'politica_cancelacion' => ['required', 'integer', 'min:1', 'max:168'],
            'instagram' => ['nullable', 'string', 'max:255'],
            'facebook' => ['nullable', 'string', 'max:255'],
            'tiktok' => ['nullable', 'string', 'max:255'],
        ]);

        $setting = $this->getSetting();
        $setting->update([
            'nombre' => $validated['nombre'],
            'direccion' => $validated['direccion'] ?? null,
            'telefono' => $validated['telefono'] ?? null,
            'horario_apertura' => $validated['horario_apertura'] ?? null,
            'horario_cierre' => $validated['horario_cierre'] ?? null,
            'politica_cancelacion' => $validated['politica_cancelacion'],
            'redes_sociales' => [
                'instagram' => $validated['instagram'] ?? null,
                'facebook' => $validated['facebook'] ?? null,
                'tiktok' => $validated['tiktok'] ?? null,
            ],
        ]);

        return response()->json([
            'message' => 'Configuracion actualizada correctamente.',
            'data' => $this->payload($setting->fresh()),
        ]);
    }

    public function toggleMaintenance(Request $request): JsonResponse
    {
        $this->authorizeAdmin($request);

        $setting = $this->getSetting();
        $setting->update([
            'maintenance_mode' => ! $setting->maintenance_mode,
        ]);

        return response()->json([
            'message' => $setting->maintenance_mode
                ? 'El sistema ha entrado en modo mantenimiento.'
                : 'El sistema esta nuevamente en linea.',
            'data' => [
                'maintenance_mode' => (bool) $setting->maintenance_mode,
            ],
        ]);
    }

    private function getSetting(): BarbershopSetting
    {
        return BarbershopSetting::query()->firstOrCreate(
            ['id' => 1],
            ['nombre' => config('app.name', 'Barbershop'), 'politica_cancelacion' => 24]
        );
    }

    private function payload(BarbershopSetting $setting): array
    {
        return [
            'id' => $setting->id,
            'nombre' => $setting->nombre,
            'direccion' => $setting->direccion,
            'telefono' => $setting->telefono,
            'horario_apertura' => $setting->horario_apertura,
            'horario_cierre' => $setting->horario_cierre,
            'politica_cancelacion' => $setting->politica_cancelacion,
            'maintenance_mode' => (bool) $setting->maintenance_mode,
            'redes_sociales' => $setting->redes_sociales ?? [],
        ];
    }

    private function authorizeAdmin(Request $request): void
    {
        $user = $request->user();

        abort_if(! $user || ! $user->hasRole('administrador'), 403, 'No autorizado.');
    }
}
