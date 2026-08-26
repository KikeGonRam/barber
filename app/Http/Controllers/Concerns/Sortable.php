<?php

namespace App\Http\Controllers\Concerns;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;

/**
 * Ordenamiento de columnas por click en las tablas del panel (citas,
 * clientes, productos, pagos, usuarios, etc.). Un mismo mecanismo sirve
 * para los tres tipos de columna que existen en el proyecto:
 *
 *   - Texto  (nombre, email, folio...)      -> orden alfabetico (A-Z / Z-A)
 *   - Numero (precio, stock, monto...)      -> orden numerico (menor-mayor / mayor-menor)
 *   - Fecha  (fecha, created_at...)         -> orden cronologico (antigua-reciente / reciente-antigua)
 *
 * MongoDB ordena los tres tipos igual (asc/desc sobre el valor real del
 * campo, sin necesidad de castear), asi que no hace falta logica distinta
 * por tipo de dato: basta con validar que la columna pedida este en la
 * lista blanca de cada controlador para evitar que alguien mande un campo
 * arbitrario (o de otra coleccion) por la URL.
 *
 * ============================================================
 *  COMO AGREGAR/QUITAR UNA COLUMNA ORDENABLE (para cambios de ultima hora)
 * ============================================================
 *
 * El ordenamiento vive en DOS lugares que siempre van juntos:
 *
 * 1) EN EL CONTROLADOR (ej. app/Http/Controllers/Client/ClientController.php),
 *    dentro de index(), busca la linea que dice $this->applySort(...):
 *
 *      $clients = $this->applySort(
 *          $query,
 *          $request,
 *          ['telefono', 'fecha_nacimiento', 'nivel', 'puntos', 'total_citas', 'id'], // <- lista blanca
 *          'id',    // <- columna por defecto si no se pidio ninguna
 *          'desc'   // <- direccion por defecto
 *      )->paginate(20)->withQueryString();
 *
 *    Para AGREGAR una columna nueva: solo agrega el nombre exacto del
 *    campo de MongoDB al arreglo de la lista blanca (segundo argumento).
 *    IMPORTANTE: debe ser un campo propio del documento que se esta
 *    listando (Client, Product, Payment...), NO un campo de una relacion
 *    (ej. no puedes poner 'user.name' porque el nombre vive en el
 *    documento User, no en Client — eso requeriria una agregacion de
 *    Mongo que este mecanismo simple no hace).
 *
 *    Para QUITAR una columna: borrala del arreglo. Asi de facil.
 *
 * 2) EN LA VISTA (ej. resources/views/clients/index.blade.php), busca el
 *    <thead> y encuentra el <th> de esa columna. Para hacerla clicable,
 *    envuelvela con el componente <x-sortable-th>:
 *
 *      Antes:   <th>Teléfono</th>
 *      Despues: <x-sortable-th column="telefono">Teléfono</x-sortable-th>
 *
 *    El atributo column="..." debe ser EXACTAMENTE el mismo nombre que
 *    agregaste a la lista blanca del paso 1. Si no coincide, el click no
 *    hace nada (el controlador lo rechaza y usa el orden por defecto).
 *
 *    Para pantallas que usan tarjetas en vez de tabla (Barberos, Pedidos),
 *    en su lugar hay un <select name="sort"> dentro del formulario de
 *    filtros — agrega ahi una <option value="nombre_del_campo">.
 *
 * Ejemplo completo — si el profesor pide poder ordenar Productos por
 * "categoria" (que ya existe en el modelo pero no estaba en la lista):
 *
 *   1. En ProductController@index, cambiar:
 *        ['nombre', 'stock_actual', 'precio_compra', 'precio_venta']
 *      por:
 *        ['nombre', 'stock_actual', 'precio_compra', 'precio_venta', 'categoria']
 *
 *   2. En resources/views/inventory/products/index.blade.php, cambiar:
 *        <th>Categoría</th>
 *      por:
 *        <x-sortable-th column="categoria">Categoría</x-sortable-th>
 *
 *   3. Guardar y recargar la pagina — no hace falta reiniciar Docker ni
 *      recompilar nada (son archivos PHP/Blade, se interpretan al vuelo).
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
