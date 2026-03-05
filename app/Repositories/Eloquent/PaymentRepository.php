<?php

namespace App\Repositories\Eloquent;

use App\Models\Payment;
use App\Repositories\Contracts\PaymentRepositoryInterface;

class PaymentRepository extends BaseRepository implements PaymentRepositoryInterface
{
    public function __construct(Payment $model)
    {
        parent::__construct($model);
    }

    public function paginateWithFilters(array $filters = [], int $perPage = 15)
    {
        return $this->model->newQuery()
            ->with(['appointment.client.user', 'appointment.barber.user', 'creator'])
            ->when(isset($filters['metodo_pago']) && $filters['metodo_pago'] !== '', function ($query) use ($filters) {
                $query->where('metodo_pago', $filters['metodo_pago']);
            })
            ->latest()
            ->paginate($perPage)
            ->withQueryString();
    }

    public function existsForAppointment(int $appointmentId): bool
    {
        return $this->model->newQuery()
            ->where('appointment_id', $appointmentId)
            ->exists();
    }
}
