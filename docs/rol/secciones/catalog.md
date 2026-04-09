# API: Catálogo

## GET /api/v1/services

Lista los servicios activos ofrecidos por la barbería.

### Ejemplo de request
```
GET /api/v1/services
```

### Respuesta exitosa (200)
```json
{
  "data": [
    {
      "id": 2,
      "nombre": "Corte de cabello",
      "categoria": "cabello",
      "precio": 15.0,
      "duracion_min": 30,
      "imagen": "services/corte-cabello.jpg",
      "descripcion": "Corte clásico para caballero."
    }
  ]
}
```

---

## GET /api/v1/barbers

Lista los barberos activos.

### Ejemplo de request
```
GET /api/v1/barbers
```

### Respuesta exitosa (200)
```json
{
  "data": [
    {
      "id": 3,
      "name": "Carlos Ruiz",
      "especialidades": ["cabello", "barba"],
      "foto": "barbers/carlos-ruiz.jpg",
      "descripcion": "Especialista en cortes modernos."
    }
  ]
}
```
