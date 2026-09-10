<?php

namespace App\Http\Controllers\Api\Dashboard;

use App\Http\Controllers\Controller;
use App\Services\Member\MemberCardService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

/**
 * Tarjeta de membresia del cliente (rol cliente): mismo PDF que
 * Client\MembershipController (Blade, sesion web), servido aqui via token
 * Bearer para el boton "Descargar tarjeta" de MembershipCard.vue.
 */
class MembershipController extends Controller
{
    public function __construct(private readonly MemberCardService $memberCard) {}

    public function downloadCard(Request $request): Response
    {
        $user = $request->user();
        abort_unless($user->hasRole('cliente') && $user->clientProfile, 403, 'Solo clientes tienen tarjeta de membresia.');

        $pdf = Pdf::loadView('pdf.membership-card', $this->memberCard->cardPdfData($user));
        $pdf->setPaper('a6', 'landscape');

        return $pdf->download('tarjeta-urbanblade.pdf');
    }
}
