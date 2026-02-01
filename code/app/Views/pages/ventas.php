  <div id="ventasDetalle">
    <div class="container position-relative">
      <!-- Cabecera con botón de cierre de sesión -->
      <div class="d-flex justify-content-end mb-3">
        <button class="btn btn-outline-danger" @click="confirmarLogout">
          <i class="bi bi-box-arrow-right me-1"></i> Salir
        </button>
      </div>
      
      <!-- Filtro de fecha -->
      <div class="mb-3 d-flex gap-3 align-items-center">
        <div class="alert alert-info mb-0 flex-grow-1 text-center">
          <i class="bi bi-calendar-check"></i> <span style="font-size: 1.2rem; font-weight: bold;">{{ textoFiltro }}</span>
        </div>
        <div class="d-flex align-items-center gap-2">
          <input type="date" 
                class="form-control" 
                style="width: 180px;" 
                v-model="fechaFiltro"
                @change="actualizarListado">
          <button class="btn btn-primary" @click="actualizarListado">
            <i class="bi bi-arrow-clockwise"></i> Actualizar
          </button>
          <button class="btn btn-success btn-lg position-relative" @click="crearNuevoPedido" 
            style="height: 50px; width: 50px; border-radius: 50%; font-size: 24px; padding: 0; box-shadow: 0 4px 8px rgba(0,0,0,0.2);">
            <i class="bi bi-plus-lg"></i>
          </button>
        </div>
      </div>

      <!-- Campo de búsqueda -->
      <div class="mb-4 d-flex justify-content-end">
        <div class="input-group" style="width: 360px;">
          <input type="text" 
                class="form-control" 
                placeholder="Buscar por número de pedido" 
                v-model="numeroTicket"
                @keyup.enter="buscarPorTicket">
          <button class="btn btn-secondary" @click="buscarPorTicket">
            <i class="bi bi-search"></i> Buscar
          </button>
          <button v-if="busquedaActiva" class="btn btn-danger" @click="limpiarBusqueda">
            <i class="bi bi-x-circle"></i> Limpiar
          </button>
        </div>
      </div>

      <div class="tables-grid">
        <template v-for="grupo in platillosAgrupados">
          <div class="pedido-grupo mb-4">
            <h5 class="bg-dark text-white p-2 rounded d-flex justify-content-between align-items-start">
              <div class="d-flex flex-column text-center" style="flex: 1;">
                <span class="fw-bold">Pedido #{{ String(grupo.id_pedido).padStart(4, '0') }}</span>
                <span>{{ grupo.platillos[0].mesa_nombre }}</span>
                <span>{{ grupo.platillos[0].mesero_nombre }}</span>
              </div>
              <div class="d-flex flex-column text-center" style="flex: 1;">
                <template v-if="grupo.platillos[0].pedido_estado != 2">
                  <div class="d-flex gap-2 align-items-center justify-content-center">
                    <button class="btn btn-secondary" @click="imprimirPreticket(grupo.id_pedido)" style="font-weight: 600; width: 140px;">
                      <i class="bi bi-receipt"></i> PRE-TICKET
                    </button>
                    <button class="btn btn-primary" v-on:click="goPago(grupo.id_pedido)" style="font-weight: 600; width: 140px;">
                      PAGAR
                    </button>
                    <button class="btn btn-warning" style="font-weight: 600; width: 140px; color: white;" @click="editarPedido(grupo.id_pedido)">
                      AGREGAR/EDIT
                    </button>
                  </div>
                </template>                
                <template v-else>
                  <div class="d-flex gap-2 align-items-center justify-content-center">
                    <button class="btn btn-success" style="font-weight: 600; width: 140px;">
                      PAGADO
                    </button>
                    <button class="btn btn-info" style="font-weight: 600; width: 140px; color: white;" @click="reimprimirTicket(grupo.id_pedido)">
                      <i class="bi bi-printer"></i> IMPRIMIR
                    </button>
                    <button class="btn btn-danger" style="font-weight: 600; width: 140px;" @click="cancelarPago(grupo.id_pedido)">
                      CANCELAR
                    </button>
                  </div>
                  <div class="mt-1">
                    <small class="">
                      {{ formatMetodoPago(grupo.platillos[0].metodo) }} | {{ formatComprobante(grupo.platillos[0].comprobante) }}
                    </small>
                  </div>
                </template>
              </div>
            </h5>
            <div class="platillos-grupo">
              <div v-for="platillo in grupo.platillos" class="platillo">
                <div class="nombrePlatillo d-flex justify-content-between align-items-center w-100">             
                  <div>
                    <span>{{platillo.p_name}}</span>
                  </div>
                  <div>
                    <span>${{platillo.precio_unitario}} x{{platillo.cantidad}} = {{formatPrecio(platillo.precio_unitario * platillo.cantidad)}}.-</span>
                  </div>
                </div>
                <div v-if="platillo.nota != ''" class="nota">{{platillo.nota}}</div>
              </div>
            <div class="platillo" style="flex: 1; display: flex; flex-direction: column; align-items: stretch; width: 100%;">
                <div class="text-end mt-2 px-3 w-100" style="background-color: #f8f9fa; border-radius: 4px; padding: 10px;">
                  <div class="d-flex justify-content-between align-items-center mb-2">
                    <span>Subtotal:</span>
                    <span>${{ formatPrecio(parseFloat(calcularTotalPedido(grupo.platillos)) / 1.16) }}</span>
                  </div>
                  <div class="d-flex justify-content-between align-items-center mb-2">
                    <span>IVA:</span>
                    <span>${{ formatPrecio(calcularIva(grupo.platillos)) }}</span>
                  </div>
                  <div class="border-top pt-2 mt-1">
                    <div class="d-flex justify-content-between align-items-center">
                      <span class="fw-bold">TOTAL:</span>
                      <span class="fw-bold text-danger">${{ formatPrecio(calcularTotal(grupo.platillos)) }}</span>
                    </div>
                  </div>
                </div>
            </div>
            </div>
            
          </div>
        </template>
      </div>
      
      <!-- Modal -->
      <div class="modal fade" id="modalPago" tabindex="-1" aria-labelledby="modalPagoLabel" aria-hidden="true" @hidden.bs.modal="cerrarModal">
        <div class="modal-dialog">
          <div class="modal-content">
            <div class="modal-header">
              <h5 class="modal-title" id="modalPagoLabel">Detalles del Pago</h5>
              <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
              <!-- Resumen del pedido a pagar -->
              <div class="mb-4 card" v-if="pedidoACobrar > 0 && platillosAgrupados.find(g => g.id_pedido == pedidoACobrar)">
                <div class="card-header bg-light">
                  <h6 class="mb-0">Resumen del pedido #{{ String(pedidoACobrar).padStart(4, '0') }}</h6>
                </div>
                <div class="card-body w-100" style="background-color: #f8f9fa;">
                  <div class="d-flex justify-content-between align-items-center mb-2">
                    <span>Subtotal:</span>
                    <span>${{ formatPrecio(parseFloat(calcularTotalPedido(platillosAgrupados.find(g => g.id_pedido == pedidoACobrar).platillos)) / 1.16) }}</span>
                  </div>
                  <div class="d-flex justify-content-between align-items-center mb-2">
                    <span>IVA:</span>
                    <span>${{ formatPrecio(calcularIva(platillosAgrupados.find(g => g.id_pedido == pedidoACobrar).platillos)) }}</span>
                  </div>
                  <div class="border-top pt-2 mt-1">
                    <div class="d-flex justify-content-between align-items-center">
                      <span class="fw-bold">TOTAL:</span>
                      <span class="fw-bold text-danger">${{ formatPrecio(calcularTotal(platillosAgrupados.find(g => g.id_pedido == pedidoACobrar).platillos)) }}</span>
                    </div>
                  </div>
                </div>
              </div>

              <div class="mb-3">
                <label for="metodoPago" class="form-label">Método de Pago</label>
                <select class="form-select" id="metodoPago" v-model="formaPago">
                  <!-- <option selected>Selecciona método de pago</option> -->
                  <option value="efectivo">Efectivo</option>
                  <option value="tarjeta">Tarjeta</option>
                  <option value="mixto">Mixto</option>
                </select>
              </div>

              <div class="mb-3">
                <label for="tipoComprobante" class="form-label">Tipo de Comprobante</label>
                <select class="form-select" id="tipoComprobante" v-model="tipoDoc">
                  <!-- <option selected=selected>Selecciona tipo de comprobante</option> -->
                  <option value="ticket">Ticket</option>
                  <option value="factura">Factura</option>
                  <option value="ticket electronico">Ticket Electrónico</option>
                </select>
              </div>

            </div>
            <div class="modal-footer">
              <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cerrar</button>
              <button type="button" class="btn btn-primary" v-on:click="cobrar()">Cobrar</button>
            </div>
          </div>
        </div>
      </div>
      
      <!-- Modal para Edición de Pedido -->
      <div class="modal fade" id="modalEdicionPedido" tabindex="-1" aria-labelledby="modalEdicionPedidoLabel" aria-hidden="true" @hidden.bs.modal="cerrarModalEdicion">
        <div class="modal-dialog modal-lg">
          <div class="modal-content">
            <div class="modal-header">
              <h5 class="modal-title" id="modalEdicionPedidoLabel">Agregar/Editar Pedido #{{ String(pedidoAEditar).padStart(4, '0') }}</h5>
              <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
              <template v-if="pedidoAEditar > 0">
                <div class="container-fluid">
                  <div class="row mb-3" v-if="pedidoAEditar > 0">
                    <h6>Productos actuales:</h6>
                    <div class="list-group">
                      <div v-for="platillo in pedidoActualTemporal" 
                           class="list-group-item list-group-item-action"
                           :class="{'border-success bg-light': platillo.temporal}">
                        <div class="d-flex w-100 justify-content-between">
                          <h6 class="mb-1">
                            {{platillo.p_name}} x {{platillo.cantidad}}
                            <span v-if="platillo.temporal" class="badge bg-success ms-2">Nuevo</span>
                            <span v-if="itemsPendientesEliminar.includes(platillo.id)" class="badge bg-danger ms-2">Eliminando</span>
                          </h6>
                          <small>${{formatPrecio(platillo.precio_unitario * platillo.cantidad)}}</small>
                        </div>
                        <small v-if="platillo.nota" class="d-block mb-2">Nota: {{platillo.nota}}</small>
                        <div class="d-flex justify-content-end gap-2">
                          <!-- Botón de editar temporalmente oculto -->
                          <!--
                          <button class="btn btn-sm btn-outline-primary">
                            <i class="bi bi-pencil"></i> Editar
                          </button>
                          -->
                          <button class="btn btn-sm btn-outline-danger" 
                                  @click="eliminarItemPedido(platillo.temporal ? platillo.id_platillo : platillo.id)"
                                  v-if="!itemsPendientesEliminar.includes(platillo.id)">
                            <i class="bi bi-trash"></i> Eliminar
                          </button>
                          <button class="btn btn-sm btn-outline-secondary" 
                                  v-if="itemsPendientesEliminar.includes(platillo.id)"
                                  @click="itemsPendientesEliminar = itemsPendientesEliminar.filter(id => id !== platillo.id)">
                            <i class="bi bi-arrow-counterclockwise"></i> Cancelar eliminación
                          </button>
                        </div>
                      </div>
                      <div class="list-group-item bg-light text-center" v-if="pedidoActualTemporal.length === 0">
                        <p class="mb-0">No hay productos en este pedido</p>
                      </div>
                    </div>
                    <div v-if="itemsPendientesEliminar.length > 0 || itemsPendientesAgregar.length > 0" class="alert alert-warning mt-3">
                      <i class="bi bi-exclamation-triangle-fill me-2"></i> Hay cambios pendientes. Recuerda guardar los cambios para aplicarlos.
                    </div>
                  </div>
                  
                  <div class="row">
                    <h6>Agregar nuevo producto:</h6>
                    <div class="mb-3">
                      <label for="nuevoPlatillo" class="form-label">Platillo</label>
                      <select class="form-select" id="nuevoPlatillo" v-model="nuevoPlatillo.id">
                        <option value="" selected>Seleccione un platillo...</option>
                        <option v-for="platillo in catalogoPlatillos" :value="platillo.id">
                          {{platillo.nombre}} - ${{platillo.precio}}
                        </option>
                      </select>
                    </div>
                    <div class="mb-3">
                      <label for="cantidadPlatillo" class="form-label">Cantidad</label>
                      <input type="number" class="form-control" id="cantidadPlatillo" min="0.5" step="0.5" value="1" v-model="nuevoPlatillo.cantidad">
                    </div>
                    <div class="mb-3">
                      <label for="notaPlatillo" class="form-label">Nota (Opcional)</label>
                      <textarea class="form-control" id="notaPlatillo" rows="2" v-model="nuevoPlatillo.nota"></textarea>
                    </div>
                    <div class="mb-3 text-end">
                      <button class="btn btn-outline-primary" @click="agregarPlatilloPedido">
                        <i class="bi bi-plus-circle"></i> Agregar al pedido
                      </button>
                    </div>
                  </div>
                </div>
              </template>
            </div>
            <div class="modal-footer">
              <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cerrar</button>
              <button type="button" class="btn btn-success" @click="guardarCambiosPedido">
                <span v-if="itemsPendientesEliminar.length > 0 || itemsPendientesAgregar.length > 0">
                  <i class="bi bi-save"></i> Guardar Cambios ({{ itemsPendientesEliminar.length + itemsPendientesAgregar.length }})
                </span>
                <span v-else>Guardar</span>
              </button>
            </div>
          </div>
        </div>
      </div>
      <!-- Modal para Crear Pedido Nuevo -->
      <div class="modal fade" id="modalCrearPedido" tabindex="-1" aria-labelledby="modalCrearPedidoLabel" aria-hidden="true" @hidden.bs.modal="cerrarModalCrearPedido">
        <div class="modal-dialog modal-lg">
          <div class="modal-content">
            <div class="modal-header bg-success text-white">
              <h5 class="modal-title" id="modalCrearPedidoLabel">Crear Nuevo Pedido</h5>
              <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
              <div class="container-fluid">
                <!-- Lista de productos seleccionados -->
                <div class="row mb-4">
                  <div class="col-12">
                    <div class="d-flex justify-content-between align-items-center mb-2">
                      <h6 class="mb-0 fw-bold">Productos seleccionados:</h6>
                      <span class="badge bg-primary rounded-pill" v-if="nuevosPedidoItems.length > 0">
                        {{ nuevosPedidoItems.length }} productos
                      </span>
                    </div>
                    
                    <div class="list-group" v-if="nuevosPedidoItems.length > 0">
                      <div v-for="(item, index) in nuevosPedidoItems" 
                          class="list-group-item list-group-item-action position-relative" 
                          style="transition: all 0.2s ease;">
                        <div class="d-flex w-100 justify-content-between">
                          <h6 class="mb-1">{{item.nombre}}</h6>
                          <span class="badge bg-secondary">{{item.cantidad}} x ${{formatPrecio(item.precio)}}</span>
                        </div>
                        <div class="d-flex justify-content-between align-items-center">
                          <small v-if="item.nota" class="text-muted">Nota: {{item.nota}}</small>
                          <div>
                            <span class="me-3 fw-bold">${{formatPrecio(item.precio * item.cantidad)}}</span>
                            <button class="btn btn-sm btn-outline-danger" @click="removerItemDeNuevoPedido(index)">
                              <i class="bi bi-trash"></i> Eliminar
                            </button>
                          </div>
                        </div>
                        <!-- Overlay para hover effect -->
                        <div class="position-absolute top-0 start-0 w-100 h-100 d-flex justify-content-center align-items-center"
                            style="background-color: rgba(0,0,0,0.03); opacity: 0; transition: opacity 0.2s ease; pointer-events: none;">
                        </div>
                      </div>
                      <div class="list-group-item w-100" style="background-color: #f8f9fa;">
                        <div class="d-flex justify-content-between align-items-center mb-2">
                          <span>Subtotal:</span>
                          <span>${{formatPrecio(calcularTotalNuevoPedido() / 1.16)}}</span>
                        </div>
                        <div class="d-flex justify-content-between align-items-center mb-2">
                          <span>IVA:</span>
                          <span>${{formatPrecio(calcularIvaNuevoPedido())}}</span>
                        </div>
                        <div class="border-top pt-2 mt-1">
                          <div class="d-flex justify-content-between align-items-center">
                            <span class="fw-bold">TOTAL:</span>
                            <span class="fw-bold text-danger">${{formatPrecio(calcularTotalNuevoPedido())}}</span>
                          </div>
                        </div>
                      </div>
                    </div>
                    <div class="alert alert-info" v-else>
                      <i class="bi bi-info-circle me-2"></i> No hay productos agregados al pedido
                    </div>
                  </div>
                </div>
                <hr>
                
                <!-- Formulario para agregar producto -->
                <div class="row">
                  <h6>Agregar producto:</h6>
                  <div class="mb-3">
                    <label for="nuevoPlatilloSelect" class="form-label">Platillo</label>
                    <select class="form-select" id="nuevoPlatilloSelect" v-model="nuevoItemPedido.id_platillo" @change="actualizarPrecioPlatillo">
                      <option value="" selected>Seleccione un platillo...</option>
                      <option v-for="platillo in catalogoPlatillos" :value="platillo.id" :data-precio="platillo.precio">
                        {{platillo.nombre}} - ${{platillo.precio}}
                      </option>
                    </select>
                  </div>
                  <div class="mb-3">
                    <label for="cantidadPlatilloNuevo" class="form-label">Cantidad</label>
                    <div class="input-group">
                      <button class="btn btn-outline-secondary" type="button" @click="decrementarCantidad">
                        <i class="bi bi-dash"></i>
                      </button>
                      <input type="number" class="form-control text-center" id="cantidadPlatilloNuevo" min="0.5" step="0.5" v-model.number="nuevoItemPedido.cantidad">
                      <button class="btn btn-outline-secondary" type="button" @click="incrementarCantidad">
                        <i class="bi bi-plus"></i>
                      </button>
                    </div>
                  </div>
                  <div class="mb-3">
                    <label for="notaPlatilloNuevo" class="form-label">Nota (Opcional)</label>
                    <textarea class="form-control" id="notaPlatilloNuevo" rows="2" v-model="nuevoItemPedido.nota"></textarea>
                  </div>
                  <div class="mb-3">
                    <div v-if="nuevoItemPedido.id_platillo" class="py-3 mt-3 w-100" style="background-color: #f8f9fa; border-radius: 4px; padding: 10px;">
                      <div class="d-flex justify-content-between align-items-center mb-2">
                        <span>Subtotal:</span>
                        <span>${{ formatPrecio(parseFloat(calcularSubtotalItem()) / 1.16) }}</span>
                      </div>
                      <div class="d-flex justify-content-between align-items-center mb-2">
                        <span>IVA:</span>
                        <span>${{ calcularIvaItem() }}</span>
                      </div>
                      <div class="border-top pt-2 mt-1">
                        <div class="d-flex justify-content-between align-items-center">
                          <span class="fw-bold">TOTAL:</span>
                          <span class="fw-bold text-danger">${{ calcularTotalItem() }}</span>
                        </div>
                      </div>
                    </div>
                  </div>
                  
                  <div class="text-end mb-3">
                    <button class="btn btn-primary" @click="agregarItemANuevoPedido">
                      <i class="bi bi-plus-circle"></i> Agregar producto
                    </button>
                  </div>
                </div>
                
                <hr>
                
                <!-- Opción para enviar a cocina/barra -->
                <div class="row">
                  <div class="col-12">
                    <div class="form-check form-switch">
                      <input class="form-check-input" type="checkbox" id="enviarCocinaBarra" v-model="enviarCocinaBarra">
                      <label class="form-check-label" for="enviarCocinaBarra">
                        <strong>Cocina/Barra</strong>
                        <small class="text-muted d-block">Marca esta opción si el pedido requiere preparación en cocina o barra</small>
                      </label>
                    </div>
                  </div>
                </div>
              </div>
            </div>
            <div class="modal-footer">
              <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
              <button type="button" class="btn btn-success" @click="guardarNuevoPedido" :disabled="nuevosPedidoItems.length === 0">
                <i class="bi bi-save"></i> Guardar Pedido
              </button>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>