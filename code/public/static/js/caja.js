const app = Vue.createApp({
  data() {
    return {
      idRol: window.sessionData?.idRol || 0, // Rol del usuario desde sesión
      reportes: [],
      reportesOriginal: [],
      numeroTicket: '',
      busquedaActiva: false,
      fechaFiltro: new Date().toISOString().split('T')[0],
      tipoTotal: 'general',
      productosVendidos: [],
      reporteSeleccionado: null,
      ticketData: null,
      // Variables para cuota por comanda
      cuotaComandaBase: 18, // Monto en pesos por comanda (configurable)
      montoBaseComanda: 800, // Monto base para 1 comanda (configurable)
      incrementoComanda: 500, // Incremento de monto para comandas adicionales
      meserosConCuotas: [], // Array para almacenar los cálculos por mesero
      // Variables para Corte Historia
      corteHistoria: {
        filtros: {
          fecha_inicio: '',
          fecha_fin: '',
          folio_inicio: '',
          folio_fin: '',
          tipo: ''
        },
        folios: [],
        resumen: {
          ticket: { cantidad: 0, total: 0 },
          factura: { cantidad: 0, total: 0 },
          'ticket electronico': { cantidad: 0, total: 0 },
          total_general: 0,
          total_folios: 0
        },
        filtrosAplicados: {},
        loading: false,
        error: null
      },
      // Variables para Respaldo
      respaldo: {
        filtros: {
          fecha_inicio: '',
          fecha_fin: '',
          folio_inicio: '',
          folio_fin: ''
        },
        formato: 'sql', // 'sql' o 'excel'
        loading: false,
        error: null
      },
      // Variables para Finalizar Día
      finalizarDia: {
        fecha: new Date().toISOString().split('T')[0],
        loading: false,
        ejecutando: false,
        error: null,
        resumen: null,
        checkbox1: false,
        checkbox2: false,
        textoConfirmacion: '',
        resultado: null
      },
      // Variables para Exportar Excel
      exportExcel: {
        fechaInicio: '',
        fechaFin: '',
        descargando: false,
        mensaje: '',
        mensajeTipo: 'success'
      }
    }
  },
  
  computed: {    
    textoFiltro() {
      const fecha = new Date(this.fechaFiltro + 'T00:00:00');
      const hoy = new Date();
      const esHoy = this.fechaFiltro === hoy.toISOString().split('T')[0];
      
      // Formato personalizado dia/mes/año
      const dia = String(fecha.getDate()).padStart(2, '0');
      const mes = String(fecha.getMonth() + 1).padStart(2, '0');
      const año = fecha.getFullYear();
      
      return `Resumen del día: ${dia}/${mes}/${año}`;
    },
    
    reportesFiltrados() {
      let reportesFiltrados = this.reportes.filter(reporte => reporte.estado === 'emitido');
      
      // Aplicar filtro según el tipo seleccionado
      switch(this.tipoTotal) {
        case 'tickets':
          return reportesFiltrados.filter(r => r.tipo === 'ticket' || r.tipo === 'ticket electronico');
        case 'facturas':
          return reportesFiltrados.filter(r => r.tipo === 'factura');
        case 'corte_z':
          // Ordenamos por número para el resumen - solo tickets electrónicos, excluyendo tickets normales y facturas
          return reportesFiltrados
            .filter(r => r.tipo === 'ticket electronico') // Solo tickets electrónicos
            .sort((a, b) => a.numero - b.numero);
        case 'resumen_prueba2':
          // Filtrar tickets normales (numero = 0 y estado = 'emitido')
          return reportesFiltrados.filter(r => r.tipo === 'ticket' && (r.numero === 0 || r.numero === '0') && r.estado === 'emitido');
        case 'resumen_prueba3':
          // Para el ticket foliado de ejemplo, simplemente devolvemos los reportes filtrados
          return reportesFiltrados;
        case 'items_vendidos':
          // Para la sección de items vendidos
          return reportesFiltrados;
        case 'general':
        default:
          return reportesFiltrados;
      }
    }
  },
  
  methods: {
    // Función para imprimir solo el contenido del Corte Z
    imprimirCorteZ() {
      // Obtener el contenido del Corte Z
      const contenidoCorteZ = document.getElementById('contenido-corte-z');
      
      if (!contenidoCorteZ) {
        alert('No se puede imprimir. El contenido del Corte Z no está disponible.');
        return;
      }
      
      // Crear una nueva ventana para imprimir más grande
      const ventanaImpresion = window.open('', '_blank', 'width=1200,height=900,scrollbars=yes,resizable=yes');
      
      // Escribir el HTML en la nueva ventana
      ventanaImpresion.document.write(`
        <!DOCTYPE html>
        <html>
        <head>
          <meta charset="UTF-8">
          <title>Corte Z - ${this.formatFechaCorteZ(this.fechaFiltro)}</title>
          <style>
            body {
              font-family: 'Courier New', monospace;
              font-size: 14px;
              margin: 20px;
              padding: 20px;
              background: white;
              line-height: 1.4;
            }
            pre {
              margin: 0;
              white-space: pre;
              font-family: 'Courier New', monospace;
              font-size: 14px;
              letter-spacing: 0;
              word-spacing: 0;
            }
            @media print {
              body { 
                margin: 0; 
                padding: 10px; 
                font-size: 12px;
              }
              pre {
                font-size: 12px;
              }
            }
          </style>
        </head>
        <body>
          ${contenidoCorteZ.innerHTML}
        </body>
        </html>
      `);
      
      ventanaImpresion.document.close();
      
      // Esperar a que cargue y luego imprimir
      ventanaImpresion.onload = function() {
        ventanaImpresion.print();
        ventanaImpresion.close();
      };
    },
    
    async cargarReportes() {
      try {
        // Cargar los reportes con información completa de pedidos (id_mesero y id_mesa)
        const { data: reportes } = await axios.get(BASE_URL + "/api/reportes/conDetallesPedido", {
          params: {
            fecha: this.fechaFiltro
          }
        });
        // Mapear los datos para que cada reporte tenga su info de pedido accesible
        // El API nos devuelve id_mesero y id_mesa directamente junto con nombre_mesero
        this.reportes = reportes;
        this.reportesOriginal = [...reportes];
        this.busquedaActiva = false;
        
        // Si por alguna razón la API no está disponible, intentar la carga normal
        if (!reportes || reportes.length === 0) {
          const { data: reportesNormales } = await axios.get(BASE_URL + "/api/reportes", {
            params: {
              fecha: this.fechaFiltro
            }
          });
          this.reportes = reportesNormales;
          this.reportesOriginal = [...reportesNormales];
        }
        
        // Cargar los productos vendidos para la misma fecha
        try {
          const { data: productos } = await axios.get(BASE_URL + "/api/reportes/vendidosPorFecha", {
            params: {
              fecha: this.fechaFiltro
            }
          });
          console.log('Productos vendidos cargados:', productos);
          this.productosVendidos = productos;
        } catch (errorProductos) {
          console.error('Error al cargar productos vendidos:', errorProductos);
          this.productosVendidos = [];
        }
        
        // Devolver una promesa resuelta para poder encadenar operaciones
        return Promise.resolve();
      } catch (error) {
        console.error('Error al cargar reportes:', error);
        // Si falla la carga con detalles, intentar la carga normal
        try {
          const { data: reportesNormales } = await axios.get(BASE_URL + "/api/reportes", {
            params: {
              fecha: this.fechaFiltro
            }
          });
          console.log('Reportes cargados sin detalles:', reportesNormales);
          this.reportes = reportesNormales;
          this.reportesOriginal = [...reportesNormales];
        } catch (secondError) {
          console.error('Error también al cargar reportes normales:', secondError);
          this.reportes = [];
          this.reportesOriginal = [];
        }
        
        this.productosVendidos = [];
        
        // Devolver una promesa resuelta incluso en caso de error
        return Promise.resolve();
      }
    },

    formatFechaCorte(fecha) {
      // Si es un objeto, podría ser un reporte seleccionado con un campo fecha
      if (typeof fecha === 'object' && fecha !== null && fecha.fecha) {
        return this.formatFechaDesdeString(fecha.fecha);
      }
      
      try {
        let fechaParaProcesar = fecha || this.fechaFiltro;
        
        // Si tenemos un reporte seleccionado y fecha no se especificó, usar la fecha del reporte
        if (!fecha && this.reporteSeleccionado && this.reporteSeleccionado.fecha) {
          fechaParaProcesar = this.reporteSeleccionado.fecha;
        }
        
        return this.formatFechaDesdeString(fechaParaProcesar);
      } catch (error) {
        console.error("Error en formatFechaCorte:", error);
        
        // En caso de error, devolver la fecha actual
        const fechaActual = new Date();
        return `${String(fechaActual.getDate()).padStart(2, '0')}/${String(fechaActual.getMonth() + 1).padStart(2, '0')}/${fechaActual.getFullYear()} ${String(fechaActual.getHours()).padStart(2, '0')}:${String(fechaActual.getMinutes()).padStart(2, '0')} hrs.`;
      }
    },
    
    formatFechaDesdeString(fechaStr) {
      if (!fechaStr) return this.formatFechaActual();
      
      try {
        // Si es una fecha ISO (YYYY-MM-DD)
        if (typeof fechaStr === 'string' && fechaStr.includes('-')) {
          // Formato ISO YYYY-MM-DD o YYYY-MM-DDTHH:mm:ss
          let fechaObj;
          if (fechaStr.includes('T')) {
            fechaObj = new Date(fechaStr);
          } else {
            fechaObj = new Date(fechaStr + 'T12:00:00'); // Usar mediodía para evitar problemas de zonas horarias
          }
          
          if (!isNaN(fechaObj.getTime())) {
            const dia = String(fechaObj.getDate()).padStart(2, '0');
            const mes = String(fechaObj.getMonth() + 1).padStart(2, '0');
            const año = fechaObj.getFullYear();
            const hora = String(fechaObj.getHours()).padStart(2, '0');
            const minutos = String(fechaObj.getMinutes()).padStart(2, '0');
            
            return `${dia}/${mes}/${año} ${hora}:${minutos} hrs.`;
          }
        }
        
        // Si es una fecha completa con timestamp
        const fechaObj = new Date(fechaStr);
        if (!isNaN(fechaObj.getTime())) {
          const dia = String(fechaObj.getDate()).padStart(2, '0');
          const mes = String(fechaObj.getMonth() + 1).padStart(2, '0');
          const año = fechaObj.getFullYear();
          const hora = String(fechaObj.getHours()).padStart(2, '0');
          const minutos = String(fechaObj.getMinutes()).padStart(2, '0');
          
          return `${dia}/${mes}/${año} ${hora}:${minutos} hrs.`;
        }
      } catch (error) {
        console.error("Error al procesar fecha:", error);
      }
      
      return this.formatFechaActual();
    },
    
    formatFechaActual() {
      const fechaActual = new Date();
      const dia = String(fechaActual.getDate()).padStart(2, '0');
      const mes = String(fechaActual.getMonth() + 1).padStart(2, '0');
      const año = fechaActual.getFullYear();
      const hora = String(fechaActual.getHours()).padStart(2, '0');
      const minutos = String(fechaActual.getMinutes()).padStart(2, '0');
      
      return `${dia}/${mes}/${año} ${hora}:${minutos} hrs.`;
    },

    // Función específica para Corte Z que combina fecha del filtro con hora actual de impresión
    formatFechaCorteZ(fechaFiltro) {
      const fechaActual = new Date();
      const horaActual = String(fechaActual.getHours()).padStart(2, '0');
      const minutosActuales = String(fechaActual.getMinutes()).padStart(2, '0');
      
      try {
        // Usar la fecha del filtro para obtener día/mes/año
        let fechaParaProcesar = fechaFiltro || this.fechaFiltro;
        
        // Si tenemos un reporte seleccionado y fecha no se especificó, usar la fecha del reporte
        if (!fechaFiltro && this.reporteSeleccionado && this.reporteSeleccionado.fecha) {
          fechaParaProcesar = this.reporteSeleccionado.fecha;
        }
        
        if (fechaParaProcesar) {
          const fechaObj = new Date(fechaParaProcesar);
          const dia = String(fechaObj.getDate()).padStart(2, '0');
          const mes = String(fechaObj.getMonth() + 1).padStart(2, '0');
          const año = fechaObj.getFullYear();
          
          return `${dia}/${mes}/${año} ${horaActual}:${minutosActuales} hrs.`;
        }
        
        // Si no hay fecha de filtro, usar fecha actual completa
        const dia = String(fechaActual.getDate()).padStart(2, '0');
        const mes = String(fechaActual.getMonth() + 1).padStart(2, '0');
        const año = fechaActual.getFullYear();
        
        return `${dia}/${mes}/${año} ${horaActual}:${minutosActuales} hrs.`;
      } catch (error) {
        console.error("Error en formatFechaCorteZ:", error);
        
        // En caso de error, devolver fecha y hora actual
        const dia = String(fechaActual.getDate()).padStart(2, '0');
        const mes = String(fechaActual.getMonth() + 1).padStart(2, '0');
        const año = fechaActual.getFullYear();
        
        return `${dia}/${mes}/${año} ${horaActual}:${minutosActuales} hrs.`;
      }
    },

    formatFecha(fecha) {
      return new Date(fecha).toLocaleString();
    },

    formatPrecio(precio) {
      const value = parseFloat(precio) || 0;
      return value.toLocaleString('es-MX', {minimumFractionDigits: 2, maximumFractionDigits: 2});
    },
    
    formatMetodoPago(metodo) {
      switch (metodo?.toLowerCase()) {
        case 'efectivo': return 'Efectivo';
        case 'tarjeta': return 'Tarjeta';
        case 'mixto': return 'Mixto';
        default: return metodo;
      }
    },
    
    formatComprobante(tipo) {
      switch(tipo) {
        case 'ticket electronico':
          return 'TICKET ELECTRÓNICO';
        case 'ticket':
          return 'TICKET';
        case 'factura':
          return 'FACTURA';
        default:
          return tipo.toUpperCase();
      }
    },
    
    async buscarPorNumeroTicket() {
      if (this.numeroTicket.trim() === '') {
        this.reportes = [...this.reportesOriginal];
        this.busquedaActiva = false;
        this.ticketData = null;
        return;
      }
      
      // Marcar la búsqueda como activa
      this.busquedaActiva = true;
      
      // Primero filtramos los reportes locales para la visualización normal
      this.reportes = this.reportesOriginal.filter(reporte => 
        reporte.numero.toString().includes(this.numeroTicket)
      );
      
      // Buscar por número exacto de ticket solo cuando se presiona el botón Buscar
      if (!isNaN(this.numeroTicket) && this.numeroTicket.trim() !== '') {
        try {
          // Mostrar un indicador de carga si es necesario
          // this.cargando = true;
          
          const { data } = await axios.get(BASE_URL + "/api/reportes/buscarTicket", {
            params: {
              numero: this.numeroTicket.trim()
            }
          });
          
          console.log('Datos del ticket:', data);
          this.ticketData = data;
          
          // Si el ticket tiene datos, usar ese reporte como seleccionado para tener acceso a su fecha original
          if (data && data.id_reporte) {
            this.reporteSeleccionado = {
              id: data.id_reporte,
              fecha: data.fecha_original || data.fecha
            };
          }
          
          // Cambiar a la vista de ticket foliado
          this.tipoTotal = 'resumen_prueba3';
          
        } catch (error) {
          console.error('Error al buscar ticket:', error);
          this.ticketData = null;
          this.reporteSeleccionado = null;
          
          // Mostrar un mensaje de error según el problema
          if (error.response) {
            if (error.response.status === 400) {
              // El error viene del servidor (ticket cancelado u otro problema)
              alert(error.response.data.messages.error || 'Error al buscar el ticket');
            } else if (error.response.status === 404) {
              // Ticket no encontrado
              alert('Ticket no encontrado');
            } else {
              // Otro tipo de error del servidor
              alert('Error al buscar el ticket: ' + (error.response.data.messages?.error || 'Error desconocido'));
            }
          } else {
            // Error de conexión u otro problema
            alert('Error de conexión al buscar el ticket');
          }
        } finally {
          // Ocultar el indicador de carga si es necesario
          // this.cargando = false;
        }
      }
    },

    async buscarPorPedido() {
      if (this.numeroTicket.trim() === '') {
        this.reportes = [...this.reportesOriginal];
        this.busquedaActiva = false;
        this.ticketData = null;
        return;
      }
      
      // Marcar la búsqueda como activa
      this.busquedaActiva = true;
      
      // Primero filtramos los reportes locales para la visualización normal
      this.reportes = this.reportesOriginal.filter(reporte => 
        reporte.id_pedido && reporte.id_pedido.toString().includes(this.numeroTicket)
      );
      
      // Buscar por id_pedido solo cuando se presiona el botón Buscar
      if (!isNaN(this.numeroTicket) && this.numeroTicket.trim() !== '') {
        try {
          const { data } = await axios.get(BASE_URL + "/api/reportes/buscarPorPedido", {
            params: {
              id_pedido: this.numeroTicket.trim()
            }
          });
          
          console.log('Datos del ticket por ID de pedido:', data);
          this.ticketData = data;
          
          // Si el ticket tiene datos, usar ese reporte como seleccionado para tener acceso a su fecha original
          if (data && data.id_reporte) {
            this.reporteSeleccionado = {
              id: data.id_reporte,
              fecha: data.fecha_original || data.fecha
            };
          }
          
          // Cambiar a la vista de ticket normal
          this.tipoTotal = 'resumen_prueba2';
          
        } catch (error) {
          console.error('Error al buscar por ID pedido:', error);
          this.ticketData = null;
          this.reporteSeleccionado = null;
          
          // Mostrar un mensaje de error según el problema
          if (error.response) {
            if (error.response.status === 400) {
              alert(error.response.data.messages?.error || 'Error al buscar el pedido');
            } else if (error.response.status === 404) {
              alert('Ticket normal no encontrado. Verifique que sea un ticket con numero=0 y estado=emitido');
            } else {
              alert('Error al buscar el pedido: ' + (error.response.data.messages?.error || 'Error desconocido'));
            }
          } else {
            alert('Error de conexión al buscar el pedido');
          }
        }
      }
    },

    limpiarBusqueda() {
      this.numeroTicket = '';
      this.reportes = [...this.reportesOriginal];
      this.busquedaActiva = false;
      this.ticketData = null;
    },
    
    // Solo filtra los reportes localmente sin buscar en la API
    filtrarReportes() {
      if (this.numeroTicket.trim() === '') {
        this.reportes = [...this.reportesOriginal];
        this.busquedaActiva = false;
      } else {
        this.reportes = this.reportesOriginal.filter(reporte => 
          reporte.numero.toString().includes(this.numeroTicket)
        );
        this.busquedaActiva = true;
      }
    },

    calcularTotalTickets() {
      return this.reportesFiltrados
        .filter(r => r.tipo === 'ticket' || r.tipo === 'ticket electronico')
        .reduce((sum, r) => sum + parseFloat(r.total_pedido || 0), 0);
    },
    
    // Calcula el subtotal (sin IVA) para tickets
    calcularSubtotalTickets() {
      const total = this.calcularTotalTickets();
      return total / 1.16; // Extrae el subtotal del total que ya incluye IVA
    },
    
    // Calcula el IVA para tickets correctamente
    calcularIvaTickets() {
      const total = this.calcularTotalTickets();
      const subtotal = total / 1.16; // Extrae el subtotal del total que ya incluye IVA
      return total - subtotal; // El IVA es la diferencia entre el total y el subtotal
    },
    
    calcularTotalFacturas() {
      return this.reportesFiltrados
        .filter(r => r.tipo === 'factura')
        .reduce((sum, r) => sum + parseFloat(r.total_pedido || 0), 0);
    },
    
    // Calcula el subtotal (sin IVA) para facturas
    calcularSubtotalFacturas() {
      const total = this.calcularTotalFacturas();
      return total / 1.16; // Extrae el subtotal del total que ya incluye IVA
    },
    
    // Calcula el IVA para facturas correctamente
    calcularIvaFacturas() {
      const total = this.calcularTotalFacturas();
      const subtotal = total / 1.16; // Extrae el subtotal del total que ya incluye IVA
      return total - subtotal; // El IVA es la diferencia entre el total y el subtotal
    },
    
    calcularTotalGeneral() {
      return this.reportesFiltrados
        .reduce((sum, r) => sum + parseFloat(r.total_pedido || 0), 0);
    },
    
    // Calcula el subtotal (sin IVA) general
    calcularSubtotalGeneral() {
      const total = this.calcularTotalGeneral();
      return total / 1.16; // Extrae el subtotal del total que ya incluye IVA
    },
    
    // Calcula el IVA general correctamente
    calcularIvaGeneral() {
      const total = this.calcularTotalGeneral();
      const subtotal = total / 1.16; // Extrae el subtotal del total que ya incluye IVA
      return total - subtotal; // El IVA es la diferencia entre el total y el subtotal
    },
    
    // Obtener los productos vendidos desde el API
    obtenerProductosVendidos() {
      // Si no hay datos en productosVendidos, devolvemos un array vacío
      if (!this.productosVendidos || !this.productosVendidos.length) {
        return [];
      }
      return this.productosVendidos.map(item => ({
        codigo: String(item.codigo),
        descripcion: item.descripcion,
        cantidad: Number(item.cantidad),  // Mantener como número para poder formatear correctamente
        cantidadFormateada: this.formatCantidad(item.cantidad), // Nuevo campo con formato
        valor: parseFloat(item.valor)
      }));
    },
    
    // Calcular subtotal de un ticket individual (sin IVA)
    calcularSubtotalTicket(total) {
      return total / 1.16; // Extrae el subtotal del total que ya incluye IVA
    },
    
    // Calcular IVA de un ticket individual
    calcularIvaTicket(total) {
      const subtotal = this.calcularSubtotalTicket(total);
      return total - subtotal; // El IVA es la diferencia entre el total y el subtotal
    },
    
    calcularTotalItemsVendidos() {
      const productos = this.obtenerProductosVendidos();
      if (!productos.length) {
        return 0;
      }
      return productos.reduce((sum, item) => sum + parseFloat(item.valor), 0);
    },
    
    // Función para formatear las cantidades con decimales manteniendo una alineación consistente
    formatCantidad(cantidad) {
      // Convertir a número y luego a string para eliminar ceros innecesarios
      const num = Number(cantidad);
      
      // Para los tickets, necesitamos un formato consistente que mantenga la alineación
      // Si es entero, mostrar como entero con espacios
      if (Number.isInteger(num)) {
        return String(num) + '    ';  // Agregar espacios suficientes para alinear con decimales
      }
      
      // Si tiene una parte decimal de .5, usar un formato específico con espacios adicionales
      if (Math.abs(num * 10 % 10) === 5) {
        return num.toFixed(1) + '  '; // Para 1.5 -> "1.5  "
      }
      
      // Para otros decimales
      return num.toFixed(2);
    },
    
    // Función para obtener tickets electrónicos y facturas para el listado superior del Corte Z
    obtenerTicketsElectronicosYFacturasParaLista() {
      return this.reportes
        .filter(reporte => reporte.estado === 'emitido' && (reporte.tipo === 'ticket electronico' || reporte.tipo === 'factura'))
        .sort((a, b) => a.numero - b.numero);
    },
    
    // Mantener función original para compatibilidad con otros cálculos específicos de tickets electrónicos
    obtenerSoloTicketsElectronicos() {
      return this.reportes
        .filter(reporte => reporte.estado === 'emitido' && reporte.tipo === 'ticket electronico')
        .sort((a, b) => a.numero - b.numero);
    },
    
    // Calcular el total de tickets electrónicos y facturas para el listado superior del Corte Z
    calcularTotalTicketsElectronicosYFacturasParaLista() {
      return this.obtenerTicketsElectronicosYFacturasParaLista()
        .reduce((sum, r) => sum + parseFloat(r.total_pedido || 0), 0);
    },
    
    // Calcular el total solo de los tickets electrónicos para el listado superior del Corte Z
    calcularTotalSoloTicketsElectronicos() {
      return this.obtenerSoloTicketsElectronicos()
        .reduce((sum, r) => sum + parseFloat(r.total_pedido || 0), 0);
    },
    
    // Obtener los productos vendidos solo de tickets electrónicos
    obtenerProductosVendidosSoloTicketsElectronicos() {
      // Primero, obtenemos los reportes de solo tickets electrónicos
      const reportesElectronicos = this.obtenerSoloTicketsElectronicos();
      
      // Si no hay reportes electrónicos, devolvemos un array vacío
      if (!reportesElectronicos.length) {
        return [];
      }
      
      // Obtener los IDs de los reportes electrónicos
      const idsReportesElectronicos = reportesElectronicos.map(r => r.id_reporte);
      
      // Filtrar los productos vendidos que pertenecen a estos reportes
      return this.productosVendidos.filter(producto => 
        idsReportesElectronicos.includes(producto.id_reporte)
      );
    },
    
    // Calcular el total de productos vendidos solo de tickets electrónicos
    calcularTotalProductosVendidosSoloTicketsElectronicos() {
      const productosVendidosElectronicos = this.obtenerProductosVendidosSoloTicketsElectronicos();
      if (!productosVendidosElectronicos.length) {
        return 0;
      }
      return productosVendidosElectronicos.reduce((sum, item) => sum + parseFloat(item.valor), 0);

    },
    
    // Función para obtener tickets electrónicos y facturas para las estadísticas del Corte Z
    obtenerTicketsElectronicosYFacturas() {
      return this.reportes
        .filter(reporte => reporte.estado === 'emitido' && (reporte.tipo === 'ticket electronico' || reporte.tipo === 'factura'))
        .sort((a, b) => a.numero - b.numero);
    },
    
    // Calcular total de tickets electrónicos y facturas para las estadísticas del Corte Z
    calcularTotalTicketsElectronicosYFacturas() {
      return this.obtenerTicketsElectronicosYFacturas()
        .reduce((sum, r) => sum + parseFloat(r.total_pedido || 0), 0);
    },
    
    // Funciones específicas para calcular subtotales e IVA de solo tickets electrónicos
    calcularTotalSoloTicketsElectronicosParaEstadisticas() {
      return this.reportes
        .filter(r => r.estado === 'emitido' && r.tipo === 'ticket electronico')
        .reduce((sum, r) => sum + parseFloat(r.total_pedido || 0), 0);
    },
    
    calcularSubtotalSoloTicketsElectronicos() {
      const total = this.calcularTotalSoloTicketsElectronicosParaEstadisticas();
      return total / 1.16;
    },
    
    calcularIvaSoloTicketsElectronicos() {
      const total = this.calcularTotalSoloTicketsElectronicosParaEstadisticas();
      const subtotal = total / 1.16;
      return total - subtotal;
    },
    
    // Funciones específicas para calcular totales de facturas para el Corte Z
    calcularTotalFacturasParaCorteZ() {
      return this.reportes
        .filter(r => r.estado === 'emitido' && r.tipo === 'factura')
        .reduce((sum, r) => sum + parseFloat(r.total_pedido || 0), 0);
    },
    
    calcularSubtotalFacturasParaCorteZ() {
      const total = this.calcularTotalFacturasParaCorteZ();
      return total / 1.16;
    },
    
    calcularIvaFacturasParaCorteZ() {
      const total = this.calcularTotalFacturasParaCorteZ();
      const subtotal = total / 1.16;
      return total - subtotal;
    },
    
    // Obtener cantidad de operaciones para tickets electrónicos y facturas independientemente del filtro actual
    cantidadTicketsElectronicos() {
      return this.reportes.filter(r => r.estado === 'emitido' && r.tipo === 'ticket electronico').length;
    },
    
    cantidadFacturas() {
      return this.reportes.filter(r => r.estado === 'emitido' && r.tipo === 'factura').length;
    },
    
    // Métodos para calcular cuotas por comanda
    calcularCuotasPorMesero() {
      // Primero, agrupamos los reportes por mesero
      const reportesPorMesero = {};
      
      // Crear entrada para "Caja" que agrupará los pedidos sin mesero ni mesa
      reportesPorMesero['caja'] = {
        id: 'caja',
        nombre: 'Desde Caja',
        reportes: [],
        totalVentas: 0,
        esCaja: true // Marcador para identificar que es el grupo de caja
      };
      
      // Solo considerar reportes emitidos (no cancelados)
      const reportesEmitidos = this.reportes.filter(r => r.estado === 'emitido');
      
      // Agrupar reportes por mesero
      reportesEmitidos.forEach(reporte => {
        // Determinar si tenemos información correcta del mesero
        // Esta información puede venir directamente en el reporte o en los datos del pedido vinculado
        const idMesero = reporte.id_mesero;
        const nombreMesero = reporte.nombre_mesero;
        
        // Verificar si el reporte tiene mesero asignado
        if (!idMesero || idMesero === '0' || idMesero === 0 || !nombreMesero) {
          // Si no tiene mesero, asignarlo a "Caja"
          reportesPorMesero['caja'].reportes.push(reporte);
          reportesPorMesero['caja'].totalVentas += parseFloat(reporte.total_pedido || 0);
        } else {
          // Si tiene mesero, asignarlo normalmente
          const meseroIdKey = String(idMesero); // Convertir a string para usar como clave
          const nombreMeseroFinal = nombreMesero || `Mesero ID: ${idMesero}`;
          
          if (!reportesPorMesero[meseroIdKey]) {
            reportesPorMesero[meseroIdKey] = {
              id: meseroIdKey,
              nombre: nombreMeseroFinal,
              reportes: [],
              totalVentas: 0,
              esCaja: false // No es caja
            };
          }
          
          reportesPorMesero[meseroIdKey].reportes.push(reporte);
          reportesPorMesero[meseroIdKey].totalVentas += parseFloat(reporte.total_pedido || 0);
        }
      });
      
      // Convertir el objeto a array y calcular cuotas
      this.meserosConCuotas = Object.values(reportesPorMesero)
        .filter(mesero => mesero.totalVentas > 0) // Filtrar solo meseros con ventas > 0
        .map(mesero => {
          // Calcular cuántas comandas le corresponden según el total de ventas
          const cuotas = this.calcularCuotasPorMonto(mesero.totalVentas);
          
          return {
            ...mesero,
            cuotaTotal: cuotas.cantidad * this.cuotaComandaBase,
            cantidadComandas: cuotas.cantidad,
            detalleCalculo: cuotas.detalle
          };
        });
      
      // Ordenar por total de ventas (de mayor a menor)
      return this.meserosConCuotas.sort((a, b) => b.totalVentas - a.totalVentas);
    },
    
    calcularCuotasPorMonto(monto) {
      let cantidad = 0;
      let detalle = '';
      
      if (monto <= this.montoBaseComanda) {
        // Si el monto es menor o igual al monto base, es 1 comanda
        cantidad = 1;
        detalle = `${cantidad} comanda por monto menor a ${this.formatPrecio(this.montoBaseComanda)}`;
      } else if (monto > this.montoBaseComanda && monto < 1000) {
        // Entre el monto base y 1000, sigue siendo 1 comanda
        cantidad = 1;
        detalle = `${cantidad} comanda por monto entre ${this.formatPrecio(this.montoBaseComanda)} y $1,000.00`;
      } else {
        // Para montos de 1000 en adelante
        // Primero 2 comandas por los primeros 1000
        cantidad = 2;
        
        // Luego, por cada 500 adicionales, una comanda más
        const montoExcedente = monto - 1000;
        if (montoExcedente > 0) {
          // Calcular cuántas comandas adicionales corresponden
          const comandasAdicionales = Math.ceil(montoExcedente / this.incrementoComanda);
          cantidad += comandasAdicionales;
          
          detalle = `2 comandas base por $1,000.00 + ${comandasAdicionales} comandas adicionales por ${this.formatPrecio(montoExcedente)} (${this.formatPrecio(this.incrementoComanda)} por comanda adicional)`;
        } else {
          detalle = `${cantidad} comandas por monto igual a $1,000.00`;
        }
      }
      
      return {
        cantidad,
        detalle
      };
    },
    
    actualizarCuotaComandaBase(valor) {
      // Validar que sea un número positivo
      const nuevoValor = parseFloat(valor);
      if (!isNaN(nuevoValor) && nuevoValor > 0) {
        this.cuotaComandaBase = nuevoValor;
        // Recalcular las cuotas con el nuevo valor base
        this.calcularCuotasPorMesero();
      }
    },
    
    actualizarMontoBaseComanda(valor) {
      // Validar que sea un número positivo
      const nuevoValor = parseFloat(valor);
      if (!isNaN(nuevoValor) && nuevoValor > 0) {
        this.montoBaseComanda = nuevoValor;
        // Recalcular las cuotas con el nuevo monto base
        this.calcularCuotasPorMesero();
      }
    },
    
    // ========================================
    // MÉTODOS PARA CORTE HISTORIA
    // ========================================
    
    async generarCorteHistoria() {
      this.corteHistoria.loading = true;
      this.corteHistoria.error = null;
      
      try {
        // Construir los parámetros de filtro
        const params = {};
        
        if (this.corteHistoria.filtros.fecha_inicio) {
          params.fecha_inicio = this.corteHistoria.filtros.fecha_inicio;
        }
        if (this.corteHistoria.filtros.fecha_fin) {
          params.fecha_fin = this.corteHistoria.filtros.fecha_fin;
        }
        if (this.corteHistoria.filtros.folio_inicio) {
          params.folio_inicio = this.corteHistoria.filtros.folio_inicio;
        }
        if (this.corteHistoria.filtros.folio_fin) {
          params.folio_fin = this.corteHistoria.filtros.folio_fin;
        }
        if (this.corteHistoria.filtros.tipo) {
          params.tipo = this.corteHistoria.filtros.tipo;
        }
        
        // Llamar a la API
        const response = await axios.get(BASE_URL + "/api/reportes/corteHistoria", { params });
        
        if (response.data.success) {
          this.corteHistoria.folios = response.data.folios;
          this.corteHistoria.resumen = response.data.resumen;
          this.corteHistoria.filtrosAplicados = response.data.filtros_aplicados;
        } else {
          this.corteHistoria.error = 'Error al generar el reporte';
        }
      } catch (error) {
        console.error('Error al generar corte historia:', error);
        this.corteHistoria.error = error.response?.data?.message || 'Error al generar el reporte';
      } finally {
        this.corteHistoria.loading = false;
      }
    },
    
    limpiarFiltrosCorteHistoria() {
      this.corteHistoria.filtros = {
        fecha_inicio: '',
        fecha_fin: '',
        folio_inicio: '',
        folio_fin: '',
        tipo: ''
      };
      this.corteHistoria.folios = [];
      this.corteHistoria.resumen = {
        ticket: { cantidad: 0, total: 0 },
        factura: { cantidad: 0, total: 0 },
        'ticket electronico': { cantidad: 0, total: 0 },
        total_general: 0,
        total_folios: 0
      };
      this.corteHistoria.filtrosAplicados = {};
      this.corteHistoria.error = null;
    },
    
    formatFechaCompleta(fecha) {
      if (!fecha) return 'N/A';
      const date = new Date(fecha);
      const dia = String(date.getDate()).padStart(2, '0');
      const mes = String(date.getMonth() + 1).padStart(2, '0');
      const año = date.getFullYear();
      const hora = String(date.getHours()).padStart(2, '0');
      const minutos = String(date.getMinutes()).padStart(2, '0');
      return `${dia}/${mes}/${año} ${hora}:${minutos}`;
    },
    
    imprimirCorteHistoria() {
      const contenido = document.getElementById('tabla-corte-historia');
      if (!contenido) {
        alert('No hay datos para imprimir');
        return;
      }
      
      const ventana = window.open('', '_blank', 'width=1200,height=900');
      ventana.document.write(`
        <!DOCTYPE html>
        <html>
        <head>
          <meta charset="UTF-8">
          <title>Corte Historia - Folios Consecutivos</title>
          <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
          <style>
            body { padding: 20px; }
            @media print {
              body { padding: 10px; }
              .no-print { display: none; }
            }
          </style>
        </head>
        <body>
          <h2 class="text-center mb-4">Corte Historia - Folios Consecutivos</h2>
          <div class="mb-3">
            <strong>Fecha de impresión:</strong> ${new Date().toLocaleString('es-MX')}
          </div>
          ${contenido.innerHTML}
          <div class="mt-4 no-print text-center">
            <button class="btn btn-primary" onclick="window.print()">Imprimir</button>
            <button class="btn btn-secondary" onclick="window.close()">Cerrar</button>
          </div>
        </body>
        </html>
      `);
      ventana.document.close();
    },
    
    exportarCorteHistoriaPDF() {
      // Construir URL con los filtros aplicados
      let params = new URLSearchParams();
      params.append('formato', 'pdf');
      
      if (this.corteHistoria.filtrosAplicados.fecha_inicio) {
        params.append('fecha_inicio', this.corteHistoria.filtrosAplicados.fecha_inicio);
      }
      if (this.corteHistoria.filtrosAplicados.fecha_fin) {
        params.append('fecha_fin', this.corteHistoria.filtrosAplicados.fecha_fin);
      }
      if (this.corteHistoria.filtrosAplicados.folio_inicio) {
        params.append('folio_inicio', this.corteHistoria.filtrosAplicados.folio_inicio);
      }
      if (this.corteHistoria.filtrosAplicados.folio_fin) {
        params.append('folio_fin', this.corteHistoria.filtrosAplicados.folio_fin);
      }
      if (this.corteHistoria.filtrosAplicados.tipo) {
        params.append('tipo', this.corteHistoria.filtrosAplicados.tipo);
      }
      
      // Abrir en nueva ventana
      const url = BASE_URL + '/api/reportes/corteHistoria?' + params.toString();
      window.open(url, '_blank', 'width=1200,height=900');
    },

    // =============================================
    // MÉTODOS PARA RESPALDO
    // =============================================

    /**
     * Descarga el respaldo en el formato seleccionado
     */
    async descargarRespaldo() {
      this.respaldo.loading = true;
      this.respaldo.error = null;

      try {
        // Construir parámetros de URL
        const params = new URLSearchParams();
        params.append('formato', this.respaldo.formato);
        
        if (this.respaldo.filtros.fecha_inicio) {
          params.append('fecha_inicio', this.respaldo.filtros.fecha_inicio);
        }
        if (this.respaldo.filtros.fecha_fin) {
          params.append('fecha_fin', this.respaldo.filtros.fecha_fin);
        }
        if (this.respaldo.filtros.folio_inicio) {
          params.append('folio_inicio', this.respaldo.filtros.folio_inicio);
        }
        if (this.respaldo.filtros.folio_fin) {
          params.append('folio_fin', this.respaldo.filtros.folio_fin);
        }

        // Hacer petición con blob response
        const url = BASE_URL + '/api/reportes/generarRespaldo?' + params.toString();
        const response = await axios.get(url, {
          responseType: 'blob' // Importante para descargas de archivos
        });

        // Obtener nombre de archivo del header o generar uno
        const contentDisposition = response.headers['content-disposition'];
        let filename = `respaldo_${new Date().toISOString().split('T')[0]}`;
        
        if (contentDisposition) {
          const matches = contentDisposition.match(/filename[^;=\n]*=((['"]).*?\2|[^;\n]*)/);
          if (matches && matches[1]) {
            filename = matches[1].replace(/['"]/g, '');
          }
        } else {
          filename += this.respaldo.formato === 'sql' ? '.sql' : '.csv';
        }

        // Crear link temporal para descargar
        const blob = new Blob([response.data]);
        const link = document.createElement('a');
        link.href = window.URL.createObjectURL(blob);
        link.download = filename;
        link.click();
        
        // Limpiar
        window.URL.revokeObjectURL(link.href);

        alert('Respaldo descargado exitosamente');
      } catch (error) {
        console.error('Error al descargar respaldo:', error);
        this.respaldo.error = error.response?.data?.message || 'Error al generar el respaldo';
      } finally {
        this.respaldo.loading = false;
      }
    },

    /**
     * Limpia los filtros de respaldo
     */
    limpiarFiltrosRespaldo() {
      this.respaldo.filtros = {
        fecha_inicio: '',
        fecha_fin: '',
        folio_inicio: '',
        folio_fin: ''
      };
      this.respaldo.formato = 'sql';
      this.respaldo.error = null;
    },

    /**
     * Obtiene el resumen de lo que se eliminará/conservará al finalizar el día
     */
    async obtenerResumenFinalizarDia() {
      if (!this.finalizarDia.fecha) {
        alert('Debe seleccionar una fecha');
        return;
      }

      this.finalizarDia.loading = true;
      this.finalizarDia.error = null;
      this.finalizarDia.resumen = null;
      this.finalizarDia.resultado = null;

      try {
        const response = await axios.get(`api/reportes/resumenFinalizarDia`, {
          params: {
            fecha: this.finalizarDia.fecha
          }
        });

        if (response.data.success) {
          this.finalizarDia.resumen = response.data.data;
          // Resetear confirmaciones
          this.finalizarDia.checkbox1 = false;
          this.finalizarDia.checkbox2 = false;
          this.finalizarDia.textoConfirmacion = '';
        } else {
          this.finalizarDia.error = response.data.message || 'Error al obtener el resumen';
        }
      } catch (error) {
        console.error('Error al obtener resumen:', error);
        this.finalizarDia.error = error.response?.data?.message || 'Error al consultar el servidor';
      } finally {
        this.finalizarDia.loading = false;
      }
    },

    /**
     * Ejecuta la finalización del día (elimina datos)
     */
    async ejecutarFinalizarDia() {
      // Validaciones
      if (!this.finalizarDia.checkbox1 || !this.finalizarDia.checkbox2) {
        alert('Debe marcar ambas casillas de confirmación');
        return;
      }

      if (this.finalizarDia.textoConfirmacion !== 'CONFIRMAR') {
        alert('Debe escribir CONFIRMAR en mayúsculas');
        return;
      }

      // Confirmación adicional del navegador
      const confirmarFinal = confirm(
        '⚠️ ÚLTIMA ADVERTENCIA ⚠️\n\n' +
        'Esta acción eliminará permanentemente los datos operativos del día.\n' +
        'Se generará un respaldo automático antes de proceder.\n\n' +
        '¿Está COMPLETAMENTE SEGURO de continuar?'
      );

      if (!confirmarFinal) {
        return;
      }

      this.finalizarDia.ejecutando = true;
      this.finalizarDia.error = null;

      try {
        const response = await axios.post(`api/reportes/finalizarDia`, {
          fecha: this.finalizarDia.fecha,
          confirmacion: 'CONFIRMAR'
        });

        if (response.data.success) {
          this.finalizarDia.resultado = response.data.data;
          this.finalizarDia.resumen = null; // Ocultar resumen
          
          // Recargar reportes para reflejar cambios
          await this.cargarReportes();
          
          alert('✅ Día finalizado correctamente. Se ha generado un respaldo automático.');
        } else {
          this.finalizarDia.error = response.data.message || 'Error al finalizar el día';
        }
      } catch (error) {
        console.error('Error al finalizar día:', error);
        this.finalizarDia.error = error.response?.data?.message || 'Error al ejecutar la operación';
      } finally {
        this.finalizarDia.ejecutando = false;
      }
    },

    /**
     * Limpia el estado de Finalizar Día
     */
    limpiarFinalizarDia() {
      this.finalizarDia.fecha = new Date().toISOString().split('T')[0];
      this.finalizarDia.resumen = null;
      this.finalizarDia.checkbox1 = false;
      this.finalizarDia.checkbox2 = false;
      this.finalizarDia.textoConfirmacion = '';
      this.finalizarDia.resultado = null;
      this.finalizarDia.error = null;
    },
    
    // ========================================
    // EXPORTAR EXCEL
    // ========================================
    
    descargarExcelFacturas() {
      this.descargarExcel('factura');
    },
    
    descargarExcelTickets() {
      this.descargarExcel('ticket electronico');
    },
    
    descargarExcel(tipo) {
      this.exportExcel.descargando = true;
      this.exportExcel.mensaje = '';
      
      // Construir URL con parámetros
      let url = BASE_URL + 'api/reportes/exportarExcel?tipo=' + encodeURIComponent(tipo);
      
      if (this.exportExcel.fechaInicio) {
        url += '&fecha_inicio=' + this.exportExcel.fechaInicio;
      }
      
      if (this.exportExcel.fechaFin) {
        url += '&fecha_fin=' + this.exportExcel.fechaFin;
      }
      
      // Descargar archivo
      fetch(url)
        .then(response => {
          if (!response.ok) {
            return response.json().then(data => {
              throw new Error(data.message || 'Error al generar el archivo');
            });
          }
          return response.blob();
        })
        .then(blob => {
          // Crear link de descarga
          const url = window.URL.createObjectURL(blob);
          const a = document.createElement('a');
          a.href = url;
          
          // Nombre del archivo
          const tipoNombre = tipo === 'factura' ? 'facturas' : 'tickets_electronicos';
          const fecha = new Date().toISOString().split('T')[0].replace(/-/g, '');
          a.download = `${tipoNombre}_${fecha}.csv`;
          
          document.body.appendChild(a);
          a.click();
          
          // Limpiar
          window.URL.revokeObjectURL(url);
          document.body.removeChild(a);
          
          // Mensaje de éxito
          this.exportExcel.mensaje = '¡Archivo descargado exitosamente!';
          this.exportExcel.mensajeTipo = 'success';
          
          // Limpiar mensaje después de 3 segundos
          setTimeout(() => {
            this.exportExcel.mensaje = '';
          }, 3000);
        })
        .catch(error => {
          console.error('Error al descargar:', error);
          this.exportExcel.mensaje = error.message || 'Error al generar el archivo';
          this.exportExcel.mensajeTipo = 'danger';
        })
        .finally(() => {
          this.exportExcel.descargando = false;
        });
    }
  },
  
  mounted() {
    this.cargarReportes().then(() => {
      // Calcular cuotas por mesero después de cargar los reportes
      if (this.tipoTotal === 'items_vendidos') {
        this.calcularCuotasPorMesero();
      }
    });
    
    // Auto-actualizar cada minuto
    this.intervaloActualizacion = setInterval(() => {
      this.cargarReportes().then(() => {
        // Recalcular cuotas si estamos en esa vista
        if (this.tipoTotal === 'items_vendidos') {
          this.calcularCuotasPorMesero();
        }
      });
    }, 60000);
    
    // Mostrar un mensaje de ayuda para el desarrollador
    console.log('IMPORTANTE: Para que funcione la agrupación por mesero, debes implementar el endpoint "api/reportes/conDetallesPedido" en el controlador API');
    console.log('Ver el archivo Api_actualizar.php para las instrucciones.');
  },

  unmounted() {
    if (this.intervaloActualizacion) {
      clearInterval(this.intervaloActualizacion);
    }
  }
});