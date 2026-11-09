<?php
require_once __DIR__.'/../conexion.php'; require_once __DIR__.'/../funciones.php'; protegerAdministrador();
require_once __DIR__.'/../version17.php'; requerirVersion17($pdo); $error='';
if ($_SERVER['REQUEST_METHOD']==='POST') {
    validarCsrf();
    try {
        $id=filter_var(textoPost('id'),FILTER_VALIDATE_INT);
        if (!$id || textoPost('confirmar')!=='si') throw new RuntimeException('Confirma el reintento después de revisar si llegó el correo anterior.');
        $pdo->beginTransaction();
        $q=$pdo->prepare('SELECT pedido_id FROM correos_pendientes WHERE id=?'); $q->execute([$id]); $pid=$q->fetchColumn();
        if (!$pid) throw new RuntimeException('Trabajo no encontrado.');
        $q=$pdo->prepare('SELECT estado FROM pedidos WHERE id=? FOR UPDATE'); $q->execute([$pid]);
        if ($q->fetchColumn()==='cancelado') throw new RuntimeException('El pedido está cancelado; no se reenvía el ticket.');
        $q=$pdo->prepare("UPDATE correos_pendientes SET estado='pendiente',ultimo_error='',iniciado_en=NULL WHERE id=? AND estado IN ('error','incierto')"); $q->execute([$id]);
        if ($q->rowCount()!==1) throw new RuntimeException('El correo no admite reintento en su estado actual.');
        registrarBitacora($pdo,'Autorizó reintento de correo del pedido #'.(int)$pid); $pdo->commit();
        header('Location: index.php'); exit;
    } catch (Throwable $e) { if($pdo->inTransaction())$pdo->rollBack();http_response_code(422);$error=$e instanceof RuntimeException && !($e instanceof PDOException)?$e->getMessage():'No se pudo autorizar el reintento.'; }
}
$filas=$pdo->query('SELECT id,pedido_id,estado,intentos,creado_en,enviado_en,ultimo_error FROM correos_pendientes ORDER BY id DESC LIMIT 100')->fetchAll();
?>
<!doctype html><html lang="es"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Correos | <?= tcEsc(tiendaNombre()) ?></title><link rel="stylesheet" href="../css/ventas.css"><link rel="stylesheet" href="../css/operacion.css"><?= tiendaEstilos('../') ?></head><body><header class="barra"><a class="marca" href="../dashboard.php"><?= tiendaMarca('../') ?></a><?= botonCerrarSesion('../logout.php') ?></header><main><nav><a href="../dashboard.php">← Panel principal</a><a href="../inventario/index.php">Inventario</a></nav><h1>Correo de tickets</h1>
<p class="aviso">El ejecutor de tareas debe estar configurado para enviar los correos pendientes. Revisa la guía 1.7. Los errores requieren revisión y reintento manual; no se vuelve a enviar un ticket confirmado como enviado.</p>
<?php if($error): ?><p class="aviso" role="alert"><?= tcEsc($error) ?></p><?php endif; ?>
<section class="panel"><h2>Últimos 100 trabajos</h2><div class="tabla-scroll"><table><thead><tr><th>Pedido</th><th>Estado</th><th>Intentos</th><th>Creado</th><th>Enviado</th><th>Observación / reintento</th></tr></thead><tbody>
<?php foreach($filas as $f): ?><tr><td>#<?= (int)$f['pedido_id'] ?></td><td><?= tcEsc($f['estado']) ?></td><td><?= (int)$f['intentos'] ?></td><td><?= tcEsc($f['creado_en']) ?></td><td><?= tcEsc($f['enviado_en'] ?? '—') ?></td><td><?= tcEsc($f['ultimo_error']) ?><?php if(in_array($f['estado'],['error','incierto'],true)): ?><form method="post" class="cola-form"><?= campoCsrf() ?><input type="hidden" name="id" value="<?= (int)$f['id'] ?>"><label><input type="checkbox" name="confirmar" value="si" required> Revisé la recepción; acepto que el reintento podría duplicar un correo.</label><button class="boton">Reintentar</button></form><?php endif; ?></td></tr><?php endforeach; ?></tbody></table></div></section></main></body></html>
