---
name: backend-performance-latency
description: 'Directrices arquitectónicas, reglas de optimización de consultas y mitigación de latencia para Laravel 13 con MongoDB Atlas M0 y Redis (UrbanBlade). Consultar antes de agregar consultas en controladores/servicios, diseñar reportes o agregaciones, crear índices de MongoDB, o modificar configuración de caché y colas.'
---

# Rendimiento, Optimización de Consultas y Mitigación de Latencia en Laravel 13 + MongoDB Atlas (UrbanBlade)

## Objetivo

Garantizar que el backend de UrbanBlade responda con latencia ultra-baja (<150ms en endpoints estándar, <300ms en dashboards agregados), contrarrestando la sobrecarga de red inherente a la comunicación con **MongoDB Atlas M0 (Free Tier)** sobre internet y la penalización de I/O en entornos Docker con volúmenes montados.

---

## 1. El Reto de Arquitectura de UrbanBlade

1. **MongoDB Atlas M0 en la Nube:**
   - La base de datos no es local; reside en un cluster Atlas gratuito accesible vía TLS por internet.
   - Cada round-trip de red hacia Atlas cuesta entre **50ms y 120ms**.
   - **Consecuencia crítica:** Si un endpoint ejecuta 10 consultas secuenciales a la base de datos, el usuario experimentará más de **1 segundo de retraso** únicamente en latencia de red, independientemente de la velocidad de PHP.
2. **Ausencia de JOINs Nativos en MongoDB:**
   - En MongoDB no existen los `JOIN` relacionales de SQL.
   - Las relaciones de Eloquent (`with()`) se resuelven mediante múltiples consultas secuenciales o por lotes (`$in`).
3. **Escrituras con `w=majority`:**
   - La configuración de conexión en `config/database.php` exige confirmación de la mayoría de nodos del replica set de Atlas para garantizar consistencia. Cada `save()` o `insert()` puede tomar **150ms a 300ms**.

---

## 2. Principios Innegociables de Rendimiento

1. **Cero Consultas N+1 (Prohibición Absoluta):**
   - Jamás acceder a relaciones de Eloquent (`$appointment->service`, `$appointment->barber`, `$appointment->client->user`) dentro de un bucle `foreach` o `map` sin eager loading previo.
   - Si se requiere relacionar datos en lotes, precargar con `with()` o recolectar los IDs y ejecutar una sola consulta `whereIn()`.
2. **Proyección Estricta de Campos (`select` / `get([...])`):**
   - Los documentos BSON en MongoDB pueden contener campos grandes (historial de logs, preferencias JSON, metadatos OCR).
   - Prohibido hacer `Model::all()` o `->get()` sin acotar campos en consultas de listados o agregaciones. Seleccionar explícitamente solo lo que se va a utilizar:
     ```php
     // BIEN: solo viajan los campos requeridos por internet
     Payment::where('created_at', '>=', $start)->get(['created_at', 'monto', 'propina']);
     ```
3. **Todo Filtro y Ordenamiento Debe Estar Respaldado por un Índice:**
   - Toda consulta que use `where()`, `whereBetween()`, `whereIn()` o `orderBy()` debe tener un índice (o índice compuesto) definido en las migraciones de MongoDB.
   - Una consulta sin índice provoca un **Full Collection Scan (COLLSCAN)**, que en Atlas M0 agota las unidades de cómputo y causa timeouts de socket.
4. **Agregaciones en el Motor de Base de Datos para Grandes Volúmenes:**
   - Para reportes históricos o analítica sobre cientos de miles de registros, usar el aggregation pipeline de MongoDB (`Appointment::raw()`) en lugar de traer miles de modelos a la memoria de PHP.
   - Asegurar que el pipeline inicie con una etapa `$match` indexada para filtrar documentos antes de proyectar o agrupar.

---

## 3. Patrones de Optimización Práctica

