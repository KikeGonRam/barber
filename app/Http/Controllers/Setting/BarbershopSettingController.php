<?php

namespace App\Http\Controllers\Setting;

use App\Http\Controllers\Controller;
use App\Http\Requests\Setting\UpdateBarbershopSettingRequest;
use App\Models\BarbershopSetting;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class BarbershopSettingController extends Controller
{
    public function edit(): View
    {
        $setting = BarbershopSetting::query()->firstOrCreate(
            ['id' => 1],
            ['nombre' => config('app.name', 'Barbershop'), 'politica_cancelacion' => 24]
        );

        return view('settings.edit', compact('setting'));
    }

    public function update(UpdateBarbershopSettingRequest $request): RedirectResponse
    {
        $setting = BarbershopSetting::query()->firstOrCreate(
            ['id' => 1],
            ['nombre' => config('app.name', 'Barbershop'), 'politica_cancelacion' => 24]
        );

        $setting->update([
            'nombre' => $request->validated()['nombre'],
            'direccion' => $request->validated()['direccion'] ?? null,
            'telefono' => $request->validated()['telefono'] ?? null,
            'horario_apertura' => $request->validated()['horario_apertura'] ?? null,
            'horario_cierre' => $request->validated()['horario_cierre'] ?? null,
            'politica_cancelacion' => $request->validated()['politica_cancelacion'],
            'redes_sociales' => [
                'instagram' => $request->validated()['instagram'] ?? null,
                'facebook' => $request->validated()['facebook'] ?? null,
                'tiktok' => $request->validated()['tiktok'] ?? null,
            ],
            'datos_bancarios' => [
                'clabe' => $request->validated()['clabe'] ?? null,
                'banco' => $request->validated()['banco'] ?? null,
                'beneficiario' => $request->validated()['beneficiario'] ?? null,
                'concepto' => $request->validated()['concepto'] ?? null,
            ],
        ]);

        return redirect()->route('settings.edit')->with('status', 'Configuracion actualizada correctamente.');
    }

    public function toggleMaintenance(): RedirectResponse
    {
        $setting = BarbershopSetting::query()->firstOrCreate(
            ['id' => 1],
            ['nombre' => config('app.name', 'Barbershop'), 'politica_cancelacion' => 24]
        );

        $setting->forceFill([
            'maintenance_mode' => ! $setting->maintenance_mode,
        ])->save();

        $setting->refresh();

        $status = $setting->maintenance_mode ? 'El sistema ha entrado en modo mantenimiento.' : 'El sistema está nuevamente en línea.';

        return back()->with('status', $status);
    }
}
