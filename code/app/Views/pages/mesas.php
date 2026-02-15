<div id="mesaDetalle">
  <nav class="navbar navbar-expand-lg navbar-light bg-light border-bottom mb-4">
    <div class="container-fluid">
      <div class="ms-auto w-100 d-flex justify-content-between">
        <button class="btn btn-outline-secondary btn-sm w-100" @click="volverAtras">
          <i class="bi bi-arrow-left"></i> Volver
        </button>

        <button class="btn btn-outline-primary btn-sm w-100" @click="vistaActual = 'menu'">
          <i class="bi bi-list"></i> Menú
        </button>

        <button class="btn btn-outline-success btn-sm w-100" @click="vistaActual = 'pedido'">
          <i class="bi bi-receipt"></i> Pedido <span v-if="pedidos.length" class="badge bg-success">{{ pedidos.length }}</span>
        </button>

        <button class="btn btn-outline-danger btn-sm w-100" @click="vistaActual = 'resumen'">
          <i class="bi bi-credit-card-fill"></i> Resumen <span v-if="resumenes.length" class="badge bg-danger">{{ resumenes.length }}</span>
        </button>
      </div>
    </div>
  </nav>
  <div class="container">
    <div v-if="vistaActual === 'menu'">
      <div class="mb-4 d-flex justify-content-end">
        <select id="categoriaSelect" v-model="categoriaSelected" class="form-select form-select-sm" style="width: auto;">
          <option value="0">Todas</option>
          <option v-for="categoria in catalogs.categoria" :key="categoria.id" :value="categoria.id">
            {{ categoria.name }} 
          </option>
        </select>
      </div>

      <div class="list-group">
        <div v-for="platillo in filteredPlatillos" :key="platillo.id" class="list-group-item d-flex justify-content-between align-items-center">
          <div>
            <span class="fw-bold">{{ platillo.nombre }}</span>
          </div>
          
          <div class="d-flex align-items-center gap-2">
            <div class="d-flex align-items-center gap-2">
              <input
                type="number"
                placeholder="Porcion"
                v-model.number="platillo.cantidadSeleccionada"
                min="1"
                step="0.5"
                class="form-control form-control-sm"
                style="width: 85px;"
              />
            </div>
            
            <button class="btn btn-sm btn-outline-primary" @click="confirmAddToOrder(platillo)">
              <i class="bi bi-plus-circle"></i>
            </button>
          </div>
          
        </div>
      </div>
    </div>

    <div v-else-if="vistaActual === 'pedido'" class="d-flex flex-column" style="min-height: 100vh;">
      <h4 class="mb-3">Pedido actual</h4>
      <div class="flex-grow-1" style="overflow-y: auto; max-height: calc(100vh - 250px);">
        <div v-if="pedidos.length > 0" class="list-group">
          <div v-for="(platillo, index) in pedidos" :key="index" class="list-group-item">
            <div class="d-flex justify-content-between align-items-center">
              <div>
                <span class="fw-bold">{{ platillo.nombre }}</span>
                <span class="fw-bold"> x{{ platillo.cantidad }}</span>
               
              </div>
              <button class="btn btn-danger btn-sm" @click="removeFromOrder(index)">
                <i class="bi bi-x-lg"></i>
              </button>
            </div>
            <input
              type="text"
              class="form-control form-control-sm mt-2"
              placeholder="Nota (opcional)"
              v-model="platillo.nota"
            />
          </div>
        </div>
        <div v-else class="alert alert-info">
          No hay platillos en el pedido actual. Agregue platillos desde el menú.
        </div>
      </div>

      <div v-if="pedidos.length > 0" class="border-top bg-white p-3">
        <div class="d-flex justify-content-between align-items-center mb-2">
        
        </div>

        <button class="btn btn-success btn-lg w-100" @click="confirmarPedido">
          <i class="bi bi-check-circle"></i> Confirmar Pedido
        </button>
      </div>
    </div>
    
    <div v-else-if="vistaActual === 'resumen'" class="d-flex flex-column" style="min-height: 100vh;">
      <h4 class="mb-3">Resumen de Mesa</h4>
      
      <!-- Pedidos confirmados -->
      <div class="flex-grow-1" style="overflow-y: auto; max-height: calc(100vh - 250px);">
        <div v-if="resumenes.length > 0">
          <div v-for="(pedido, pedidoIndex) in resumenes" :key="'pedido-'+pedido.id" class="card mb-3">
            <div class="card-header bg-light d-flex justify-content-between align-items-center">
              <span>Pedido #{{ pedido.id }}</span>
              <button class="btn btn-sm btn-warning text-white" @click="editarPedido(pedido)">
                <i class="bi bi-pencil-fill me-1"></i>Agregar/Editar
              </button>
            </div>
            <div class="card-body p-0">
              <ul class="list-group list-group-flush">
                <li v-for="(item, itemIndex) in agruparItemsPorNombre(pedido.items)" :key="'item-agrupado-'+pedidoIndex+'-'+itemIndex" class="list-group-item d-flex justify-content-between align-items-center">
                  <div>
                    <span class="fw-bold">{{ item.nombre }}</span>
                    <span class="text-muted ms-2"> x{{ item.cantidad }}</span>
                    <div v-if="item.nota" class="small text-muted">{{ item.nota }}</div>
                  </div>
                </li>
              </ul>
            </div>
            <div class="card-footer">
              <span class="text-muted">Estado: {{ pedido.estado === '1' ? 'Activo' : 'Completado' }}</span>
            </div>
          </div>
          <div class="border-top bg-white p-3">
           
            
            <div class="d-flex gap-2">
              <button class="btn btn-danger flex-grow-1" @click="finalizarMesa">
                <i class="bi bi-credit-card-fill"></i> Finalizar Mesa
              </button>
            </div>
          </div>
        </div>
        
        <div v-if="resumenes.length === 0" class="alert alert-info">
          No hay pedidos en el resumen. Puede agregar platillos desde el menú y confirmar el pedido.
        </div>
      </div>
    </div>
  </div>

  <!-- Modal para editar pedido -->
  <div class="modal fade" id="editarPedidoModal" tabindex="-1" aria-labelledby="editarPedidoModalLabel" aria-hidden="true" @hidden.bs.modal="cerrarModalEdicion">
    <div class="modal-dialog modal-lg">
      <div class="modal-content">
        <div class="modal-header">
          <h5 class="modal-title" id="editarPedidoModalLabel">Agregar/Editar Pedido #{{ pedidoEditando?.id || '' }}</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
        </div>
        <div class="modal-body">
          <template v-if="pedidoEditando">
            <div class="container-fluid">
              <div class="row mb-3" v-if="pedidoEditando.items && pedidoEditando.items.length > 0">
                <h6>Productos actuales:</h6>
                <div class="list-group">
                  <div v-for="(item, index) in pedidoEditando.items" 
                       class="list-group-item list-group-item-action"
                       :class="{
                         'border-warning': item.preparado == 1,
                         'border-success': item.preparado == 2,
                       }">
                    <div class="d-flex w-100 justify-content-between">
                      <h6 class="mb-1">{{ item.nombre }} x {{ item.cantidad }}
                        <span v-if="item.preparado == 1" class="badge bg-warning text-dark ms-2">En preparación</span>
                        <span v-if="item.preparado == 2" class="badge bg-success ms-2">Listo</span>
                      </h6>
                    </div>
                    <div class="row mt-2">
                      <div class="col-md-4">
                        <div class="input-group">
                          <span class="input-group-text">Cant.</span>
                          <input 
                            type="number" 
                            class="form-control form-control-sm" 
                            v-model="item.cantidad" 
                            min="1" 
                            step="0.5"
                            style="width: 80px;"
                          >
                        </div>
                      </div>
                      <div class="col-md-5">
                        <div class="input-group">
                          <span class="input-group-text">Nota</span>
                          <input 
                            type="text" 
                            class="form-control form-control-sm" 
                            v-model="item.nota" 
                            placeholder="Nota"
                          >
                        </div>
                      </div>
                      <div class="col-md-3 text-end">
                        <button class="btn btn-outline-danger btn-sm" @click="eliminarItemPedido(index)">
                          <i class="bi bi-trash"></i> Eliminar
                        </button>
                      </div>
                    </div>
                  </div>
                </div>


              </div>
              
              <div class="row mt-4">
                <h6>Agregar nuevo producto:</h6>
                <div class="mb-3">
                  <label for="nuevoPlatillo" class="form-label">Platillo</label>
                  <select class="form-select" id="nuevoPlatillo" v-model="nuevoPlatillo.id" @change="seleccionarPlatillo">
                    <option value="" selected>Seleccione un platillo...</option>
                    <option v-for="platillo in platillos" :key="platillo.id" :value="platillo.id">
                      {{platillo.nombre}}
                    </option>
                  </select>
                </div>
                <div class="mb-3">
                  <label for="cantidadPlatillo" class="form-label">Cantidad</label>
                  <div class="input-group">
                    <button class="btn btn-outline-secondary" type="button" @click="decrementarCantidad">
                      <i class="bi bi-dash"></i>
                    </button>
                    <input type="number" class="form-control text-center" id="cantidadPlatillo" min="0.5" step="0.5" v-model.number="nuevoPlatillo.cantidad">
                    <button class="btn btn-outline-secondary" type="button" @click="incrementarCantidad">
                      <i class="bi bi-plus"></i>
                    </button>
                  </div>
                </div>
                <div class="mb-3">
                  <label for="notaPlatillo" class="form-label">Nota (Opcional)</label>
                  <textarea class="form-control" id="notaPlatillo" rows="2" v-model="nuevoPlatillo.nota"></textarea>
                </div>

                <div class="mb-3 text-end">
                  <button 
                    class="btn btn-outline-primary" 
                    @click="agregarPlatilloAlPedido"
                    :disabled="!nuevoPlatillo.id || !nuevoPlatillo.cantidad"
                  >
                    <i class="bi bi-plus-circle"></i> Agregar al pedido
                  </button>
                </div>
              </div>
            </div>
          </template>
          <div v-else class="alert alert-info">
            No hay datos del pedido para editar
          </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cerrar</button>
          <button type="button" class="btn btn-success" @click="guardarPedidoEditado">Guardar Cambios</button>
        </div>
      </div>
    </div>
  </div>

  <!-- Modal para motivo de eliminación -->
  <div class="modal fade" id="motivoEliminacionModal" tabindex="-1" aria-labelledby="motivoEliminacionModalLabel" aria-hidden="true">
    <div class="modal-dialog">
      <div class="modal-content">
        <div class="modal-header bg-danger text-white">
          <h5 class="modal-title" id="motivoEliminacionModalLabel">
            <i class="bi bi-exclamation-triangle-fill me-2"></i>Motivo de eliminación
          </h5>
          <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Cerrar"></button>
        </div>
        <div class="modal-body">
          <div v-if="itemAEliminar" class="alert alert-warning mb-3">
            <strong>Producto:</strong> {{ itemAEliminar.nombre }} x {{ itemAEliminar.cantidad }}
            <span v-if="itemAEliminar.preparado == 1" class="badge bg-warning text-dark ms-2">En preparación</span>
            <span v-if="itemAEliminar.preparado == 2" class="badge bg-success ms-2">Listo</span>
          </div>
          <div class="mb-3">
            <label for="motivoEliminacion" class="form-label">
              <strong>Debe indicar el motivo de la cancelación:</strong>
            </label>
            <textarea 
              class="form-control" 
              id="motivoEliminacion" 
              rows="3" 
              v-model="motivoEliminacion"
              placeholder="Escriba aquí el motivo..."
            ></textarea>
            <div class="form-text text-danger" v-if="!motivoEliminacion || motivoEliminacion.trim().length < 10">
              * Campo obligatorio (mínimo 10 caracteres)
            </div>
            <div class="form-text text-muted" v-else>
              {{ motivoEliminacion.trim().length }} caracteres
            </div>
          </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
          <button 
            type="button" 
            class="btn btn-danger" 
            @click="confirmarEliminacion"
            :disabled="!motivoEliminacion || motivoEliminacion.trim().length < 10"
          >
            <i class="bi bi-trash"></i> Eliminar producto
          </button>
        </div>
      </div>
    </div>
  </div>
</div>