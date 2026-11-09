<?php
require_once __DIR__.'/../rendimiento.php';
function cqEncolar(PDO $pdo,int $pedido,int $usuario,string $correo,string $nombre): void {
    $q=$pdo->prepare('INSERT INTO correos_pendientes(pedido_id,usuario_id,correo,nombre) VALUES (?,?,?,?)');
    $q->execute([$pedido,$usuario,$correo,$nombre]);
}
/** Solo el ejecutor CLI usa esta función. $enviar permite verificar sin transmitir correo. */
function cqProcesar(PDO $pdo,int $limite=5,?callable $enviar=null): array {
    $limite=max(1,min(20,$limite)); $res=['enviados'=>0,'errores'=>0,'cancelados'=>0];
    $base=(string)$pdo->query('SELECT DATABASE()')->fetchColumn(); $lock='cola17_'.substr(hash('sha256',strtolower($base)),0,48);
    $q=$pdo->prepare('SELECT GET_LOCK(?,0)'); $q->execute([$lock]);
    if ((int)$q->fetchColumn()!==1) return array_merge($res,['ocupado'=>true]);
    try {
        // Una interrupción después de transmitir puede haber entregado el correo.
        // No se reenvía automáticamente un trabajo que quedó en esa situación.
        $pdo->exec("UPDATE correos_pendientes SET estado='incierto',ultimo_error='Ejecución interrumpida. Verifica recepción antes de reintentar.' WHERE estado='enviando' AND iniciado_en < DATE_SUB(NOW(),INTERVAL 15 MINUTE)");
        for($i=0;$i<$limite;$i++) {
            $inicio=microtime(true);
            $pdo->beginTransaction();
            $fila=$pdo->query("SELECT * FROM correos_pendientes WHERE estado='pendiente' ORDER BY id LIMIT 1 FOR UPDATE")->fetch();
            if (!$fila) { $pdo->commit(); break; }
            $q=$pdo->prepare("UPDATE correos_pendientes SET estado='enviando',intentos=intentos+1,iniciado_en=NOW(),ultimo_error='' WHERE id=?"); $q->execute([$fila['id']]); $pdo->commit();
            try {
                $q=$pdo->prepare('SELECT estado,usuario_id FROM pedidos WHERE id=?'); $q->execute([$fila['pedido_id']]); $pedido=$q->fetch();
                if (!$pedido || $pedido['estado']==='cancelado' || (int)$pedido['usuario_id']!==(int)$fila['usuario_id']) {
                    $q=$pdo->prepare("UPDATE correos_pendientes SET estado='cancelado' WHERE id=?"); $q->execute([$fila['id']]); ++$res['cancelados']; continue;
                }
                if ($enviar===null) {
                    require_once __DIR__.'/../productos/correo_compra.php';
                    $mail=prepararCorreoPedido($pdo,(int)$fila['pedido_id'],$fila['nombre'],$fila['correo'],(int)$fila['usuario_id']);
                    // Revisión final después de generar el PDF, antes de SMTP.
                    $q=$pdo->prepare("SELECT c.estado FROM correos_pendientes c JOIN pedidos p ON p.id=c.pedido_id WHERE c.id=? AND p.estado<>'cancelado'"); $q->execute([$fila['id']]);
                    if ($q->fetchColumn()!=='enviando') { ++$res['cancelados']; continue; }
                    $mail->send();
                } else $enviar($fila);
                $q=$pdo->prepare("UPDATE correos_pendientes SET ultimo_error=CASE WHEN estado='cancelado' THEN 'El pedido se canceló durante un envío ya iniciado.' ELSE '' END,estado='enviado',enviado_en=NOW() WHERE id=? AND estado IN ('enviando','cancelado')"); $q->execute([$fila['id']]); ++$res['enviados'];
            } catch (Throwable $e) {
                $q=$pdo->prepare("UPDATE correos_pendientes SET estado='error',ultimo_error='No se confirmó la entrega. Revisa SMTP y reintenta desde Administración.' WHERE id=? AND estado='enviando'"); $q->execute([$fila['id']]);
                error_log('Cola17: fallo de envío; trabajo '.(int)$fila['id'].'; tipo '.get_class($e)); ++$res['errores'];
            } finally { rtTiempo('correo_trabajador',$inicio,['trabajo'=>(int)$fila['id']]); }
        }
    } finally { if ($pdo->inTransaction()) $pdo->rollBack(); $q=$pdo->prepare('SELECT RELEASE_LOCK(?)'); $q->execute([$lock]); }
    return $res;
}
