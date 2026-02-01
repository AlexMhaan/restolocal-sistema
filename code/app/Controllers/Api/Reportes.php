<?php

namespace App\Controllers\Api;

use CodeIgniter\RESTful\ResourceController;
use App\Models\ReportesModel;
use App\Models\ConsecutivosModel;

class Reportes extends ResourceController
{
    protected $modelName = 'App\Models\ReportesModel';
    protected $format    = 'json';

    public function index()
    {
        $data = $this->request->getVar();
        if (isset($data['fecha'])) {
            return $this->respond($this->model->where('DATE(fecha)', $data['fecha'])->findAll());
        }
        return $this->respond($this->model->findAll());
    }

    public function create()
    {
        try {
            $data = $this->request->getJSON(true);
              if (empty($data)) {
                return $this->fail(['message' => 'No se recibieron datos', 'received' => $this->request->getBody()]);
            }

            $requiredFields = ['tipo', 'forma_pago', 'total_pedido', 'id_pedido'];
            $missingFields = [];
            foreach ($requiredFields as $field) {
                if (!isset($data[$field]) || $data[$field] === '') {
                    $missingFields[] = $field;
                }
            }
            
            if (!empty($missingFields)) {
                return $this->fail([
                    'message' => 'Faltan datos requeridos',
                    'missing_fields' => $missingFields,
                    'received_data' => $data
                ]);
            }
            
            // Obtener el siguiente número según el tipo de documento
            $consecutivosModel = new ConsecutivosModel();
            $numero = $consecutivosModel->getSiguienteNumero($data['tipo']);
            
            // Agregar el número al reporte
            $data['numero'] = $numero;
            
            // Asegurar que tenga estado y fecha
            $data['estado'] = $data['estado'] ?? 'emitido';
            // $data['fecha'] = $data['fecha'] ?? date('Y-m-d H:i:s');
            
            // Guardar el reporte
            if ($this->model->save($data)) {
                $responseData = [
                    'status' => 'success',
                    'message' => 'Reporte creado correctamente',
                    'data' => array_merge(['id' => $this->model->getInsertID()], $data)
                ];
                return $this->respond($responseData, 201);
            }
            
            return $this->fail($this->model->errors());
            
        } catch (\Exception $e) {
            log_message('error', '[Reportes/create] Error: ' . $e->getMessage());
            return $this->fail($e->getMessage());
        }
    }

    public function cancelar($id_pedido = null)
    {
        try {
            if ($id_pedido === null) {
                return $this->fail('No se proporcionó un ID de pedido');
            }

            // Obtener el reporte asociado al pedido que esté en estado 'emitido'
            $reporte = $this->model->where('id_pedido', $id_pedido)->where('estado', 'emitido')->first();
            if (!$reporte) {
                return $this->fail('No se encontró un reporte activo asociado al pedido');
            }

            // Obtener el pedido asociado
            $pedidosModel = new \App\Models\PedidosModel();
            $pedido = $pedidosModel->find($id_pedido);
            if (!$pedido) {
                return $this->fail('No se encontró el pedido');
            }

            // Convertir las fechas a timestamps para poder calcular la diferencia
            $timestampPedido = strtotime($pedido['ultima_modif']);
            $timestampReporte = strtotime($reporte['fecha']);

            // Calcular la diferencia absoluta en segundos
            $diferenciaSegundos = abs($timestampPedido - $timestampReporte);

            // Validar que la diferencia sea de máximo 1 segundo
            if ($diferenciaSegundos > 2) {
                return $this->fail('La diferencia entre las fechas es mayor a 1 segundo');
            }
          
            // Si todas las validaciones pasan, actualizar el estado
            if ($this->model->update($reporte['id'], ['estado' => 'cancelado'])) {
                return $this->respond(['message' => 'Reporte cancelado']);
            }

            return $this->fail($this->model->errors());
        } catch (\Exception $e) {
            log_message('error', '[Reportes/cancelar] Error: ' . $e->getMessage());
            return $this->fail($e->getMessage());
        }
    }

