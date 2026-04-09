# API: Usuarios

## GET /api/v1/users

Lista usuarios del sistema (solo administradores).

### Headers:
- Authorization: Bearer {token}

### Parámetros de consulta (query string):
- q (string, opcional): Búsqueda por nombre o email
- role (string, opcional): Filtrar por rol

### Ejemplo de request
```
GET /api/v1/users?q=juan&role=cliente
Authorization: Bearer eyJ0eXAiOiJKV1QiLCJhbGci...
```

### Respuesta exitosa (200)
```json
{
  "data": [
    {
      "id": 2,
      "name": "Juan Pérez",
      "email": "cliente@ejemplo.com",
      "email_verified_at": "2026-04-09T10:00:00+00:00",
      "created_at": "2026-04-01T09:00:00+00:00",
      "roles": ["cliente"]
    },
    {
      "id": 1,
      "name": "Admin",
      "email": "admin@ejemplo.com",
      "email_verified_at": "2026-04-01T08:00:00+00:00",
      "created_at": "2026-04-01T08:00:00+00:00",
      "roles": ["administrador"]
    }
  ],
  "meta": {
    "current_page": 1,
    "last_page": 1,
    "per_page": 15,
    "total": 2
  },
  "filters": {
    "q": "juan",
    "role": "cliente"
  },
  "roles": [
    "administrador",
    "barbero",
    "cliente",
    "recepcionista"
  ]
}
```

### Respuesta no autorizado (403)
```json
{
  "message": "Solo administradores pueden consultar usuarios."
}
```
