<?php

namespace App\Http\Requests\Appointment;

/**
 * Reutiliza exactamente las mismas reglas/mensajes que StoreAppointmentRequest;
 * existe como clase aparte solo para que el type-hint en el controlador de
 * actualización sea explícito (UpdateAppointmentRequest vs Store...).
 */
class UpdateAppointmentRequest extends StoreAppointmentRequest {}
