<?php

namespace App\Http\Controllers\Service;

use App\Http\Controllers\Controller;
use App\Models\Service;
use Illuminate\View\View;

/**
 * Catálogo público de servicios (sin autenticación). La gestión
 * administrativa (CRUD) se retiró de Blade: Nuxt (frontend-urban) tiene
 * paridad funcional confirmada para esa pantalla — ver
 * Api\Service\ServiceManagementController para el equivalente JSON.
 */
class ServiceController extends Controller
{
    public function publicIndex(): View
    {
        $services = Service::where('activo', true)->get();

        return view('services.public-index', compact('services'));
    }
}
