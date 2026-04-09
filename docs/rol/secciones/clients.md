# API: Clientes

## GET /api/v1/clients

Lista clientes del sistema (solo administradores y recepcionistas).

### Headers:
- Authorization: Bearer {token}

### Parámetros de consulta (query string):
- q (string, opcional): Búsqueda por nombre o email

### Ejemplo de request
```
GET /api/v1/clients?q=ana
Authorization: Bearer eyJ0eXAiOiJKV1QiLCJhbGci...
```

### Respuesta exitosa (200)
```json
{
  "data": [
    {
      "id": 5,
      "telefono": "+34123456789",
      "fecha_nacimiento": "1990-05-10",
      "created_at": "2026-04-01T09:00:00+00:00",
      "appointments_count": 3,
      "preferencias_notificacion": {
        "in_app": true,
        "email": true,
        "sms": false,
        "whatsapp": false
      },
      "user": {
        "id": 2,
        "name": "Ana López",
        "email": "ana@ejemplo.com"
      }
    }
  ],
  "meta": {
    "current_page": 1,
    "last_page": 1,
    "per_page": 15,
    "total": 1
  },
  "filters": {
    "q": "ana"
  }
}
```

### Respuesta no autorizado (403)
```json
{
  "message": "Solo administradores y recepcionistas pueden consultar clientes."
}
```
