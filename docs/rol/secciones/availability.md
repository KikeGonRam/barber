# API: Disponibilidad

## GET /api/v1/availability/slots

Consulta los horarios disponibles para agendar una cita con un barbero y servicio en una fecha específica.

### Parámetros de consulta (query string):
- barber_id (int, requerido): ID del barbero
- service_id (int, requerido): ID del servicio
- date (date, requerido, formato YYYY-MM-DD): Fecha a consultar

### Ejemplo de request
```
GET /api/v1/availability/slots?barber_id=3&service_id=2&date=2026-04-10
```

### Respuesta exitosa (200)
```json
{
  "slots": [
    "10:00",
    "10:30",
    "11:00",
    "11:30"
  ]
}
```

### Respuesta error validación (422)
```json
{
  "message": "The given data was invalid.",
  "errors": {
    "barber_id": ["The barber id field is required."],
    "service_id": ["The service id field is required."],
    "date": ["The date field is required."]
  }
}
```
