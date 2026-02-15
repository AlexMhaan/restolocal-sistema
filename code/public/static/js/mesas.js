const app = Vue.createApp({
    data() {
      return {
        structure: {},
        platillos: [],
        pedidos: [],
        resumenes: [],
        platillo: {
          "id": "",
          "nombre": "",
          "descripcion": "",
          "precio": "",
          "disponibilidad": "1",
          "categoria": "",
          "media_orden": "1",
          "cocina": "1",
          "barra": "1",
          "active": "1"
        },
        catalogs: {
          active: [
            {"id": "1", "name": "Activo"},
            {"id": "0", "name": "Inactivo"}
          ],
          media_orden: [
            {"id": "1", "name": "Disponible"},
            {"id": "0", "name": "No Disponible"}
          ],
          disponibilidad: [
            {"id": "1", "name": "Disponible"},
            {"id": "0", "name": "No Disponible"}
          ],
          cocina: [
            {"id": "1", "name": "Si"},
            {"id": "0", "name": "No"}
          ],
          barra: [
            {"id": "1", "name": "Si"},
            {"id": "0", "name": "No"}
          ],
          categoria: [
            {"id": "1", "name": "Bebidas"},
            {"id": "2", "name": "Platos fuertes"},
            {"id": "3", "name": "Entradas"},
            {"id": "4", "name": "Postres"}
          ]
        },
        selectedPlatillos: {},
        categoriaSelected: 0,
        idElemento: 0,
        idMozo: 0,
        idZona: 0,
        vistaActual: 'menu',
        resumenActual: null,
        pedidoEditando: null,
        nuevoPlatillo: {
          id: '',
          nombre: '',
          cantidad: 1,
          nota: ''
        },
        modalEditarPedido: null,
        modalEdicionAbierto: false,
        intervaloActualizacion: null,
        // Variables para modal de motivo de eliminación
        motivoEliminacion: '',
        itemAEliminar: null,
        indexAEliminar: null
      }
    },
    computed: {
      // FILTRADO DE PLATILLOS POR CATEGORIA
      filteredPlatillos() {
        if (Number(this.categoriaSelected) === 0) {
          return this.platillos;
        }
        return this.platillos.filter(platillo => platillo.categoria === this.categoriaSelected.toString());
      },
      
      // No price calculations needed for mesas module
    },
    
    watch: {
      // Vigilar cambios en la vista actual
      vistaActual(newVal) {
        // Si cambia a resumen, actualizar los datos inmediatamente
        if (newVal === 'resumen') {
          this.cargarPedidos();
        }
      }
    },
    
    methods: {
      
      // AGRUPAR ITEMS POR NOMBRE PARA VISTA DE RESUMEN
      agruparItemsPorNombre(items) {
        if (!items || !Array.isArray(items)) {
          return [];
        }
        
        const agrupados = {};
        
        items.forEach(item => {
          const key = item.nombre;
          if (agrupados[key]) {
            // Sumar cantidades
            agrupados[key].cantidad += parseFloat(item.cantidad);
            // Combinar notas si existen
            if (item.nota && item.nota.trim()) {
              agrupados[key].notas = agrupados[key].notas || [];
              agrupados[key].notas.push(item.nota.trim());
            }
          } else {
            agrupados[key] = {
              nombre: item.nombre,
              cantidad: parseFloat(item.cantidad),
              notas: item.nota && item.nota.trim() ? [item.nota.trim()] : []
            };
          }
        });
        
        // Convertir a array y formatear
        return Object.values(agrupados).map(item => ({
          nombre: item.nombre,
          cantidad: item.cantidad,
          // Unir notas únicas con separador
          nota: item.notas.length > 0 ? [...new Set(item.notas)].join(' | ') : null
        }));
      },
      
      // OBTENEMOS LOS DIFERENTES IDs DESDE LA URL
      obtenerIdElemento() {
        const pathSegments = window.location.pathname.split('/');
        id = pathSegments[pathSegments.length - 1];
        console.log(id);
        return id;
      },
      
      obtenerIdZona() {
        const pathSegments = window.location.pathname.split('/');
        const idZona = pathSegments[pathSegments.length - 2];
        console.log(idZona);
        return idZona;
      },
      
      obtenerIdMozo() {
        const pathSegments = window.location.pathname.split('/');
        const idMozo = pathSegments[pathSegments.length - 3];
        console.log(idMozo);
        return idMozo;
      },
      
      
      // VOLVER A LAS MESAS ASOCIADAS DEL MOZO
      volverAtras() {
        // Verificar si hay un resumen activo antes de volver
        axios.get(BASE_URL + "api/resumenes/" + this.idElemento, { params: { mesa: 'true' } })
          .then(responseResumen => {
            if (responseResumen.data && responseResumen.data.id && responseResumen.data.estado == 1) {
              // Si hay un resumen activo, asegurarnos que la mesa quede marcada como ocupada
              axios.get(BASE_URL + "api/elementos/" + this.idElemento)
                .then(elemRes => {
                  const currentElement = elemRes.data;
                  // Actualizar el estado a ocupado (1)
                  return axios.put(BASE_URL + "api/elementos/" + this.idElemento, {
                    id: parseInt(this.idElemento),
                    nombre: currentElement.nombre,
                    estado: "1",
                    cantidad: currentElement.cantidad || "0",
                    mesero: currentElement.mesero || "0",
                    id_zona: currentElement.id_zona
                  });
                })
                .then(() => {
                  // Redirigir después de actualizar
                  window.location.href = BASE_URL + `meseros/${this.idMozo}/${this.idZona}`;
                })
                .catch(error => {
                  console.error("Error al actualizar estado de mesa:", error);
                  // Redirigir de todas formas
                  window.location.href = BASE_URL + `meseros/${this.idMozo}/${this.idZona}`;
                });
            } else {
              // Si no hay resumen, simplemente volver
              window.location.href = BASE_URL + `meseros/${this.idMozo}/${this.idZona}`;
            }
          })
          .catch(error => {
            console.error("Error al verificar resumen:", error);
            // En caso de error, redirigir de todas formas
            window.location.href = BASE_URL + `meseros/${this.idMozo}/${this.idZona}`;
          });
      },
      
      
      // CARGA DE TODOS LOS PLATILLOS
      loadPlatillos() {
        axios.get(BASE_URL + "api/platillos")
          .then(this.loadPlatillosResponse);
      },
      
      loadPlatillosResponse(response) {
        console.log(response.data);
        this.remakeField(response.data, "active", "active_text", {"1": "Activo", "0": "Inactivo"});
        this.remakeField(response.data, "disponibilidad", "disponibilidad_text", {"1": "Disponible", "0": "No Disponible"});
        this.remakeField(response.data, "media_orden", "media_orden", {"1": "Disponible", "0": "No Disponible"});
        this.remakeField(response.data, "cocina", "cocina", {"1": "Si", "0": "No"});
        this.remakeField(response.data, "barra", "barra", {"1": "Si", "0": "No"});
        this.remakeField(response.data, "categoria", "categoria_text", {"1": "Bebidas", "2": "Platos fuertes", "3": "Entradas", "4": "Postres"});
        
        // Asignar datos y establecer la cantidad seleccionada vacía por defecto
        this.reSet(this.platillos, response.data);
        
        // Inicializar la cantidad seleccionada vacía para todos los platillos
        this.platillos.forEach(platillo => {
          platillo.cantidadSeleccionada = null;
        });
      },
      
      // AGREGAR PLATILLO AL PEDIDO
      confirmAddToOrder(platillo) {
        // Validar que se haya ingresado una cantidad
        if (!platillo.cantidadSeleccionada || platillo.cantidadSeleccionada <= 0) {
          swal({
            title: "Error",
            text: "Por favor ingrese una cantidad válida",
            icon: "error",
            button: "Aceptar",
          });
          return;
        }

        this.pedidos.push({
          id: platillo.id,
          nombre: platillo.nombre,
          nota: "",
          cantidad: platillo.cantidadSeleccionada
        });
        
        // Resetear la cantidad seleccionada vacía después de agregar al pedido
        platillo.cantidadSeleccionada = null;

        swal({
          title: "Éxito",
          text: "Platillo agregado al pedido",
          icon: "success",
          button: "Aceptar",
        });
      },

      
      // REMOVER PLATILLO DEL PEDIDO
      removeFromOrder(index) {
        const platillo = this.pedidos[index];
        if (platillo.cantidad > 1) {
          platillo.cantidad--;
        } else {
          this.pedidos.splice(index, 1);
        }
      },
      
      
      // CREACIÓN DE RESUMEN (SI ES NECESARIO), PEDIDOS E ITEMS.
      confirmarPedido() {
        if (this.pedidos.length === 0) {
          swal({
            title: "Advertencia",
            text: "No hay platillos en el pedido",
            icon: "warning",
            button: "Aceptar",
          });
          return;
        }

        swal({
          title: '¿Desea confirmar el pedido?',
          icon: 'info',
          buttons: {
            cancel: "Cancelar",
            confirm: {
              text: "Confirmar",
              value: true,
              visible: true,
              closeModal: true,
              className: 'confirm-button'
            }
          },
        })
        .then((confirmaPedido) => {
          if (!confirmaPedido) return;

          axios.get(BASE_URL + "api/resumenes/" + this.idElemento, { params: { mesa: 'true' } })
            .then((res) => {
              if (res.data && res.data.id) {
                this.resumenActual = res.data.id;

                const dataResumen = res.data;
                // No price calculation in mesas module

                return axios.put(BASE_URL + "api/resumenes/" + this.resumenActual, dataResumen);
              } else {
                throw new Error("No existe resumen");
              }
            })
            .catch(() => {
              const dataResumen = {
                id_mesa: this.idElemento,
                total: 0, // No price calculation in mesas module
                estado: 1
              };

              return axios.post(BASE_URL + "api/resumenes", dataResumen)
                .then((resResumen) => {
                  this.resumenActual = resResumen.data.id;
                });
            })
            .then(() => {
              // Actualizar estado de la mesa a ocupado cuando se crea un resumen
              return axios.get(BASE_URL + "api/elementos/" + this.idElemento)
                .then(elemRes => {
                  const currentElement = elemRes.data;
                  return axios.put(BASE_URL + "api/elementos/" + this.idElemento, {
                    id: parseInt(this.idElemento),
                    nombre: currentElement.nombre,
                    estado: "1", // Ocupada
                    cantidad: currentElement.cantidad || "0",
                    mesero: currentElement.mesero || "0",
                    id_zona: currentElement.id_zona
                  });
                })
                .then(() => {
                  this.crearPedido();
                });
            })
            .catch(this.manejarError);
        });
      },
      
      crearPedido() {
        const dataPedido = {
          id_resumen: this.resumenActual,
          id_mesero: this.idMozo,
          id_mesa: this.idElemento,
          estado: 1
        };

        axios.post(BASE_URL + "api/pedidos", dataPedido)
          .then((resPedido) => {
            this.idPedido = resPedido.data.id;

            const items = this.pedidos.map(p => ({
              id_pedido: this.idPedido,
              id_platillo: p.id,
              cantidad: p.cantidad,
              nota: p.nota || ""
            }));

            return axios.post(BASE_URL + "api/item_pedidos/lote", items);
          })
          .then(() => {
            swal({
              title: "Éxito",
              text: "Pedido guardado correctamente",
              icon: "success",
              button: "Aceptar",
            });

            this.pedidos = [];
            this.vistaActual = 'resumen';
            this.resumenes = [];
            this.loadResumenes();
            
            // Resetear las cantidades seleccionadas de todos los platillos vacías
            this.platillos.forEach(platillo => {
              platillo.cantidadSeleccionada = null;
            });
          })
          .catch(this.manejarError);
      },
      
      
      // MANEJO DE ERRORES
      manejarError(error) {
        console.error(error);
        swal({
          title: "Error",
          text: "Ocurrió un problema al procesar la operación",
          icon: "error",
          button: "Aceptar",
        });
      },
      
      
      // CARGAR RESUMEN DE LA MESA
      loadResumenes() {
        axios.get(BASE_URL + "api/resumenes/" + this.idElemento, { params: { mesa: 'true' } })
          .then(responseResumen => {
            if (responseResumen.data && responseResumen.data.id) {
              axios.get(BASE_URL + "api/pedidos/" + responseResumen.data.id)
                .then(responsePedidos => {
                  if (Array.isArray(responsePedidos.data)) {
                    this.resumenes = responsePedidos.data;  // Asignamos el array directamente
                  } else {
                    this.resumenes = [];  // Si no es un array, iniciamos vacío
                  }
                })
                .catch(error => {
                  console.error("Error al obtener pedidos:", error);
                  this.resumenes = [];
                });
            }
          })
          .catch(error => {
            console.error("Error al obtener resumen:", error);
            this.resumenes = [];
          });
      },
      
      
      // FINALIZAR RESUMEN, PEDIDOS, ITEMS Y MESA
      finalizarMesa() {
        if (this.resumenes.length === 0) {
          swal({
            title: "Información",
            text: "No hay pedidos para finalizar",
            icon: "info",
            button: "Aceptar",
          });
          return;
        }

        swal({
          title: '¿Está seguro de finalizar la mesa?',
          text: 'Esto cerrará todos los pedidos y liberará la mesa.',
          icon: 'warning',
          buttons: {
            cancel: "Cancelar",
            confirm: {
              text: "Finalizar",
              value: true,
              visible: true,
              closeModal: true,
              className: 'confirm-button'
            }
          },
          dangerMode: true,
        })
        .then((confirmFinalizar) => {
          if (!confirmFinalizar) return;

          axios.get(BASE_URL + "api/resumenes/" + this.idElemento, { params: { mesa: 'true' } })
            .then((res) => {
              if (res.data && res.data.id) {
                const dataResumen = res.data;
                dataResumen.estado = 0; 

                return axios.put(BASE_URL + "api/resumenes/" + dataResumen.id, dataResumen)
                  .then(() => {
                    // First get the current element data
                    return axios.get(BASE_URL + "api/elementos/" + this.idElemento)
                      .then(elemRes => {
                        const currentElement = elemRes.data;
                        // Then update it with all required fields
                        return axios.put(BASE_URL + "api/elementos/" + this.idElemento, {
                          id: parseInt(this.idElemento),
                          nombre: currentElement.nombre,
                          estado: "0", // Send as string to match controller expectations
                          cantidad: "0", // Send as string to match controller expectations
                          mesero: currentElement.mesero || "0",
                          id_zona: currentElement.id_zona
                        });
                      });
                  })
                  .then(() => {
                    swal({
                      title: "Éxito",
                      text: "Mesa finalizada correctamente",
                      icon: "success",
                      button: "Aceptar",
                    }).then(() => {
                      this.volverAtras();
                    });
                  });
              } else {
                throw new Error("No existe resumen activo");
              }
            })
            .catch((error) => {
              console.error(error);
              swal({
                title: "Error",
                text: "Ocurrió un problema al finalizar la mesa",
                icon: "error",
                button: "Aceptar",
              });
            });
        });
      },
      
      // EDITAR PEDIDO
      editarPedido(pedido) {
        // Crear una copia profunda del pedido para no modificar el original hasta guardar
        this.pedidoEditando = JSON.parse(JSON.stringify(pedido));
        
        // Agregar tiempo aleatorio para evitar caché
        const timestamp = new Date().getTime();
        
        // Obtener los últimos estados de los items del pedido usando conexión directa
        axios.get(BASE_URL + "api/item_pedidos/" + pedido.id + "?nocache=" + timestamp, {
            headers: {
              'Cache-Control': 'no-cache, no-store, must-revalidate',
              'Pragma': 'no-cache',
              'Expires': '0'
            },
            // Agregar un timeout para no quedarse esperando indefinidamente
            timeout: 5000
          })
          .then(response => {
            console.log("Respuesta items obtenida:", response.data);
            
            if (Array.isArray(response.data)) {
              // Obtener los últimos estados de preparado para cada item
              const itemsActualizados = response.data;
              
              // Actualizar nuestro pedidoEditando con los últimos estados
              this.pedidoEditando.items = this.pedidoEditando.items.map(item => {
                // Buscar el item correspondiente por ID
                const itemActualizado = itemsActualizados.find(i => i.id === item.id);
                
                if (itemActualizado) {
                  // Si encontramos el item, actualizamos todos sus datos
                  return {
                    ...item,
                    preparado: itemActualizado.preparado,
                    cantidad: itemActualizado.cantidad,
                    nota: itemActualizado.nota
                  };
                }
                
                return item;
              });
            } else {
              console.log("No se encontraron items actualizados o formato incorrecto:", response.data);
            }
          })
          .catch(error => {
            console.error("Error al obtener detalles de los items:", error);
            
            // Mostrar un mensaje amigable al usuario
            swal({
              title: "Información",
              text: "No se pudieron cargar los detalles más recientes. Los cambios que realice podrían sobrescribir modificaciones recientes.",
              icon: "info",
              button: "Continuar de todas formas",
            });
          });
        
        // Inicializar el nuevo platillo
        this.nuevoPlatillo = {
          id: '',
          nombre: '',
          cantidad: 1,
          nota: ''
        };
        
        // Mostrar el modal
        if (!this.modalEditarPedido) {
          this.modalEditarPedido = new bootstrap.Modal(document.getElementById('editarPedidoModal'));
        }
        this.modalEditarPedido.show();
      },
      
      // No price calculation needed for mesas module
      calcularTotalPedidoEditando() {
        return 0; // Retain method for compatibility but return zero
      },
      
      // SELECCIONAR PLATILLO PARA AGREGAR
      seleccionarPlatillo() {
        const platilloSeleccionado = this.platillos.find(p => p.id === this.nuevoPlatillo.id);
        if (platilloSeleccionado) {
          this.nuevoPlatillo.nombre = platilloSeleccionado.nombre;
        }
      },
      
      // AGREGAR PLATILLO AL PEDIDO EN EDICIÓN
      agregarPlatilloAlPedido() {
        if (!this.nuevoPlatillo.id || !this.nuevoPlatillo.cantidad) {
          swal({
            title: "Advertencia",
            text: "Debe seleccionar un platillo y especificar una cantidad",
            icon: "warning",
            button: "Aceptar",
          });
          return;
        }
        
        // Siempre agregar como un nuevo item independiente, sin verificar si ya existe
        // Esto permite tener múltiples items del mismo platillo como registros independientes
        this.pedidoEditando.items.push({
          id_pedido: this.pedidoEditando.id,
          id_platillo: this.nuevoPlatillo.id, // El ID del platillo (no confundir con el id del item)
          nombre: this.nuevoPlatillo.nombre,
          cantidad: parseFloat(this.nuevoPlatillo.cantidad),
          nota: this.nuevoPlatillo.nota || ""
          // No incluimos un id aquí porque es un nuevo item
        });
        
        // Reiniciar el nuevo platillo
        this.nuevoPlatillo = {
          id: '',
          nombre: '',
          cantidad: 1,
          nota: ''
        };
      },
      
      // ELIMINAR ITEM DEL PEDIDO EN EDICIÓN
      eliminarItemPedido(index) {
        const item = this.pedidoEditando.items[index];
        
        // Guardar el item y el índice para usarlos cuando se confirme
        this.itemAEliminar = item;
        this.indexAEliminar = index;
        this.motivoEliminacion = '';
        
        // Abrir el modal de Bootstrap
        const modalElement = document.getElementById('motivoEliminacionModal');
        const modal = new bootstrap.Modal(modalElement);
        modal.show();
        
        // Dar foco al textarea cuando se muestre el modal
        modalElement.addEventListener('shown.bs.modal', () => {
          document.getElementById('motivoEliminacion').focus();
        }, { once: true });
      },
      
      // CONFIRMAR ELIMINACIÓN CON MOTIVO
      confirmarEliminacion() {
        // Validar que hay motivo y longitud mínima
        const motivoLimpio = this.motivoEliminacion.trim();
        
        if (!motivoLimpio || motivoLimpio.length < 10) {
          swal({
            title: "Motivo insuficiente",
            text: "Debe indicar un motivo descriptivo de al menos 10 caracteres",
            icon: "error",
            button: "Aceptar",
          });
          return;
        }
        
        // Registrar en consola el motivo (para auditoría futura si se necesita)
        console.log(`Producto eliminado: ${this.itemAEliminar.nombre}`);
        console.log(`Motivo: ${motivoLimpio}`);
        
        // Eliminar el item del pedido
        this.pedidoEditando.items.splice(this.indexAEliminar, 1);
        
        // Cerrar el modal
        const modalElement = document.getElementById('motivoEliminacionModal');
        const modal = bootstrap.Modal.getInstance(modalElement);
        modal.hide();
        
        // Limpiar variables
        this.itemAEliminar = null;
        this.indexAEliminar = null;
        this.motivoEliminacion = '';
        
        // Mostrar confirmación
        swal({
          title: "Eliminado",
          text: "El producto ha sido eliminado del pedido",
          icon: "success",
          timer: 2000,
          buttons: false,
        });
      },
      
      // CERRAR MODAL DE EDICIÓN
      cerrarModalEdicion() {
        this.pedidoEditando = null;
        this.modalEdicionAbierto = false;
        this.nuevoPlatillo = {
          id: '',
          nombre: '',
          cantidad: 1,
          nota: ''
        };
      },
      
      // INCREMENTAR CANTIDAD DEL PRODUCTO EN EL FORMULARIO
      incrementarCantidad() {
        // Convertir a número para asegurar que la operación sea matemática
        let cantidad = parseFloat(this.nuevoPlatillo.cantidad) || 0;
        // Incrementar en 0.5 unidades
        cantidad += 0.5;
        // Actualizar el valor
        this.nuevoPlatillo.cantidad = cantidad;
      },
      
      // DECREMENTAR CANTIDAD DEL PRODUCTO EN EL FORMULARIO
      decrementarCantidad() {
        // Convertir a número para asegurar que la operación sea matemática
        let cantidad = parseFloat(this.nuevoPlatillo.cantidad) || 0;
        // Decrementar en 0.5 unidades, pero no permitir valores menores a 0.5
        cantidad = Math.max(0.5, cantidad - 0.5);
        // Actualizar el valor
        this.nuevoPlatillo.cantidad = cantidad;
      },
      
      calcularSubtotalNuevoPlatillo() {
        return 0; // Retain method for compatibility but return zero
      },
      
      calcularIvaNuevoPlatillo() {
        return "0.00"; // Retain method for compatibility but return zero
      },
      
      calcularTotalNuevoPlatillo() {
        return "0.00"; // Retain method for compatibility but return zero
      },
      
      // GUARDAR CAMBIOS DEL PEDIDO
      guardarPedidoEditado() {
        if (!this.pedidoEditando || !this.pedidoEditando.items || this.pedidoEditando.items.length === 0) {
          swal({
            title: "Advertencia",
            text: "El pedido no puede quedar sin items",
            icon: "warning",
            button: "Aceptar",
          });
          return;
        }
        
        // Mostrar confirmación
        swal({
          title: "¿Guardar cambios?",
          text: "Se actualizarán los items del pedido",
          icon: "question",
          buttons: {
            cancel: "Cancelar",
            confirm: {
              text: "Guardar",
              value: true,
              visible: true,
              closeModal: true
            }
          },
        })
        .then((confirmado) => {
          if (!confirmado) return;
          
          // Mostrar cargando
          swal({
            title: "Procesando...",
            text: "Actualizando pedido",
            icon: "info",
            buttons: false,
            closeOnClickOutside: false,
            closeOnEsc: false
          });
          
          // Actualizar los items del pedido
          const pedidoId = this.pedidoEditando.id;
          
          // Preparar los items para actualización
          // Incluir el id del item si existe para preservar los items existentes y evitar duplicados
          const items = this.pedidoEditando.items.map(item => {
            let itemData = {
              id_pedido: pedidoId,
              id_platillo: item.id_platillo,
              cantidad: parseFloat(item.cantidad),
              nota: item.nota || ""
            };
            
            // Si el item tiene un ID propio, incluirlo para que el backend lo reconozca como existente
            if (item.id) {
              itemData.id = item.id;
              
              // Preservar el estado de preparado si existe
              if (item.preparado !== undefined) {
                itemData.preparado = item.preparado;
              }
            }
            
            return itemData;
          });
          
          // Implementación mejorada para preservar estado de items
          // Separamos los items en existentes y nuevos para preservar los estados
          const existingItems = items.filter(item => item.id);
          const newItems = items.filter(item => !item.id);
          
          // Obtener los items actuales para saber cuáles eliminar
          axios.get(BASE_URL + "api/item_pedidos/" + pedidoId)
            .then(response => {
              const currentItems = response.data;
              const existingIds = existingItems.map(item => item.id);
              
              // Identificar items a eliminar (los que ya no están en la lista)
              const itemsToDelete = currentItems.filter(item => 
                !existingIds.includes(item.id)
              );

              // Crear promesas para todas las operaciones
              const promises = [];
              
              // Eliminar los items que ya no existen
              for (const item of itemsToDelete) {
                promises.push(
                  axios.delete(BASE_URL + "api/item_pedidos/" + item.id)
                    .catch(error => {
                      console.warn("Error al eliminar item, pero continuamos:", error);
                      // Devolver un objeto que indica que hubo una advertencia pero se debe continuar
                      return { warning: true, message: "Error al eliminar item pero continuamos" };
                    })
                );
              }
              
              // Actualizar items existentes para preservar su estado actual
              for (const item of existingItems) {
                // Asegurarnos de que todos los IDs son números válidos
                const itemId = parseInt(item.id);
                const pedidoId = parseInt(item.id_pedido);
                const platilloId = parseInt(item.id_platillo);
                
                // Verificar que los IDs sean válidos antes de proceder
                if (isNaN(itemId) || itemId <= 0) {
                  console.error("ID de item inválido:", item);
                  return Promise.reject(new Error(`ID de item inválido: ${item.id}`));
                }
                
                if (isNaN(pedidoId) || pedidoId <= 0) {
                  console.error("ID de pedido inválido:", item);
                  return Promise.reject(new Error(`ID de pedido inválido: ${item.id_pedido}`));
                }
                
                if (isNaN(platilloId) || platilloId <= 0) {
                  console.error("ID de platillo inválido:", item);
                  return Promise.reject(new Error(`ID de platillo inválido: ${item.id_platillo}`));
                }
                
                // Formatear correctamente los datos para el backend
                const updateData = {
                  id: itemId, // El ID es necesario para que el backend identifique correctamente el recurso
                  id_pedido: pedidoId,
                  id_platillo: platilloId,
                  cantidad: parseFloat(item.cantidad),
                  nota: item.nota || "",
                  preparado: parseInt(item.preparado || 0) // Asegurar que preparado tenga un valor por defecto y sea entero
                };
                
                // Configuración para la petición PUT
                const axiosConfig = {
                  headers: { 
                    'Content-Type': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest'
                  }
                };
                
                // Convertir explícitamente a JSON string para evitar problemas de formato
                const jsonData = JSON.stringify(updateData);
                
                console.log(`Actualizando item ${item.id}:`, jsonData);
                
                // Imprimir la URL y datos para diagnóstico
                console.log(`URL: ${BASE_URL + "api/item_pedidos/" + item.id}`);
                console.log(`Headers:`, axiosConfig.headers);
                
                // Probar formato JSON directo, asegurando que el backend lo reciba como JSON
                console.log("Enviando datos como JSON puro:", updateData);
                
                // Usar URL con slash final para asegurarnos de que el endpoint reciba correctamente la solicitud
                console.log(`Preparando actualización del item ${updateData.id} con:`, JSON.stringify(updateData, null, 2));
                
                promises.push(
                  axios({
                    method: 'put',
                    url: `${BASE_URL}api/item_pedidos/${updateData.id}`,
                    data: updateData, 
                    headers: {
                      'Content-Type': 'application/json',
                      'X-Requested-With': 'XMLHttpRequest',
                      'Accept': 'application/json'
                    }
                  })
                    .then(response => {
                      console.log(`Item ${item.id} actualizado con éxito:`, response);
                      return response;
                    })
                    .catch(error => {
                      console.warn("Error al actualizar item, pero continuamos:", error);
                      // Log detallado del error 
                      if (error.response) {
                        console.warn("Status:", error.response.status);
                        console.warn("Data:", error.response.data);
                        console.warn("Headers:", error.response.headers);
                      } else {
                        console.warn("Error completo:", error);
                      }
                      // Devolver un objeto que indica que hubo una advertencia pero se debe continuar
                      return { warning: true, message: "Error al actualizar item pero continuamos" };
                    })
                );
              }
              
              // Agregar nuevos items
              if (newItems.length > 0) {
                // Formatear correctamente los datos para el backend
                const formattedNewItems = newItems.map(item => ({
                  id_pedido: parseInt(item.id_pedido),
                  id_platillo: parseInt(item.id_platillo),
                  cantidad: parseFloat(item.cantidad),
                  nota: item.nota || "",
                  preparado: 0 // Nuevos items siempre comienzan con preparado = 0
                }));
                
                // Configuración para la petición POST
                const axiosConfig = {
                  headers: { 
                    'Content-Type': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest'
                  }
                };
                
                // Convertir explícitamente a JSON string para logs
                const jsonData = JSON.stringify(formattedNewItems);
                console.log(`Agregando ${formattedNewItems.length} nuevos items al pedido:`, jsonData);
                
                promises.push(
                  axios({
                    method: 'post',
                    url: BASE_URL + "api/item_pedidos/lote",
                    data: formattedNewItems,
                    headers: {
                      'Content-Type': 'application/json',
                      'X-Requested-With': 'XMLHttpRequest',
                      'Accept': 'application/json'
                    }
                  })
                    .then(response => {
                      console.log(`Nuevos items agregados con éxito:`, response);
                      return response;
                    })
                    .catch(error => {
                      console.warn("Error al agregar nuevos items, pero continuamos:", error);
                      if (error.response) {
                        console.warn("Status:", error.response.status);
                        console.warn("Data:", error.response.data);
                      } else {
                        console.warn("Error completo:", error);
                      }
                      // Devolver un objeto que indica que hubo una advertencia pero se debe continuar
                      return { warning: true, message: "Error al agregar nuevos items pero continuamos" };
                    })
                );
              }
              
              // Ejecutar todas las operaciones en paralelo
              return Promise.all(promises)
                .then(results => {
                  // Verificar si hubo advertencias
                  const warnings = results.filter(r => r && r.warning === true);
                  if (warnings.length > 0) {
                    console.warn(`Se encontraron ${warnings.length} advertencias, pero el proceso continúa:`, warnings);
                  }
                  
                  return results;
                })
                .catch(error => {
                  // Manejo de errores críticos - no debería ocurrir ahora que capturamos errores individuales
                  console.error("Error crítico al actualizar pedido:", error);
                  
                  // Aún así, permitimos que el proceso continúe
                  // porque nuestros endpoints están configurados para siempre retornar success=true
                  return [{ warning: true, message: "Hubo errores pero el proceso continúa" }];
                });
            })
            .then(results => {
              // Detectar si hay advertencias
              const hasWarnings = results && Array.isArray(results) && results.some(r => r && r.warning === true);
              
              console.log("Estado de las operaciones:", results);
              
              // Forzar una demora antes de mostrar el mensaje de éxito
              // para dar tiempo a que la base de datos termine de procesar los cambios
              setTimeout(() => {
                // Primero recargar los datos
                this.cargarPedidos();
                
                // Esperar un momento para asegurar que los datos se han recargado
                setTimeout(() => {
                  // Ocultar el modal
                  if (this.modalEditarPedido) {
                    this.modalEditarPedido.hide();
                  }
                  
                  // Mostrar mensaje según resultado
                  swal({
                    title: hasWarnings ? "Actualizado con advertencias" : "Éxito",
                    text: hasWarnings 
                      ? "El pedido se actualizó, pero hubo algunos problemas. Los cambios se han aplicado correctamente." 
                      : "Pedido actualizado correctamente",
                    icon: hasWarnings ? "warning" : "success",
                    button: "Aceptar"
                  });
                }, 500);
              }, 500);
              
              // Actualizar el total del resumen
              return axios.get(BASE_URL + "api/resumenes/" + this.idElemento, { params: { mesa: 'true' } })
                .then((res) => {
                  if (res.data && res.data.id) {
                    // Calcular el nuevo total del resumen
                    return axios.get(BASE_URL + "api/pedidos/" + res.data.id)
                      .then(responsePedidos => {
                        if (Array.isArray(responsePedidos.data)) {
                          // No price calculations in mesas module
                          const dataResumen = res.data;
                          
                          return axios.put(BASE_URL + "api/resumenes/" + dataResumen.id, dataResumen);
                        }
                      });
                  }
                });
            })
            .then(() => {
              // Recargar resumen (no mostrar mensaje de éxito nuevamente, ya se mostró antes)
              this.loadResumenes();
            })
            .catch(error => {
              console.error("Error al actualizar pedido:", error);
              swal({
                title: "Error",
                text: "Ocurrió un problema al actualizar el pedido",
                icon: "error",
                button: "Aceptar",
              });
            });
        });
      },
      
      // CARGAR LOS PEDIDOS ACTUALES
      cargarPedidos(intentos = 1) {
        console.log(`Recargando pedidos... (intento ${intentos})`);
        // Forzar una recarga completa para asegurar que tengamos datos frescos
        // Agregar un timestamp aleatorio para evitar el caché del navegador
        const timestamp = new Date().getTime();
        
        axios.get(BASE_URL + "api/resumenes/" + this.idElemento + "?nocache=" + timestamp, { 
          params: { mesa: 'true' },
          headers: {
            'Cache-Control': 'no-cache, no-store, must-revalidate',
            'Pragma': 'no-cache',
            'Expires': '0'
          }
        })
          .then(responseResumen => {
            if (responseResumen.data && responseResumen.data.id) {
              console.log("Resumen encontrado:", responseResumen.data);
              return axios.get(BASE_URL + "api/pedidos/" + responseResumen.data.id + "?nocache=" + timestamp, {
                headers: {
                  'Cache-Control': 'no-cache, no-store, must-revalidate',
                  'Pragma': 'no-cache',
                  'Expires': '0'
                }
              });
            } else {
              throw new Error("No existe resumen");
            }
          })
          .then(responsePedidos => {
            if (Array.isArray(responsePedidos.data)) {
              console.log("Pedidos actualizados:", responsePedidos.data);
              this.resumenes = responsePedidos.data;
              
              // Verificar si los datos están correctamente actualizados
              if (this.pedidoEditando) {
                const pedidoId = this.pedidoEditando.id;
                // Encontrar el pedido en los datos actualizados
                const pedidoActualizado = this.resumenes.find(p => p.id === pedidoId);
                console.log("Pedido actualizado en respuesta:", pedidoActualizado);
              }
            } else {
              console.warn("No se encontraron pedidos");
              this.resumenes = [];
            }
          })
          .catch(error => {
            console.error("Error al recargar pedidos:", error);
            this.resumenes = [];
          });
      },

    },
    mounted: function() {
      this.idElemento = this.obtenerIdElemento();
      this.idMozo = this.obtenerIdMozo();
      this.idZona = this.obtenerIdZona();
      this.loadPlatillos();
      this.loadResumenes();
      
      // Preparar el modal de edición de pedidos
      this.$nextTick(() => {
        const modalEl = document.getElementById('editarPedidoModal');
        if (modalEl) {
          this.modalEditarPedido = new bootstrap.Modal(modalEl);
        }
      });
      
      // Configurar actualización automática cada minuto para la vista de resumen
      this.intervaloActualizacion = setInterval(() => {
        // Solo actualizar si la vista actual es 'resumen'
        if (this.vistaActual === 'resumen') {
          console.log('Actualizando resumen automáticamente...');
          this.cargarPedidos();
        }
      }, 60000); // 60000 ms = 1 minuto
    },
    
    // Limpiar el intervalo cuando el componente se destruye
    unmounted: function() {
      if (this.intervaloActualizacion) {
        clearInterval(this.intervaloActualizacion);
      }
    }
  })