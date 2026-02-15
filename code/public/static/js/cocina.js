const app = Vue.createApp({
  data() {
    return {
      structure: {},
      platillos: [],          // Platillos en cocina
      catalogoPlatillos: [],  // Catálogo completo de platillos
      mostrarEntregados: true, // Toggle para mostrar/ocultar entregados
      catalogs: {
        preparado: [
          {"id": "0", "name": "Pendiente"},
          {"id": "1", "name": "Cocinando"},
          {"id": "2", "name": "Listo"}
        ],        
        categoria: [
          {"id": "1", "name": "Bebidas"},
          {"id": "2", "name": "Platos fuertes"},
          {"id": "3", "name": "Entradas"},
          {"id": "4", "name": "Postres"}
        ]
      },
      categoriaSelected: 1,
      idElemento: 0,     
      idMozo: 0,
      idZona: 0,
    }
  },  
  computed: {    // FILTRADO DE PLATILLOS
    filteredPlatillos() {
      return this.platillos.filter(platillo => {
        // Si mostrarEntregados está activo, mostrar todos
        if (this.mostrarEntregados) {
          return true;
        }
        // Si no, ocultar los que están listos (preparado 2)
        return platillo.preparado !== "2";
      });
    },
    
    // AGRUPAR PLATILLOS POR PEDIDO
    platillosAgrupados() {
      // Primero filtramos los platillos
      const platillosFiltrados = this.filteredPlatillos;
      
      // Luego los agrupamos por id_pedido
      const grupos = {};
      platillosFiltrados.forEach(platillo => {
        if (!grupos[platillo.id_pedido]) {
          grupos[platillo.id_pedido] = [];
        }
        grupos[platillo.id_pedido].push(platillo);
      });
      
      // Convertimos el objeto en un array para v-for
      return Object.entries(grupos).map(([id_pedido, platillos]) => ({
        id_pedido,
        platillos
      }));
    }
  },
  methods: {
    // Cargar el catálogo completo de platillos
    cargarCatalogoPlatillos() {
      axios.get(BASE_URL + "api/platillos")
        .then(response => {
          this.catalogoPlatillos = response.data;
        })
        .catch(error => {
          console.error("Error al cargar catálogo:", error);
        });
    },

    // OBTENEMOS LOS DIFERENTES IDs DESDE LA URL
    obtenerIdElemento() {
      const pathSegments = window.location.pathname.split('/');
      id = pathSegments[pathSegments.length - 1];
      console.log(id);
      return id;
    },
    
    
     listarPlatillosCocina() {
      // Modificado para filtrar solo platillos de cocina
      axios.get(BASE_URL + "api/test/?preparado=true&tipo=cocina").then(responseItems => {
        console.log('Platillos de cocina:', responseItems.data);
        this.platillos = responseItems.data;
      });
    },    actualizarEstado(platillo, nuevoEstado) {
      const estadoAnterior = platillo.preparado;
      
      const datos = {
        id: parseInt(platillo.id),
        id_platillo: parseInt(platillo.id_platillo),
        id_pedido: parseInt(platillo.id_pedido),
        preparado: parseInt(nuevoEstado),
        cantidad: parseFloat(platillo.cantidad)
      };

      axios.put(BASE_URL + "api/test/" + platillo.id, datos)
        .then(response => {
          console.log('Respuesta:', response.data);
          // Actualizar el estado localmente
          const index = this.platillos.findIndex(p => p.id === platillo.id);
          if (index !== -1) {
            // Actualizar el estado sin eliminar (mantener como fantasma)
            this.platillos[index].preparado = parseInt(nuevoEstado);
          }
        })
        .catch(error => {
          console.error("Error al actualizar estado:", error.response?.data || error);
          platillo.preparado = parseInt(estadoAnterior);
        });
    },
    
    // Función para confirmar y realizar el cierre de sesión
    confirmarLogout() {
      Swal.fire({
        title: '¿Cerrar sesión?',
        text: '¿Está seguro de que desea salir del sistema?',
        icon: 'question',
        showCancelButton: true,
        confirmButtonColor: '#d33',
        cancelButtonColor: '#3085d6',
        confirmButtonText: 'Salir',
        cancelButtonText: 'Cancelar',
        width: '360px',
        didOpen: () => {
          // Aplicar estilos directamente a los botones
          const confirmBtn = Swal.getConfirmButton();
          const cancelBtn = Swal.getCancelButton();
          
          if (confirmBtn && cancelBtn) {
            // Asegurar que ambos botones tengan el mismo ancho
            const minWidth = '120px';
            confirmBtn.style.minWidth = minWidth;
            cancelBtn.style.minWidth = minWidth;
            confirmBtn.style.padding = '8px 16px';
            cancelBtn.style.padding = '8px 16px';
          }
        }
      }).then((result) => {
        if (result.isConfirmed) {
          this.logout();
        }
      });
    },
    
    // Función para cerrar sesión
    logout() {
      axios.get(BASE_URL + "api/logout")
        .then(response => {
          window.location.href = BASE_URL; // Redirigir al inicio o login
        })
        .catch(error => {
          console.error("Error al cerrar sesión:", error);
          // Si falla el logout, redirigir de todos modos
          window.location.href = BASE_URL;
        });
    },
    
    // Toggle para mostrar/ocultar pedidos entregados
    toggleEntregados() {
      this.mostrarEntregados = !this.mostrarEntregados;
    }
  },
  
  mounted: function() {
    this.idElemento = this.obtenerIdElemento();
    this.cargarCatalogoPlatillos(); // Primero cargamos el catálogo
    this.listarPlatillosCocina();   // Luego los platillos en cocina

    // Configurar actualización automática cada minuto
    this.intervaloActualizacion = setInterval(() => {
      this.listarPlatillosCocina();
    }, 60000); // 60000 ms = 1 minuto
  },

  // Limpiar el intervalo cuando el componente se destruye
  unmounted: function() {
    if (this.intervaloActualizacion) {
      clearInterval(this.intervaloActualizacion);
    }
  }
});