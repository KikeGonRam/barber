# API: Logs

## GET /api/v1/logs

Lista logs de actividad del sistema (solo administradores).

### Headers:
- Authorization: Bearer {token}

### Parámetros de consulta (query string):
- q (string, opcional): Búsqueda por descripción, evento o log_name
- log_name (string, opcional): Filtrar por nombre de log

### Ejemplo de request
```
GET /api/v1/logs?q=login&log_name=auth
Authorization: Bearer eyJ0eXAiOiJKV1QiLCJhbGci...
```

### Respuesta exitosa (200)
```json
{
  "data": [
    {
      "id": 101,
      "log_name": "auth",
      "description": "Usuario inició sesión",
      "event": "login",
      "subject_type": "App\\Models\\User",
      "subject_id": 2,
      "properties": {
        "ip": "192.168.1.10"
      },
      "created_at": "2026-04-09T10:00:00+00:00",
      "causer": {
        "id": 2,
        "name": "Ana López",
        "email": "ana@ejemplo.com"
      }
    }
  ],
  "meta": {
    "current_page": 1,
    "last_page": 1,
    "per_page": 20,
    "total": 1
  },
  "filters": {
    "q": "login",
    "log_name": "auth"
  },
  "log_names": [
    "auth",
    "appointments",
    "inventory"
  ]
}
```

### Respuesta no autorizado (403)
```json
{
  "message": "Solo administradores pueden consultar logs."
}
```
