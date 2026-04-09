# API: Inventario

## GET /api/v1/inventory/products

Lista productos del inventario (solo staff autorizado).

### Headers:
- Authorization: Bearer {token}

### Parámetros de consulta (query string):
- categoria (string, opcional): Filtrar por categoría
- tipo (string, opcional): Filtrar por tipo

### Ejemplo de request
```
GET /api/v1/inventory/products?categoria=shampoo&tipo=venta
Authorization: Bearer eyJ0eXAiOiJKV1QiLCJhbGci...
```

### Respuesta exitosa (200)
```json
{
  "data": [
    {
      "id": 1,
      "nombre": "Shampoo Premium",
      "categoria": "shampoo",
      "descripcion": "Shampoo para todo tipo de cabello",
      "tipo": "venta",
      "stock_actual": 12,
      "stock_minimo": 5,
      "precio_compra": 3.5,
      "precio_venta": 8.0,
      "active": true,
      "low_stock": false,
      "imagen_url": "https://.../storage/products/shampoo-premium.jpg"
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

## GET /api/v1/inventory/movements

Lista movimientos de inventario (solo staff autorizado).

### Headers:
- Authorization: Bearer {token}

### Parámetros de consulta (query string):
- tipo (string, opcional): Filtrar por tipo de movimiento (entrada, salida, ajuste, etc.)
- product_id (int, opcional): Filtrar por producto

### Ejemplo de request
```
GET /api/v1/inventory/movements?tipo=salida&product_id=1
Authorization: Bearer eyJ0eXAiOiJKV1QiLCJhbGci...
```

### Respuesta exitosa (200)
```json
{
  "data": [
    {
      "id": 10,
      "tipo": "salida",
      "cantidad": 2,
      "motivo": "Venta mostrador",
      "fecha": "2026-04-09T10:00:00+00:00",
      "product": {
        "id": 1,
        "nombre": "Shampoo Premium"
      },
      "user": {
        "id": 2,
        "name": "Ana López"
      },
      "appointment": {
        "id": 5,
        "fecha": "2026-04-09",
        "client": "Juan Pérez"
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
