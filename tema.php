<?php
require_once __DIR__ . '/conexion.php';
require_once __DIR__ . '/tienda_funciones.php';
$tienda=obtenerConfiguracionTienda($pdo);
$fondo=$tienda['color_fondo']; $principal=$tienda['color_principal'];
$panel=tcMezclar($fondo,.045); $borde=tcMezclar($fondo,.16);
$boton=tcContraste($principal,'#000000') >= tcContraste($principal,'#ffffff') ? '#000000' : '#ffffff';
header('Content-Type: text/css; charset=UTF-8');
header('Cache-Control: no-store');
?>
:root{--fondo:<?= $fondo ?>;--panel:<?= $panel ?>;--borde:<?= $borde ?>;--texto:#edf4f0;--suave:#b4c2ca;--verde:<?= $principal ?>;--texto-boton:<?= $boton ?>}
body{background:var(--fondo);color:var(--texto)}
.topbar,.barra,.sidebar,.card,.panel,.form-card,.login-card,.table-container,.tarjeta,.filtros{background:var(--panel);border-color:var(--borde)}
.topbar{height:auto;min-height:68px;padding-top:12px;padding-bottom:12px;gap:16px;flex-wrap:wrap}
.sidebar a:hover,.sidebar a.active{background:var(--fondo);color:var(--verde)}
.quick-links a,.btn:not(.danger),.boton:not(.secundario):not(.peligro),.login-card form button{background:var(--verde);color:var(--texto-boton);border-color:var(--verde)}
.brand,.marca{color:var(--texto)}a{color:var(--verde)}
th{background:var(--fondo);color:var(--verde)}th,td{border-color:var(--borde)}tr:hover td{background:var(--fondo)}
input,select,textarea{background:var(--fondo);color:var(--texto);border-color:var(--borde)}
input:focus,select:focus,textarea:focus{outline-color:var(--verde);border-color:var(--verde)}
.back,.punto,.stock,.role,.etiqueta{color:var(--verde)}.stock.low{color:#ffb06b}.etiqueta,.role{background:var(--fondo);border-color:var(--borde)}
.linea polyline{stroke:var(--verde)}.linea circle{fill:var(--verde)}
.marca-tienda{display:inline-flex;align-items:center;gap:10px;max-width:100%;min-width:0;vertical-align:middle}
.marca-tienda>span:last-child{overflow:hidden;text-overflow:ellipsis;white-space:nowrap;max-width:42vw}
.logo-negocio{display:inline-block;width:38px;height:38px;object-fit:contain;border-radius:7px;vertical-align:middle;flex-shrink:0}
.marca-icono{font-size:26px;line-height:1;flex-shrink:0}.login-card .logo-negocio{width:90px;height:90px;border-radius:14px}
.login-card h1{overflow-wrap:anywhere}.login-card .contacto-negocio{font-size:12px;line-height:1.7;text-align:center;color:var(--suave);margin-top:20px;overflow-wrap:anywhere}
.login-card .contacto-negocio span{display:block}.salir-boton{color:#ff9b9b;background:transparent;border:0;cursor:pointer}
@media(max-width:600px){.marca-tienda>span:last-child{max-width:64vw}.logo-negocio{width:32px;height:32px}.topbar .user-info{font-size:13px;flex-wrap:wrap}}
@media print{:root{--fondo:#fff;--panel:#fff;--borde:#ccd5db;--texto:#15232e;--suave:#465862;--verde:#107352;--texto-boton:#fff}body{background:white;color:#15232e}.panel,.tarjeta,.table-container{background:white;color:#15232e}.logo-negocio{display:inline-block}.barra,.topbar,.sidebar{display:none}}
