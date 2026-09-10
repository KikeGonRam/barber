<?php

namespace App\Http\Controllers\Concerns;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;

/**
 * Ordenamiento seguro por query params (`sort`, `dir`) para endpoints JSON
 * que devuelven listados. MongoDB ordena texto/numero/fecha igual (asc/desc
 * sobre el valor real del campo, sin castear), asi que basta con validar la
 * columna pedida contra una lista blanca por controlador para evitar que se
 * mande un campo arbitrario (o de otra coleccion) por la URL.
 *
 * Originalmente pensado para las tablas del panel Blade/Alpine (con el
 * componente <x-sortable-th> aplicando el click-to-sort en el <th>), pero
 * ese panel se retiro por completo el 2026-09-06 junto con el componente.
 * Hoy solo lo usa `Api\Review\ReviewController::index()` (aditivo: solo
 * afecta el orden de la respuesta JSON, no requiere ningun elemento
 * clicable del lado del consumidor).
 *
 * Para agregar una columna ordenable a un endpoint: agrega el nombre exacto
 * del campo de MongoDB al arreglo de lista blanca en la llamada a
 * applySort() de ese controlador. Debe ser un campo propio del documento
 * que se esta listando, no un campo de una relacion.
 */
trait Sortable
{
    /**
     * Aplica ->orderBy() a partir de los query params `sort` y `dir` de la
     * request, validando `sort` contra una lista blanca de columnas
     * permitidas para ese listado. Si no viene nada en la URL (primera
     * carga de la pantalla), usa el orden por defecto que ya tenia el
     * controlador antes de este cambio.
     *
     * @param  Builder|\MongoDB\Laravel\Eloquent\Builder  $query
     * @param  array<string>  $allowedColumns  columnas que se pueden ordenar en este listado
     * @param  string  $defaultColumn  columna usada si no hay `sort` en la URL
     * @param  string  $defaultDirection  'asc' o 'desc', usada si no hay `dir` en la URL
     */
    protected function applySort(
        $query,
        Request $request,
        array $allowedColumns,
        string $defaultColumn,
        string $defaultDirection = 'asc'
    ) {
        $column = (string) $request->query('sort', $defaultColumn);
        $direction = strtolower((string) $request->query('dir', $defaultDirection)) === 'desc' ? 'desc' : 'asc';

        // Lista blanca: si mandan una columna que no existe en este listado
        // (o que no queremos exponer, como un campo interno), caemos de
        // vuelta a la columna por defecto en vez de fallar o de permitir
        // ordenar por cualquier cosa.
        if (! in_array($column, $allowedColumns, true)) {
            $column = $defaultColumn;
            $direction = strtolower($defaultDirection) === 'desc' ? 'desc' : 'asc';
        }

        return $query->orderBy($column, $direction);
    }
}
