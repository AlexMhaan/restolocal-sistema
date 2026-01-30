<!-- ========================================
     EXPORTAR REPORTES EXCEL
     Vista para exportar facturas y tickets electrónicos
     ======================================== -->
<div v-show="tipoTotal === 'exportar_excel'" class="card shadow-sm mb-4">
  <div class="card-header bg-success text-white d-flex justify-content-between align-items-center">
    <h5 class="mb-0">
      <i class="bi bi-file-earmark-excel"></i>
      Exportar Reportes Excel
    </h5>
  </div>
  
  <div class="card-body">
    <div class="row g-3">
      
      <!-- Filtro: Fecha Inicio -->
      <div class="col-md-4">
        <label class="form-label fw-bold">Fecha Inicio</label>
        <input 
          type="date" 
          class="form-control" 
          v-model="exportExcel.fechaInicio"
          :max="exportExcel.fechaFin || new Date().toISOString().split('T')[0]"
        >
        <small class="text-muted">Opcional - Desde cuándo</small>
      </div>
      
      <!-- Filtro: Fecha Fin -->
      <div class="col-md-4">
        <label class="form-label fw-bold">Fecha Fin</label>
        <input 
          type="date" 
          class="form-control" 
          v-model="exportExcel.fechaFin"
          :min="exportExcel.fechaInicio"
          :max="new Date().toISOString().split('T')[0]"
        >
        <small class="text-muted">Opcional - Hasta cuándo</small>
      </div>
      
      <!-- Botones de Descarga -->
      <div class="col-md-4">
        <label class="form-label fw-bold">Descargar</label>
        <div class="d-flex gap-2">
          <!-- Botón Facturas -->
          <button 
            type="button" 
            class="btn btn-primary flex-fill"
            @click="descargarExcelFacturas"
            :disabled="exportExcel.descargando"
          >
            <span v-if="!exportExcel.descargando">
              <i class="bi bi-file-earmark-excel"></i>
              Facturas
            </span>
            <span v-else>
              <span class="spinner-border spinner-border-sm me-1"></span>
              Generando...
            </span>
          </button>
          
          <!-- Botón Tickets Electrónicos -->
          <button 
            type="button" 
            class="btn btn-info text-white flex-fill"
            @click="descargarExcelTickets"
            :disabled="exportExcel.descargando"
          >
            <span v-if="!exportExcel.descargando">
              <i class="bi bi-file-earmark-excel"></i>
              Tickets Electrónicos
            </span>
            <span v-else>
              <span class="spinner-border spinner-border-sm me-1"></span>
              Generando...
            </span>
          </button>
        </div>
      </div>
      
    </div>
    
    <!-- Mensaje de ayuda -->
    <div class="alert alert-info mt-3 mb-0">
      <i class="bi bi-info-circle"></i>
      <strong>Formato del archivo:</strong> NUMERO DE FOLIO, FECHA, HORA, CONSECUTIVO, SUBTOTAL, IVA, TOTAL
      <br>
      <small>Si no seleccionas fechas, se exportarán todos los registros.</small>
    </div>
    
    <!-- Mensaje de resultado -->
    <div v-if="exportExcel.mensaje" class="mt-3">
      <div 
        :class="['alert', exportExcel.mensajeTipo === 'success' ? 'alert-success' : 'alert-danger']"
        role="alert"
      >
        {{ exportExcel.mensaje }}
      </div>
    </div>
    
  </div>
</div>
