<!-- Respaldo de Folios Fiscales -->
<div v-if="tipoTotal === 'respaldo'" class="card">
  <div class="card-body text-center">
    <div class="mb-4">
      <h2 class="mb-3">📁 Respaldo de Folios Fiscales</h2>
      <p class="text-muted">Genera un respaldo de facturas y tickets electrónicos con folio consecutivo</p>
    </div>

    <!-- Información incluida -->
    <div class="alert alert-info mb-4">
      <strong>Este respaldo incluye únicamente:</strong>
      <ul class="list-unstyled mt-2 mb-0">
        <li>✅ Facturas (con folio consecutivo)</li>
        <li>✅ Tickets Electrónicos (con folio consecutivo)</li>
        <li>❌ NO incluye tickets normales sin folio</li>
      </ul>
    </div>

    <!-- Filtros Opcionales -->
    <div class="card mb-4">
      <div class="card-header bg-light">
        <h5 class="mb-0">Filtros Opcionales</h5>
      </div>
      <div class="card-body">
        <div class="row g-3">
          <!-- Filtros de Fecha -->
          <div class="col-md-3">
            <label class="form-label">Fecha Inicio</label>
            <input type="date" class="form-control" v-model="respaldo.filtros.fecha_inicio">
          </div>
          <div class="col-md-3">
            <label class="form-label">Fecha Fin</label>
            <input type="date" class="form-control" v-model="respaldo.filtros.fecha_fin">
          </div>
          
          <!-- Filtros de Folios -->
          <div class="col-md-3">
            <label class="form-label">Folio Inicio</label>
            <input type="number" class="form-control" v-model.number="respaldo.filtros.folio_inicio" placeholder="Ej: 1" min="0">
          </div>
          <div class="col-md-3">
            <label class="form-label">Folio Fin</label>
            <input type="number" class="form-control" v-model.number="respaldo.filtros.folio_fin" placeholder="Ej: 100" min="0">
          </div>
          
          <!-- Formato -->
          <div class="col-md-12">
            <label class="form-label">Formato de Exportación</label>
            <div class="btn-group w-100" role="group">
              <input type="radio" class="btn-check" id="formatoSQL" value="sql" v-model="respaldo.formato" autocomplete="off" checked>
              <label class="btn btn-outline-primary" for="formatoSQL">
                <i class="bi bi-file-earmark-code"></i> SQL (Base de Datos)
              </label>
              
              <input type="radio" class="btn-check" id="formatoExcel" value="excel" v-model="respaldo.formato" autocomplete="off">
              <label class="btn btn-outline-success" for="formatoExcel">
                <i class="bi bi-file-earmark-excel"></i> Excel (CSV)
              </label>
            </div>
            <small class="text-muted d-block mt-2">
              <strong>SQL:</strong> Para restaurar en otra base de datos | 
              <strong>Excel:</strong> Para auditorías y revisión
            </small>
          </div>
        </div>
      </div>
    </div>

    <!-- Botones de Acción -->
    <div class="d-flex justify-content-center gap-3 mb-4">
      <button class="btn btn-success btn-lg" @click="descargarRespaldo" :disabled="respaldo.loading">
        <i class="bi bi-download" v-if="!respaldo.loading"></i>
        <span v-if="respaldo.loading" class="spinner-border spinner-border-sm me-2" role="status"></span>
        {{ respaldo.loading ? 'Generando...' : 'Descargar Respaldo' }}
      </button>
      
      <button class="btn btn-outline-secondary btn-lg" @click="limpiarFiltrosRespaldo" :disabled="respaldo.loading">
        <i class="bi bi-x-circle"></i> Limpiar Filtros
      </button>
    </div>

    <!-- Mensaje de Error -->
    <div v-if="respaldo.error" class="alert alert-danger" role="alert">
      <i class="bi bi-exclamation-triangle"></i> {{ respaldo.error }}
    </div>
  </div>
</div>
