@props(['column', 'align' => 'left'])

@php
    // Encabezado de columna clicable para ordenar tablas (citas, clientes,
    // productos, pagos, usuarios...). Funciona con el trait
    // App\Http\Controllers\Concerns\Sortable del lado del servidor: este
    // componente solo arma el link con los query params `sort`/`dir`,
    // conservando cualquier otro filtro ya activo (busqueda, fechas, etc.)
    // via request()->query().
    //
    // USO: <x-sortable-th column="nombre_del_campo">Texto del encabezado</x-sortable-th>
    // El "column" debe existir en la lista blanca que ese mismo controlador
    // le pasa a applySort() (ver App\Http\Controllers\Concerns\Sortable
    // para la guia completa de como agregar/quitar columnas ordenables).
    $currentSort = request()->query('sort');
    $currentDir = request()->query('dir', 'asc');
    $isActive = $currentSort === $column;

    // Si ya estamos ordenando por esta columna, el click invierte la
    // direccion (asc <-> desc); si es una columna nueva, empieza en asc
    // (alfabetico A-Z / numerico menor-mayor / fecha mas antigua primero).
    $nextDir = $isActive && $currentDir === 'asc' ? 'desc' : 'asc';

    $queryParams = array_merge(request()->query(), ['sort' => $column, 'dir' => $nextDir]);
    $alignClass = $align === 'right' ? 'text-right' : ($align === 'center' ? 'text-center' : 'text-left');
@endphp

<th class="{{ $alignClass }}">
    <a href="{{ request()->url() }}?{{ http_build_query($queryParams) }}"
       class="inline-flex items-center gap-1 hover:text-gold transition-colors {{ $isActive ? 'text-gold' : '' }}">
        {{ $slot }}
        <span class="inline-flex flex-col leading-none text-[8px] -space-y-0.5">
            <svg class="h-2 w-2 {{ $isActive && $currentDir === 'asc' ? 'text-gold' : 'text-white/20' }}" viewBox="0 0 12 12" fill="currentColor"><path d="M6 2l4 5H2z"/></svg>
            <svg class="h-2 w-2 {{ $isActive && $currentDir === 'desc' ? 'text-gold' : 'text-white/20' }}" viewBox="0 0 12 12" fill="currentColor"><path d="M6 10L2 5h8z"/></svg>
        </span>
    </a>
</th>