    // Método para obtener productos vendidos por fecha para el corte Z
    public function vendidosPorFecha()
    {
        try {
            // Obtenemos la fecha del request
            $fecha = $this->request->getVar('fecha');
            
            if (!$fecha) {
                return $this->fail('Se requiere una fecha para filtrar los productos vendidos');
            }
            
            $db = \Config\Database::connect();
            
            // Consulta para obtener productos vendidos por fecha, agrupados por producto
            // Solo incluye tickets electrónicos y facturas, excluyendo tickets normales
            // NOTA: No usar ROUND() para mantener precisión completa en los cálculos
            $query = $db->query("
                SELECT 
                p.id as codigo, 
                p.nombre as descripcion, 
                SUM(ip.cantidad) as cantidad, 
                SUM(ip.cantidad * p.precio) as valor
                FROM item_pedidos ip
                JOIN platillos p ON ip.id_platillo = p.id
                JOIN reportes r ON r.id_pedido = ip.id_pedido
                WHERE DATE(r.fecha) = ? 
                AND r.estado = 'emitido'
                AND r.tipo IN ('ticket electronico', 'factura')
                GROUP BY p.id, p.nombre
                ORDER BY p.id ASC
            ", [$fecha]);
            
            $result = $query->getResultArray();
            
            return $this->respond($result);
        } catch (\Exception $e) {
            log_message('error', '[Reportes/vendidosPorFecha] Error: ' . $e->getMessage());
            return $this->fail($e->getMessage());
        }
    }
    
    // Método para obtener detalles del pedido incluyendo el mesero
    public function obtenerDetalleReporte($id = null)
    {
        try {
            if ($id === null) {
                return $this->fail('Se requiere un ID de reporte');
            }
            
            $db = \Config\Database::connect();
            
            $query = $db->query("
                SELECT 
                r.id as id_reporte,
                r.numero as numero_ticket,
                r.fecha as fecha,
                r.total_pedido as total,
                r.forma_pago as metodo_pago,
                u.nombre as nombre_mesero,
                u.apellido as apellido_mesero
                FROM reportes r
                JOIN pedidos pe ON pe.id = r.id_pedido
                LEFT JOIN usuarios u ON u.id = pe.id_mesero
                WHERE r.id = ? AND r.estado = 'emitido'
            ", [$id]);
            
            $result = $query->getRowArray();
            
            if (!$result) {
                return $this->failNotFound('Reporte no encontrado');
            }
            
            return $this->respond($result);
        } catch (\Exception $e) {
            log_message('error', '[Reportes/obtenerDetalleReporte] Error: ' . $e->getMessage());
            return $this->fail($e->getMessage());
        }
    }
    
    // Método para buscar reporte por número de ticket
    public function buscarPorNumeroTicket()
    {
        try {
            $numero = $this->request->getVar('numero');
            
            if (!$numero) {
                return $this->fail('Se requiere un número de ticket');
            }
            
            $db = \Config\Database::connect();
            
            // Primero verificamos si existe el ticket sin importar su estado
            $checkQuery = $db->query("
                SELECT numero, estado
                FROM reportes
                WHERE numero = ?
            ", [$numero]);
            
            $checkResult = $checkQuery->getRowArray();
            
            // Si el ticket existe pero está cancelado, mostrar un mensaje específico
            if ($checkResult && $checkResult['estado'] === 'cancelado') {
                return $this->fail('El ticket #' . $numero . ' existe pero ha sido cancelado');
            }
            
            // Consulta para obtener el reporte por número de ticket (solo emitidos)
            $query = $db->query("
                SELECT 
                r.id as id_reporte,
                r.numero as numero_ticket,
                r.fecha as fecha,
                r.total_pedido as total,
                r.forma_pago as metodo_pago,
                r.id_pedido,
                u.nombre as nombre_mesero,
                u.apellido as apellido_mesero
                FROM reportes r
                JOIN pedidos pe ON pe.id = r.id_pedido
                LEFT JOIN usuarios u ON u.id = pe.id_mesero
                WHERE r.numero = ? AND r.estado = 'emitido'
            ", [$numero]);
            
            $result = $query->getRowArray();
            
            if (!$result) {
                return $this->failNotFound('Ticket no encontrado o no está en estado emitido');
            }
            
            // También buscar los items del pedido
            $queryItems = $db->query("
                SELECT 
                p.nombre as descripcion,
                ip.cantidad as cantidad,
                p.precio as precio,
                ip.cantidad * p.precio as importe
                FROM item_pedidos ip
                JOIN platillos p ON p.id = ip.id_platillo
                WHERE ip.id_pedido = ?
            ", [$result['id_pedido']]);
            
            $result['items'] = $queryItems->getResultArray();
            
            // Convertir el total a texto
            $result['total_texto'] = $this->numeroALetras($result['total']);
            
            // Asegurar que se use la fecha original del reporte sin modificaciones
            // Esta fecha viene directamente de la tabla reportes
            if (isset($result['fecha'])) {
                $result['fecha_original'] = $result['fecha'];
            }
            
            return $this->respond($result);
        } catch (\Exception $e) {
            log_message('error', '[Reportes/buscarPorNumeroTicket] Error: ' . $e->getMessage());
            return $this->fail($e->getMessage());
        }
    }
    
    // Método para buscar reporte por id_pedido
    public function buscarPorPedido()
    {
        try {
            $idPedido = $this->request->getVar('id_pedido');
            
            if (!$idPedido) {
                return $this->fail('Se requiere un ID de pedido');
            }
            
            $db = \Config\Database::connect();
            
            // Primero verificamos si existe el pedido y su estado
            $checkQuery = $db->query("
                SELECT id, estado
                FROM pedidos
                WHERE id = ?
            ", [$idPedido]);
            
            $checkResult = $checkQuery->getRowArray();
            
            if (!$checkResult) {
                return $this->failNotFound('Pedido no encontrado');
            }
            
            // Consulta para obtener el reporte por id_pedido con numero = 0 
            $query = $db->query("
                SELECT 
                r.id as id_reporte,
                r.numero as numero_ticket,
                r.fecha as fecha,
                r.total_pedido as total,
                r.forma_pago as metodo_pago,
                r.id_pedido,
                u.nombre as nombre_mesero,
                u.apellido as apellido_mesero
                FROM reportes r
                JOIN pedidos pe ON pe.id = r.id_pedido
                LEFT JOIN usuarios u ON u.id = pe.id_mesero
                WHERE r.id_pedido = ? AND r.estado = 'emitido' AND r.numero = 0
            ", [$idPedido]);
            
            $result = $query->getRowArray();
            
            if (!$result) {
                return $this->failNotFound('No se encontró un ticket asociado a este pedido (con numero=0 y estado=emitido)');
            }
            
            // También buscar los items del pedido
            $queryItems = $db->query("
                SELECT 
                p.nombre as descripcion,
                ip.cantidad as cantidad,
                p.precio as precio,
                ip.cantidad * p.precio as importe
                FROM item_pedidos ip
                JOIN platillos p ON p.id = ip.id_platillo
                WHERE ip.id_pedido = ?
            ", [$idPedido]);
            
            $result['items'] = $queryItems->getResultArray();
            
            // Convertir el total a texto
            $result['total_texto'] = $this->numeroALetras($result['total']);
            
            // Asegurar que se use la fecha original del reporte sin modificaciones
            if (isset($result['fecha'])) {
                $result['fecha_original'] = $result['fecha'];
            }
            
            return $this->respond($result);
        } catch (\Exception $e) {
            log_message('error', '[Reportes/buscarPorPedido] Error: ' . $e->getMessage());
            return $this->fail($e->getMessage());
        }
    }
    
    // Función para convertir números a letras (pesos mexicanos)
    private function numeroALetras($numero) {
        $unidades = array('', 'un', 'dos', 'tres', 'cuatro', 'cinco', 'seis', 'siete', 'ocho', 'nueve');
        $decenas = array('', 'diez', 'veinte', 'treinta', 'cuarenta', 'cincuenta', 'sesenta', 'setenta', 'ochenta', 'noventa');
        $centenas = array('', 'ciento', 'doscientos', 'trescientos', 'cuatrocientos', 'quinientos', 'seiscientos', 'setecientos', 'ochocientos', 'novecientos');
        $especiales = array('once', 'doce', 'trece', 'catorce', 'quince', 'dieciséis', 'diecisiete', 'dieciocho', 'diecinueve',
                            'veintiuno', 'veintidós', 'veintitrés', 'veinticuatro', 'veinticinco', 'veintiséis', 'veintisiete', 'veintiocho', 'veintinueve');
        
        // NOTA: Este number_format() está OK aquí porque solo se usa para MOSTRAR en el ticket impreso
        // No afecta los cálculos internos ni totales guardados en BD
        $numero = number_format($numero, 2, '.', '');
        $partes = explode('.', $numero);
        $entero = (int)$partes[0];
        $decimal = isset($partes[1]) ? $partes[1] : '00';
        
        if ($entero == 0) {
            return 'cero Pesos ' . $decimal . '/100 M.N.';
        }
        
        if ($entero == 1) {
            return 'un Peso ' . $decimal . '/100 M.N.';
        }
        
        if ($entero == 1000000) {
            return 'un millón de Pesos ' . $decimal . '/100 M.N.';
        }
        
        $texto = '';
        
        // Millones
        $millones = floor($entero / 1000000);
        if ($millones > 1) {
            $texto .= $this->convertirGrupo($millones) . ' millones ';
        } else if ($millones == 1) {
            $texto .= 'un millón ';
        }
        $entero = $entero % 1000000;
        
        // Miles
        $miles = floor($entero / 1000);
        if ($miles > 1) {
            $texto .= $this->convertirGrupo($miles) . ' mil ';
        } else if ($miles == 1) {
            $texto .= 'mil ';
        }
        $entero = $entero % 1000;
        
        // Cientos y unidades
        if ($entero > 0) {
            $texto .= $this->convertirGrupo($entero);
        }
        
        return trim($texto) . ' Pesos ' . $decimal . '/100 M.N.';
    }
    
    private function convertirGrupo($numero) {
        $unidades = array('', 'un', 'dos', 'tres', 'cuatro', 'cinco', 'seis', 'siete', 'ocho', 'nueve');
        $decenas = array('', 'diez', 'veinte', 'treinta', 'cuarenta', 'cincuenta', 'sesenta', 'setenta', 'ochenta', 'noventa');
        $centenas = array('', 'ciento', 'doscientos', 'trescientos', 'cuatrocientos', 'quinientos', 'seiscientos', 'setecientos', 'ochocientos', 'novecientos');
        $especiales = array('once', 'doce', 'trece', 'catorce', 'quince', 'dieciséis', 'diecisiete', 'dieciocho', 'diecinueve',
                            'veintiuno', 'veintidós', 'veintitrés', 'veinticuatro', 'veinticinco', 'veintiséis', 'veintisiete', 'veintiocho', 'veintinueve');
        
        if ($numero == 100) {
            return 'cien';
        }
        
        $texto = '';
        
        // Centenas
        $cent = floor($numero / 100);
        if ($cent > 0) {
            $texto .= $centenas[$cent] . ' ';
        }
        
        // Decenas y unidades
        $resto = $numero % 100;
        
        if ($resto <= 9) {
            // Solo unidades
            $texto .= $unidades[$resto];
        } else if ($resto <= 29) {
            // Para números especiales entre 11-19 y 21-29
            if (in_array($resto, [11, 12, 13, 14, 15, 16, 17, 18, 19])) {
                $texto .= $especiales[$resto - 11];
            } else if ($resto == 20) {
                $texto .= 'veinte';
            } else {
                // FIX: Para 21-29, el índice correcto es ($resto - 11) + 9
                // porque los primeros 9 elementos son 11-19, luego vienen 21-29
                $texto .= $especiales[($resto - 11) + 9];
            }
        } else {
            // Para el resto de números
            $dec = floor($resto / 10);
            $uni = $resto % 10;
            $texto .= $decenas[$dec];
            if ($uni > 0) {
                $texto .= ' y ' . $unidades[$uni];
            }
        }
        
        return trim($texto);
    }

    /**
     * Obtiene reportes con los detalles de los pedidos (mesero, mesa) unidos
     * Esta función es necesaria para agrupar reportes por mesero en la vista de caja
     */
    public function conDetallesPedido()
    {
        try {
            $data = $this->request->getVar();
            
            // Crear consulta base
            $db = \Config\Database::connect();
            $builder = $db->table('reportes r');
            
            // Hacer join con pedidos y usuarios para obtener datos del mesero
            $builder->select('r.*, p.id_mesero, p.id_mesa, u.nombre as nombre_mesero')
                    ->join('pedidos p', 'p.id = r.id_pedido', 'left')
                    ->join('usuarios u', 'u.id = p.id_mesero', 'left');
            
            // Filtrar por fecha si se proporciona
            if (isset($data['fecha'])) {
                $builder->where('DATE(r.fecha)', $data['fecha']);
            }
            
            $result = $builder->get()->getResult();
            
            return $this->respond($result);
        } catch (\Exception $e) {
            return $this->fail('Error al obtener los reportes con detalles: ' . $e->getMessage());
        }
    }
    
    /**
     * Corte Historia - Reporte de folios consecutivos
     * Muestra todos los folios emitidos con filtros por fecha y/o rango de folios
     * 
     * Parámetros GET:
     * - fecha_inicio: Fecha inicial (formato: Y-m-d) - Opcional
     * - fecha_fin: Fecha final (formato: Y-m-d) - Opcional
     * - folio_inicio: Número de folio inicial - Opcional
     * - folio_fin: Número de folio final - Opcional
     * - tipo: Filtrar por tipo de documento (ticket, factura, ticket electronico) - Opcional
     * - formato: 'json' (default) o 'pdf' - Opcional
     * 
     * Retorna:
     * - folios: Array de reportes con información detallada
     * - resumen: Subtotales por tipo de comprobante y total general
     * - filtros_aplicados: Confirmación de filtros usados
     */
    public function corteHistoria()
    {
        try {
            $data = $this->request->getVar();
            
            $db = \Config\Database::connect();
            $builder = $db->table('reportes r');
            
            // SELECT con información completa
            $builder->select('
                r.numero as folio,
                r.tipo,
                r.fecha,
                r.total_pedido as total,
                r.forma_pago,
                r.id_pedido,
                p.id_mesa,
                CONCAT(u.nombre, " ", u.apellido) as mesero
            ')
            ->join('pedidos p', 'p.id = r.id_pedido', 'left')
            ->join('usuarios u', 'u.id = p.id_mesero', 'left')
            ->where('r.estado', 'emitido'); // Solo emitidos
            
            // Filtros opcionales
            $filtros_aplicados = [];
            
            // Filtro por rango de fechas
            if (!empty($data['fecha_inicio'])) {
                $builder->where('DATE(r.fecha) >=', $data['fecha_inicio']);
                $filtros_aplicados['fecha_inicio'] = $data['fecha_inicio'];
            }
            
            if (!empty($data['fecha_fin'])) {
                $builder->where('DATE(r.fecha) <=', $data['fecha_fin']);
                $filtros_aplicados['fecha_fin'] = $data['fecha_fin'];
            }
            
            // Filtro por rango de folios
            if (!empty($data['folio_inicio'])) {
                $builder->where('r.numero >=', (int)$data['folio_inicio']);
                $filtros_aplicados['folio_inicio'] = (int)$data['folio_inicio'];
            }
            
            if (!empty($data['folio_fin'])) {
                $builder->where('r.numero <=', (int)$data['folio_fin']);
                $filtros_aplicados['folio_fin'] = (int)$data['folio_fin'];
            }
            
            // Filtro por tipo de documento
            if (!empty($data['tipo']) && in_array($data['tipo'], ['ticket', 'factura', 'ticket electronico'])) {
                $builder->where('r.tipo', $data['tipo']);
                $filtros_aplicados['tipo'] = $data['tipo'];
            }
            
            // Ordenar por número de folio
            $builder->orderBy('r.numero', 'ASC');
            
            $folios = $builder->get()->getResultArray();
            
            // Calcular resumen por tipo de comprobante
            $resumen = [
                'ticket' => ['cantidad' => 0, 'total' => 0],
                'factura' => ['cantidad' => 0, 'total' => 0],
                'ticket electronico' => ['cantidad' => 0, 'total' => 0],
                'total_general' => 0,
                'total_folios' => 0
            ];
            
            foreach ($folios as $folio) {
                $tipo = $folio['tipo'];
                $resumen[$tipo]['cantidad']++;
                $resumen[$tipo]['total'] += $folio['total'];
                $resumen['total_general'] += $folio['total'];
            }
            
            $resumen['total_folios'] = count($folios);
            
            // Si se solicita PDF, generar y devolver
            if (!empty($data['formato']) && $data['formato'] === 'pdf') {
                return $this->generarCorteHistoriaPDF($folios, $resumen, $filtros_aplicados);
            }
            
            // Por defecto, devolver JSON
            return $this->respond([
                'success' => true,
                'folios' => $folios,
                'resumen' => $resumen,
                'filtros_aplicados' => $filtros_aplicados
            ]);
            
        } catch (\Exception $e) {
            log_message('error', '[Reportes/corteHistoria] Error: ' . $e->getMessage());
            return $this->fail('Error al generar el corte historia: ' . $e->getMessage());
        }
    }
    
    /**
     * Genera el PDF del Corte Historia
     * Utiliza HTML simple que se puede imprimir o guardar como PDF desde el navegador
     */
    private function generarCorteHistoriaPDF($folios, $resumen, $filtros_aplicados)
    {
        // Preparar datos para la vista
        $data = [
            'folios' => $folios,
            'resumen' => $resumen,
            'filtros_aplicados' => $filtros_aplicados,
            'fecha_generacion' => date('d/m/Y H:i:s')
        ];
        
        // Usar vista para generar HTML
        $html = view('components/reportes/corte_historia_pdf', $data);
        
        // Devolver como HTML que el navegador puede imprimir/guardar como PDF
        return $this->response->setContentType('text/html')->setBody($html);
    }
    
    /**
     * Genera el ticket HTML para impresión usando el método show() RESTful
     */
    public function show($id_pedido = null)
    {
        // Redirigir al método generarTicket existente
        return $this->generarTicket($id_pedido);
    }
    
    /**
     * Genera un PRE-TICKET (sin marca de pagado, sin valor fiscal)
     * GET /api/reportes/preticket/{id_pedido}
     * 
     * Este endpoint se usa ANTES de cobrar para que el mesero
     * pueda mostrar al cliente cuánto debe pagar.
     * NO modifica el estado del pedido ni crea reporte fiscal.
     */
    public function preticket($id_pedido = null)
    {
        try {
            if ($id_pedido === null) {
                $id_pedido = $this->request->getVar('id');
            }
            
            if (!$id_pedido || $id_pedido <= 0) {
                return $this->fail('ID de pedido inválido');
            }
            
            // Llamar a generarTicket con flag de pre-ticket
            return $this->generarTicket($id_pedido, true);
            
        } catch (\Exception $e) {
            log_message('error', '[Reportes/preticket] Error: ' . $e->getMessage());
            return $this->fail('Error al generar el pre-ticket: ' . $e->getMessage());
        }
    }
    
    /**
     * Genera el ticket HTML para impresión
     */
    public function generarTicket($id_pedido = null, $es_preticket = false)
    {
        try {
            if ($id_pedido === null) {
                $id_pedido = $this->request->getVar('id');
            }
            
            if (!$id_pedido || $id_pedido <= 0) {
                return $this->fail('ID de pedido inválido');
            }
            
            $db = \Config\Database::connect();
            
            // Consultar datos del pedido con el mesero y el reporte asociado
            $query = $db->query("
                SELECT p.*, u.nombre as nombre_mesero, u.apellido as apellido_mesero,
                       r.numero as numero_reporte, r.tipo as tipo_reporte, r.fecha as fecha_reporte
                FROM pedidos p 
                LEFT JOIN usuarios u ON p.id_mesero = u.id 
                LEFT JOIN reportes r ON r.id_pedido = p.id AND r.estado = 'emitido'
                WHERE p.id = ?
            ", [$id_pedido]);
            
            $pedido = $query->getRowArray();
            
            if (!$pedido) {
                return $this->failNotFound('Pedido no encontrado');
            }
            
            // Consultar items del pedido
            // NOTA: No usar ROUND() para mantener precisión completa
            $queryItems = $db->query("
                SELECT ip.cantidad, ip.nota, pl.nombre as descripcion, pl.precio as precio_unitario,
                       (ip.cantidad * pl.precio) as importe
                FROM item_pedidos ip
                INNER JOIN platillos pl ON ip.id_platillo = pl.id
                WHERE ip.id_pedido = ?
            ", [$id_pedido]);
            
            $items = $queryItems->getResultArray();
            
            // Calcular total - mantener precisión completa
            // PHP mantendrá todos los decimales de MySQL
            $total = 0;
            foreach ($items as $item) {
                $total += $item['importe'];
            }
            
            // Convertir total a texto
            $total_texto = $this->numeroALetras($total);
            
            // Formatear fecha
            // Si NO es pre-ticket y existe fecha_reporte, usar esa (fecha del cobro)
            // Si es pre-ticket o no hay reporte, usar fecha del pedido (fecha de creación)
            if (!$es_preticket && isset($pedido['fecha_reporte']) && !empty($pedido['fecha_reporte'])) {
                $fecha_formateada = date('n/j/Y, g:i:s A', strtotime($pedido['fecha_reporte']));
            } else {
                $fecha_formateada = date('n/j/Y, g:i:s A', strtotime($pedido['fecha']));
            }
            
            // Generar el HTML del ticket
            $html = $this->generarHtmlTicket($pedido, $items, $total, $total_texto, $fecha_formateada, $id_pedido, $es_preticket);
            
            // Retornar como respuesta HTML
            return $this->response->setContentType('text/html')->setBody($html);
            
        } catch (\Exception $e) {
            log_message('error', '[Reportes/generarTicket] Error: ' . $e->getMessage());
            return $this->fail('Error al generar el ticket: ' . $e->getMessage());
        }
    }
    
    /**
     * Genera el HTML del ticket usando archivo separado en Views/components
     * 
     * @param array $pedido Datos del pedido
     * @param array $items Items del pedido
     * @param float $total Total del pedido
     * @param string $total_texto Total en letras
     * @param string $fecha_formateada Fecha formateada
     * @param int $id_pedido ID del pedido
     * @param bool $es_preticket Si es un pre-ticket (sin marca de pagado)
     */
    private function generarHtmlTicket($pedido, $items, $total, $total_texto, $fecha_formateada, $id_pedido, $es_preticket = false)
    {
        // Determinar el número de ticket a mostrar según el tipo
        $numero_ticket = $id_pedido; // Por defecto usar ID del pedido
        
        // Determinar si el pedido está pagado
        // Si es pre-ticket, forzar a NO pagado
        // Si NO es pre-ticket, verificar: 1) que tenga reporte O 2) que el pedido tenga estado = 2 (pagado)
        $esta_pagado = !$es_preticket && (
            (isset($pedido['numero_reporte']) && !empty($pedido['numero_reporte'])) || 
            (isset($pedido['estado']) && $pedido['estado'] == 2)
        );
        
        // Si existe información del reporte, usar su lógica
        if (isset($pedido['tipo_reporte']) && isset($pedido['numero_reporte'])) {
            switch ($pedido['tipo_reporte']) {
                case 'ticket':
                    $numero_ticket = $id_pedido; // Usar ID del pedido
                    break;
                case 'ticket electronico':
                    $numero_ticket = $pedido['numero_reporte']; // Usar número consecutivo
                    break;
                case 'factura':
                    $numero_ticket = $pedido['numero_reporte']; // Usar número consecutivo
                    break;
                default:
                    $numero_ticket = $id_pedido;
            }
        }
        
        // Preparar datos para la vista
        $data = [
            'pedido' => $pedido,
            'items' => $items,
            'total' => $total,
            'total_texto' => $total_texto,
            'fecha_formateada' => $fecha_formateada,
            'id_pedido' => $id_pedido,
            'numero_ticket' => $numero_ticket,
            'esta_pagado' => $esta_pagado,
            'es_preticket' => $es_preticket
        ];
        
        // Usar el helper view() de CodeIgniter para cargar la vista
        return view('components/ticket_imp', $data);
    }
    
    /**
     * Exporta reportes fiscales a Excel con formato específico del cliente
     * GET /api/reportes/exportarExcel?fecha_inicio=YYYY-MM-DD&fecha_fin=YYYY-MM-DD&tipo=factura
     * 
     * Parámetros:
     * - fecha_inicio: Fecha inicial (opcional)
     * - fecha_fin: Fecha final (opcional)
     * - tipo: 'factura' o 'ticket electronico' (requerido)
     * 
     * Retorna: Archivo Excel con columnas:
     * NUMERO DE FOLIO | FECHA | HORA | CONSECUTIVO | SUBTOTAL | IVA | TOTAL
     */
    public function exportarExcel()
    {
        try {
            $fechaInicio = $this->request->getVar('fechaInicio') ?: $this->request->getVar('fecha_inicio');
            $fechaFin = $this->request->getVar('fechaFin') ?: $this->request->getVar('fecha_fin');
            $tipo = $this->request->getVar('tipo');
            
            // Validar tipo
            if (!$tipo || !in_array($tipo, ['factura', 'ticket electronico'])) {
                return $this->fail('Tipo inválido. Use "factura" o "ticket electronico"');
            }
            
            $db = \Config\Database::connect();
            
            // Query base
            $query = "SELECT 
                r.id_pedido as numero_folio,
                r.fecha,
                r.numero as consecutivo,
                r.total_pedido as total
            FROM reportes r
            WHERE r.tipo = ?
              AND r.estado = 'emitido'
              AND r.numero > 0";
            
            $params = [$tipo];
            
            // Agregar filtro de fecha inicio
            if ($fechaInicio) {
                $query .= " AND DATE(r.fecha) >= ?";
                $params[] = $fechaInicio;
            }
            
            // Agregar filtro de fecha fin
            if ($fechaFin) {
                $query .= " AND DATE(r.fecha) <= ?";
                $params[] = $fechaFin;
            }
            
            $query .= " ORDER BY r.fecha DESC";
            
            $reportes = $db->query($query, $params)->getResultArray();
            
            if (empty($reportes)) {
                return $this->fail('No se encontraron reportes con los filtros especificados');
            }
            
            // Generar CSV (Excel puede abrirlo)
            $csv = '';
            
            // BOM UTF-8 para Excel
            $csv = "\xEF\xBB\xBF";
            
            // Encabezado
            $csv .= "NUMERO DE FOLIO,FECHA,HORA,CONSECUTIVO,SUBTOTAL,IVA,TOTAL\n";
            
            // Datos
            foreach ($reportes as $reporte) {
                $fechaHora = new \DateTime($reporte['fecha']);
                $fecha = $fechaHora->format('m/d/Y');
                $hora = $fechaHora->format('h:i A');
                
                $total = floatval($reporte['total']);
                $subtotal = round($total / 1.16, 2);
                $iva = round($total - $subtotal, 2);
                
                $csv .= $reporte['numero_folio'] . ",";
                $csv .= $fecha . ",";
                $csv .= $hora . ",";
                $csv .= $reporte['consecutivo'] . ",";
                $csv .= number_format($subtotal, 2, '.', '') . ",";
                $csv .= number_format($iva, 2, '.', '') . ",";
                $csv .= number_format($total, 2, '.', '') . "\n";
            }
            
            // Nombre del archivo
            $tipoArchivo = ($tipo === 'factura') ? 'facturas' : 'tickets_electronicos';
            $fechaSufijo = date('Ymd');
            $filename = "{$tipoArchivo}_{$fechaSufijo}.csv";
            
            return $this->response
                ->setHeader('Content-Type', 'text/csv; charset=UTF-8')
                ->setHeader('Content-Disposition', 'attachment; filename="' . $filename . '"')
                ->setBody($csv);
                
        } catch (\Exception $e) {
            log_message('error', '[Reportes/exportarExcel] Error: ' . $e->getMessage());
            return $this->fail('Error al generar el archivo: ' . $e->getMessage());
        }
    }
    
    /**
     * Genera un respaldo de folios fiscales (facturas y tickets electrónicos)
     * Solo incluye documentos con folio consecutivo (numero > 0)
     * 
     * Parámetros GET:
     * - fecha_inicio: Fecha inicial (formato: Y-m-d) - Opcional
     * - fecha_fin: Fecha final (formato: Y-m-d) - Opcional
     * - folio_inicio: Número de folio inicial - Opcional
     * - folio_fin: Número de folio final - Opcional
     * - formato: 'sql' o 'excel' - Requerido
     * 
     * Retorna:
     * - Archivo descargable (SQL o Excel según formato)
     */
    public function generarRespaldo()
    {
        try {
            $data = $this->request->getVar();
            
            // Validar formato
            $formato = $data['formato'] ?? 'sql';
            if (!in_array($formato, ['sql', 'excel'])) {
                return $this->fail('Formato inválido. Use "sql" o "excel"');
            }
            
            $db = \Config\Database::connect();
            
            // ========================================
            // 1. OBTENER REPORTES FISCALES
            // ========================================
            $builderReportes = $db->table('reportes r');
            $builderReportes->select('
                r.id,
                r.numero as folio,
                r.tipo,
                r.forma_pago,
                r.total_pedido,
                r.estado,
                r.fecha,
                r.id_pedido,
                r.ultimo_cambio
            ')
            ->whereIn('r.tipo', ['factura', 'ticket electronico'])
            ->where('r.numero >', 0)
            ->where('r.estado', 'emitido');
            
            // Aplicar filtros opcionales
            $filtros_aplicados = [];
            
            if (!empty($data['fecha_inicio'])) {
                $builderReportes->where('DATE(r.fecha) >=', $data['fecha_inicio']);
                $filtros_aplicados['fecha_inicio'] = $data['fecha_inicio'];
            }
            
            if (!empty($data['fecha_fin'])) {
                $builderReportes->where('DATE(r.fecha) <=', $data['fecha_fin']);
                $filtros_aplicados['fecha_fin'] = $data['fecha_fin'];
            }
            
            if (!empty($data['folio_inicio'])) {
                $builderReportes->where('r.numero >=', (int)$data['folio_inicio']);
                $filtros_aplicados['folio_inicio'] = (int)$data['folio_inicio'];
            }
            
            if (!empty($data['folio_fin'])) {
                $builderReportes->where('r.numero <=', (int)$data['folio_fin']);
                $filtros_aplicados['folio_fin'] = (int)$data['folio_fin'];
            }
            
            $builderReportes->orderBy('r.numero', 'ASC');
            $reportes = $builderReportes->get()->getResultArray();
            
            if (empty($reportes)) {
                return $this->fail('No se encontraron registros para respaldar con los filtros seleccionados');
            }
            
            // Extraer IDs de pedidos para consultas relacionadas
            $idsPedidos = array_column($reportes, 'id_pedido');
            
            // ========================================
            // 2. OBTENER PEDIDOS RELACIONADOS
            // ========================================
            $builderPedidos = $db->table('pedidos p');
            $builderPedidos->select('
                p.id,
                p.id_resumen,
                p.id_mesero,
                p.id_mesa,
                p.fecha,
                p.estado,
                p.ultima_modif,
                p.metodo,
                p.comprobante,
                u.nombre as nombre_mesero,
                u.apellido as apellido_mesero
            ')
            ->join('usuarios u', 'u.id = p.id_mesero', 'left')
            ->whereIn('p.id', $idsPedidos);
            
            $pedidos = $builderPedidos->get()->getResultArray();
            
            // ========================================
            // 3. OBTENER ITEMS DE PEDIDOS
            // ========================================
            $builderItems = $db->table('item_pedidos ip');
            $builderItems->select('
                ip.id,
                ip.id_pedido,
                ip.id_platillo,
                ip.cantidad,
                ip.nota,
                ip.preparado,
                ip.pagado,
                pl.nombre as nombre_platillo,
                pl.precio as precio_platillo,
                pl.categoria as id_categoria
            ')
            ->join('platillos pl', 'pl.id = ip.id_platillo', 'left')
            ->whereIn('ip.id_pedido', $idsPedidos);
            
            $items = $builderItems->get()->getResultArray();
            
            // ========================================
            // 4. CALCULAR RESUMEN
            // ========================================
            $resumen = [
                'total_reportes' => count($reportes),
                'facturas' => ['cantidad' => 0, 'total' => 0],
                'tickets_electronicos' => ['cantidad' => 0, 'total' => 0],
                'total_general' => 0,
                'fecha_generacion' => date('Y-m-d H:i:s'),
                'filtros' => $filtros_aplicados
            ];
            
            foreach ($reportes as $reporte) {
                if ($reporte['tipo'] === 'factura') {
                    $resumen['facturas']['cantidad']++;
                    $resumen['facturas']['total'] += $reporte['total_pedido'];
                } else {
                    $resumen['tickets_electronicos']['cantidad']++;
                    $resumen['tickets_electronicos']['total'] += $reporte['total_pedido'];
                }
                $resumen['total_general'] += $reporte['total_pedido'];
            }
            
            // ========================================
            // 5. GENERAR ARCHIVO SEGÚN FORMATO
            // ========================================
            if ($formato === 'sql') {
                return $this->generarRespaldoSQL($reportes, $pedidos, $items, $resumen);
            } else {
                return $this->generarRespaldoExcel($reportes, $pedidos, $items, $resumen);
            }
            
        } catch (\Exception $e) {
            log_message('error', '[Reportes/generarRespaldo] Error: ' . $e->getMessage());
            return $this->fail('Error al generar el respaldo: ' . $e->getMessage());
        }
    }    
    /**
     * Genera archivo SQL con estructura e INSERTs
     */
    private function generarRespaldoSQL($reportes, $pedidos, $items, $resumen)
    {
        $sql = "";
        
        // ========================================
        // ENCABEZADO
        // ========================================
        $sql .= "-- ========================================\n";
        $sql .= "-- RESPALDO DE FOLIOS FISCALES\n";
        $sql .= "-- FONDA 4 VIENTOS\n";
        $sql .= "-- AGUILAR NUÑEZ ANA LILIA\n";
        $sql .= "-- R.F.C: AUNA730803EL8\n";
        $sql .= "-- ========================================\n";
        $sql .= "-- Fecha generación: " . date('d/m/Y H:i:s') . "\n";
        
        if (!empty($resumen['filtros']['fecha_inicio'])) {
            $sql .= "-- Período: " . $resumen['filtros']['fecha_inicio'];
            if (!empty($resumen['filtros']['fecha_fin'])) {
                $sql .= " al " . $resumen['filtros']['fecha_fin'];
            }
            $sql .= "\n";
        }
        
        if (!empty($resumen['filtros']['folio_inicio']) || !empty($resumen['filtros']['folio_fin'])) {
            $sql .= "-- Folios: ";
            if (!empty($resumen['filtros']['folio_inicio'])) {
                $sql .= "del " . $resumen['filtros']['folio_inicio'];
            }
            if (!empty($resumen['filtros']['folio_fin'])) {
                $sql .= " al " . $resumen['filtros']['folio_fin'];
            }
            $sql .= "\n";
        }
        
        $sql .= "-- ========================================\n";
        $sql .= "-- RESUMEN DEL RESPALDO\n";
        $sql .= "-- ========================================\n";
        $sql .= "-- Total de reportes: " . $resumen['total_reportes'] . "\n";
        $sql .= "-- Facturas: " . $resumen['facturas']['cantidad'] . " (\$" . number_format($resumen['facturas']['total'], 2) . ")\n";
        $sql .= "-- Tickets Electrónicos: " . $resumen['tickets_electronicos']['cantidad'] . " (\$" . number_format($resumen['tickets_electronicos']['total'], 2) . ")\n";
        $sql .= "-- Total General: \$" . number_format($resumen['total_general'], 2) . "\n";
        $sql .= "-- ========================================\n\n";
        
        // ========================================
        // CREAR TABLAS DE RESPALDO
        // ========================================
        $sql .= "-- Crear tablas de respaldo si no existen\n\n";
        
        $sql .= "CREATE TABLE IF NOT EXISTS `reportes_respaldo` (\n";
        $sql .= "  `id` int(11) NOT NULL,\n";
        $sql .= "  `numero` int(11) NOT NULL,\n";
        $sql .= "  `tipo` enum('ticket','factura','ticket electronico') NOT NULL,\n";
        $sql .= "  `forma_pago` enum('efectivo','tarjeta','mixto') NOT NULL,\n";
        $sql .= "  `total_pedido` DECIMAL(10,2) NOT NULL COMMENT 'Soporta centavos desde 2026-01-25',\n";
        $sql .= "  `estado` enum('emitido','cancelado') NOT NULL DEFAULT 'emitido',\n";
        $sql .= "  `fecha` datetime NOT NULL DEFAULT current_timestamp(),\n";
        $sql .= "  `id_pedido` int(11) NOT NULL,\n";
        $sql .= "  `ultimo_cambio` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),\n";
        $sql .= "  `fecha_respaldo` datetime NOT NULL DEFAULT current_timestamp(),\n";
        $sql .= "  PRIMARY KEY (`id`)\n";
        $sql .= ") ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;\n\n";
        
        $sql .= "CREATE TABLE IF NOT EXISTS `pedidos_respaldo` (\n";
        $sql .= "  `id` int(11) NOT NULL,\n";
        $sql .= "  `id_resumen` int(11) NOT NULL,\n";
        $sql .= "  `id_mesero` int(11) NOT NULL,\n";
        $sql .= "  `id_mesa` int(11) NOT NULL,\n";
        $sql .= "  `fecha` datetime NOT NULL DEFAULT current_timestamp(),\n";
        $sql .= "  `estado` int(11) NOT NULL,\n";
        $sql .= "  `ultima_modif` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),\n";
        $sql .= "  `metodo` enum('efectivo','tarjeta','mixto') DEFAULT NULL,\n";
        $sql .= "  `comprobante` enum('ticket','factura','ticket electronico') DEFAULT NULL,\n";
        $sql .= "  `nombre_mesero` varchar(60) DEFAULT NULL,\n";
        $sql .= "  `fecha_respaldo` datetime NOT NULL DEFAULT current_timestamp(),\n";
        $sql .= "  PRIMARY KEY (`id`)\n";
        $sql .= ") ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;\n\n";
        
        $sql .= "CREATE TABLE IF NOT EXISTS `item_pedidos_respaldo` (\n";
        $sql .= "  `id` int(11) NOT NULL,\n";
        $sql .= "  `id_pedido` int(11) NOT NULL,\n";
        $sql .= "  `id_platillo` int(11) NOT NULL,\n";
        $sql .= "  `cantidad` float NOT NULL,\n";
        $sql .= "  `nota` varchar(255) NOT NULL,\n";
        $sql .= "  `preparado` int(11) NOT NULL DEFAULT 0,\n";
        $sql .= "  `pagado` int(11) NOT NULL,\n";
        $sql .= "  `nombre_platillo` varchar(255) DEFAULT NULL,\n";
        $sql .= "  `precio_platillo` DECIMAL(10,2) DEFAULT NULL COMMENT 'Soporta centavos desde 2026-01-25',\n";
        $sql .= "  `fecha_respaldo` datetime NOT NULL DEFAULT current_timestamp(),\n";
        $sql .= "  PRIMARY KEY (`id`)\n";
        $sql .= ") ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;\n\n";
        
        // ========================================
        // INSERTS - REPORTES
        // ========================================
        $sql .= "-- ========================================\n";
        $sql .= "-- REPORTES FISCALES (" . count($reportes) . " registros)\n";
        $sql .= "-- ========================================\n\n";
        
        $db = \Config\Database::connect();
        foreach ($reportes as $reporte) {
            $sql .= "INSERT INTO `reportes_respaldo` ";
            $sql .= "(`id`, `numero`, `tipo`, `forma_pago`, `total_pedido`, `estado`, `fecha`, `id_pedido`, `ultimo_cambio`, `fecha_respaldo`) VALUES ";
            $sql .= "(";
            $sql .= $reporte['id'] . ", ";
            $sql .= $reporte['folio'] . ", ";
            $sql .= "'" . $reporte['tipo'] . "', ";
            $sql .= "'" . $reporte['forma_pago'] . "', ";
            $sql .= $reporte['total_pedido'] . ", ";
            $sql .= "'" . $reporte['estado'] . "', ";
            $sql .= "'" . $reporte['fecha'] . "', ";
            $sql .= $reporte['id_pedido'] . ", ";
            $sql .= "'" . $reporte['ultimo_cambio'] . "', ";
            $sql .= "'" . date('Y-m-d H:i:s') . "'";
            $sql .= ");\n";
        }
        
        $sql .= "\n";
        
        // ========================================
        // INSERTS - PEDIDOS
        // ========================================
        $sql .= "-- ========================================\n";
        $sql .= "-- PEDIDOS RELACIONADOS (" . count($pedidos) . " registros)\n";
        $sql .= "-- ========================================\n\n";
        
        foreach ($pedidos as $pedido) {
            $sql .= "INSERT INTO `pedidos_respaldo` ";
            $sql .= "(`id`, `id_resumen`, `id_mesero`, `id_mesa`, `fecha`, `estado`, `ultima_modif`, `metodo`, `comprobante`, `nombre_mesero`, `fecha_respaldo`) VALUES ";
            $sql .= "(";
            $sql .= $pedido['id'] . ", ";
            $sql .= $pedido['id_resumen'] . ", ";
            $sql .= $pedido['id_mesero'] . ", ";
            $sql .= $pedido['id_mesa'] . ", ";
            $sql .= "'" . $pedido['fecha'] . "', ";
            $sql .= $pedido['estado'] . ", ";
            $sql .= "'" . $pedido['ultima_modif'] . "', ";
            $sql .= ($pedido['metodo'] ? "'" . $pedido['metodo'] . "'" : "NULL") . ", ";
            $sql .= ($pedido['comprobante'] ? "'" . $pedido['comprobante'] . "'" : "NULL") . ", ";
            $mesero = $pedido['nombre_mesero'] . ' ' . $pedido['apellido_mesero'];
            $sql .= "'" . $db->escapeString($mesero) . "', ";
            $sql .= "'" . date('Y-m-d H:i:s') . "'";
            $sql .= ");\n";
        }
        
        $sql .= "\n";
        
        // ========================================
        // INSERTS - ITEMS
        // ========================================
        $sql .= "-- ========================================\n";
        $sql .= "-- ITEMS DE PEDIDOS (" . count($items) . " registros)\n";
        $sql .= "-- ========================================\n\n";
        
        foreach ($items as $item) {
            $sql .= "INSERT INTO `item_pedidos_respaldo` ";
            $sql .= "(`id`, `id_pedido`, `id_platillo`, `cantidad`, `nota`, `preparado`, `pagado`, `nombre_platillo`, `precio_platillo`, `fecha_respaldo`) VALUES ";
            $sql .= "(";
            $sql .= $item['id'] . ", ";
            $sql .= $item['id_pedido'] . ", ";
            $sql .= $item['id_platillo'] . ", ";
            $sql .= $item['cantidad'] . ", ";
            $sql .= "'" . $db->escapeString($item['nota']) . "', ";
            $sql .= $item['preparado'] . ", ";
            $sql .= $item['pagado'] . ", ";
            $sql .= "'" . $db->escapeString($item['nombre_platillo']) . "', ";
            $sql .= ($item['precio_platillo'] ?? 0) . ", ";
            $sql .= "'" . date('Y-m-d H:i:s') . "'";
            $sql .= ");\n";
        }
        
        $sql .= "\n-- FIN DEL RESPALDO\n";
        
        // ========================================
        // GENERAR DESCARGA
        // ========================================
        $filename = 'respaldo_folios_' . date('Ymd_His') . '.sql';
        
        return $this->response
            ->setHeader('Content-Type', 'application/sql')
            ->setHeader('Content-Disposition', 'attachment; filename="' . $filename . '"')
            ->setBody($sql);
    }
    
    /**
     * Genera archivo Excel (CSV) con múltiples secciones
     */
    private function generarRespaldoExcel($reportes, $pedidos, $items, $resumen)
    {
        // Crear contenido CSV (compatible con Excel)
        $csv = "";
        
        // ========================================
        // SECCIÓN 1: INFORMACIÓN DEL RESPALDO
        // ========================================
        $csv .= "RESPALDO DE FOLIOS FISCALES\n";
        $csv .= "FONDA 4 VIENTOS\n";
        $csv .= "R.F.C: AUNA730803EL8\n";
        $csv .= "\n";
        $csv .= "Fecha de generación:," . date('d/m/Y H:i:s') . "\n";
        
        if (!empty($resumen['filtros']['fecha_inicio'])) {
            $csv .= "Período:,Del " . date('d/m/Y', strtotime($resumen['filtros']['fecha_inicio']));
            if (!empty($resumen['filtros']['fecha_fin'])) {
                $csv .= " al " . date('d/m/Y', strtotime($resumen['filtros']['fecha_fin']));
            }
            $csv .= "\n";
        }
        
        $csv .= "\n";
        $csv .= "RESUMEN\n";
        $csv .= "Total de reportes:," . $resumen['total_reportes'] . "\n";
        $csv .= "Facturas:," . $resumen['facturas']['cantidad'] . ",\$" . number_format($resumen['facturas']['total'], 2) . "\n";
        $csv .= "Tickets Electrónicos:," . $resumen['tickets_electronicos']['cantidad'] . ",\$" . number_format($resumen['tickets_electronicos']['total'], 2) . "\n";
        $csv .= "Total General:,,\$" . number_format($resumen['total_general'], 2) . "\n";
        $csv .= "\n\n";
        
        // ========================================
        // SECCIÓN 2: REPORTES
        // ========================================
        $csv .= "REPORTES FISCALES\n";
        $csv .= "ID,Folio,Tipo,Forma Pago,Total,Estado,Fecha,ID Pedido\n";
        
        foreach ($reportes as $reporte) {
            $csv .= $reporte['id'] . ",";
            $csv .= $reporte['folio'] . ",";
            $csv .= $reporte['tipo'] . ",";
            $csv .= $reporte['forma_pago'] . ",";
            $csv .= $reporte['total_pedido'] . ",";
            $csv .= $reporte['estado'] . ",";
            $csv .= $reporte['fecha'] . ",";
            $csv .= $reporte['id_pedido'] . "\n";
        }
        
        $csv .= "\n\n";
        
        // ========================================
        // SECCIÓN 3: PEDIDOS
        // ========================================
        $csv .= "PEDIDOS RELACIONADOS\n";
        $csv .= "ID,ID Resumen,ID Mesero,ID Mesa,Fecha,Estado,Método,Comprobante,Mesero\n";
        
        foreach ($pedidos as $pedido) {
            $csv .= $pedido['id'] . ",";
            $csv .= $pedido['id_resumen'] . ",";
            $csv .= $pedido['id_mesero'] . ",";
            $csv .= $pedido['id_mesa'] . ",";
            $csv .= $pedido['fecha'] . ",";
            $csv .= $pedido['estado'] . ",";
            $csv .= ($pedido['metodo'] ?? '') . ",";
            $csv .= ($pedido['comprobante'] ?? '') . ",";
            $csv .= '"' . $pedido['nombre_mesero'] . ' ' . $pedido['apellido_mesero'] . '"' . "\n";
        }
        
        $csv .= "\n\n";
        
        // ========================================
        // SECCIÓN 4: ITEMS
        // ========================================
        $csv .= "ITEMS DE PEDIDOS\n";
        $csv .= "ID,ID Pedido,ID Platillo,Cantidad,Platillo,Precio,Nota,Preparado,Pagado\n";
        
        foreach ($items as $item) {
            $csv .= $item['id'] . ",";
            $csv .= $item['id_pedido'] . ",";
            $csv .= $item['id_platillo'] . ",";
            $csv .= $item['cantidad'] . ",";
            $csv .= '"' . $item['nombre_platillo'] . '",';
            $csv .= ($item['precio_platillo'] ?? 0) . ",";
            $csv .= '"' . str_replace('"', '""', $item['nota']) . '",';
            $csv .= $item['preparado'] . ",";
            $csv .= $item['pagado'] . "\n";
        }
        
        // ========================================
        // GENERAR DESCARGA
        // ========================================
        $filename = 'respaldo_folios_' . date('Ymd_His') . '.csv';
        
        // Agregar BOM UTF-8 para Excel
        $csv = "\xEF\xBB\xBF" . $csv;
        
        return $this->response
            ->setHeader('Content-Type', 'text/csv; charset=UTF-8')
            ->setHeader('Content-Disposition', 'attachment; filename="' . $filename . '"')
            ->setBody($csv);
    }

    /**
     * Obtiene un resumen de qué se eliminará al finalizar el día (sin ejecutar eliminación)
     * GET /api/reportes/resumenFinalizarDia?fecha=YYYY-MM-DD
     */
    public function resumenFinalizarDia()
    {
        try {
            $fecha = $this->request->getVar('fecha') ?: date('Y-m-d');
            
            // Validar formato de fecha
            if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $fecha)) {
                return $this->fail('Formato de fecha inválido. Use YYYY-MM-DD');
            }
            
            // No permitir fechas futuras
            if ($fecha > date('Y-m-d')) {
                return $this->fail('No se puede finalizar un día futuro');
            }
            
            $db = \Config\Database::connect();
            
            // ========================================
            // REPORTES FISCALES A CONSERVAR
            // ========================================
            $reportesFiscales = $db->query("
                SELECT COUNT(*) as total, SUM(total_pedido) as monto_total
                FROM reportes
                WHERE DATE(fecha) = ?
                  AND tipo IN ('factura', 'ticket electronico')
                  AND numero > 0
                  AND estado = 'emitido'
            ", [$fecha])->getRowArray();
            
            // ========================================
            // PEDIDOS CON REPORTES FISCALES (CONSERVAR)
            // ========================================
            $pedidosFiscales = $db->query("
                SELECT COUNT(DISTINCT p.id) as total
                FROM pedidos p
                INNER JOIN reportes r ON r.id_pedido = p.id
                WHERE DATE(p.fecha) = ?
                  AND r.tipo IN ('factura', 'ticket electronico')
                  AND r.numero > 0
                  AND r.estado = 'emitido'
            ", [$fecha])->getRowArray();
            
            // ========================================
            // PEDIDOS SIN REPORTES FISCALES (ELIMINAR)
            // ========================================
            $pedidosSinFiscales = $db->query("
                SELECT COUNT(DISTINCT p.id) as total
                FROM pedidos p
                LEFT JOIN reportes r ON r.id_pedido = p.id 
                  AND r.tipo IN ('factura', 'ticket electronico')
                  AND r.numero > 0
                WHERE DATE(p.fecha) = ?
                  AND r.id IS NULL
            ", [$fecha])->getRowArray();
            
            // ========================================
            // ITEMS DE PEDIDOS SIN FISCALES (ELIMINAR)
            // ========================================
            $itemsEliminar = $db->query("
                SELECT COUNT(*) as total
                FROM item_pedidos ip
                INNER JOIN pedidos p ON p.id = ip.id_pedido
                LEFT JOIN reportes r ON r.id_pedido = p.id 
                  AND r.tipo IN ('factura', 'ticket electronico')
                  AND r.numero > 0
                WHERE DATE(p.fecha) = ?
                  AND r.id IS NULL
            ", [$fecha])->getRowArray();
            
            // ========================================
            // TICKETS NORMALES (ELIMINAR)
            // ========================================
            $ticketsNormales = $db->query("
                SELECT COUNT(*) as total
                FROM reportes
                WHERE DATE(fecha) = ?
                  AND tipo = 'ticket'
                  AND numero = 0
            ", [$fecha])->getRowArray();
            
            // ========================================
            // RESÚMENES CERRADOS (ELIMINAR)
            // ========================================
            $resumenesCerrados = $db->query("
                SELECT COUNT(*) as total
                FROM resumenes
                WHERE estado = 0
            ", [])->getRowArray();
            
            // ========================================
            // FOLIO FISCAL ACTUAL
            // ========================================
            $folioActual = $db->query("
                SELECT numero
                FROM consecutivos
                WHERE tipo = 'num_consecutivo'
            ", [])->getRowArray();
            
            return $this->respond([
                'success' => true,
                'data' => [
                    'fecha' => $fecha,
                    'eliminados' => [
                        'pedidos' => (int)$pedidosSinFiscales['total'],
                        'items' => (int)$itemsEliminar['total'],
                        'tickets_normales' => (int)$ticketsNormales['total'],
                        'resumenes' => (int)$resumenesCerrados['total']
                    ],
                    'conservados' => [
                        'reportes_fiscales' => (int)$reportesFiscales['total'],
                        'monto_fiscal' => (float)$reportesFiscales['monto_total'],
                        'pedidos_fiscales' => (int)$pedidosFiscales['total'],
                        'folio_actual' => (int)$folioActual['numero']
                    ]
                ]
            ]);
            
        } catch (\Exception $e) {
            log_message('error', 'Error en resumenFinalizarDia: ' . $e->getMessage());
            return $this->fail('Error al obtener resumen: ' . $e->getMessage());
        }
    }

    /**
     * Finaliza el día: elimina pedidos sin fiscales y conserva consecutivo
     * POST /api/reportes/finalizarDia
     * Body: { "fecha": "YYYY-MM-DD", "confirmacion": "CONFIRMAR" }
     */
    public function finalizarDia()
    {
        try {
            $data = $this->request->getJSON(true);
            
            if (empty($data)) {
                return $this->fail('No se recibieron datos');
            }
            
            // Validar confirmación
            if (!isset($data['confirmacion']) || $data['confirmacion'] !== 'CONFIRMAR') {
                return $this->fail('Confirmación inválida. Debe escribir CONFIRMAR');
            }
            
            $fecha = $data['fecha'] ?? date('Y-m-d');
            
            // Validar formato de fecha
            if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $fecha)) {
                return $this->fail('Formato de fecha inválido. Use YYYY-MM-DD');
            }
            
            // No permitir fechas futuras
            if ($fecha > date('Y-m-d')) {
                return $this->fail('No se puede finalizar un día futuro');
            }
            
            // Verificar que no hay pedidos activos (estado=1)
            $db = \Config\Database::connect();
            $pedidosActivos = $db->query("
                SELECT COUNT(*) as total
                FROM pedidos
                WHERE DATE(fecha) = ? AND estado = 1
            ", [$fecha])->getRowArray();
            
            if ($pedidosActivos['total'] > 0) {
                return $this->fail('Hay ' . $pedidosActivos['total'] . ' pedidos activos. Cierre todas las cuentas antes de finalizar el día');
            }
            
            // ========================================
            // PASO 1: GENERAR RESPALDO AUTOMÁTICO
            // ========================================
            log_message('info', 'Iniciando respaldo automático para finalizar día: ' . $fecha);
            
            // Generar respaldo SQL directamente (sin simular request)
            try {
                // Query de reportes fiscales del día
                $respaldoReportes = $db->query("
                    SELECT r.*, p.id_mesero, p.id_mesa, p.comprobante
                    FROM reportes r
                    LEFT JOIN pedidos p ON r.id_pedido = p.id
                    WHERE DATE(r.fecha) = ?
                      AND r.tipo IN ('factura', 'ticket electronico')
                      AND r.numero > 0
                      AND r.estado = 'emitido'
                    ORDER BY r.numero ASC
                ", [$fecha])->getResultArray();
                
                $respaldoPedidos = [];
                $respaldoItems = [];
                
                if (!empty($respaldoReportes)) {
                    $idsReportes = array_column($respaldoReportes, 'id');
                    
                    // Pedidos asociados
                    $respaldoPedidos = $db->query("
                        SELECT DISTINCT p.*
                        FROM pedidos p
                        INNER JOIN reportes r ON r.id_pedido = p.id
                        WHERE r.id IN (" . implode(',', $idsReportes) . ")
                    ")->getResultArray();
                    
                    if (!empty($respaldoPedidos)) {
                        $idsPedidos = array_column($respaldoPedidos, 'id');
                        
                        // Items de esos pedidos
                        $respaldoItems = $db->query("
                            SELECT i.*
                            FROM item_pedidos i
                            WHERE i.id_pedido IN (" . implode(',', $idsPedidos) . ")
                        ")->getResultArray();
                    }
                }
                
                // Calcular resumen por tipo
                $facturas = array_filter($respaldoReportes, fn($r) => $r['tipo'] === 'factura');
                $ticketsElectronicos = array_filter($respaldoReportes, fn($r) => $r['tipo'] === 'ticket electronico');
                
                $totalFacturas = array_sum(array_column($facturas, 'total_pedido'));
                $totalTickets = array_sum(array_column($ticketsElectronicos, 'total_pedido'));
                
                // Generar SQL usando el método privado
                $respaldoSQL = $this->generarRespaldoSQL(
                    $respaldoReportes,
                    $respaldoPedidos,
                    $respaldoItems,
                    [
                        'filtros' => ['fecha_inicio' => $fecha, 'fecha_fin' => $fecha],
                        'total_reportes' => count($respaldoReportes),
                        'total_pedidos' => count($respaldoPedidos),
                        'total_items' => count($respaldoItems),
                        'facturas' => [
                            'cantidad' => count($facturas),
                            'total' => $totalFacturas
                        ],
                        'tickets_electronicos' => [
                            'cantidad' => count($ticketsElectronicos),
                            'total' => $totalTickets
                        ],
                        'total_general' => $totalFacturas + $totalTickets
                    ]
                );
                
                // Guardar respaldo en writable/respaldos/
                $respaldosDir = WRITEPATH . 'respaldos/';
                if (!is_dir($respaldosDir)) {
                    mkdir($respaldosDir, 0755, true);
                }
                
                $respaldoFilename = 'finalizardia_' . str_replace('-', '', $fecha) . '.sql';
                $respaldoPath = $respaldosDir . $respaldoFilename;
                file_put_contents($respaldoPath, $respaldoSQL);
                
                log_message('info', 'Respaldo guardado: ' . $respaldoPath);
                
            } catch (\Exception $e) {
                log_message('error', 'Error al generar respaldo: ' . $e->getMessage());
                return $this->fail('No se pudo generar el respaldo. Operación abortada: ' . $e->getMessage());
            }
            
            // ========================================
            // PASO 2: OBTENER IDs A ELIMINAR
            // ========================================
            
            // IDs de pedidos sin reportes fiscales
            $pedidosSinFiscales = $db->query("
                SELECT p.id
                FROM pedidos p
                LEFT JOIN reportes r ON r.id_pedido = p.id 
                  AND r.tipo IN ('factura', 'ticket electronico')
                  AND r.numero > 0
                WHERE DATE(p.fecha) = ?
                  AND r.id IS NULL
            ", [$fecha])->getResultArray();
            
            $idsPedidosSinFiscales = array_column($pedidosSinFiscales, 'id');
            
            // ========================================
            // PASO 3: ELIMINAR EN TRANSACCIÓN
            // ========================================
            
            $db->transStart();
            
            $contadores = [
                'items' => 0,
                'tickets_normales' => 0,
                'pedidos' => 0,
                'resumenes' => 0
            ];
            
            try {
                // 1. Eliminar item_pedidos de pedidos sin fiscales
                if (!empty($idsPedidosSinFiscales)) {
                    $placeholders = implode(',', array_fill(0, count($idsPedidosSinFiscales), '?'));
                    $result = $db->query("
                        DELETE FROM item_pedidos
                        WHERE id_pedido IN ($placeholders)
                    ", $idsPedidosSinFiscales);
                    $contadores['items'] = $db->affectedRows();
                }
                
                // 2. Eliminar reportes tipo ticket normal (numero=0)
                $result = $db->query("
                    DELETE FROM reportes
                    WHERE DATE(fecha) = ?
                      AND tipo = 'ticket'
                      AND numero = 0
                ", [$fecha]);
                $contadores['tickets_normales'] = $db->affectedRows();
                
                // 3. Eliminar pedidos sin fiscales
                if (!empty($idsPedidosSinFiscales)) {
                    $placeholders = implode(',', array_fill(0, count($idsPedidosSinFiscales), '?'));
                    $result = $db->query("
                        DELETE FROM pedidos
                        WHERE id IN ($placeholders)
                    ", $idsPedidosSinFiscales);
                    $contadores['pedidos'] = $db->affectedRows();
                }
                
                // 4. Eliminar resumenes cerrados
                $result = $db->query("DELETE FROM resumenes WHERE estado = 0");
                $contadores['resumenes'] = $db->affectedRows();
                
                // 5. Reiniciar AUTO_INCREMENT de pedidos
                $db->query("ALTER TABLE pedidos AUTO_INCREMENT = 1");
                
                $db->transComplete();
                
                if ($db->transStatus() === false) {
                    throw new \Exception('Error en la transacción de eliminación');
                }
                
            } catch (\Exception $e) {
                $db->transRollback();
                log_message('error', 'Error al eliminar registros: ' . $e->getMessage());
                return $this->fail('Error al eliminar registros: ' . $e->getMessage());
            }
            
            // ========================================
            // PASO 4: OBTENER DATOS CONSERVADOS
            // ========================================
            
            $reportesFiscales = $db->query("
                SELECT COUNT(*) as total, SUM(total_pedido) as monto_total
                FROM reportes
                WHERE DATE(fecha) = ?
                  AND tipo IN ('factura', 'ticket electronico')
                  AND numero > 0
                  AND estado = 'emitido'
            ", [$fecha])->getRowArray();
            
            $pedidosFiscales = $db->query("
                SELECT COUNT(DISTINCT p.id) as total
                FROM pedidos p
                INNER JOIN reportes r ON r.id_pedido = p.id
                WHERE DATE(p.fecha) = ?
                  AND r.tipo IN ('factura', 'ticket electronico')
                  AND r.numero > 0
            ", [$fecha])->getRowArray();
            
            $folioActual = $db->query("
                SELECT numero FROM consecutivos WHERE tipo = 'num_consecutivo'
            ")->getRowArray();
            
            // ========================================
            // PASO 5: LOG Y RESPUESTA
            // ========================================
            
            log_message('info', 'Día finalizado: ' . $fecha . ' | Eliminados: ' . json_encode($contadores));
            
            return $this->respond([
                'success' => true,
                'data' => [
                    'mensaje' => 'Día finalizado correctamente',
                    'fecha' => $fecha,
                    'respaldo' => $respaldoFilename,
                    'respaldo_path' => $respaldoPath,
                    'eliminados' => $contadores,
                    'conservados' => [
                        'reportes_fiscales' => (int)$reportesFiscales['total'],
                        'monto_fiscal' => (float)$reportesFiscales['monto_total'],
                        'pedidos_fiscales' => (int)$pedidosFiscales['total'],
                        'folio_actual' => (int)$folioActual['numero']
                    ]
                ]
            ]);
            
        } catch (\Exception $e) {
            log_message('error', 'Error en finalizarDia: ' . $e->getMessage());
            return $this->fail('Error al finalizar día: ' . $e->getMessage());
        }
    }
}