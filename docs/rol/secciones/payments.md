# API: Pagos (Payments)

## GET /api/v1/payments

Lista pagos registrados (solo staff autorizado).

### Headers:
- Authorization: Bearer {token}

### Parámetros de consulta (query string):
- metodo_pago (string, opcional): Filtrar por método de pago (efectivo, tarjeta, transferencia, qr)

### Ejemplo de request
```
GET /api/v1/payments?metodo_pago=efectivo
Authorization: Bearer eyJ0eXAiOiJKV1QiLCJhbGci...
```

### Respuesta exitosa (200)
```json
{
  "data": [
    {
      "id": 7,
      "monto": 20.0,
      "metodo_pago": "efectivo",
      "propina": 2.0,
      "receipt_url": "https://.../storage/receipts/recibo-7.pdf",
      "created_at": "2026-04-09T10:00:00+00:00",
      "appointment": {
        "id": 10,
        "fecha": "2026-04-09",
        "hora_inicio": "10:00:00",
        "service": "Corte de cabello",
        "client": "Ana López",
        "barber": "Carlos Ruiz"
      },
      "creator": {
        "id": 2,
        "name": "Ana López"
      }
    }
  ],
  "meta": {
    "current_page": 1,
    "last_page": 1,
    "total": 1
  }
}
```

---

## POST /api/v1/payments

Registra un nuevo pago (solo staff autorizado).

### Headers:
- Authorization: Bearer {token}

### Parámetros (JSON):
- appointment_id (int, requerido)
- monto (float, requerido)
- metodo_pago (string, requerido): efectivo, tarjeta, transferencia, qr
- propina (float, opcional)

### Ejemplo de request
```json
{
  "appointment_id": 10,
  "monto": 20.0,
  "metodo_pago": "efectivo",
  "propina": 2.0
}
```

### Respuesta exitosa (201)
```json
{
  "message": "Pago registrado correctamente.",
  "data": {
    "id": 7,
    "monto": 20.0,
    "metodo_pago": "efectivo",
    "propina": 2.0,
    "receipt_url": "https://.../storage/receipts/recibo-7.pdf",
    "appointment": {
      "id": 10,
      "fecha": "2026-04-09",
      "service": "Corte de cabello"
    }
  }
}
```
