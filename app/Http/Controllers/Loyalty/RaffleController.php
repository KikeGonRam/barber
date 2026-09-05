<?php

namespace App\Http\Controllers\Loyalty;

use App\Http\Controllers\Controller;
use App\Models\RaffleResult;
use Illuminate\View\View;

/**
 * Historial de sorteos mensuales para administración: quién ganó, qué
 * premio, y si ya lo reclamó, sigue vigente o caducó. Antes no existía
 * ninguna pantalla para ver esto — un ganador solo aparecía en el
 * dashboard del propio cliente, sin que admin pudiera verificar nada.
 */
class RaffleController extends Controller
{
    public function index(): View
    {
        $results = RaffleResult::query()
            ->with('client.user:id,name')
            ->orderByDesc('mes')
            ->paginate(20);

        $stats = [
            'total' => RaffleResult::count(),
            'reclamados' => RaffleResult::whereNotNull('reclamado_en')->count(),
            'vigentes' => RaffleResult::whereNull('reclamado_en')->where('vence_en', '>=', now())->count(),
        ];

        return view('raffles.index', compact('results', 'stats'));
    }
}
