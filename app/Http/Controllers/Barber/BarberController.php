<?php

namespace App\Http\Controllers\Barber;

use App\Http\Controllers\Controller;
use App\Http\Requests\Barber\UpdateBarberRequest;
use App\Models\Barber;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class BarberController extends Controller
{
    public function index(Request $request): View
    {
        $search = trim((string) $request->query('q', ''));
        $status = (string) $request->query('activo', '');

        $barbers = Barber::query()
            ->with('user:id,name,email')
            ->when($search !== '', function ($query) use ($search): void {
                $query->whereHas('user', function ($userQuery) use ($search): void {
                    $userQuery->where('name', 'like', "%{$search}%")
                        ->orWhere('email', 'like', "%{$search}%");
                });
            })
            ->when($status !== '', function ($query) use ($status): void {
                $query->where('activo', $status === '1');
            })
            ->latest('id')
            ->paginate(15)
            ->withQueryString();

        return view('barbers.index', compact('barbers', 'search', 'status'));
    }

    public function edit(Barber $barber): View
    {
        $barber->load('user:id,name,email');

        return view('barbers.edit', compact('barber'));
    }

    public function update(UpdateBarberRequest $request, Barber $barber): RedirectResponse
    {
        $data = $request->validated();

        $barber->user()->update([
            'name' => $data['name'],
            'email' => $data['email'],
        ]);

        $barber->update([
            'especialidades' => $data['especialidades'] ?? null,
            'descripcion' => $data['descripcion'] ?? null,
            'foto' => $data['foto'] ?? null,
            'activo' => (bool) ($data['activo'] ?? false),
        ]);

        return redirect()->route('barbers.index')->with('status', 'Barbero actualizado correctamente.');
    }
}
