<?php
require_once __DIR__.'/funciones.php';
function rePdf(array $r): string {
    require_once __DIR__.'/../vendor/autoload.php';
    require_once __DIR__.'/../productos/ticket_cache.php';
    $tienda=$r['tienda'];$color=tcColorPapel($tienda['color_principal']);$logo=ticketRutaImagen('assets/marca',$tienda['logo'] ?? null);
    $uri=$logo?ticketImagenCompacta($logo,280,200):'';
    $anchoUnidades=min(22,max(15,(int)ceil(strlen((string)max(array_column($r['productos'],'unidades') ?: [0]))*1.15+3)));
    $anchoNombre=40-$anchoUnidades;
    ob_start();
    ?>
<!doctype html><html lang="es"><head><meta charset="utf-8"><style>
@page{margin:34pt 36pt 43pt}body{font-family:DejaVu Sans,sans-serif;font-size:8.4pt;line-height:1.45;color:#172c3d}h1{font-size:21pt;line-height:1.15;margin:4pt 0 8pt;color:<?= $color ?>}h2{font-size:12pt;color:<?= $color ?>;margin:18pt 0 6pt;page-break-after:avoid}.sobre{font-size:8pt;letter-spacing:1pt;color:#536779}.marca{font-weight:bold;font-size:12pt}.sub{color:#536779;font-size:8pt}p{margin:5pt 0}table{border-collapse:collapse;width:100%;table-layout:fixed;margin:6pt 0 12pt}th{background:#eaf2f4;text-align:left;font-weight:bold}th,td{padding:6pt 5pt;border-bottom:1pt solid #dce6eb;vertical-align:top;word-wrap:break-word}thead{display:table-header-group}thead tr{page-break-after:avoid}tbody tr:first-child{page-break-before:avoid}p.intro{page-break-after:avoid}tr{page-break-inside:avoid}.num{text-align:right}.indicadores td{background:#f2f6f7;border-bottom:2pt solid <?= $color ?>;padding:10pt 8pt}.valor{font-size:13pt;font-weight:bold;display:block;margin-top:4pt}.etiqueta{font-size:8pt;color:#536779}.nota{border-left:3pt solid <?= $color ?>;padding:7pt 10pt;background:#f3f7f7;font-size:8pt}.logo{max-height:45pt;max-width:85pt;float:right}.sin-datos{padding:12pt;background:#f2f6f7}.sin-partir{page-break-inside:avoid}.diario td,.diario th{padding:4pt 5pt}
</style></head><body>
<?php if($uri): ?><img class="logo" src="<?= $uri ?>" alt="Logo"><?php endif; ?>
<div class="sobre">ADMINISTRACIÓN / REPORTE 1.8</div><p class="marca"><?= reEsc($tienda['nombre']) ?></p><h1>Resumen de ventas</h1>
<p><strong><?= $r['desde']->format('d/m/Y') ?> al <?= $r['hasta']->format('d/m/Y') ?></strong> / <?= (int)$r['dias'] ?> días</p>
<p class="sub">Generado: <?= reEsc($r['generado']) ?>. Fechas del servidor de la tienda. Moneda: MXN.</p>
<table class="indicadores"><tr><td><span class="etiqueta">Importe vigente</span><span class="valor"><?= reDinero($r['actual']['importe']) ?></span></td><td><span class="etiqueta">Pedidos vigentes</span><span class="valor"><?= number_format($r['actual']['pedidos']) ?></span></td></tr><tr><td><span class="etiqueta">Unidades vigentes</span><span class="valor"><?= number_format($r['actual']['unidades']) ?></span></td><td><span class="etiqueta">Promedio por pedido</span><span class="valor"><?= reDinero($r['actual']['promedio']) ?></span></td></tr></table>
<p class="nota">Incluye pedidos confirmados, en preparación y entregados. Los cancelados y estados no reconocidos se muestran por separado. Son importes de pedidos registrados; no acreditan cobros ni representan utilidad o facturación fiscal.</p>
<?php if(!$r['total_pedidos']): ?><p class="sin-datos">No hay pedidos en el período seleccionado.</p><?php endif; ?>
<h2>Pedidos por estado</h2><table><thead><tr><th>Estado</th><th class="num">Pedidos</th><th class="num">Importe MXN</th><th class="num">% de pedidos</th></tr></thead><tbody>
<?php foreach($r['estados']as $e): ?><tr><td><?= reEsc($e['nombre']) ?></td><td class="num"><?= $e['pedidos'] ?></td><td class="num"><?= reDinero($e['importe']) ?></td><td class="num"><?= number_format($r['total_pedidos']?$e['pedidos']*100/$r['total_pedidos']:0,1) ?>%</td></tr><?php endforeach; ?>
</tbody></table><p class="sub">El importe cancelado no se suma al importe vigente y no representa un reembolso procesado.</p>
<?php if($r['diferencia_centavos']!==0): ?><p class="nota">Revisión de datos: los totales de pedidos y la suma de sus detalles difieren en <?= reEsc(reDecimal($r['diferencia_centavos'])) ?> MXN. Revisa los registros antes de usar el reporte.</p><?php endif; ?>
<h2>Productos más vendidos</h2><?php if($r['productos']): ?><p class="sub intro">Se muestran <?= min(20,count($r['productos'])) ?> de <?= count($r['productos']) ?> productos, ordenados por unidades. El CSV incluye todos.</p>
<table><thead><tr><th style="width:12%">ID</th><th style="width:<?= $anchoNombre ?>%">Producto</th><th class="num" style="width:<?= $anchoUnidades ?>%">Unidades</th><th class="num" style="width:10%">Pedidos</th><th class="num" style="width:26%">Importe MXN</th><th class="num" style="width:12%">% unidades</th></tr></thead><tbody>
<?php foreach(array_slice($r['productos'],0,20)as $p): ?><tr><td><?= (int)$p['producto_id'] ?></td><td><?= reEsc($p['nombre']) ?></td><td class="num"><?= $p['unidades'] ?></td><td class="num"><?= $p['pedidos'] ?></td><td class="num"><?= reDinero($p['importe']) ?></td><td class="num"><?= number_format($p['porcentaje'],1) ?>%</td></tr><?php endforeach; ?>
<?php if(!$r['productos']): ?><tr><td colspan="6">Sin unidades vendidas en pedidos vigentes.</td></tr><?php endif; ?></tbody></table>
<p class="sub">Agrupación por ID; el nombre es una referencia histórica de los detalles. Porcentajes sobre todas las unidades vigentes, redondeados. Un pedido puede contener varios productos.</p>
<?php else: ?><p>Sin unidades vendidas en pedidos vigentes.</p><?php endif; ?>
<h2>Totales diarios</h2><p class="sub intro">Incluye días sin ventas con valor cero. Ambos extremos del período están incluidos.</p>
<table class="diario"><thead><tr><th>Fecha</th><th class="num">Pedidos vigentes</th><th class="num">Unidades vigentes</th><th class="num">Importe MXN</th></tr></thead><tbody>
<?php foreach($r['serie']as $s): ?><tr><td><?= reEsc($s['dia']) ?></td><td class="num"><?= $s['pedidos'] ?></td><td class="num"><?= $s['unidades'] ?></td><td class="num"><?= reDinero($s['importe']) ?></td></tr><?php endforeach; ?>
<tr><td><strong>Total vigente</strong></td><td class="num"><strong><?= $r['actual']['pedidos'] ?></strong></td><td class="num"><strong><?= $r['actual']['unidades'] ?></strong></td><td class="num"><strong><?= reDinero($r['actual']['importe']) ?></strong></td></tr>
</tbody></table><p class="sub">Fuente: pedidos y detalles registrados en la tienda. El CSV de pedidos permite consultar el detalle individual, incluidos los cancelados. Cada descarga vuelve a consultar los datos; si cambia un pedido entre descargas, sus resultados pueden variar.</p>
</body></html>
<?php
    $html=ob_get_clean();
    $pdf=new \Dompdf\Dompdf(['isRemoteEnabled'=>false,'isPhpEnabled'=>false,'isJavascriptEnabled'=>false,'isFontSubsettingEnabled'=>true]);
    $pdf->loadHtml($html,'UTF-8');$pdf->setPaper('A4','portrait');$pdf->render();
    $canvas=$pdf->getCanvas();$font=$pdf->getFontMetrics()->getFont('Helvetica');
    $canvas->page_text(36,812,'Reporte de ventas / '.$r['desde']->format('Y-m-d').' a '.$r['hasta']->format('Y-m-d'),$font,7,[0.3,0.4,0.45]);
    $canvas->page_text(475,812,'Página {PAGE_NUM} de {PAGE_COUNT}',$font,7,[0.3,0.4,0.45]);
    return $pdf->output();
}
