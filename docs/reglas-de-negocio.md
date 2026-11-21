# Reglas de negocio

## Herrajes de un pedido

Los herrajes de un pedido son opcionales y se capturan antes de completar el pedido. Para completar un pedido sin herrajes, se debe confirmar explícitamente que no lleva herrajes.

El stock se descuenta al guardar los herrajes. Los pedidos completados sin herrajes se señalan en el listado de pedidos y en el dashboard.

## Fechas del pedido y de la entrega

La fecha de entrega del pedido (`fecha_entrega`) es una estimación y puede extenderse si cambian los encargos. La fecha asignada de la entrega (`fecha_asignada`) es el día real de salida.

Los pedidos completados se asignan y salen el mismo día; no se guardan para fechas futuras. No hay validación entre ambas fechas y la app móvil no muestra la fecha estimada.
