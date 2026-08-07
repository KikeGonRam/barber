<?php

namespace App\Http\Requests\Inventory;

/**
 * Misma validación que StoreProductRequest; clase separada solo para el
 * type-hint explícito en el controlador de actualización.
 */
class UpdateProductRequest extends StoreProductRequest {}
