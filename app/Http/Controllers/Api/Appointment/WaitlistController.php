<?php

namespace App\Http\Controllers\Api\Appointment;

use App\Exceptions\Domain\WaitlistException;
use App\Http\Controllers\Controller;
use App\Models\Barber;
use App\Models\Service;
use App\Models\Waitlist;
use App\Services\Appointment\WaitlistService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * @group Lista de espera
 *
 * Un cliente se anota cuando un barbero+servicio+fecha ya no tiene
 * horarios disponibles, para que se le avise si se libera uno.
 */
class WaitlistController extends Controller
{
    public function __construct(
        private readonly WaitlistService $waitlist,
    ) {}

    /**
     * Anotarse en la lista de espera
     *
     * @authenticated
     *
     * @bodyParam barber_id string required ID del barbero. Example: 66f0...
     * @bodyParam service_id string required ID del servicio. Example: 66f1...
     * @bodyParam fecha date required Fecha deseada (YYYY-MM-DD). Example: 2026-09-20
     */
    public function store(Request $request): JsonResponse
    {
        $user = $request->user();
        abort_unless($user?->hasRole('cliente') && $user->clientProfile, 403, 'No autorizado.');

        $validated = $request->validate([
            'barber_id' => ['required', 'string', 'exists:barbers,id'],
            'service_id' => ['required', 'string', 'exists:services,id'],
            'fecha' => ['required', 'date', 'after_or_equal:today'],
        ]);

        $barber = Barber::findOrFail($validated['barber_id']);
        $service = Service::findOrFail($validated['service_id']);

        try {
            $entry = $this->waitlist->join($user->clientProfile, $barber, $service, $validated['fecha']);
        } catch (WaitlistException $exception) {
            return response()->json(['message' => $exception->getMessage()], 422);
        }

        return response()->json([
            'message' => 'Te anotamos en la lista de espera. Te avisaremos si se libera un horario.',
            'data' => $this->payload($entry),
        ], 201);
    }

    /**
     * Mis anotaciones en lista de espera (cliente) o, para staff, las de
     * un barbero+fecha (filtros opcionales) -- vista operativa de cuánta
     * demanda hay para un día ya lleno.
     */
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        $isStaff = $user?->hasAnyRole(['administrador', 'recepcionista']);
        $isClient = $user?->hasRole('cliente') && $user->clientProfile;

        abort_unless($isStaff || $isClient, 403, 'No autorizado.');

        $query = Waitlist::query()->with(['client.user', 'barber.user', 'service']);

        if ($isClient && ! $isStaff) {
            $query->where('client_id', (string) $user->clientProfile->id);
        } else {
            $query->when($request->filled('barber_id'), fn ($q) => $q->where('barber_id', $request->query('barber_id')))
                ->when($request->filled('fecha'), fn ($q) => $q->whereDate('fecha', $request->query('fecha')))
                ->when($request->filled('estado'), fn ($q) => $q->where('estado', $request->query('estado')));
        }

        $entries = $query->latest()->get();

        return response()->json([
            'data' => $entries->map(fn (Waitlist $entry) => $this->payload($entry))->values(),
        ]);
    }

    /**
     * Cancela la propia anotación.
     */
    public function destroy(Request $request, Waitlist $waitlist): JsonResponse
    {
        $user = $request->user();
        $isOwner = $user?->hasRole('cliente') && $user->clientProfile && (string) $waitlist->client_id === (string) $user->clientProfile->id;

        abort_unless($isOwner, 403, 'No autorizado.');

        try {
            $this->waitlist->cancel($waitlist);
        } catch (WaitlistException $exception) {
            return response()->json(['message' => $exception->getMessage()], 422);
        }

        return response()->json(['message' => 'Saliste de la lista de espera.']);
    }

    private function payload(Waitlist $entry): array
    {
        return [
            'id' => $entry->id,
            'estado' => $entry->estado,
            'fecha' => optional($entry->fecha)->toDateString(),
            'notificado_en' => optional($entry->notificado_en)->toIso8601String(),
            'client' => ['id' => $entry->client?->id, 'name' => $entry->client?->user?->name],
            'barber' => ['id' => $entry->barber?->id, 'name' => $entry->barber?->user?->name],
            'service' => ['id' => $entry->service?->id, 'nombre' => $entry->service?->nombre],
        ];
    }
}
