<?php

use CodeIgniter\Router\RouteCollection;

/**
 * @var RouteCollection $routes
 */

$routes->resource('api/usuarios');
$routes->resource('api/restaurantes');
$routes->resource('api/platillos');
$routes->resource('api/categorias');
$routes->resource('api/zonas');
$routes->resource('api/elementos');
$routes->get('api/pedidos/ventas', 'Api\Pedidos::ventas');  
$routes->put('api/pedidos/pagar/(:num)', 'Api\Pedidos::procesarPago/$1');
$routes->post('api/pedidos/actualizar_items/(:num)', 'Api\Pedidos::actualizar_items/$1');
$routes->resource('api/pedidos');
// Las rutas de reportes se han movido a su controlador correspondiente

// Rutas para ItemPedidos (API RESTful para item_pedidos)
$routes->delete('api/item_pedidos/(:num)', 'Api\ItemPedidos::delete/$1'); 
$routes->delete('api/item_pedidos/pedido/(:num)', 'Api\ItemPedidos::deleteByPedidoId/$1');
$routes->post('api/item_pedidos', 'Api\ItemPedidos::create');
$routes->get('api/item_pedidos', 'Api\ItemPedidos::index');
$routes->get('api/item_pedidos/(:num)', 'Api\ItemPedidos::show/$1');
$routes->put('api/item_pedidos/(:num)', 'Api\ItemPedidos::update/$1');
$routes->post('api/item_pedidos/lote', 'Api\ItemPedidos::lote');

$routes->resource('api/resumenes');
$routes->resource('api/test');
$routes->get('api/meseros/zona/(:num)', 'Api\Meseros::zona/$1');

// Rutas específicas para reportes (DEBEN ir ANTES del resource)
$routes->put('api/reportes/cancelar/(:num)', 'Api\Reportes::cancelar/$1');
$routes->get('api/reportes/vendidosPorFecha', 'Api\Reportes::vendidosPorFecha');
$routes->get('api/reportes/detalle/(:num)', 'Api\Reportes::obtenerDetalleReporte/$1');
$routes->get('api/reportes/buscarTicket', 'Api\Reportes::buscarPorNumeroTicket');
$routes->get('api/reportes/buscarPorPedido', 'Api\Reportes::buscarPorPedido');
$routes->get('api/reportes/conDetallesPedido', 'Api\Reportes::conDetallesPedido');
$routes->get('api/reportes/corteHistoria', 'Api\Reportes::corteHistoria'); // Nueva ruta para Corte Historia
$routes->get('api/reportes/corteX', 'Api\Reportes::corteX'); // Corte X - Totales del día en tiempo real
$routes->get('api/reportes/generarRespaldo', 'Api\Reportes::generarRespaldo'); // Nueva ruta para Respaldo
$routes->get('api/reportes/exportarExcel', 'Api\Reportes::exportarExcel'); // Exportar a Excel (facturas/tickets)
$routes->get('api/reportes/resumenFinalizarDia', 'Api\Reportes::resumenFinalizarDia'); // Preview Finalizar Día
$routes->post('api/reportes/finalizarDia', 'Api\Reportes::finalizarDia'); // Finalizar Día
$routes->get('api/reportes/preticket/(:num)', 'Api\Reportes::preticket/$1'); // Pre-ticket sin marca de pagado
$routes->get('api/reportes/ticket/(:num)', 'Api\Reportes::generarTicket/$1'); // Nueva ruta para generar tickets
$routes->resource('api/reportes'); // Resource al final para no sobrescribir rutas específicas

// Login functions
$routes->get('api/currentUserRole', 'Api::currentUserRole');
$routes->post('api/login', 'Api::login');
$routes->get('api/logout', 'Api::logout');

// Basic functions
$routes->post('api/getMySession', 'Api::getMySession');
$routes->post('api/upload', 'Api::upload');
$routes->get('api/perfil', 'Api\Users::perfil');
$routes->post('api/savePass', 'Api\Users::savePass');

// Pages functions
$routes->get('/', 'Pages::view/login');
$routes->get('/fileView/(:any)/(:any)', 'Pages::fileView/$1/$2');
$routes->get('(:any)', 'Pages::view/$1');
