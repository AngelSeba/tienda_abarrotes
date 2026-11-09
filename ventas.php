<?php
require_once __DIR__.'/funciones.php'; protegerAdministrador();
require_once __DIR__.'/conexion.php'; require_once __DIR__.'/reportes/funciones.php';
function vEsc($v): string { return reEsc($v); }
function vDinero($v): string { return reDinero($v); }
function vVariacion($actual,$anterior): string {
    if((float)$anterior===0.0)return (float)$actual===0.0?'Sin cambio':'Sin base de comparación';
    $v=(($actual-$anterior)/$anterior)*100;return ($v>0?'+':'').number_format($v,1).'% vs. período anterior';
}
try { [$desde,$hasta]=rePeriodo($pdo,$_GET);$reporte=reCargar($pdo,$desde,$hasta); }
catch(InvalidArgumentException $e){http_response_code(400);header('Content-Type: text/html; charset=UTF-8');exit('<p>'.reEsc($e->getMessage()).'</p><a href="ventas.php">Volver al reporte</a>');}
catch(ReporteError $e){http_response_code(422);header('Content-Type: text/html; charset=UTF-8');exit('<p>'.reEsc($e->getMessage()).'</p><a href="ventas.php">Reducir período</a>');}
catch(Throwable $e){error_log('Reportes18: consulta no disponible; '.get_class($e));http_response_code(503);exit('No se pudo consultar el reporte. Revisa la conexión y el registro de PHP.');}
$fin=$hasta->modify('+1 day');$dias=$reporte['dias'];$anteriorDesde=$reporte['anteriorDesde'];
$actual=$reporte['actual'];$anterior=$reporte['anterior'];$serie=$reporte['serie'];$productos=$reporte['productos'];$error='';
$exportBase=['desde'=>$desde->format('Y-m-d'),'hasta'=>$hasta->format('Y-m-d')];
function vDescarga(array $base,string $formato,string $seccion='pedidos'): string { return 'reportes/descargar.php?'.http_build_query(array_merge($base,['formato'=>$formato,'seccion'=>$seccion]),'','&',PHP_QUERY_RFC3986); }
$maximo = max(1, ...array_column($serie, 'importe'));
$puntos = [];
foreach ($serie as $i => $punto) {
    $x = 72 + ($dias === 1 ? 354 : $i * 708 / ($dias - 1));
    $y = 216 - $punto['importe'] * 174 / $maximo;
    $puntos[] = number_format($x, 2, '.', '') . ',' . number_format($y, 2, '.', '');
}
$colores = ['#52e0ad','#66a7ff','#b69aff','#ffd078','#ff91a5','#7ad9e5'];
$segmentos = [];
foreach (array_slice($productos, 0, 5) as $i => $p) {
    $segmentos[] = ['nombre' => $p['nombre'], 'unidades' => (int)$p['unidades'], 'color' => $colores[$i]];
}
$resto = (int)$actual['unidades'] - array_sum(array_column($segmentos, 'unidades'));
if ($resto > 0) $segmentos[] = ['nombre' => 'Otros productos', 'unidades' => $resto, 'color' => $colores[5]];
$top = $productos[0] ?? null;
?>
<!doctype html>
<html lang="es">
<head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>Análisis de ventas | <?= tcEsc(tiendaNombre()) ?></title>
<link rel="stylesheet" href="css/ventas.css"><link rel="stylesheet" href="css/reportes.css">
<?= tiendaEstilos('') ?></head>
<body>
<header class="barra"><a class="marca" href="dashboard.php"><?= tiendaMarca('') ?></a><div><span class="etiqueta">Administración</span> <?= vEsc($_SESSION['usuario']['nombre']) ?> <?= botonCerrarSesion('logout.php') ?></div></header>
<main>
<nav><a href="dashboard.php">← Panel principal</a><a href="pedidos/index.php">Pedidos</a><a href="configuracion/index.php">Configuración</a><a href="productos/index.php">Productos</a><a href="usuarios/index.php">Usuarios</a><a href="bitacora/index.php">Bitácora</a></nav>
<div class="titulo"><div><p class="sobre">REPORTE COMERCIAL</p><h1>Análisis de ventas</h1><p class="muted">Pedidos vigentes · <?= vEsc($desde->format('d/m/Y')) ?> — <?= vEsc($hasta->format('d/m/Y')) ?></p></div><button class="boton secundario" onclick="window.print()">Imprimir reporte</button></div>
<form method="get" class="filtros">
<label>Desde<input type="date" name="desde" value="<?= vEsc($desde->format('Y-m-d')) ?>" required></label>
<label>Hasta<input type="date" name="hasta" value="<?= vEsc($hasta->format('Y-m-d')) ?>" required></label>
<button class="boton" type="submit">Actualizar reporte</button><a href="ventas.php">Últimos 30 días</a>
</form>
<section class="panel exportaciones" aria-label="Descargar reportes"><h2>Descargar este período</h2><div class="exportar-enlaces">
<a class="boton" href="<?= vEsc(vDescarga($exportBase,'pdf')) ?>">PDF de resumen</a>
<a class="boton secundario" href="<?= vEsc(vDescarga($exportBase,'csv','pedidos')) ?>">CSV de pedidos</a>
<a class="boton secundario" href="<?= vEsc(vDescarga($exportBase,'csv','productos')) ?>">CSV de productos</a>
<a class="boton secundario" href="<?= vEsc(vDescarga($exportBase,'csv','diario')) ?>">CSV diario</a></div>
<p class="nota">CSV para abrir en Excel. El de pedidos incluye cancelados en filas separadas con importe vigente cero. Los CSV contienen todos los registros del período; el PDF resume hasta 20 productos y todos los días. Cada descarga consulta los datos al momento.</p></section>
<?php if($reporte['diferencia_centavos']!==0): ?><p class="aviso" role="alert">Revisión de datos: los totales de pedidos y sus detalles difieren en <?= vEsc(reDecimal($reporte['diferencia_centavos'])) ?> MXN. Revisa los registros.</p><?php endif; ?>

