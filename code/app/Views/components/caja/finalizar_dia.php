<!-- Finalizar Día -->
<div v-if="tipoTotal === 'finalizar_dia'" class="card">
  <div class="card-body">
    <div class="text-center mb-4">
      <h2 class="mb-3">⚠️ Finalizar Día</h2>
      <p class="text-muted">Limpia datos operativos del día conservando el consecutivo fiscal</p>
    </div>

    <!-- ADVERTENCIA CRÍTICA -->
    <div class="alert alert-danger mb-4">
      <h4 class="alert-heading">
        <i class="bi bi-exclamation-triangle-fill"></i> OPERACIÓN IRREVERSIBLE
      </h4>
      <hr>
      <p class="mb-2"><strong>Esta acción eliminará permanentemente:</strong></p>
      <ul class="mb-2">
        <li>✅ Pedidos sin reportes fiscales</li>
        <li>✅ Items de pedidos eliminados</li>
        <li>✅ Tickets normales (sin folio)</li>
        <li>✅ Resúmenes cerrados</li>
        <li>✅ Reinicio del contador de pedidos (AUTO_INCREMENT)</li>
      </ul>
      <p class="mb-2"><strong>Se CONSERVARÁ intacto:</strong></p>
      <ul class="mb-0">
        <li>❌ Facturas y tickets electrónicos (reportes fiscales)</li>
        <li>❌ Pedidos con reportes fiscales asociados</li>
        <li>❌ Consecutivo fiscal (continúa incrementando)</li>
        <li>❌ Usuarios, platillos, mesas, configuraciones</li>
      </ul>
    </div>

    <!-- Selector de Fecha -->
    <div class="card mb-4">
      <div class="card-header bg-light">
        <h5 class="mb-0">Seleccionar Día a Finalizar</h5>
      </div>
      <div class="card-body">
        <div class="row g-3">
          <div class="col-md-6">
            <label class="form-label">Fecha</label>
            <input 
              type="date" 
              class="form-control form-control-lg" 
              v-model="finalizarDia.fecha" 
              :max="new Date().toISOString().split('T')[0]"
            >
            <small class="text-muted">No se pueden finalizar días futuros</small>
          </div>
          <div class="col-md-6">
            <label class="form-label invisible">Acción</label>
            <button 
              class="btn btn-primary btn-lg w-100" 
              @click="obtenerResumenFinalizarDia" 
              :disabled="finalizarDia.loading || !finalizarDia.fecha"
            >
              <i class="bi bi-search"></i> Ver Resumen
            </button>
            <small class="text-muted invisible">Placeholder</small>
          </div>
        </div>
      </div>
    </div>

    <!-- Loading -->
    <div v-if="finalizarDia.loading" class="text-center my-5">
      <div class="spinner-border text-primary" role="status">
        <span class="visually-hidden">Cargando...</span>
      </div>
      <p class="mt-2">Consultando datos...</p>
    </div>

    <!-- Resumen Preview -->
    <div v-if="finalizarDia.resumen && !finalizarDia.loading" class="card mb-4 border-warning">
      <div class="card-header bg-warning text-dark">
        <h5 class="mb-0">
          <i class="bi bi-clipboard-data"></i> Resumen del Día: {{ formatFecha(finalizarDia.fecha) }}
        </h5>
      </div>
      <div class="card-body">
        <div class="row">
          <!-- Columna Izquierda: SE ELIMINARÁ -->
          <div class="col-md-6">
            <h6 class="text-danger">
              <i class="bi bi-trash"></i> SE ELIMINARÁ:
            </h6>
            <ul class="list-group mb-3">
              <li class="list-group-item d-flex justify-content-between align-items-center">
                Pedidos sin fiscales
                <span class="badge bg-danger rounded-pill">{{ finalizarDia.resumen.eliminados.pedidos }}</span>
              </li>
              <li class="list-group-item d-flex justify-content-between align-items-center">
                Items de pedidos
                <span class="badge bg-danger rounded-pill">{{ finalizarDia.resumen.eliminados.items }}</span>
              </li>
              <li class="list-group-item d-flex justify-content-between align-items-center">
                Tickets normales
                <span class="badge bg-danger rounded-pill">{{ finalizarDia.resumen.eliminados.tickets_normales }}</span>
              </li>
              <li class="list-group-item d-flex justify-content-between align-items-center">
                Resúmenes cerrados
                <span class="badge bg-danger rounded-pill">{{ finalizarDia.resumen.eliminados.resumenes }}</span>
              </li>
            </ul>
          </div>

          <!-- Columna Derecha: SE CONSERVARÁ -->
          <div class="col-md-6">
            <h6 class="text-success">
              <i class="bi bi-shield-check"></i> SE CONSERVARÁ:
            </h6>
            <ul class="list-group mb-3">
              <li class="list-group-item d-flex justify-content-between align-items-center">
                Reportes fiscales
                <span class="badge bg-success rounded-pill">{{ finalizarDia.resumen.conservados.reportes_fiscales }}</span>
              </li>
              <li class="list-group-item d-flex justify-content-between align-items-center">
                Pedidos con fiscales
                <span class="badge bg-success rounded-pill">{{ finalizarDia.resumen.conservados.pedidos_fiscales }}</span>
              </li>
              <li class="list-group-item d-flex justify-content-between align-items-center">
                Monto fiscal
                <span class="badge bg-success rounded-pill">${{ formatPrecio(finalizarDia.resumen.conservados.monto_fiscal) }}</span>
              </li>
              <li class="list-group-item d-flex justify-content-between align-items-center">
                Folio consecutivo
                <span class="badge bg-primary rounded-pill">{{ finalizarDia.resumen.conservados.folio_actual }}</span>
              </li>
            </ul>
          </div>
        </div>

        <!-- Confirmación Doble -->
        <div class="card bg-light mt-4">
          <div class="card-body">
            <h6 class="card-title">Confirmación Requerida</h6>
            
            <!-- Checkbox 1 -->
            <div class="form-check mb-3">
              <input 
                class="form-check-input" 
                type="checkbox" 
                v-model="finalizarDia.checkbox1" 
                id="confirmCheck1"
              >
              <label class="form-check-label" for="confirmCheck1">
                ✓ He revisado el resumen y entiendo que esta operación es <strong>IRREVERSIBLE</strong>
              </label>
            </div>

            <!-- Checkbox 2 -->
            <div class="form-check mb-3">
              <input 
                class="form-check-input" 
                type="checkbox" 
                v-model="finalizarDia.checkbox2" 
                id="confirmCheck2"
              >
              <label class="form-check-label" for="confirmCheck2">
                ✓ Sé que se generará un respaldo automático en <code>writable/respaldos/</code>
              </label>
            </div>

            <!-- Input texto CONFIRMAR -->
            <div class="mb-3">
              <label class="form-label">
                Para continuar, escriba <strong>CONFIRMAR</strong> en mayúsculas:
              </label>
              <input 
                type="text" 
                class="form-control" 
                v-model="finalizarDia.textoConfirmacion" 
                placeholder="CONFIRMAR"
                :disabled="!finalizarDia.checkbox1 || !finalizarDia.checkbox2"
              >
            </div>

            <!-- Botón Finalizar -->
            <button 
              class="btn btn-danger btn-lg w-100" 
              @click="ejecutarFinalizarDia" 
              :disabled="!finalizarDia.checkbox1 || !finalizarDia.checkbox2 || finalizarDia.textoConfirmacion !== 'CONFIRMAR' || finalizarDia.ejecutando"
            >
              <i class="bi bi-exclamation-octagon" v-if="!finalizarDia.ejecutando"></i>
              <span v-if="finalizarDia.ejecutando" class="spinner-border spinner-border-sm me-2"></span>
              {{ finalizarDia.ejecutando ? 'Finalizando...' : 'FINALIZAR DÍA' }}
            </button>
          </div>
        </div>
      </div>
    </div>

    <!-- Resultado Exitoso -->
    <div v-if="finalizarDia.resultado && !finalizarDia.loading" class="card border-success mb-4">
      <div class="card-header bg-success text-white">
        <h5 class="mb-0">
          <i class="bi bi-check-circle"></i> Día Finalizado Correctamente
        </h5>
      </div>
      <div class="card-body">
        <div class="row">
          <div class="col-md-6">
            <h6>Registros Eliminados:</h6>
            <ul>
              <li>Pedidos: <strong>{{ finalizarDia.resultado.eliminados.pedidos }}</strong></li>
              <li>Items: <strong>{{ finalizarDia.resultado.eliminados.items }}</strong></li>
              <li>Tickets normales: <strong>{{ finalizarDia.resultado.eliminados.tickets_normales }}</strong></li>
              <li>Resúmenes: <strong>{{ finalizarDia.resultado.eliminados.resumenes }}</strong></li>
            </ul>
          </div>
          <div class="col-md-6">
            <h6>Datos Conservados:</h6>
            <ul>
              <li>Reportes fiscales: <strong>{{ finalizarDia.resultado.conservados.reportes_fiscales }}</strong></li>
              <li>Monto fiscal: <strong>${{ formatPrecio(finalizarDia.resultado.conservados.monto_fiscal) }}</strong></li>
              <li>Folio actual: <strong>{{ finalizarDia.resultado.conservados.folio_actual }}</strong></li>
            </ul>
          </div>
        </div>
        <hr>
        <div class="alert alert-info mb-0">
          <strong>Respaldo generado:</strong> <code>{{ finalizarDia.resultado.respaldo }}</code><br>
          <small>Ubicación: <code>{{ finalizarDia.resultado.respaldo_path }}</code></small>
        </div>
      </div>
    </div>

    <!-- Mensaje de Error -->
    <div v-if="finalizarDia.error" class="alert alert-danger" role="alert">
      <i class="bi bi-exclamation-triangle"></i> {{ finalizarDia.error }}
    </div>
  </div>
</div>
