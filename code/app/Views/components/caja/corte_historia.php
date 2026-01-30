<!-- Corte Historia - Reporte de Folios Consecutivos -->
<div v-if="tipoTotal === 'corte_historia'" class="card">
  <div class="card-header bg-info text-white">
    <h5 class="mb-0"><i class="bi bi-folder2-open"></i> Corte Historia - Folios Consecutivos</h5>
  </div>
  <div class="card-body">
    <!-- Formulario de Filtros -->
    <div class="row g-3 mb-4">
      <div class="col-md-12">
        <h6 class="text-muted mb-3">Filtros de Búsqueda</h6>
      </div>
      
      <!-- Filtros de Fecha -->
      <div class="col-md-3">
        <label class="form-label">Fecha Inicio</label>
        <input type="date" class="form-control" v-model="corteHistoria.filtros.fecha_inicio">
      </div>
      <div class="col-md-3">
        <label class="form-label">Fecha Fin</label>
        <input type="date" class="form-control" v-model="corteHistoria.filtros.fecha_fin">
      </div>
      
      <!-- Filtros de Folios -->
      <div class="col-md-2">
        <label class="form-label">Folio Inicio</label>
        <input type="number" class="form-control" v-model.number="corteHistoria.filtros.folio_inicio" placeholder="Ej: 91" min="0">
      </div>
      <div class="col-md-2">
        <label class="form-label">Folio Fin</label>
        <input type="number" class="form-control" v-model.number="corteHistoria.filtros.folio_fin" placeholder="Ej: 120" min="0">
      </div>
      
      <!-- Filtro de Tipo -->
      <div class="col-md-2">
        <label class="form-label">Tipo</label>
        <select class="form-select" v-model="corteHistoria.filtros.tipo">
          <option value="">Todos</option>
          <option value="ticket">Ticket</option>
          <option value="factura">Factura</option>
          <option value="ticket electronico">Ticket Electrónico</option>
        </select>
      </div>
      
      <!-- Botones de Acción -->
      <div class="col-md-12">
        <button class="btn btn-primary" @click="generarCorteHistoria">
          <i class="bi bi-file-earmark-text"></i> Generar Reporte
        </button>
        <button class="btn btn-outline-secondary ms-2" @click="limpiarFiltrosCorteHistoria">
          <i class="bi bi-x-circle"></i> Limpiar Filtros
        </button>
        <button v-if="corteHistoria.folios.length > 0" class="btn btn-success ms-2" @click="exportarCorteHistoriaPDF">
          <i class="bi bi-file-pdf"></i> Exportar PDF
        </button>
        <button v-if="corteHistoria.folios.length > 0" class="btn btn-outline-success ms-2" @click="imprimirCorteHistoria">
          <i class="bi bi-printer"></i> Imprimir
        </button>
      </div>
    </div>
    
    <!-- Loading -->
    <div v-if="corteHistoria.loading" class="text-center py-5">
      <div class="spinner-border text-primary" role="status">
        <span class="visually-hidden">Cargando...</span>
      </div>
      <p class="mt-2 text-muted">Generando reporte...</p>
    </div>
    
    <!-- Mensaje de Error -->
    <div v-if="corteHistoria.error" class="alert alert-danger" role="alert">
      <i class="bi bi-exclamation-triangle"></i> {{ corteHistoria.error }}
    </div>
    
    <!-- Resumen (si hay datos) -->
    <div v-if="corteHistoria.folios.length > 0" class="row mb-4">
      <div class="col-md-12">
        <div class="alert alert-info">
          <strong>Filtros aplicados:</strong>
          <span v-if="corteHistoria.filtrosAplicados.fecha_inicio"> Desde: {{ corteHistoria.filtrosAplicados.fecha_inicio }}</span>
          <span v-if="corteHistoria.filtrosAplicados.fecha_fin"> Hasta: {{ corteHistoria.filtrosAplicados.fecha_fin }}</span>
          <span v-if="corteHistoria.filtrosAplicados.folio_inicio"> | Folios del {{ corteHistoria.filtrosAplicados.folio_inicio }}</span>
          <span v-if="corteHistoria.filtrosAplicados.folio_fin"> al {{ corteHistoria.filtrosAplicados.folio_fin }}</span>
          <span v-if="corteHistoria.filtrosAplicados.tipo"> | Tipo: {{ corteHistoria.filtrosAplicados.tipo }}</span>
        </div>
      </div>
      
      <!-- Tarjetas de Resumen -->
      <div class="col-md-3">
        <div class="card bg-light">
          <div class="card-body text-center">
            <h6 class="text-muted mb-1">Total Folios</h6>
            <h3 class="mb-0">{{ corteHistoria.resumen.total_folios }}</h3>
          </div>
        </div>
      </div>
      <div class="col-md-3">
        <div class="card bg-success bg-opacity-10">
          <div class="card-body text-center">
            <h6 class="text-muted mb-1">Tickets ({{ corteHistoria.resumen.ticket.cantidad }})</h6>
            <h4 class="mb-0 text-success">${{ formatPrecio(corteHistoria.resumen.ticket.total) }}</h4>
          </div>
        </div>
      </div>
      <div class="col-md-3">
        <div class="card bg-primary bg-opacity-10">
          <div class="card-body text-center">
            <h6 class="text-muted mb-1">Facturas ({{ corteHistoria.resumen.factura.cantidad }})</h6>
            <h4 class="mb-0 text-primary">${{ formatPrecio(corteHistoria.resumen.factura.total) }}</h4>
          </div>
        </div>
      </div>
      <div class="col-md-3">
        <div class="card bg-warning bg-opacity-10">
          <div class="card-body text-center">
            <h6 class="text-muted mb-1">T. Electrónicos ({{ corteHistoria.resumen['ticket electronico'].cantidad }})</h6>
            <h4 class="mb-0 text-warning">${{ formatPrecio(corteHistoria.resumen['ticket electronico'].total) }}</h4>
          </div>
        </div>
      </div>
      <div class="col-md-12 mt-3">
        <div class="card bg-dark text-white">
          <div class="card-body text-center">
            <h5 class="mb-1">Total General</h5>
            <h2 class="mb-0">${{ formatPrecio(corteHistoria.resumen.total_general) }}</h2>
          </div>
        </div>
      </div>
    </div>
    
    <!-- Tabla de Folios -->
    <div v-if="corteHistoria.folios.length > 0" id="tabla-corte-historia">
      <div class="table-responsive">
        <table class="table table-striped table-hover table-sm">
          <thead class="table-dark">
            <tr>
              <th width="10%">Folio #</th>
              <th width="15%">Tipo</th>
              <th width="20%">Fecha</th>
              <th width="15%">Total</th>
              <th width="15%">Forma de Pago</th>
              <th width="15%">Mesero</th>
              <th width="10%">Mesa</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="folio in corteHistoria.folios" :key="folio.folio">
              <td><strong>{{ folio.folio }}</strong></td>
              <td>
                <span class="badge" :class="{
                  'bg-success': folio.tipo === 'ticket',
                  'bg-primary': folio.tipo === 'factura',
                  'bg-warning text-dark': folio.tipo === 'ticket electronico'
                }">
                  {{ folio.tipo }}
                </span>
              </td>
              <td>{{ formatFechaCompleta(folio.fecha) }}</td>
              <td class="text-end"><strong>${{ formatPrecio(folio.total) }}</strong></td>
              <td>
                <span class="badge bg-secondary">{{ folio.forma_pago }}</span>
              </td>
              <td>{{ folio.mesero || 'N/A' }}</td>
              <td class="text-center">{{ folio.id_mesa || '-' }}</td>
            </tr>
          </tbody>
          <tfoot class="table-secondary">
            <tr>
              <td colspan="3" class="text-end"><strong>TOTAL:</strong></td>
              <td class="text-end"><strong>${{ formatPrecio(corteHistoria.resumen.total_general) }}</strong></td>
              <td colspan="3"></td>
            </tr>
          </tfoot>
        </table>
      </div>
    </div>
    
    <!-- Mensaje cuando no hay datos -->
    <div v-if="!corteHistoria.loading && corteHistoria.folios.length === 0 && !corteHistoria.error" class="text-center py-5">
      <i class="bi bi-inbox" style="font-size: 3rem; color: #ccc;"></i>
      <p class="text-muted mt-3">No se encontraron folios con los filtros seleccionados.<br>Presiona "Generar Reporte" para comenzar.</p>
    </div>
  </div>
</div>
