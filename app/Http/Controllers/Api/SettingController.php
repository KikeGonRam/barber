<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\BarbershopSettingResource;
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
            'data' => new BarbershopSettingResource($setting),
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
            'data' => new BarbershopSettingResource($setting->fresh()),
        ]);
    }

    public function toggleMaintenance(Request $request): JsonResponse
    {
        $this->authorizeAdmin($request);

        $setting = $this->getSetting();
        $setting->forceFill([
            'maintenance_mode' => ! $setting->maintenance_mode,
        ])->save();

        $setting->refresh();

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

    private function authorizeAdmin(Request $request): void
    {
        $user = $request->user();

        abort_if(! $user || ! $user->hasRole('administrador'), 403, 'No autorizado.');
    }
}
