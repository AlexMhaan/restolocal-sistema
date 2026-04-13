<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Corte Historia - Folios Consecutivos</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Courier New', Courier, monospace;
            font-size: 12px;
            background: white;
            color: #111;
            padding: 20px;
        }

        /* ---- Botones no imprimibles ---- */
        .btn-container {
            text-align: center;
            margin-bottom: 20px;
        }
        .btn {
            display: inline-block;
            padding: 10px 20px;
            margin: 0 5px;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            font-size: 14px;
            color: white;
        }
        .btn-primary { background: #007bff; }
        .btn-secondary { background: #6c757d; }

        /* ---- Encabezado del reporte ---- */
        .reporte-header {
            text-align: center;
            margin-bottom: 16px;
            border-bottom: 2px solid #333;
            padding-bottom: 10px;
        }
        .reporte-header h1 {
            font-size: 16px;
            letter-spacing: 1px;
            text-transform: uppercase;
        }
        .reporte-header p {
            font-size: 11px;
            color: #555;
            margin-top: 4px;
        }

        /* ---- SecciÃ³n de filtros aplicados ---- */
        .filtros {
            font-size: 11px;
            margin-bottom: 12px;
            border: 1px solid #bbb;
            padding: 6px 10px;
            background: #f7f7f7;
        }
        .filtros p { margin: 2px 0; }

        /* ---- Tarjetas de resumen ---- */
        .resumen {
            display: flex;
            gap: 8px;
            margin-bottom: 20px;
            flex-wrap: wrap;
        }
        .resumen-card {
            flex: 1;
            min-width: 100px;
            border: 1px solid #bbb;
            padding: 8px;
            text-align: center;
            font-size: 11px;
        }
        .resumen-card .label {
            text-transform: uppercase;
            font-size: 10px;
            margin-bottom: 4px;
            font-weight: bold;
        }
        .resumen-card .valor { font-size: 13px; font-weight: bold; }
        .resumen-card.total-card { background: #222; color: #fff; border-color: #222; }

        /* ---- Separador entre secciÃ³n de resumen y tickets ---- */
        .separador-principal {
            border: none;
            border-top: 2px solid #333;
            margin: 10px 0 20px;
        }

        /* ---- Cada ticket individual ---- */
        .ticket-wrapper {
            margin-bottom: 24px;
            page-break-inside: avoid;
        }

        .ticket-block {
            width: 340px;
            margin: 0 auto;
            font-family: 'Courier New', Courier, monospace;
            font-size: 12px;
            line-height: 1.3;
        }

        .ticket-block pre {
            margin: 0;
            white-space: pre;
            font-family: 'Courier New', Courier, monospace;
            font-size: 12px;
            letter-spacing: 0;
        }

        .ticket-sep {
            border: none;
            border-top: 1px dashed #aaa;
            margin: 12px auto;
            width: 340px;
        }

        /* ---- Print ---- */
        @media print {
            .no-print { display: none !important; }
            body { padding: 5px; }
            @page { margin: 1cm; }
        }
    </style>
</head>
<body>

    <div class="btn-container no-print">
        <button class="btn btn-primary" onclick="window.print()">ðŸ–¨ï¸ Imprimir</button>
        <button class="btn btn-secondary" onclick="window.close()">âŒ Cerrar</button>
    </div>

    <!-- Encabezado del reporte -->
    <div class="reporte-header">
        <h1>Corte Historia â€” Folios Consecutivos</h1>
        <p>Generado el <?= $fecha_generacion ?></p>
    </div>

    <!-- Filtros aplicados -->
    <?php if (!empty($filtros_aplicados)): ?>
    <div class="filtros">
        <?php if (!empty($filtros_aplicados['fecha_inicio'])): ?>
            <p><strong>PerÃ­odo:</strong>
                Del <?= date('d/m/Y', strtotime($filtros_aplicados['fecha_inicio'])) ?>
                <?php if (!empty($filtros_aplicados['fecha_fin'])): ?>
                    al <?= date('d/m/Y', strtotime($filtros_aplicados['fecha_fin'])) ?>
                <?php endif; ?>
            </p>
        <?php endif; ?>
        <?php if (!empty($filtros_aplicados['folio_inicio']) || !empty($filtros_aplicados['folio_fin'])): ?>
            <p><strong>Folios:</strong>
                <?= !empty($filtros_aplicados['folio_inicio']) ? 'desde ' . $filtros_aplicados['folio_inicio'] : '' ?>
                <?= !empty($filtros_aplicados['folio_fin']) ? ' hasta ' . $filtros_aplicados['folio_fin'] : '' ?>
            </p>
        <?php endif; ?>
        <?php if (!empty($filtros_aplicados['tipos'])): ?>
            <p><strong>Tipo:</strong> <?= implode(', ', array_map('ucfirst', $filtros_aplicados['tipos'])) ?></p>
        <?php endif; ?>
    </div>
    <?php endif; ?>

    <!-- Resumen general -->
    <div class="resumen">
        <div class="resumen-card">
            <div class="label">Total Folios</div>
            <div class="valor"><?= $resumen['total_folios'] ?></div>
        </div>
        <?php if (empty($filtros_aplicados['tipos']) || in_array('ticket', $filtros_aplicados['tipos'] ?? [])): ?>
        <div class="resumen-card">
            <div class="label">Tickets</div>
            <div class="valor"><?= $resumen['ticket']['cantidad'] ?></div>
            <div>$<?= number_format($resumen['ticket']['total'], 2) ?></div>
        </div>
        <?php endif; ?>
        <?php if (empty($filtros_aplicados['tipos']) || in_array('factura', $filtros_aplicados['tipos'] ?? [])): ?>
        <div class="resumen-card">
            <div class="label">Facturas</div>
            <div class="valor"><?= $resumen['factura']['cantidad'] ?></div>
            <div>$<?= number_format($resumen['factura']['total'], 2) ?></div>
        </div>
        <?php endif; ?>
        <?php if (empty($filtros_aplicados['tipos']) || in_array('ticket electronico', $filtros_aplicados['tipos'] ?? [])): ?>
        <div class="resumen-card">
            <div class="label">T. ElectrÃ³nicos</div>
            <div class="valor"><?= $resumen['ticket electronico']['cantidad'] ?></div>
            <div>$<?= number_format($resumen['ticket electronico']['total'], 2) ?></div>
        </div>
        <?php endif; ?>
        <div class="resumen-card total-card">
            <div class="label">Total General</div>
            <div class="valor">$<?= number_format($resumen['total_general'], 2) ?></div>
        </div>
    </div>

    <hr class="separador-principal">

    <!-- Tickets consecutivos -->
    <?php if (empty($folios)): ?>
        <p style="text-align:center; color:#888;">No se encontraron folios con los filtros seleccionados.</p>
    <?php else: ?>
        <?php foreach ($folios as $folio): ?>
        <div class="ticket-wrapper">
            <div class="ticket-block">
<pre>
FOLIO: <?= str_pad($folio['folio'], 8) ?>  TIPO: <?= strtoupper($folio['tipo']) ?>

FECHA:    <?= date('d/m/Y', strtotime($folio['fecha'])) ?>

MESERO:   <?= strtoupper($folio['mesero'] ?: 'N/A') ?>

MESA:     <?= $folio['id_mesa'] ?: '-' ?>   PEDIDO: <?= $folio['id_pedido'] ?>

----------------------------------------
CANT DESCRIPCION       PRECIO   IMPORTE
----------------------------------------
<?php if (!empty($folio['items'])): ?>
<?php foreach ($folio['items'] as $item):
    $cant = number_format($item['cantidad'], 0);
    $desc = mb_substr(trim($item['descripcion']), 0, 16);
    $precio = number_format($item['precio_unitario'], 2);
    $importe = number_format($item['importe'], 2);
    printf("%4s %-16s %8s %8s\n", $cant, $desc, $precio, $importe);
endforeach; ?>
<?php else: ?>
     (sin detalle de productos)
<?php endif; ?>
----------------------------------------
<?php printf("%4s %-16s %8s %8s\n", '', 'TOTAL $', '', number_format($folio['total'], 2)); ?>

FORMA DE PAGO: <?= strtoupper($folio['forma_pago']) ?>

</pre>
            </div>
        </div>
        <hr class="ticket-sep">
        <?php endforeach; ?>
    <?php endif; ?>

</body>
</html>
