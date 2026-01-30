const app = Vue.createApp({
  data() {
    return {
      platillos: [],          
      platillosOriginal: [], // Para guardar la lista completa
      catalogoPlatillos: [],  // Catálogo completo de platillos disponibles
      numeroTicket: '',
      busquedaActiva: false,
      fechaFiltro: new Date().toISOString().split('T')[0], // Formato YYYY-MM-DD
      pedidoACobrar: 0,
      // Formateamos la fecha actual en formato día/mes/año
      fechaHoy: `${String(new Date().getDate()).padStart(2, '0')}/${String(new Date().getMonth() + 1).padStart(2, '0')}/${new Date().getFullYear()}`,
      formaPago: 'efectivo',
      tipoDoc: '',
      pedidoAEditar: 0,
      modalEdicionAbierto: false,
      nuevoPlatillo: {
        id: '',
        cantidad: 1,
        nota: ''
      },
      // Nuevos arrays para la edición "en memoria" antes de guardar
      itemsPendientesEliminar: [], // IDs de items a eliminar al guardar
      itemsPendientesAgregar: [], // Nuevos items a agregar al guardar
      pedidoActualTemporal: [], // Copia temporal de los items actuales del pedido
      // Nuevas propiedades para crear pedidos desde cero
      nuevosPedidoItems: [], // Array para almacenar los items del nuevo pedido
      nuevoItemPedido: {
        id_platillo: '',
        cantidad: 1,
        nota: ''
      }
    }
  },  
  computed: {    
    // Texto del filtro
    textoFiltro() {
      // Corregimos el problema de zona horaria usando la fecha directamente del input
      // El formato fechaFiltro es 'YYYY-MM-DD'
      const [year, month, day] = this.fechaFiltro.split('-').map(num => parseInt(num, 10));
      
      // Formato personalizado dia/mes/año
      const dia = String(day).padStart(2, '0');
      const mes = String(month).padStart(2, '0');
      const año = year;
      const fechaFormateada = `${dia}/${mes}/${año}`;
      
      const hoy = new Date();
      const esHoy = 
        año === hoy.getFullYear() && 
        month === (hoy.getMonth() + 1) && 
        day === hoy.getDate();
      
      return esHoy ? 
        `Mostrando pedidos del día de hoy: ${fechaFormateada}` :
        `Mostrando pedidos del día: ${fechaFormateada}`;
    },

    // FILTRADO DE PLATILLOS
    filteredPlatillos() {
      return this.platillos;
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
    
    cancelarPago(idPedido) {
      swal({
        title: "¿Cancelar pago?",
        text: "¿Está seguro de que desea cancelar el pago de este pedido? Esta acción no se puede deshacer.",
        icon: "warning",
        buttons: ["No", "Cancelar"],
        dangerMode: true
      })
      .then((willCancel) => {
        if (willCancel) {
          console.log('Cancelando pago del pedido:', idPedido);
          
          // Primero cancelar el reporte
          axios.put(`${BASE_URL}api/reportes/cancelar/${idPedido}`)
            .then(() => {
              // Luego actualizar el estado del pedido
              return axios.put(`${BASE_URL}api/pedidos/pagar/${idPedido}`, {
                estado: 1, // Volver a estado no pagado
              });
            })
            .then(() => {
              swal({
                title: "¡Éxito!",
                text: "El pago ha sido cancelado correctamente",
                icon: "success",
                timer: 2000,
                buttons: false
              });
              this.listarPlatillosCocina(); // Actualizar la lista
            })
            .catch(error => {
              console.error('Error al cancelar pago:', error);
              swal("Error", "Ocurrió un error al cancelar el pago", "error");
            });
        }
      });
    },
    
    formatMetodoPago(metodo) {
      switch (metodo?.toLowerCase()) {
        case 'efectivo': return 'Efectivo';
        case 'tarjeta': return 'Tarjeta';
        case 'mixto': return 'Mixto';
        default: return 'N/A';
      }
    },
    
    formatComprobante(comprobante) {
      switch (comprobante?.toLowerCase()) {
        case 'ticket': return 'Ticket';
        case 'factura': return 'Factura';
        case 'ticket electronico': return 'Ticket E.';
        default: return 'N/A';
      }
    },
    
    actualizarListado() {
      console.log('Actualizando listado...');
      this.listarPlatillosCocina();
    },
    
    calcularTotalPedido(platillos) {
      const total = platillos.reduce((sum, platillo) => {
        const precio = parseFloat(platillo.precio_unitario) || 0;
        const cantidad = parseFloat(platillo.cantidad) || 0;
        return sum + (precio * cantidad);
      }, 0);
      // MANTENER PRECISIÓN COMPLETA - No redondear, solo retornar el número exacto
      return total;
    },
    
    calcularIva(platillos) {
      // Como el precio ya incluye IVA, el valor devuelto por calcularTotalPedido es el total con IVA
      const totalConIVA = this.calcularTotalPedido(platillos);
      // Extraer el subtotal: dividir el total entre 1.16
      const subtotalSinIVA = totalConIVA / 1.16;
      // El IVA es la diferencia entre el total con IVA y el subtotal sin IVA
      const iva = totalConIVA - subtotalSinIVA;
      // MANTENER PRECISIÓN COMPLETA - No redondear, solo retornar el número exacto
      return iva;
    },
    
    calcularTotal(platillos) {
      // Como los precios ya incluyen IVA, el valor devuelto por calcularTotalPedido es el total con IVA
      // No necesitamos multiplicar por 1.16
      // MANTENER PRECISIÓN COMPLETA - No redondear, solo retornar el número exacto
      return this.calcularTotalPedido(platillos);
    },
    
    formatPrecio(precio) {
      const num = parseFloat(precio) || 0;
      return num.toFixed(2);
    },
    

    
    goPago(idPedido) {
      this.pedidoACobrar = idPedido;
      // Pre-seleccionar efectivo como método de pago por defecto
      this.formaPago = 'efectivo';
      this.tipoDoc = '';
      const modalPago = new bootstrap.Modal(document.getElementById('modalPago'));
      modalPago.show();
    },
    
    cobrar() {
      // Validaciones
      if (!this.formaPago || !this.tipoDoc) {
        swal({
          title: "Error",
          text: "Debe seleccionar método de pago y tipo de comprobante",
          icon: "error"
        });
        return;
      }

      // Encontrar el pedido actual
      const pedidoActual = this.platillosAgrupados.find(g => g.id_pedido == this.pedidoACobrar);
      if (!pedidoActual) {
        swal("Error", "No se encontró el pedido", "error");
        return;
      }

      // Calcular el total con IVA y el IVA
      const subtotal = parseFloat(this.calcularTotalPedido(pedidoActual.platillos));  // Este es realmente el total con IVA
      const iva = parseFloat(this.calcularIva(pedidoActual.platillos));
      const total = parseFloat(this.calcularTotal(pedidoActual.platillos));  // Igual que subtotal, ya que incluye IVA

      // Preparar datos para el reporte
      const reporteData = {
        tipo: this.tipoDoc,
        forma_pago: this.formaPago,
        total_pedido: parseFloat(total.toFixed(2)),  // Preserva decimales (2 decimales)
        estado: 'emitido',
        id_pedido: this.pedidoACobrar
      };

      // Confirmar pago
      const metodoPagoFormateado = this.formatMetodoPago(this.formaPago);
      const tipoDocFormateado = this.formatComprobante(this.tipoDoc);
      
      // Crear contenido HTML para el mensaje
      const mensajeHTML = `
        <div style="text-align: left; margin-top: 10px;">
          <p>¿Está seguro de procesar el pago?</p>
          <p>
            <b>Método de pago:</b> ${metodoPagoFormateado}<br>
            <b>Tipo de comprobante:</b> ${tipoDocFormateado}
          </p>
          <div style="background-color: #f8f9fa; padding: 15px; margin-top: 15px;">
            <table style="width: 100%; border-collapse: collapse;">
              <tr>
                <td style="text-align: left; padding: 3px 10px;">Subtotal:</td>
                <td style="text-align: right; padding: 3px 10px;">$${(subtotal / 1.16).toFixed(2)}</td>
              </tr>
              <tr>
                <td style="text-align: left; padding: 3px 10px;">IVA:</td>
                <td style="text-align: right; padding: 3px 10px;">$${(subtotal - (subtotal / 1.16)).toFixed(2)}</td>
              </tr>
              <tr style="border-top: 1px solid #dee2e6;">
                <td style="text-align: left; padding: 8px 10px 0px 10px;"><b>TOTAL:</b></td>
                <td style="text-align: right; padding: 8px 10px 0px 10px; color: #dc3545; font-weight: bold;">$${total.toFixed(2)}</td>
              </tr>
            </table>
          </div>
        </div>
      `;
      
      swal({
        title: "Confirmar Pago",
        content: {
          element: "div",
          attributes: {
            innerHTML: mensajeHTML
          }
        },
        icon: "warning",
        buttons: {
          cancel: "Cancelar",
          confirm: "Confirmar"
        },
        dangerMode: false
      }).then((willProceed) => {
        if (!willProceed) return;

        // Primero crear el reporte
        axios.post(BASE_URL + "api/reportes", reporteData)
          .then(response => {
            // Luego actualizar el estado del pedido
            return axios.put(BASE_URL + "api/pedidos/pagar/" + this.pedidoACobrar, {
              estado: 2, // Marcar como pagado
              comprobante: this.tipoDoc,
              metodo: this.formaPago
            });
          })
          .then(() => {
            swal({
              title: "Éxito",
              text: "Pago procesado correctamente",
              icon: "success",
              timer: 2000,
              buttons: false
            });
            
            // Abrir ticket en nueva ventana usando el controlador
            const ticketUrl = BASE_URL + `api/reportes/ticket/${this.pedidoACobrar}`;
            window.open(ticketUrl, '_blank', 'width=400,height=600,scrollbars=yes,resizable=yes');
            
            const modalPago = bootstrap.Modal.getInstance(document.getElementById('modalPago'));
            modalPago.hide();
            this.formaPago = 'efectivo';
            this.tipoDoc = '';
            this.actualizarListado();
          })
          .catch(error => {
            console.error("Error details:", {
              response: error.response?.data,
              reporteData: reporteData
            });
            let errorMsg = "Error al procesar el pago: ";
            if (error.response?.data) {
              if (error.response.data.missing_fields) {
                errorMsg += "Faltan campos requeridos: " + error.response.data.missing_fields.join(", ");
              } else if (error.response.data.message) {
                errorMsg += error.response.data.message;
              }
            } else {
              errorMsg += error.message || 'Error desconocido';
            }
            swal({
              title: "Error", 
              text: errorMsg,
              icon: "error"
            });
          });
      });
    },
    
    listarPlatillosCocina() {
      let params = { porPagar: 0 };
      
      if (this.fechaFiltro) {
        params.fecha = this.fechaFiltro;
      }
      
      axios.get(BASE_URL + "api/test/", { params }).then(responseItems => {
        console.log('Estructura de datos recibidos:', responseItems.data[0]); // Para ver la estructura
        // Asegurarnos de que todos los campos están correctos
        this.platillos = responseItems.data.map(item => ({
          ...item,
          precio_unitario: parseFloat(item.precio_unitario) || 0,
          // Asegurar que el id del item_pedido esté disponible
          id_item_pedido: item.id // El campo 'id' en item_pedidos es la clave primaria
        }));
        
        // Mostrar los campos disponibles en un registro para diagnosticar
        if(responseItems.data.length > 0) {
          console.log('Campos disponibles en un registro:', Object.keys(responseItems.data[0]));
        }
        
        // Guardar una copia de la lista completa para futuras búsquedas
        this.platillosOriginal = [...this.platillos];
      });
    },   
    
    buscarPorTicket() {
      if (!this.numeroTicket) {
        swal({
          title: "Error",
          text: "Por favor ingrese un número de pedido",
          icon: "warning"
        });
        return;
      }

      const ticketNum = parseInt(this.numeroTicket);
      const resultados = this.platillosOriginal.filter(p => p.id_pedido == ticketNum);

      if (resultados.length > 0) {
        this.platillos = resultados;
        this.busquedaActiva = true;
      } else {
        swal({
          title: "No encontrado",
          text: "No se encontró el pedido #" + ticketNum,
          icon: "info"
        });
      }
    },

    limpiarBusqueda() {
      this.numeroTicket = '';
      this.platillos = [...this.platillosOriginal];
      this.busquedaActiva = false;
    },

    cerrarModal() {
      this.formaPago = 'efectivo';
      this.tipoDoc = '';
    },

    reimprimirTicket(idPedido) {
      // Confirmar antes de reimprimir
      swal({
        title: "¿Reimprimir ticket?",
        text: "¿Desea reimprimir el ticket de este pedido?",
        icon: "question",
        buttons: ["Cancelar", "Reimprimir"],
        dangerMode: false
      })
      .then((willReprint) => {
        if (willReprint) {
          // Abrir ticket en nueva ventana usando el controlador
          const ticketUrl = BASE_URL + `api/reportes/ticket/${idPedido}`;
          window.open(ticketUrl, '_blank', 'width=400,height=600,scrollbars=yes,resizable=yes');
          
          // Mostrar mensaje de confirmación
          swal({
            title: "¡Listo!",
            text: "Ticket reabierto para impresión",
            icon: "success",
            timer: 1500,
            buttons: false
          });
        }
      });
    },

    editarPedido(idPedido) {
      this.pedidoAEditar = idPedido;
      
      // Encontrar el pedido actual
      const pedidoActual = this.platillosAgrupados.find(g => g.id_pedido == idPedido);
      if (!pedidoActual) {
        swal("Error", "No se encontró el pedido", "error");
        return;
      }
      
      // Limpiar arrays temporales
      this.itemsPendientesEliminar = [];
      this.itemsPendientesAgregar = [];
      
      // Crear una copia profunda de los ítems actuales para manipularlos temporalmente
      this.pedidoActualTemporal = JSON.parse(JSON.stringify(pedidoActual.platillos));
      
      // Mostrar modal de edición del pedido
      const modalEdicion = new bootstrap.Modal(document.getElementById('modalEdicionPedido'));
      this.modalEdicionAbierto = true;
      modalEdicion.show();
    },

    cerrarModalEdicion() {
      this.pedidoAEditar = 0;
      this.modalEdicionAbierto = false;
      // Limpiar los arrays temporales
      this.itemsPendientesEliminar = [];
      this.itemsPendientesAgregar = [];
      this.pedidoActualTemporal = [];
      // Reiniciar el form de nuevo platillo
      this.nuevoPlatillo = {
        id: '',
        cantidad: 1,
        nota: ''
      };
    },

    // Cargar el catálogo completo de platillos disponibles
    cargarCatalogoPlatillos() {
      axios.get(BASE_URL + "api/platillos", {
        params: {
          disponibilidad: 1, // Solo platillos disponibles
          active: 1 // Solo activos
        }
      })
      .then(response => {
        this.catalogoPlatillos = response.data;
      })
      .catch(error => {
        console.error("Error al cargar catálogo de platillos:", error);
      });
    },
    
    // Agregar un nuevo platillo al pedido (en memoria hasta guardar cambios)
    agregarPlatilloPedido() {
      // Validaciones
      if (!this.nuevoPlatillo.id) {
        swal({
          title: "Error",
          text: "Debe seleccionar un platillo",
          icon: "error"
        });
        return;
      }
      
      if (!this.nuevoPlatillo.cantidad || this.nuevoPlatillo.cantidad <= 0) {
        swal({
          title: "Error",
          text: "La cantidad debe ser mayor a cero",
          icon: "error"
        });
        return;
      }
      
      // Buscar el platillo en el catálogo para obtener más información
      const platilloCatalogo = this.catalogoPlatillos.find(p => p.id === this.nuevoPlatillo.id);
      if (!platilloCatalogo) {
        swal("Error", "No se encontró el platillo seleccionado en el catálogo", "error");
        return;
      }
      
      // Crear objeto con los datos del nuevo item
      const nuevoItem = {
        id_pedido: this.pedidoAEditar,
        id_platillo: this.nuevoPlatillo.id,
        cantidad: this.nuevoPlatillo.cantidad,
        nota: this.nuevoPlatillo.nota || "",
        // Campos adicionales para mostrar en la interfaz
        p_name: platilloCatalogo.nombre,
        precio_unitario: platilloCatalogo.precio,
        temporal: true // Marcar como temporal para identificarlo en la vista
      };
      
      // Agregar el item al array temporal
      this.itemsPendientesAgregar.push(nuevoItem);
      
      // También agregarlo a la vista temporal del pedido
      this.pedidoActualTemporal.push(nuevoItem);
      
      // Mostrar feedback
      swal({
        title: "¡Éxito!",
        text: "El platillo ha sido agregado temporalmente al pedido. Recuerda guardar los cambios.",
        icon: "success",
        timer: 2000,
        buttons: false
      });
      
      // Limpiar el formulario
      this.nuevoPlatillo = {
        id: '',
        cantidad: 1,
        nota: ''
      };
    },
    
    eliminarItemPedido(itemId) {
      console.log('Marcando item para eliminación, ID:', itemId);
      
      // Si no tenemos un ID para el item, mostramos error
      if (!itemId) {
        swal("Error", "No se pudo identificar el producto a eliminar", "error");
        return;
      }
      
      // Mostrar confirmación antes de marcar para eliminar
      swal({
        title: "¿Eliminar item?",
        text: "¿Está seguro de que desea eliminar este producto del pedido?",
        icon: "warning",
        buttons: ["Cancelar", "Eliminar"],
        dangerMode: true
      })
      .then((willDelete) => {
        if (willDelete) {
          // Verificar si el item es uno temporal (recién agregado)
          const indexTemporal = this.itemsPendientesAgregar.findIndex(item => 
            item.temporal && item.id_platillo === itemId);
          
          if (indexTemporal >= 0) {
            // Si es un item temporal, simplemente lo quitamos del array
            this.itemsPendientesAgregar.splice(indexTemporal, 1);
          } else {
            // Si es un item existente en la base de datos, lo agregamos a los pendientes por eliminar
            if (!this.itemsPendientesEliminar.includes(itemId)) {
              this.itemsPendientesEliminar.push(itemId);
            }
          }
          
          // Quitar el item de la vista temporal del pedido
          this.pedidoActualTemporal = this.pedidoActualTemporal.filter(item => {
            // Si es un item temporal, comparamos por id_platillo
            if (item.temporal) {
              return item.id_platillo !== itemId;
            }
            // Si no es temporal, comparamos por id del item
            return item.id !== itemId;
          });
          
          swal({
            title: "Eliminado temporalmente", 
            text: "El producto ha sido marcado para eliminación. Recuerda guardar cambios para confirmar.",
            icon: "success",
            timer: 2000,
            buttons: false
          });
        }
      });
    },
    
    guardarCambiosPedido() {
      // Validamos que exista el pedido
      const pedidoActual = this.platillosAgrupados.find(g => g.id_pedido == this.pedidoAEditar);
      if (!pedidoActual) {
        swal("Error", "No se encontró el pedido", "error");
        return;
      }
      
      // Verificar si hay cambios para guardar
      const hayEliminaciones = this.itemsPendientesEliminar.length > 0;
      const hayAdiciones = this.itemsPendientesAgregar.length > 0;
      
      if (!hayEliminaciones && !hayAdiciones) {
        // No hay cambios, simplemente cerramos
        const modalEdicion = bootstrap.Modal.getInstance(document.getElementById('modalEdicionPedido'));
        modalEdicion.hide();
        this.cerrarModalEdicion();
        return;
      }
      
      // Mostrar indicador de carga
      swal({
        title: "Guardando cambios...",
        text: "Aplicando modificaciones al pedido",
        icon: "info",
        buttons: false,
        closeOnClickOutside: false,
        closeOnEsc: false
      });
      
      // Array para almacenar todas las promesas de operaciones
      const operaciones = [];
      
      // Procesar eliminaciones
      if (hayEliminaciones) {
        this.itemsPendientesEliminar.forEach(itemId => {
          const promesaEliminar = axios({
            method: 'DELETE',
            url: `${BASE_URL}api/item_pedidos/${itemId}`,
            headers: {
              'Content-Type': 'application/json',
              'X-Requested-With': 'XMLHttpRequest'
            }
          });
          
          operaciones.push(promesaEliminar);
        });
      }
      
      // Procesar adiciones
      if (hayAdiciones) {
        this.itemsPendientesAgregar.forEach(item => {
          // Crear objeto para enviar a la API (solo con los campos necesarios)
          const nuevoItem = {
            id_pedido: this.pedidoAEditar,
            id_platillo: item.id_platillo,
            cantidad: item.cantidad,
            nota: item.nota || ""
          };
          
          const promesaAgregar = axios.post(BASE_URL + "api/item_pedidos", nuevoItem);
          operaciones.push(promesaAgregar);
        });
      }
      
      // Esperar a que todas las operaciones terminen
      Promise.all(operaciones)
        .then(() => {
          // Cerrar el modal y mostrar mensaje de éxito
          const modalEdicion = bootstrap.Modal.getInstance(document.getElementById('modalEdicionPedido'));
          modalEdicion.hide();
          this.cerrarModalEdicion();
          
          swal({
            title: "¡Listo!",
            text: "Los cambios al pedido han sido aplicados correctamente",
            icon: "success",
            timer: 2000,
            buttons: false
          });
          // Asegurarnos de que la lista esté actualizada
          this.actualizarListado();
        })
        .catch(error => {
          console.error("Error al guardar cambios:", error);
          swal({
            title: "Error",
            text: "Ha ocurrido un error al guardar los cambios. Intente nuevamente.",
            icon: "error"
          });
        });
    },

    // ---- Funciones para crear pedidos desde cero ----

    // Abrir el modal para crear un nuevo pedido
    crearNuevoPedido() {
      // Reseteamos los arrays y objetos
      this.nuevosPedidoItems = [];
      this.nuevoItemPedido = {
        id_platillo: '',
        cantidad: 1,
        nota: ''
      };

      // Mostramos el modal
      const modalCrearPedido = new bootstrap.Modal(document.getElementById('modalCrearPedido'));
      modalCrearPedido.show();
    },

    // Agregar un item al nuevo pedido
    agregarItemANuevoPedido() {
      // Validaciones
      if (!this.nuevoItemPedido.id_platillo) {
        swal({
          title: "Error",
          text: "Debe seleccionar un platillo",
          icon: "error"
        });
        return;
      }
      
      if (!this.nuevoItemPedido.cantidad || this.nuevoItemPedido.cantidad <= 0) {
        swal({
          title: "Error",
          text: "La cantidad debe ser mayor a cero",
          icon: "error"
        });
        return;
      }

      // Encontrar el platillo seleccionado en el catálogo
      const platilloSeleccionado = this.catalogoPlatillos.find(p => p.id == this.nuevoItemPedido.id_platillo);
      if (!platilloSeleccionado) {
        swal("Error", "No se encontró el platillo seleccionado", "error");
        return;
      }

      // Verificar si el platillo ya existe en el pedido y aumentar la cantidad en lugar de duplicarlo
      const itemExistente = this.nuevosPedidoItems.findIndex(item => item.id_platillo == this.nuevoItemPedido.id_platillo && 
        item.nota == (this.nuevoItemPedido.nota || ""));
      
      if (itemExistente >= 0) {
        // Si ya existe un item con el mismo platillo y nota, aumentamos la cantidad
        this.nuevosPedidoItems[itemExistente].cantidad += parseFloat(this.nuevoItemPedido.cantidad);
        
        // Mensaje de éxito
        swal({
          title: "Actualizado",
          text: "Se actualizó la cantidad del platillo en el pedido",
          icon: "success",
          timer: 1500,
          buttons: false
        });
      } else {
        // Agregar al array de items
        this.nuevosPedidoItems.push({
          id_platillo: this.nuevoItemPedido.id_platillo,
          nombre: platilloSeleccionado.nombre,
          precio: parseFloat(platilloSeleccionado.precio),
          cantidad: this.nuevoItemPedido.cantidad,
          nota: this.nuevoItemPedido.nota || ""
        });
      }

      // Limpiar el formulario
      this.nuevoItemPedido = {
        id_platillo: '',
        cantidad: 1,
        nota: ''
      };
    },

    // Eliminar un item del nuevo pedido
    removerItemDeNuevoPedido(index) {
      const item = this.nuevosPedidoItems[index];
      
      // Mostrar confirmación antes de eliminar si el precio total del item es alto (por ejemplo, más de $100)
      const precioTotal = parseFloat(item.precio) * parseFloat(item.cantidad);
      
      if (precioTotal > 100) {
        // Pedir confirmación para eliminar items costosos
        swal({
          title: "¿Eliminar producto?",
          text: `¿Está seguro de eliminar ${item.nombre} (${item.cantidad} x $${item.precio})?`,
          icon: "warning",
          buttons: ["Cancelar", "Eliminar"],
          dangerMode: true
        })
        .then((willDelete) => {
          if (willDelete) {
            // Eliminar el item
            this.nuevosPedidoItems.splice(index, 1);
          }
        });
      } else {
        // Eliminar directamente para items de bajo costo
        this.nuevosPedidoItems.splice(index, 1);
      }
    },

    // Calcular el total del nuevo pedido (con IVA incluido)
    calcularTotalNuevoPedido() {
      return this.nuevosPedidoItems.reduce((total, item) => {
        return total + (parseFloat(item.precio) * parseFloat(item.cantidad));
      }, 0);
    },
    
    // Calcular el IVA del nuevo pedido
    calcularIvaNuevoPedido() {
      // Como el precio ya incluye IVA, el total del pedido incluye IVA
      const totalConIVA = this.calcularTotalNuevoPedido();
      // Extraer el subtotal: dividir el total entre 1.16
      const subtotalSinIVA = totalConIVA / 1.16;
      // El IVA es la diferencia entre el total con IVA y el subtotal sin IVA
      return totalConIVA - subtotalSinIVA;
    },
    
    // Calcular el total con IVA del nuevo pedido
    calcularTotalConIvaNuevoPedido() {
      // Como los precios ya incluyen IVA, el total ya tiene IVA incluido
      return this.calcularTotalNuevoPedido();
    },

    // Guardar el nuevo pedido
    guardarNuevoPedido() {
      // Validación
      if (this.nuevosPedidoItems.length === 0) {
        swal({
          title: "Error",
          text: "No hay productos en el pedido",
          icon: "error"
        });
        return;
      }
      
      // Mostrar cargando
      swal({
        title: "Procesando...",
        text: "Creando nuevo pedido",
        icon: "info",
        buttons: false,
        closeOnClickOutside: false,
        closeOnEsc: false
      });

      // Primero verificamos la conectividad
      axios.get(BASE_URL + "api/test")
        .then(() => {
          // Si hay conectividad, procedemos a crear el pedido
          const fechaActual = new Date().toISOString().split('T')[0]; // YYYY-MM-DD
          const horaActual = new Date().toTimeString().split(' ')[0]; // HH:MM:SS
          
          const nuevoPedido = {
            id_mesero: "00", // Indicando que se creó en caja
            id_mesa: "00",   // Indicando que se creó en caja
            fecha: fechaActual,
            estado: 1, // Activo pero no pagado
            ultima_modif: `${fechaActual} ${horaActual}`
          };

          let idPedidoCreado;

          // Crear el pedido en la base de datos
          return axios.post(BASE_URL + "api/pedidos", nuevoPedido)
            .then(response => {
              if (!response.data || !response.data.id) {
                throw new Error('No se recibió un ID de pedido válido');
              }
              
              idPedidoCreado = response.data.id;
              
              // Ahora creamos todos los items del pedido
              const itemsPromises = this.nuevosPedidoItems.map(item => {
                const nuevoItem = {
                  id_pedido: idPedidoCreado,
                  id_platillo: item.id_platillo,
                  cantidad: item.cantidad,
                  nota: item.nota,
                  preparado: 0 // Por defecto, no preparado.
                };
                
                return axios.post(BASE_URL + "api/item_pedidos", nuevoItem)
                  .catch(err => {
                    // Capturamos errores individuales de cada item para no detener todo el proceso
                    console.error(`Error al guardar item ${item.nombre}:`, err);
                    return { error: true, item: item.nombre };
                  });
              });
              
              // Esperar a que todos los items se guarden
              return Promise.all(itemsPromises);
            })
            .then(results => {
              // Verificamos si algún item tuvo error
              const errores = results.filter(r => r.error);
              
              // Cerrar el modal
              const modalCrearPedido = bootstrap.Modal.getInstance(document.getElementById('modalCrearPedido'));
              modalCrearPedido.hide();
              
              this.cerrarModalCrearPedido();
              
              if (errores.length > 0) {
                // Algunos items tuvieron error
                const itemsConError = errores.map(e => e.item).join(', ');
                swal({
                  title: "¡Advertencia!",
                  text: `Pedido #${String(idPedidoCreado).padStart(4, '0')} creado pero con errores en los productos: ${itemsConError}`,
                  icon: "warning"
                }).then(() => {
                  this.actualizarListado();
                });
              } else {
                // Todo correcto
                swal({
                  title: "¡Éxito!",
                  text: `Pedido #${String(idPedidoCreado).padStart(4, '0')} creado correctamente`,
                  icon: "success",
                  timer: 2000,
                  buttons: false
                });
                // Actualizar la lista de pedidos
                this.actualizarListado();
              }
            });
        })
        .catch(error => {
          console.error("Error al crear pedido:", error);
          let mensaje = "Ocurrió un error al crear el pedido";
          
          if (error.response && error.response.data) {
            // Si tenemos detalles específicos del error
            if (error.response.data.message) {
              mensaje += ": " + error.response.data.message;
            }
          } else if (error.message) {
            mensaje += ": " + error.message;
          }
          
          swal("Error", mensaje, "error");
        });
    },

    // Cerrar el modal de crear pedido
    cerrarModalCrearPedido() {
      this.nuevosPedidoItems = [];
      this.nuevoItemPedido = {
        id_platillo: '',
        cantidad: 1,
        nota: ''
      };
    },
    
    // Actualizar información del platillo seleccionado
    actualizarPrecioPlatillo() {
      // Si no hay un platillo seleccionado, no hacemos nada
      if (!this.nuevoItemPedido.id_platillo) return;
      
      // Buscar el platillo en el catálogo
      const platilloSeleccionado = this.catalogoPlatillos.find(p => p.id == this.nuevoItemPedido.id_platillo);
      if (platilloSeleccionado) {
        // Podríamos mostrar el precio en alguna parte de la interfaz si se requiere
        console.log(`Platillo seleccionado: ${platilloSeleccionado.nombre}, Precio: $${platilloSeleccionado.precio}`);
      }
    },
    
    // Calcular total (con IVA) del item actual basado en precio y cantidad
    calcularSubtotalItem() {
      if (!this.nuevoItemPedido.id_platillo) return '0.00';
      
      // Buscar el platillo en el catálogo
      const platilloSeleccionado = this.catalogoPlatillos.find(p => p.id == this.nuevoItemPedido.id_platillo);
      if (!platilloSeleccionado) return '0.00';
      
      // NOTA: Este precio ya incluye IVA
      const precioConIVA = parseFloat(platilloSeleccionado.precio) || 0;
      const cantidad = parseFloat(this.nuevoItemPedido.cantidad) || 0;
      
      // Este cálculo da el total con IVA
      return this.formatPrecio(precioConIVA * cantidad);
    },
    
    // Calcular IVA del item actual
    calcularIvaItem() {
      if (!this.nuevoItemPedido.id_platillo) return '0.00';
      
      // El total con IVA
      const totalConIVA = parseFloat(this.calcularSubtotalItem());
      // Extraer el subtotal real (sin IVA)
      const subtotalSinIVA = totalConIVA / 1.16;
      // El IVA es la diferencia
      return this.formatPrecio(totalConIVA - subtotalSinIVA);
    },
    
    // Calcular total con IVA del item actual
    calcularTotalItem() {
      if (!this.nuevoItemPedido.id_platillo) return '0.00';
      
      // Como los precios ya incluyen IVA, el total ya tiene IVA
      return this.calcularSubtotalItem();
    },
    
    // Incrementar cantidad del producto en el formulario
    incrementarCantidad() {
      // Convertir a número para asegurar que la operación sea matemática
      let cantidad = parseFloat(this.nuevoItemPedido.cantidad) || 0;
      // Incrementar en 0.5 unidades
      cantidad += 0.5;
      // Actualizar el valor
      this.nuevoItemPedido.cantidad = cantidad;
    },
    
    // Decrementar cantidad del producto en el formulario
    decrementarCantidad() {
      // Convertir a número para asegurar que la operación sea matemática
      let cantidad = parseFloat(this.nuevoItemPedido.cantidad) || 0;
      // Decrementar en 0.5 unidades, pero no permitir valores menores a 0.5
      cantidad = Math.max(0.5, cantidad - 0.5);
      // Actualizar el valor
      this.nuevoItemPedido.cantidad = cantidad;
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
    } 
  }, 
    
  mounted: function() {
    this.listarPlatillosCocina();
    this.cargarCatalogoPlatillos(); // Cargamos el catálogo de platillos disponibles

    this.intervaloActualizacion = setInterval(() => {
      this.listarPlatillosCocina();
    }, 60000);
  },

  // Limpiar el intervalo cuando el componente se destruye
  unmounted: function() {
    if (this.intervaloActualizacion) {
      clearInterval(this.intervaloActualizacion);
    }
  }
});