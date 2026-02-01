<?php



namespace App\Controllers\Api;

use CodeIgniter\RESTful\ResourceController;



class Test extends ResourceController

{

  protected $modelName = 'App\Models\ItemPedidosModel';

  protected $format    = 'json';

  

  function __construct()

  {

    $this->session = \Config\Services::session();

    $this->session->start();

  }

  public function test()
  {
    return $this->respond(['ok' => 'ok'], 200);
  }
  

public function index()
{
    $data = $this->request->getVar();
    $items = $this->getItemsFiltrados($data);
    return $this->respond($items);
}

private function getItemsFiltrados($data)
{
    $builder = $this->model
        ->select("item_pedidos.*, platillos.nombre as p_name, platillos.precio as precio_unitario, 
                COALESCE(usuarios.nombre, 'CAJA') as mesero_nombre, 
                COALESCE(elementos.nombre, 'CAJA') as mesa_nombre, 
                pedidos.fecha, pedidos.estado as pedido_estado, pedidos.metodo, pedidos.comprobante")
        ->join("platillos", "platillos.id = item_pedidos.id_platillo", "left")
        ->join("pedidos", "pedidos.id = item_pedidos.id_pedido", "left")
        ->join("usuarios", "usuarios.id = pedidos.id_mesero", "left")
        ->join("elementos", "elementos.id = pedidos.id_mesa", "left");

    // Filtro por fecha
    if (isset($data["fecha"])) {
        if ($data["fecha"] === "hoy") {
            $builder->where('DATE(pedidos.fecha)', date('Y-m-d'));
        } else {
            // Si se proporciona una fecha específica en formato YYYY-MM-DD
            $builder->where('DATE(pedidos.fecha)', $data["fecha"]);
        }
    }
  
    if (isset($data["active"])) {
        $builder->where("active", $data["active"]);
    }

    if (isset($data["preparado"])) {
        $builder->where("preparado <=", 2);

        // Filtro dinámico por cocina o barra
        if (isset($data["tipo"])) {
            if ($data["tipo"] === "cocina") {
                $builder->where("platillos.cocina", 1);
            } elseif ($data["tipo"] === "barra") {
                $builder->where("platillos.barra", 1);
            }
        } else {
            // Por defecto, asumimos cocina si no se pasa "tipo"
            // $builder->where("platillos.cocina", 1);
        }
    }

    if (isset($data["categoria"])) {
        $builder->where("categoria", $data["categoria"]);
    }

    // Filtro por estado de pedido (para la vista de caja)
    if (isset($data["pedidoEstado"])) {
        $builder->where("pedidos.estado", $data["pedidoEstado"]);
    }

    return $builder->findAll();
}


  

  public function show($id = null)
  {
      $item_pedido = $this->model->where('id_pedido', $id)->findAll();

      if ($item_pedido) {
          return $this->respond($item_pedido);
      } else {
          return $this->failNotFound('Item Pedidos no encontrados para el pedido ' . $id);
      }
  }


  

  public function create()

  {

    $data = $this->request->getVar();

    $response = $this->model->save($data);

    if ($response) {

      $id = $this->model->getInsertID();

      $inserted = $this->model->where('id',$id)->first();

      return $this->respond($inserted);

    }

    return $this->fail('Sorry! not created');

  }
  
  public function lote()
  {
      $items = $this->request->getJSON(true);

      if (!is_array($items)) {
          return $this->fail('Formato de datos inválido');
      }

      foreach ($items as $item) {
          if (!$this->model->insert($item)) {
              return $this->fail('Error al insertar uno de los ítems');
          }
      }

      return $this->respond(['message' => 'Todos los ítems guardados correctamente']);
  }


  
    public function update($id = NULL)
  {
    try {
        if (!$id) {
            return $this->fail('No se proporcionó ID');
        }

        $data = $this->request->getJSON(true);
        
        // Verificar que el registro existe
        $existing = $this->model->find($id);
        if (!$existing) {
            return $this->failNotFound('Registro no encontrado');
        }

        // Preparar datos para actualizar
        $updateData = [
            'id' => $id,
            'id_pedido' => $existing['id_pedido'],
            'id_platillo' => $existing['id_platillo'],
            'preparado' => $data['preparado'],
            'cantidad' => $existing['cantidad']
        ];

        if (!$this->model->save($updateData)) {
            return $this->fail($this->model->errors());
        }

        $updated = $this->model->find($id);
        return $this->respond($updated);
    } catch (\Exception $e) {
        log_message('error', '[Update] Error: ' . $e->getMessage());
        return $this->fail($e->getMessage());
    }

  }
  
  /* 
   * Este método ya no se utiliza - eliminación redirigida a ItemPedidos::delete
   *
  public function delete($id = null)
  {
      if ($id === null) {
          return $this->fail('No ID provided');
      }

      $existing = $this->model->find($id);

      if (!$existing) {
          return $this->failNotFound("Item with ID $id not found");
      }

      if ($this->model->delete($id)) {
          return $this->respondDeleted(['message' => 'Deleted successfully']);
      }

      return $this->fail('Failed to delete');
  }
  */

}