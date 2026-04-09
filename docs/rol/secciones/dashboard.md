# API: Dashboard

## GET /api/v1/dashboard

Devuelve métricas y datos del dashboard según el rol del usuario autenticado.

### Headers:
- Authorization: Bearer {token}

### Ejemplo de request
```
GET /api/v1/dashboard
Authorization: Bearer eyJ0eXAiOiJKV1QiLCJhbGci...
```

### Respuesta ejemplo (administrador)
```json
{
  "role": "administrador",
  "data": {
    "total_usuarios": 25,
    "total_citas": 120,
    "total_ingresos": 1500.0,
    "citas_hoy": 8
  }
}
```

### Respuesta ejemplo (barbero)
```json
{
  "role": "barbero",
  "data": {
    "citas_hoy": 4,
    "citas_pendientes": 2,
    "ingresos_mes": 300.0
  }
}
```

### Respuesta ejemplo (cliente)
```json
{
  "role": "cliente",
  "data": {
    "citas_proximas": 1,
    "historial_citas": 5
  }
}
```

### Respuesta no autorizado (401)
```json
{
  "message": "No autorizado."
}
```
