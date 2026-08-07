<?php

namespace App\Http\Requests\Service;

/**
 * Misma validación que StoreServiceRequest; clase separada solo para el
 * type-hint explícito en el controlador de actualización.
 */
class UpdateServiceRequest extends StoreServiceRequest {}
