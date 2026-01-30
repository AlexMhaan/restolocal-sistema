<!-- Listado de Reportes -->
<div class="card" v-if="tipoTotal !== 'resumen_prueba1' && tipoTotal !== 'general' && tipoTotal !== 'corte_z' && tipoTotal !== 'corte_historia' && tipoTotal !== 'respaldo' && tipoTotal !== 'exportar_excel' && tipoTotal !== 'finalizar_dia'">
  <div class="card-body">
    <div v-if="reportesFiltrados.length === 0" class="text-center text-muted my-5">
      No hay reportes para mostrar
    </div>
    <div v-else class="list-group">
      <div v-for="reporte in reportesFiltrados" :key="reporte.id" class="list-group-item">
        <pre class="m-0">
========================================

    {{ formatComprobante(reporte.tipo) }}{{ reporte.tipo !== 'ticket' ? ' #' + String(reporte.numero).padStart(4, '0') : '' }}
----------------------------------------
    ID Pedido:  {{ reporte.id_pedido }}
    Mesero:     {{ reporte.nombre_mesero || 'Caja' }}
    Mesa:       {{ reporte.id_mesa && reporte.id_mesa !== '0' && reporte.id_mesa !== 0 ? reporte.id_mesa : 'Desde Caja' }}
    Fecha:      {{ formatFecha(reporte.fecha) }}
    Método:     {{ formatMetodoPago(reporte.forma_pago) }}
----------------------------------------

    TOTAL:      ${{ formatPrecio(reporte.total_pedido) }}.-

========================================
        </pre>
      </div>
    </div>
  </div>
</div>
