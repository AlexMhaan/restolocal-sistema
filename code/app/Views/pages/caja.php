<?= view('templates/menu', ['required' => true]); ?>

<div id="cajaDetalle">
  <div class="content-header">
    <div class="container-fluid">
      <div class="row">
        <div class="col-md-12 mt-3">
          <!-- Filtros -->
          <div class="d-flex justify-content-between align-items-center mb-3">
            <h3>{{ textoFiltro }}</h3>
            <div class="d-flex gap-2">
              <input type="date" class="form-control" v-model="fechaFiltro" @change="cargarReportes">
              <div class="input-group">
                <input type="text" class="form-control" v-model="numeroTicket" placeholder="Buscar por número..." @input="filtrarReportes" @keyup.enter="buscarPorNumeroTicket">
                <button class="btn btn-primary" type="button" @click="buscarPorNumeroTicket">
                  <i class="bi bi-search"></i> Buscar
                </button>
                <button class="btn btn-outline-secondary" type="button" @click="limpiarBusqueda" v-if="busquedaActiva">
                  <i class="bi bi-x-lg"></i>
                </button>
              </div>
            </div>
          </div>

          <!-- Totales -->
          <div class="card mb-4">
            <div class="card-header">
              <div class="d-flex justify-content-between align-items-center">
                <div class="btn-group" role="group">
                  <!-- <button type="button" class="btn" :class="{'btn-primary': tipoTotal === 'tickets', 'btn-outline-primary': tipoTotal !== 'tickets'}" @click="tipoTotal = 'tickets'">
                    Total Tickets: ${{ formatPrecio(calcularTotalTickets()) }}
                  </button>
                  <button type="button" class="btn" :class="{'btn-primary': tipoTotal === 'facturas', 'btn-outline-primary': tipoTotal !== 'facturas'}" @click="tipoTotal = 'facturas'">
                    Total Facturas: ${{ formatPrecio(calcularTotalFacturas()) }}
                  </button>
                  <button type="button" class="btn" :class="{'btn-primary': tipoTotal === 'general', 'btn-outline-primary': tipoTotal !== 'general'}" @click="tipoTotal = 'general'">
                    Resumen General
                  </button> -->
                  <button type="button" class="btn" :class="{'btn-primary': tipoTotal === 'items_vendidos', 'btn-outline-primary': tipoTotal !== 'items_vendidos'}" @click="tipoTotal = 'items_vendidos'; calcularCuotasPorMesero()">
                    Cuotas x comanda
                  </button>
                  <button type="button" class="btn" :class="{'btn-primary': tipoTotal === 'corte_z', 'btn-outline-primary': tipoTotal !== 'corte_z'}" @click="tipoTotal = 'corte_z'">
                    Reporte de Corte Z
                  </button>
                  <button type="button" class="btn" :class="{'btn-primary': tipoTotal === 'resumen_prueba2', 'btn-outline-primary': tipoTotal !== 'resumen_prueba2'}" @click="buscarPorPedido">
                    resumen_prueba2(ticket normal)
                  </button>
                  <button type="button" class="btn" :class="{'btn-primary': tipoTotal === 'resumen_prueba3', 'btn-outline-primary': tipoTotal !== 'resumen_prueba3'}" @click="tipoTotal = 'resumen_prueba3'">
                    resumen_prueba3(ticekt foliado)
                  </button>
                  <button type="button" class="btn" :class="{'btn-primary': tipoTotal === 'corte_historia', 'btn-outline-primary': tipoTotal !== 'corte_historia'}" @click="tipoTotal = 'corte_historia'">
                    <i class="bi bi-folder2-open"></i> Corte Historia
                  </button>
                  <button type="button" class="btn" :class="{'btn-primary': tipoTotal === 'respaldo', 'btn-outline-primary': tipoTotal !== 'respaldo'}" @click="tipoTotal = 'respaldo'">
                    <i class="bi bi-database-fill-down"></i> Respaldo
                  </button>
                  <button v-if="idRol === 1" type="button" class="btn" :class="{'btn-success': tipoTotal === 'exportar_excel', 'btn-outline-success': tipoTotal !== 'exportar_excel'}" @click="tipoTotal = 'exportar_excel'" title="Solo Administradores">
                    <i class="bi bi-file-earmark-excel"></i> Exportar Excel
                  </button>
                  <button v-if="idRol === 1" type="button" class="btn" :class="{'btn-warning': tipoTotal === 'finalizar_dia', 'btn-outline-warning': tipoTotal !== 'finalizar_dia'}" @click="tipoTotal = 'finalizar_dia'" title="Solo Administradores">
                    <i class="bi bi-exclamation-octagon"></i> Finalizar Día
                  </button>
                  
                  <!-- Botón de imprimir solo visible cuando se está viendo el Corte Z -->
                  <button v-if="tipoTotal === 'corte_z'" type="button" class="btn btn-success no-print" @click="imprimirCorteZ">
                    <i class="bi bi-printer"></i> Imprimir
                  </button>
                </div>
              </div>
            </div>
          </div>

          <?= view('components/caja/resumen_tickets'); ?>

          <?= view('components/caja/ticket_normal'); ?>

          <?= view('components/caja/ticket_foliado'); ?>

          <?= view('components/caja/cuotas_comanda'); ?>

          <?= view('components/caja/listado_reportes'); ?>

          <?= view('components/caja/resumen_general'); ?>

          <?= view('components/caja/corte_historia'); ?>

          <?= view('components/caja/respaldo'); ?>

          <?= view('components/caja/exportar_excel'); ?>

          <?= view('components/caja/finalizar_dia'); ?>
        </div>
      </div>
    </div>
  </div>
</div>
<script>
// Inyectar datos de sesión desde PHP
window.sessionData = {
  idRol: <?= session()->get('rol') ?? 0 ?>
};
console.log('🔐 Session Data:', window.sessionData);
console.log('📋 rol from session:', <?= json_encode(session()->get('rol')) ?>);
</script>