<!-- Resumen de Tickets -->
<div v-if="tipoTotal === 'corte_z'" class="card" id="contenido-corte-z">
  <div class="card-body">
    <pre class="m-0">
         FONDA 4 VIENTOS

********************************
          C O R T E  Z
********************************

GLOBAL DE TICKETS
{{ formatFechaCorteZ(fechaFiltro) }}

AGUILAR NUÑEZ ANA LILIA

Car. Fed. Mexico-Acapulco km. 107
Puente de Ixtla Mor. C.P. 62660

R.F.C  AUNA730803EL8

Resumen de Tickets:
<template v-for="reporte in obtenerSoloTicketsElectronicos()">
{{ String(reporte.numero).padStart(6, '0') }}           ${{ formatPrecio(reporte.total_pedido) }}
</template>

      Total: ${{ formatPrecio(calcularTotalSoloTicketsElectronicos()) }}

------------------------------------------
DESCRIPCION          CANT.        VALOR
------------------------------------------
<template v-for="item in obtenerProductosVendidos()">
{{ item.descripcion.substring(0,17).padEnd(17) }}{{ String(item.cantidadFormateada).padStart(9) }}{{ String('$'+item.valor.toLocaleString('es-MX', {minimumFractionDigits: 2, maximumFractionDigits: 2})).trim().padStart(14) }}
</template>
------------------------------------------
                   TOTAL: {{ formatPrecio(calcularTotalItemsVendidos()) }}

*********************************
*********************************

RESUMEN DE TICKETS

Cantidada de Operaciones: {{ cantidadTicketsElectronicos() }}
Subtotal:              ${{ formatPrecio(calcularSubtotalSoloTicketsElectronicos()) }}
IVA:                   ${{ formatPrecio(calcularIvaSoloTicketsElectronicos()) }}
Total:                 ${{ formatPrecio(calcularTotalSoloTicketsElectronicosParaEstadisticas()) }}

*********************************
*********************************

RESUMEN DE FACTURAS FISCALES

Cantidada de Operaciones: {{ cantidadFacturas() }}
Subtotal:              ${{ formatPrecio(calcularSubtotalFacturasParaCorteZ()) }}
IVA:                   ${{ formatPrecio(calcularIvaFacturasParaCorteZ()) }}
Total:                 ${{ formatPrecio(calcularTotalFacturasParaCorteZ()) }}

*********************************
*********************************

RESUMEN GLOBAL GENERAL
TICKET + FACTURAS

{{ obtenerTicketsElectronicosYFacturas().length > 0 ? 'Folios consecutivos del: ' + Math.min(...obtenerTicketsElectronicosYFacturas().map(r => r.numero)) + ' al ' + Math.max(...obtenerTicketsElectronicosYFacturas().map(r => r.numero)) : 'Sin folios' }}
Cantidada de Operaciones: {{ cantidadTicketsElectronicos() + cantidadFacturas() }}
Subtotal:              ${{ formatPrecio(calcularSubtotalSoloTicketsElectronicos() + calcularSubtotalFacturasParaCorteZ()) }}
IVA:                   ${{ formatPrecio(calcularIvaSoloTicketsElectronicos() + calcularIvaFacturasParaCorteZ()) }}
Total:                 ${{ formatPrecio(calcularTotalSoloTicketsElectronicosParaEstadisticas() + calcularTotalFacturasParaCorteZ()) }}

*********************************
*********************************
    </pre>
  </div>
</div>
