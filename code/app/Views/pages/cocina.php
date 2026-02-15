<div id="cocinaDetalle">
  <div class="container">
    <!-- Cabecera con botones -->
    <div class="d-flex justify-content-between mb-3">
      <!-- Botón toggle entregados -->
      <button class="btn" 
              :class="mostrarEntregados ? 'btn-success' : 'btn-danger'"
              @click="toggleEntregados">
        <i class="bi" :class="mostrarEntregados ? 'bi-eye' : 'bi-eye-slash'"></i>
        {{ mostrarEntregados ? 'Ocultar Entregados' : 'Ver Entregados' }}
      </button>
      
      <!-- Botón de cierre de sesión -->
      <button class="btn btn-outline-danger" @click="confirmarLogout">
        <i class="bi bi-box-arrow-right me-1"></i> Salir
      </button>
    </div>
    
    <template v-for="grupo in platillosAgrupados">      <div class="pedido-grupo mb-4">
        <h5 class="bg-dark text-white p-2 rounded d-flex justify-content-between">
          <span>Pedido #{{ grupo.id_pedido }} | Mesa: {{ grupo.platillos[0].mesa_nombre }} | Mesero: {{ grupo.platillos[0].mesero_nombre }}</span>
          
        </h5>
        <div class="platillos-grupo">
          <div v-for="platillo in grupo.platillos" 
               class="platillo"
               :class="{ 'platillo-entregado': platillo.preparado == '2' }">
            <div class="nombrePlatillo">
              <span class="cantidad">{{platillo.cantidad}}</span>
              {{platillo.p_name}}
              
              <!-- Badge ENTREGADO -->
              <span v-if="platillo.preparado == '2'" class="badge bg-secondary ms-2">ENTREGADO</span>
              
              <!-- Botón Iniciar (Pendiente -> Cocinando) -->
              <span @click="actualizarEstado(platillo, 1)" 
                    v-if="platillo.preparado == 0" 
                    class="btn btn-primary btns m-2 float-end">
                <i class="bi bi-play-fill"></i>
              </span>
              
              <!-- Botón Finalizar (Cocinando -> Listo) -->
              <span @click="actualizarEstado(platillo, 2)" 
                    v-if="platillo.preparado == 1" 
                    class="btn btn-success btns m-2 float-end">
                <i class="bi bi-check2-circle"></i>
              </span>
            </div>
            <div v-if="platillo.nota != ''" class="nota">{{platillo.nota}}</div>
          </div>
        </div>
      </div>
    </template>
  </div>
</div>