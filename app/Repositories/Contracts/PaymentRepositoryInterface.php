<?php

namespace App\Repositories\Contracts;

interface PaymentRepositoryInterface extends BaseRepositoryInterface
{
    public function paginateWithFilters(array $filters = [], int $perPage = 15);

    // Solo cuenta el cobro final (es_deposito=false/ausente) -- un depósito
    // anti-no-show verificado NO debe bloquear el cobro real del servicio.
    // Ver DepositService, que tiene su propia guarda independiente para
    // depósitos duplicados.
    public function existsForAppointment(string $appointmentId): bool;
}
