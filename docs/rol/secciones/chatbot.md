# API: Chatbot

## POST /api/v1/chatbot/query

Consulta al chatbot de la barbería. Responde dudas sobre servicios, citas, horarios, ubicación, membresía, etc.

### Parámetros (JSON):
- message (string, requerido): Mensaje o pregunta del usuario

### Ejemplo de request
```json
{
  "message": "¿Cuáles son los servicios disponibles?"
}
```

### Respuesta exitosa (200)
```json
{
  "response": "📋 Nuestros servicios disponibles:\n• Corte de cabello ($15.00)\n• Afeitado clásico ($10.00)\n¿Quieres reservar alguno?"
}
```

### Respuesta límite de consultas (429)
```json
{
  "response": "Has alcanzado el limite temporal de consultas. Intenta de nuevo en unos segundos.",
  "retry_after": 30
}
```

---

## GET /api/v1/chatbot/history

Devuelve el historial de conversación y resumen del usuario autenticado.

### Headers:
- Authorization: Bearer {token}

### Respuesta ejemplo (200)
```json
{
  "history": [
    {"role": "user", "message": "¿Qué servicios ofrecen?"},
    {"role": "bot", "message": "Ofrecemos cortes, afeitados..."}
  ],
  "summary": "El usuario pregunta por servicios y horarios."
}
```

---

## POST /api/v1/chatbot/clear-history

Limpia el historial de conversación y perfil del usuario.

### Headers:
- Authorization: Bearer {token}

### Respuesta ejemplo (200)
```json
{
  "message": "Historial y perfil limpiados"
}
```

---

## GET /api/v1/chatbot/profile

Devuelve el perfil y resumen del usuario autenticado para el chatbot.

### Headers:
- Authorization: Bearer {token}

### Respuesta ejemplo (200)
```json
{
  "profile": {
    "temas_frecuentes": ["servicios", "citas"],
    "nivel": "V.I.P"
  },
  "summary": "Usuario interesado en promociones y reservas."
}
```
