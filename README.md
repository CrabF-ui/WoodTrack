# WoodTrack
Sistema web y móvil para digitalizar la gestión de ventas, inventario y logística de entregas de una empresa de carpintería, reemplazando el control manual actual (Excel, libretas y WhatsApp)
## Estrategia de Ramas

Este proyecto sigue un flujo de trabajo basado en ramas de características (*feature branches*), con la rama `main` protegida como fuente única de verdad del proyecto.

### Reglas principales

- **`main`**: rama protegida. No se permite hacer push directo; todo cambio debe llegar mediante un Pull Request (PR) aprobado.
- **`feature/*`**: ramas de trabajo creadas a partir de `main` para desarrollar nuevas funcionalidades o módulos (ej. `feature/proyecto-base-laravel`).
- Cada cambio se integra a `main` mediante un **Pull Request**, que requiere:
  - Al menos una revisión (*code review*) aprobada por otro integrante del equipo.
  - Resolución de comentarios pendientes antes de la fusión.
- La fusión se realiza mediante **Squash and Merge**, combinando todos los commits de la rama en uno solo para mantener un historial limpio y legible en `main`.
- Una vez fusionada, la rama de trabajo se elimina del repositorio remoto para evitar acumulación de ramas obsoletas.

### Flujo típico

1. Crear rama desde `main`: `git checkout -b feature/nombre-modulo`
2. Desarrollar y hacer commits en la rama.
3. Subir la rama: `git push -u origin feature/nombre-modulo`
4. Abrir un Pull Request hacia `main`.
5. Esperar revisión y aprobación.
6. Fusionar con *Squash and Merge*.
7. Eliminar la rama de trabajo.
