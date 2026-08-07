<?php

namespace App\Http\Controllers\Client;

use App\Http\Controllers\Controller;
use App\Services\Loyalty\LoyaltyService;
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
        $user = $request->user();
        $client = $user->clientProfile;

        $nivel = $client->nivel ?? 'nuevo';
        $puntos = (int) ($client->puntos ?? 0);
        $label = LoyaltyService::LEVEL_LABELS[$nivel] ?? strtoupper($nivel);
        $discount = LoyaltyService::DISCOUNTS[$nivel] ?? 0;

        $beneficios = [
            ['on' => $discount > 0, 'text' => $discount > 0 ? "{$discount}% de descuento" : 'Sin descuento aun'],
            ['on' => in_array($nivel, ['regular', 'vip', 'leyenda']), 'text' => 'Reserva prioritaria'],
            ['on' => in_array($nivel, ['vip', 'leyenda']), 'text' => 'Sorteo mensual'],
            ['on' => $nivel === 'leyenda', 'text' => 'Producto gratis al mes'],
        ];

        $pdf = Pdf::loadView('pdf.membership-card', [
            'nombre' => $user->name,
            'nivel' => $nivel,
            'label' => $label,
            'puntos' => $puntos,
            'numero' => $this->memberCard->memberNumber($user),
            'desde' => $this->memberCard->memberSince($user),
            'qr' => $this->memberCard->qrPngDataUri($user),
            'beneficios' => $beneficios,
        ]);

        $pdf->setPaper('a6', 'landscape');

        return $pdf->download('tarjeta-urbanblade.pdf');
    }
}
