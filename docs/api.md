# API del chofer

## Iniciar sesión

- **Método y ruta:** `POST /api/login`
- **Autenticación:** pública.
- **Encabezados:** `Accept: application/json`.
- **Cuerpo:** `email` y `password` obligatorios; `device_name` opcional (máximo 60 caracteres, predeterminado `app`).

Ejemplo `200 OK`:

```json
{
  "token": "1|token-de-ejemplo",
  "token_type": "Bearer",
  "expires_at": "2026-12-09T18:00:00.000000Z",
  "user": { "id": 7, "name": "Chofer Ejemplo", "email": "chofer@example.com" }
}
```

El token dura 30 días por defecto. Iniciar sesión de nuevo con el mismo `device_name` reemplaza el token anterior de ese dispositivo.

Errores:

- `401`: Laravel responde cuando falta el token o ya venció; la app debe volver al login.
- `403`: `{"message":"Tu cuenta está desactivada. Contacta a un administrador."}` o `{"message":"Esta cuenta no tiene acceso a la app."}`.
- `422`: datos inválidos o `{"message":"Las credenciales no son correctas.","errors":{"email":["Las credenciales no son correctas."]}}`.
- `429`: `{"message":"Demasiados intentos. Intenta de nuevo en un minuto."}` después de cinco intentos por minuto para correo e IP.

## Cerrar sesión

- **Método y ruta:** `POST /api/logout`
- **Autenticación:** requiere token de Sanctum de un chofer activo.
- **Encabezados:** enviar siempre `Authorization: Bearer <token>` y `Accept: application/json`.
- **Cuerpo:** ninguno.

Ejemplo `200 OK`:

```json
{ "message": "Sesión cerrada correctamente." }
```

El cierre revoca únicamente el token usado en la petición.

Errores:

- `401`: falta un token válido o venció; la app debe volver al login.
- `403`: `{"message":"Tu cuenta está desactivada. Contacta a un administrador."}` o `{"message":"Esta cuenta no tiene acceso a la app."}`.

## Listar entregas

- **Método y ruta:** `GET /api/entregas`
- **Autenticación:** enviar siempre token de Sanctum en `Authorization: Bearer <token>` y encabezado `Accept: application/json`.
- **Parámetros opcionales:** `fecha` con formato `Y-m-d`; `estado` con uno de `pendiente`, `en_camino`, `entregada` o `incidencia`.

La respuesta incluye únicamente entregas asignadas al chofer autenticado cuyo pedido no esté eliminado. Sin filtros devuelve los estados `pendiente` y `en_camino`. Con `fecha` devuelve todos los estados de ese día; con `estado` filtra por ese estado y, si se combinan ambos parámetros, aplica ambos filtros. El orden es fecha asignada ascendente, orden ascendente (los valores nulos al final) e ID ascendente. La lista no se pagina.

Ejemplo `200 OK`:

```json
{
  "data": [
    {
      "id": 42,
      "fecha_asignada": "2026-11-08",
      "orden": 1,
      "estado": "pendiente",
      "estado_fecha_hora": "2026-11-08T09:30:00-06:00",
      "notas": "Llamar al llegar",
      "pedido": {
        "id": 18,
        "cliente": "María Ejemplo",
        "telefono": "5551234567",
        "direccion": "Calle de la Madera 123",
        "fecha_entrega": "2026-11-08",
        "productos": [
          { "producto": "Mesa de pino", "cantidad": 1 }
        ]
      }
    }
  ]
}
```

Errores:

- `401`: falta un token válido o venció; la app debe volver al login.
- `403`: `{"message":"Tu cuenta está desactivada. Contacta a un administrador."}` si la cuenta está desactivada; `{"message":"Esta cuenta no tiene acceso a la app."}` si el usuario no es chofer.
- `422`: parámetros inválidos; el cuerpo contiene `message` y `errors` con mensajes en español.
