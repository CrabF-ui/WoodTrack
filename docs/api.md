# API del chofer

## Listar entregas

- **Método y ruta:** `GET /api/entregas`
- **Autenticación:** token de Sanctum en `Authorization: Bearer <token>` y encabezado `Accept: application/json`.
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

- `401`: no se proporcionó un token válido.
- `403`: `{"message":"Tu cuenta está desactivada. Contacta a un administrador."}` si la cuenta está desactivada; `{"message":"Esta cuenta no tiene acceso a la app."}` si el usuario no es chofer.
- `422`: parámetros inválidos; el cuerpo contiene `message` y `errors` con mensajes en español.
