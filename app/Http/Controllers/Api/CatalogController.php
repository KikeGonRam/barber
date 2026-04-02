<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Barber;
use App\Models\Service;
use Illuminate\Http\JsonResponse;

class CatalogController extends Controller
{
    public function services(): JsonResponse
    {
        $services = Service::query()
            ->where('activo', true)
            ->orderBy('nombre')
            ->get(['id', 'nombre', 'categoria', 'precio', 'duracion_min', 'imagen', 'descripcion']);

        return response()->json([
            'data' => $services,
        ]);
    }

    public function barbers(): JsonResponse
    {
        $barbers = Barber::query()
            ->with('user:id,name')
            ->where('activo', true)
            ->orderBy('id')
            ->get()
            ->map(fn (Barber $barber) => [
                'id' => $barber->id,
                'name' => $barber->user?->name,
                'especialidades' => $barber->especialidades,
                'foto' => $barber->foto,
                'descripcion' => $barber->descripcion,
            ])
            ->values();

        return response()->json([
            'data' => $barbers,
        ]);
    }
}