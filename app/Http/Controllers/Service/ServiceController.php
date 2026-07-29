<?php

namespace App\Http\Controllers\Service;

use App\Http\Controllers\Controller;
use App\Http\Requests\Service\StoreServiceRequest;
use App\Http\Requests\Service\UpdateServiceRequest;
use App\Models\Service;
use App\Services\Service\ServiceService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;

class ServiceController extends Controller
{
    public function __construct(private readonly ServiceService $serviceService) {}

    public function index(Request $request): View
    {
        $filters = $request->only(['q', 'categoria', 'activo']);

        $services = Service::query()
            ->when(! empty($filters['q']), fn ($query) => $query->where('nombre', 'like', '%'.$filters['q'].'%')
                ->orWhere('descripcion', 'like', '%'.$filters['q'].'%'))
            ->when(isset($filters['categoria']) && $filters['categoria'] !== '', fn ($q) => $q->where('categoria', $filters['categoria']))
            ->when(isset($filters['activo']) && $filters['activo'] !== '', fn ($q) => $q->where('activo', (bool) $filters['activo']))
            ->orderBy('nombre')
            ->paginate(20)
            ->withQueryString();

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
        $data = $request->validated();
        if ($request->hasFile('imagen')) {
            $data['imagen'] = $request->file('imagen')->store('services', 'public');
        }
        $this->serviceService->create($data);

        return redirect()->route('services.index')->with('status', 'Servicio creado correctamente.');
    }

    public function edit(Service $service): View
    {
        return view('services.edit', compact('service'));
    }

    public function update(UpdateServiceRequest $request, Service $service): RedirectResponse
    {
        $data = $request->validated();
        if ($request->hasFile('imagen')) {
            if ($service->imagen) {
                Storage::disk('public')->delete($service->imagen);
            }
            $data['imagen'] = $request->file('imagen')->store('services', 'public');
        } else {
            unset($data['imagen']);
        }
        $this->serviceService->update($service, $data);

        return redirect()->route('services.index')->with('status', 'Servicio actualizado correctamente.');
    }

    public function destroy(Service $service): RedirectResponse
    {
        $this->serviceService->delete($service);

        return redirect()->route('services.index')->with('status', 'Servicio eliminado correctamente.');
    }
}
