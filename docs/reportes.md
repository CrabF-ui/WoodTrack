# Reportes PDF

Los reportes se generan en PDF con papel carta y orientación vertical. Todos usan el encabezado común con **WoodTrack**, el título del reporte, la fecha y hora de generación y el usuario que lo genera. El pie incluye numeración de páginas.

El índice de reportes está disponible para administradores y encargados de inventario. Cada reporte debe declarar explícitamente sus permisos mediante middleware de rol; el reporte de prueba es exclusivo para administradores. Esta base no requiere cambios en la base de datos.

Para agregar un reporte:

1. Crear una vista que extienda `reportes.layout` y defina las secciones `titulo` y `contenido`.
2. Generar la respuesta inline mediante el servicio `App\Services\ReportePdf`.
3. Registrar la ruta dentro de un grupo con middleware `auth` y `rol`.
4. Agregar pruebas de acceso para cada rol autorizado y no autorizado.

## Nota de pedido

La nota de pedido contiene los datos del pedido y del cliente, dirección, productos con especificaciones y subtotales, y resumen de pago. Solo los administradores pueden generarla desde el botón **Nota PDF** del listado de pedidos. Los herrajes no se incluyen porque son información interna del taller.

En pedidos con anticipo, el saldo se calcula como el total menos el monto del anticipo, sin permitir valores negativos. La dirección mostrada siempre proviene del pedido, no del cliente.