### A. Eager Loading Selectivo
Al cargar relaciones anidadas, especificar las columnas para reducir el payload transferido:
```php
// BIEN: Eager loading con columnas restringidas
$appointments = Appointment::query()
    ->with([
        'client:id,user_id,telefono',
        'client.user:id,name,email',
        'barber:id,user_id,especialidad',
        'barber.user:id,name',
        'service:id,nombre,precio,duracion_minutos'
    ])
    ->limit(50)
    ->get();
```

### B. Estrategia de Caché en Redis para Datos Frecuentes
UrbanBlade cuenta con un contenedor Redis activo (`barber-redis`). Los datos de configuración y catálogos deben servirse desde memoria:

```php
// 1. Catálogo de servicios y configuración del negocio (TTL largo)
$services = Cache::remember('catalog.services.active', 3600, function () {
    return Service::where('activo', true)->get(['id', 'nombre', 'precio', 'duracion_minutos']);
});

// 2. Métricas de Dashboard (TTL corto con clave acotada)
$adminMetrics = Cache::remember('dashboard.admin.metrics', 120, fn () => $this->buildAdminMetrics());
$barberMetrics = Cache::remember("dashboard.barber.{$barberId}", 60, fn () => $this->buildBarberMetrics($barberId));
```
**Regla de invalidación:** Todo evento de mutación (creación, edición o borrado de servicios, citas o configuración) debe invalidar la clave de caché correspondiente (`Cache::forget(...)`).

### C. Consolidación de Consultas Temporales (In-Memory Grouping)
En lugar de disparar 7 o 12 consultas separadas para calcular métricas por rangos de días:
```php
// MAL: 7 consultas separadas a Atlas M0 (7 * 100ms = 700ms de latencia)
for ($i = 0; $i < 7; $i++) {
    $count = Appointment::whereDate('fecha', $date)->count();
}

// BIEN: 1 sola consulta acotada al rango completo y agrupada en memoria con Collections de PHP (100ms)
$appointments = Appointment::whereBetween('fecha', [$weekStart, $weekEnd])
    ->get(['fecha'])
    ->groupBy(fn ($a) => substr((string) $a->fecha, 0, 10));
```

### D. Delegación Asíncrona a la Cola de Redis (Queue Worker)
Cualquier tarea que no sea estrictamente necesaria para generar la respuesta HTTP inmediata debe enviarse a la cola (`QUEUE_CONNECTION=redis`):
- Envíos de correos electrónicos transaccionales y notificaciones Push Web.
- Procesamiento OCR de comprobantes de transferencia.
- Generación de comprobantes PDF pesados.
- Notificaciones a Telegram / Slack o alertas de bajo stock.

---

## 4. Índices Compuestos en MongoDB

Al agregar nuevas consultas, verificar que los índices cubran los prefijos de igualdad y rango (regla ESR: Equality, Sort, Range):

```php
// Ejemplo de migración segura con índice compuesto:
$this->safeIndex('appointments', function (Blueprint $c) {
    // Cubre: where('barber_id', ...)->where('estado', ...)->whereBetween('fecha', ...)
    $c->index(['barber_id', 'estado', 'fecha']);
    $c->index(['client_id', 'estado', 'fecha']);
});
```

---

## 5. Checklist de Rendimiento antes de Todo Commit/PR

- [ ] ¿Se eliminó cualquier consulta dentro de bucles (`foreach`/`map`)?
- [ ] ¿Las relaciones cargadas usan `with()` con selección explícita de campos?
- [ ] ¿Los listados paginados tienen límite razonable (`paginate(15)` o `limit(50)`)?
- [ ] ¿Las consultas nuevas cuentan con un índice en MongoDB que las respalde?
- [ ] ¿Los endpoints analíticos o de dashboard utilizan `Cache::remember`?
- [ ] ¿Los envíos de correo, push y tareas pesadas se delegan al `worker` de Redis vía Jobs/Events?
- [ ] ¿Se ejecutó `.\test.ps1` verificando que la suite complete sin degradación de tiempos?
