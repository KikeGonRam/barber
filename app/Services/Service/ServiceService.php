<?php

namespace App\Services\Service;

use App\Models\Service;
use App\Repositories\Contracts\ServiceRepositoryInterface;

/**
 * Capa fina de orquestacion sobre el catalogo de servicios (cortes,
 * tratamientos, etc.) ofrecidos por la barberia. Delega la persistencia
 * al ServiceRepositoryInterface, sin logica de negocio propia.
 */
class ServiceService
{
    public function __construct(private readonly ServiceRepositoryInterface $services) {}

    /**
     * Lista servicios paginados aplicando filtros del repositorio.
     */
    public function list(array $filters = [], int $perPage = 15)
    {
        return $this->services->paginateWithFilters($filters, $perPage);
    }

    /**
     * Devuelve las categorias distintas de servicio (para filtros/UI).
     */
    public function categories(): array
    {
        return $this->services->getCategories();
    }

    /**
     * Crea un nuevo servicio en el catalogo.
     */
    public function create(array $payload): Service
    {
        return $this->services->create($payload);
    }

    /**
     * Actualiza un servicio existente.
     */
    public function update(Service $service, array $payload): bool
    {
        return $this->services->update($service->id, $payload);
    }

    /**
     * Elimina un servicio del catalogo.
     */
    public function delete(Service $service): bool
    {
        return $this->services->delete($service->id);
    }
}
