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

## Lista de compras

La lista de compras puede ser generada por administradores y encargados de inventario. Incluye únicamente materiales activos cuyo stock actual es menor o igual al stock mínimo, agrupados por tipo y ordenados por nombre.

El faltante se calcula como el stock mínimo menos el stock actual, sin permitir resultados negativos. La cantidad a comprar se deja en blanco para llenarla a mano en el reporte impreso.

## Comprobante de entrega

El comprobante de entrega solo puede generarlo un administrador cuando la entrega está marcada como entregada y cuenta con evidencia completa: receptor, al menos una foto y firma o motivo de ausencia de firma. Incluye los datos de la entrega, cliente, dirección del pedido, chofer, productos y cantidades, receptor, firma y fotografías. No incluye precios, herrajes ni datos de pago.

Se incorporan como máximo tres fotos, ordenadas por registro, redimensionadas a un ancho máximo de 800 píxeles y comprimidas para mantener ligero el PDF. La firma se descifra únicamente en memoria para incorporarla al comprobante; su contenido descifrado no se guarda ni se registra en logs.

## Problemas con entregas

El reporte de problemas con entregas solo puede generarlo un administrador. El periodo se filtra por la fecha y hora en que se registró la incidencia; de forma predeterminada abarca el mes en curso y puede elegirse un intervalo inclusivo máximo de 366 días.

Incluye el pedido, cliente, chofer, fecha de incidencia, motivo, acción siguiente y situación. La situación se deriva de los intentos posteriores del mismo pedido: **Sin atender** cuando no existe otro intento, **Resuelta** cuando alguno de los intentos posteriores fue entregado y **Reasignada** cuando existen intentos posteriores pero ninguno ha sido entregado.

## Ventas del mes

El reporte de ventas del mes solo puede generarlo un administrador. Una venta se define como la suma de `pedidos.total` de los pedidos no cancelados y no eliminados cuya fecha del pedido pertenece al mes seleccionado. Mide lo vendido y no lo cobrado, por lo que no distingue anticipos ni saldos.

Incluye el total vendido, número de pedidos, ticket promedio, comparación con el mes anterior y los cinco principales clientes y productos. Los productos se agrupan por un nombre normalizado, sin distinguir mayúsculas y minúsculas, espacios sobrantes ni espacios internos repetidos; se muestra el nombre del primer registro del grupo.