<p class="nota">Los importes corresponden a pedidos registrados. El sistema no comprueba cobros ni calcula utilidad. Se incluyen pedidos confirmados, en preparación y entregados. Se excluyen cancelados y estados no reconocidos.</p>
<section class="metricas" aria-label="Resumen de ventas">
<?php foreach ([['Importe de pedidos','importe',vDinero($actual['importe'])], ['Unidades vendidas','unidades',number_format((int)$actual['unidades'])], ['Pedidos vigentes','pedidos',number_format((int)$actual['pedidos'])], ['Promedio por pedido','promedio',vDinero($actual['promedio'])]] as [$titulo,$clave,$valor]): ?>
<article class="tarjeta"><p><?= vEsc($titulo) ?></p><strong><?= vEsc($valor) ?></strong><small><?= vEsc(vVariacion($actual[$clave], $anterior[$clave])) ?></small></article>
<?php endforeach; ?>
</section>
<p class="nota">Comparación con <?= vEsc($anteriorDesde->format('d/m/Y')) ?> — <?= vEsc($desde->modify('-1 day')->format('d/m/Y')) ?> (<?= (int)$dias ?> días).</p>
<?php if (!(int)$actual['pedidos']): ?><div class="vacio"><h2>Sin ventas en estas fechas</h2><p>Cambia el período o registra una compra con una cuenta de cliente. Los indicadores se actualizarán al recargar.</p></div><?php endif; ?>
<section class="graficas">
<article class="panel"><div class="cabecera"><div><h2>Evolución diaria</h2><p class="muted">Importe de pedidos · MXN</p></div><span class="punto">● Vigentes</span></div>
<svg class="linea" viewBox="0 0 820 270" role="img" aria-labelledby="titulo-linea descripcion-linea">
<title id="titulo-linea">Importe de pedidos por día</title><desc id="descripcion-linea">Los días sin pedidos se representan con cero. Debajo del reporte hay una tabla diaria con los valores exactos.</desc>
<?php for ($i = 0; $i <= 4; $i++): $y = 216 - $i * 43.5; ?>
<line x1="72" y1="<?= $y ?>" x2="780" y2="<?= $y ?>" stroke="#293b43" stroke-dasharray="4 5"/>
<text x="62" y="<?= $y + 4 ?>" text-anchor="end" class="eje"><?= number_format($maximo * $i / 4, 0) ?></text>
<?php endfor; ?>
<polyline points="<?= vEsc(implode(' ', $puntos)) ?>" fill="none" stroke="#52e0ad" stroke-width="3" stroke-linejoin="round"/>
<?php foreach ($serie as $i => $punto): $xy = explode(',', $puntos[$i]); ?>
<circle cx="<?= $xy[0] ?>" cy="<?= $xy[1] ?>" r="<?= $dias > 60 ? 2 : 4 ?>" fill="#52e0ad"><title><?= vEsc($punto['dia'] . ': ' . vDinero($punto['importe'])) ?> | <?= tcEsc(tiendaNombre()) ?></title></circle>
<?php endforeach; ?>
<text x="72" y="250" class="eje"><?= vEsc($desde->format('d/m/Y')) ?></text><text x="780" y="250" class="eje" text-anchor="end"><?= vEsc($hasta->format('d/m/Y')) ?></text>
</svg></article>
<article class="panel"><h2>Participación por producto</h2><p class="muted">Porcentaje del total de unidades vendidas</p>
<div class="distribucion"><svg class="dona" viewBox="0 0 160 160" role="img" aria-label="Distribución de unidades por producto; valores exactos en la leyenda">
<circle cx="80" cy="80" r="58" fill="none" stroke="#293b43" stroke-width="18"/>
<?php $offset = 0; foreach ($segmentos as $segmento): $porcentaje = $actual['unidades'] ? $segmento['unidades'] * 100 / $actual['unidades'] : 0; ?>
<circle cx="80" cy="80" r="58" pathLength="100" fill="none" stroke="<?= $segmento['color'] ?>" stroke-width="18" stroke-dasharray="<?= $porcentaje ?> <?= 100-$porcentaje ?>" stroke-dashoffset="<?= -$offset ?>" transform="rotate(-90 80 80)"><title><?= vEsc($segmento['nombre']) ?>: <?= number_format($porcentaje,1) ?>% | <?= tcEsc(tiendaNombre()) ?></title></circle>
<?php $offset += $porcentaje; endforeach; ?>
<text x="80" y="79" text-anchor="middle" class="total-dona"><?= number_format((int)$actual['unidades']) ?></text><text x="80" y="98" text-anchor="middle" class="eje">unidades</text>
</svg><ul class="leyenda"><?php foreach ($segmentos as $segmento): ?><li><span><i style="background:<?= $segmento['color'] ?>"></i><?= vEsc($segmento['nombre']) ?></span><b><?= number_format($segmento['unidades'] * 100 / $actual['unidades'],1) ?>%</b></li><?php endforeach; ?><?php if (!$segmentos): ?><li>Sin unidades vendidas</li><?php endif; ?></ul></div></article>
</section>
<section class="panel"><div class="cabecera"><div><h2>Productos más vendidos</h2><p class="muted">Hasta 10 productos, ordenados por unidades. Los porcentajes usan el total de todos los productos del período.</p></div><?php if ($top): ?><span class="etiqueta">Líder: <?= vEsc($top['nombre']) ?></span><?php endif; ?></div>
<div class="tabla"><table><thead><tr><th>Producto</th><th class="numero">Unidades</th><th class="numero">Pedidos</th><th class="numero">Importe</th><th>Participación en unidades</th></tr></thead><tbody>
<?php foreach (array_slice($productos,0,10) as $i => $producto): $porcentaje = $actual['unidades'] ? $producto['unidades'] * 100 / $actual['unidades'] : 0; ?>
<tr><td><span class="puesto"><?= $i+1 ?></span><?= vEsc($producto['nombre']) ?></td><td class="numero"><?= number_format((int)$producto['unidades']) ?></td><td class="numero"><?= (int)$producto['pedidos'] ?></td><td class="numero"><?= vDinero($producto['importe']) ?></td><td><div class="participacion"><progress max="100" value="<?= $porcentaje ?>" aria-label="Participación de <?= vEsc($producto['nombre']) ?>"></progress><b><?= number_format($porcentaje,1) ?>%</b></div></td></tr>
<?php endforeach; ?>
<?php if (!$productos): ?><tr><td colspan="5">No hay productos vendidos en este período.</td></tr><?php endif; ?>
</tbody></table></div><p class="nota">Un pedido puede contener varios productos. Los porcentajes se redondean a un decimal y pueden sumar aproximadamente 100%.</p></section>
<section class="panel"><h2>Pedidos cancelados y estados</h2><p class="muted">Los importes cancelados se muestran como referencia y no se suman a las ventas vigentes. No acreditan un reembolso.</p><div class="tabla"><table><thead><tr><th>Estado</th><th class="numero">Pedidos</th><th class="numero">Importe</th><th class="numero">% de todos los pedidos</th></tr></thead><tbody>
<?php foreach($reporte['estados']as $e): ?><tr><td><?= vEsc($e['nombre']) ?></td><td class="numero"><?= $e['pedidos'] ?></td><td class="numero"><?= vDinero($e['importe']) ?></td><td class="numero"><?= number_format($reporte['total_pedidos']?$e['pedidos']*100/$reporte['total_pedidos']:0,1) ?>%</td></tr><?php endforeach; ?></tbody></table></div></section>
<details class="panel"><summary>Ver importes diarios exactos</summary><div class="tabla"><table><thead><tr><th>Fecha</th><th class="numero">Importe de pedidos (MXN)</th></tr></thead><tbody><?php foreach ($serie as $punto): ?><tr><td><?= vEsc($punto['dia']) ?></td><td class="numero"><?= vDinero($punto['importe']) ?></td></tr><?php endforeach; ?></tbody></table></div></details>
<footer>Fuente: pedidos y sus detalles registrados en la tienda. Consulta: <?= vEsc($reporte['generado']) ?>. Fechas del servidor; ambos extremos incluidos.</footer>
</main>
</body></html>
