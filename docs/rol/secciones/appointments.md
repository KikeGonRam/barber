# API: Citas (Appointments)

## GET /api/v1/appointments

Lista las citas del usuario autenticado (cliente/barbero) o todas si es admin.

### Headers:
- Authorization: Bearer {token}

### Respuesta exitosa (200)
```json
{
  "data": [
    {
      "id": 10,
      "fecha": "2026-04-10",
      "hora_inicio": "10:00:00",
      "hora_fin": "10:30:00",
      "estado": "pendiente",
      "notas": "Corte clásico",
      "precio_cobrado": 15.0,
      "client": {
        "id": 5,
        "name": "Ana López"
      },
      "barber": {
        "id": 3,
        "name": "Carlos Ruiz"
      },
      "service": {
        "id": 2,
        "nombre": "Corte de cabello",
        "precio": 15.0,
        "duracion_min": 30
      }
    }
  ]
}
```

---

## POST /api/v1/appointments

Crea una nueva cita (solo clientes).

### Headers:
- Authorization: Bearer {token}

### Parámetros (JSON):
- barber_id (int, requerido)
- service_id (int, requerido)
- fecha (date, requerido, formato YYYY-MM-DD)
- hora_inicio (string, requerido, formato HH:mm)
- notas (string, opcional)

### Ejemplo de request
```json
{
  "barber_id": 3,
  "service_id": 2,
  "fecha": "2026-04-10",
  "hora_inicio": "10:00",
  "notas": "Corte clásico"
}
```

### Respuesta exitosa (201)
```json
{
  "message": "Cita creada correctamente.",
  "data": {
    "id": 10,
    "fecha": "2026-04-10",
    "hora_inicio": "10:00:00",
    "hora_fin": "10:30:00",
    "estado": "pendiente",
    "notas": "Corte clásico",
    "precio_cobrado": 15.0,
    "client": {"id": 5, "name": "Ana López"},
    "barber": {"id": 3, "name": "Carlos Ruiz"},
    "service": {"id": 2, "nombre": "Corte de cabello", "precio": 15.0, "duracion_min": 30}
  }
}
```

### Respuesta conflicto de agenda (422)
```json
{
  "message": "El barbero ya tiene una cita en ese horario."
}
```

---

## PATCH /api/v1/appointments/{appointment}/status

Actualiza el estado de una cita (solo barberos dueños de la cita).

### Headers:
- Authorization: Bearer {token}

### Parámetros (JSON):
- estado (string, requerido): pendiente, confirmada, en_proceso, completada, cancelada, no_asistio
- notas (string, opcional)

### Ejemplo de request
```json
{
  "estado": "completada",
  "notas": "Cliente satisfecho"
}
```

### Respuesta exitosa (200)
```json
{
  "message": "Estado actualizado correctamente.",
  "data": {
    "id": 10,
    "fecha": "2026-04-10",
    "hora_inicio": "10:00:00",
    "hora_fin": "10:30:00",
    "estado": "completada",
    "notas": "Cliente satisfecho",
    "precio_cobrado": 15.0,
    "client": {"id": 5, "name": "Ana López"},
    "barber": {"id": 3, "name": "Carlos Ruiz"},
    "service": {"id": 2, "nombre": "Corte de cabello", "precio": 15.0, "duracion_min": 30}
  }
}
```

---

## DELETE /api/v1/appointments/{appointment}

Cancela una cita (cliente dueño o admin).

### Headers:
- Authorization: Bearer {token}

### Respuesta exitosa (200)
```json
{
  "message": "Cita cancelada correctamente."
}
```

### Respuesta no autorizado (403)
```json
{
  "message": "This action is unauthorized."
}
```
