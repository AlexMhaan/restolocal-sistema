<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Ticket #<?php echo $numero_ticket; ?></title>
    <style>
        body {
            font-family: 'Courier New', monospace;
            font-size: 12px;
            margin: 0;
            padding: 10px;
            background: white;
            line-height: 1.2;
        }
        .ticket {
            width: 320px;
            margin: 0 auto;
        }
        pre {
            margin: 0;
            white-space: pre;
            font-family: 'Courier New', monospace;
            font-size: 12px;
            letter-spacing: 0;
            word-spacing: 0;
        }
        @media print {
            body { margin: 0; padding: 5px; }
            .no-print { display: none; }
        }
    </style>
</head>
<body>
    <div class="ticket">
        <pre>         FONDA 4 VIENTOS

AGUILAR NUÑEZ ANA LILIA

PERSONAS FISICAS CON ACTIVIDAD
EMPRESARIAL Y PROFESIONAL

Car. Fed. Mexico-Acapulco km. 107
Puente de Ixtla Mor. C.P. 62660

R.F.C  AUNA730803EL8

Atendido por: <?php echo strtoupper($pedido['nombre_mesero'] ?: ''); ?>

TICKET: <?php echo $numero_ticket; ?>

<?php echo $fecha_formateada; ?>

----------------------------------------
CANT DESCRIPCION       PRECIO   IMPORTE
----------------------------------------
<?php foreach ($items as $item): ?>
<?php 
    // Formatear cada campo individualmente con ancho fijo
    $cantidad_str = number_format($item['cantidad'], 1);
    if (substr($cantidad_str, -2) == '.0') {
        $cantidad_str = substr($cantidad_str, 0, -2);
    }
    
    // Limpiar descripción de caracteres especiales y truncar a 16 caracteres exactos
    $descripcion = trim($item['descripcion']);
    $descripcion = substr($descripcion, 0, 16);
    
    $precio = number_format($item['precio_unitario'], 2);
    $importe = number_format($item['importe'], 2);
    
    // Formatear cada campo con ancho exacto usando sprintf
    // %4s = cantidad (4 chars, alineado derecha)
    // %-16s = descripción (16 chars, alineado izquierda)
    // %8s = precio (8 chars, alineado derecha)  
    // %8s = importe (8 chars, alineado derecha)
    printf("%4s %-16s %8s %8s\n", 
           $cantidad_str, 
           $descripcion, 
           $precio, 
           $importe);
?>
<?php endforeach; ?>
----------------------------------------
<?php 
    // Formatear línea del total usando el mismo patrón
    $total_formateado = number_format($total, 2);
    printf("%4s %-16s %8s %8s\n", '', 'TOTAL$', '', $total_formateado);
?>

<?php echo $total_texto; ?>


<?php if ($esta_pagado): ?>
========================================
            *** PAGADO ***
========================================


<?php else: ?>

========================================
        *** SIN VALOR FISCAL ***
========================================


<?php endif; ?>

Comprobante de operación con el
público en general de acuerdo
a la regla 2.7.1.21 de la
resolución miscelánea fiscal
para <?php echo date('Y'); ?>

Gracias por su preferencia...!!!
</pre>
    </div>
    
    <div class="no-print" style="text-align: center; margin-top: 20px;">
        <button onclick="window.print()">Imprimir</button>
        <button onclick="window.close()">Cerrar</button>
    </div>
</body>
</html>
