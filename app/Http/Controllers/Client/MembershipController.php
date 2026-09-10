<?php

namespace App\Http\Controllers\Client;

use App\Http\Controllers\Controller;
use App\Services\Member\MemberCardService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

/**
 * Tarjeta de membresía del cliente (rol cliente): genera un PDF descargable
 * con nivel de lealtad, puntos, beneficios y código QR de identificación.
 */
class MembershipController extends Controller
{
    public function __construct(private readonly MemberCardService $memberCard) {}

    /**
     * Tarjeta de membresia descargable en PDF.
     */
    public function card(Request $request): Response
    {
        $pdf = Pdf::loadView('pdf.membership-card', $this->memberCard->cardPdfData($request->user()));
        $pdf->setPaper('a6', 'landscape');

        return $pdf->download('tarjeta-urbanblade.pdf');
    }
}
