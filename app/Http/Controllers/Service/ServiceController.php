<?php

namespace App\Http\Controllers\Service;

use App\Http\Controllers\Controller;
use App\Http\Requests\Service\StoreServiceRequest;
use App\Http\Requests\Service\UpdateServiceRequest;
use App\Models\Service;
use App\Services\ServiceService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ServiceController extends Controller
{
    public function __construct(private readonly ServiceService $serviceService) {}

    public function index(Request $request): View
    {
        $filters = $request->only(['categoria', 'activo']);

        $services = $this->serviceService->list($filters);
        $categories = $this->serviceService->categories();

        return view('services.index', compact('services', 'categories', 'filters'));
    }

    public function publicIndex(): View
    {
        $services = Service::where('activo', true)->get();

        return view('services.public-index', compact('services'));
    }

    public function create(): View
    {
        return view('services.create');
    }

    public function store(StoreServiceRequest $request): RedirectResponse
    {
        $this->serviceService->create($request->validated());

        return redirect()->route('services.index')->with('status', 'Servicio creado correctamente.');
    }

    public function edit(Service $service): View
    {
        return view('services.edit', compact('service'));
    }

    public function update(UpdateServiceRequest $request, Service $service): RedirectResponse
    {
        $this->serviceService->update($service, $request->validated());

        return redirect()->route('services.index')->with('status', 'Servicio actualizado correctamente.');
    }

    public function destroy(Service $service): RedirectResponse
    {
        $this->serviceService->delete($service);

        return redirect()->route('services.index')->with('status', 'Servicio eliminado correctamente.');
    }
}
