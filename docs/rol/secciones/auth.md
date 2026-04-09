# API: Autenticación (Auth)

## POST /api/v1/auth/login

Inicia sesión de usuario móvil.

### Parámetros (JSON):
- email (string, requerido)
- password (string, requerido)
- device_name (string, opcional)

### Ejemplo de request
```json
{
  "email": "cliente@ejemplo.com",
  "password": "12345678",
  "device_name": "MiCelular"
}
```

### Respuesta exitosa (200)
```json
{
  "message": "Autenticación exitosa.",
  "token_type": "Bearer",
  "token": "eyJ0eXAiOiJKV1QiLCJhbGci...",
  "user": {
    "id": 2,
    "name": "Juan Pérez",
    "email": "cliente@ejemplo.com",
    "roles": ["cliente"],
    "client_id": 5,
    "barber_id": null
  }
}
```

### Respuesta error credenciales (422)
```json
{
  "message": "Las credenciales no son válidas."
}
```

### Respuesta email no verificado (403)
```json
{
  "message": "Debes verificar tu correo para iniciar sesión."
}
```

---

## POST /api/v1/auth/register

Registra un nuevo usuario móvil.

### Parámetros (JSON):
- name (string, requerido)
- email (string, requerido, único)
- password (string, requerido, min:8)
- password_confirmation (string, requerido)
- device_name (string, opcional)

### Ejemplo de request
```json
{
  "name": "Juan Pérez",
  "email": "cliente@ejemplo.com",
  "password": "12345678",
  "password_confirmation": "12345678",
  "device_name": "MiCelular"
}
```

### Respuesta exitosa (201)
```json
{
  "message": "Cuenta creada exitosamente.",
  "token_type": "Bearer",
  "token": "eyJ0eXAiOiJKV1QiLCJhbGci...",
  "user": {
    "id": 2,
    "name": "Juan Pérez",
    "email": "cliente@ejemplo.com",
    "roles": ["cliente"],
    "client_id": 5,
    "barber_id": null
  }
}
```

---

## GET /api/v1/auth/me

Devuelve los datos del usuario autenticado.

### Headers:
- Authorization: Bearer {token}

### Respuesta (200)
```json
{
  "user": {
    "id": 2,
    "name": "Juan Pérez",
    "email": "cliente@ejemplo.com",
    "roles": ["cliente"],
    "client_id": 5,
    "barber_id": null
  }
}
```

---

## POST /api/v1/auth/logout

Cierra la sesión del usuario móvil.

### Headers:
- Authorization: Bearer {token}

### Respuesta (200)
```json
{
  "message": "Sesión cerrada correctamente."
}
```
