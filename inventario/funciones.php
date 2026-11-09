<?php
/** El llamador debe mantener una transacción y el bloqueo del producto. */
function invMovimiento(PDO $pdo,array $p,string $tipo,int $antes,int $despues,string $motivo,?int $pedido=null,?string $operacion=null): void {
    $q=$pdo->prepare('INSERT INTO movimientos_inventario(producto_id,producto_nombre,tipo,cantidad,anterior,saldo,motivo,usuario_id,usuario_nombre,pedido_id,operacion) VALUES (?,?,?,?,?,?,?,?,?,?,?)');
    $q->execute([(int)$p['id'],$p['nombre'],$tipo,$despues-$antes,$antes,$despues,$motivo,$_SESSION['usuario']['id'] ?? null,$_SESSION['usuario']['nombre'] ?? 'Sistema',$pedido,$operacion]);
}
function invAplicar(PDO $pdo,int $id,string $tipo,int $cantidad,string $motivo,string $operacion): string {
    if ($id<1 || !in_array($tipo,['entrada','ajuste','minimo'],true) || $cantidad<0 || $cantidad>2147483647 || ($tipo==='entrada' && $cantidad===0)) throw new RuntimeException('Revisa producto, tipo y cantidad.');
    if (!preg_match('/^[a-f0-9]{64}$/D',$operacion) || !isset($_SESSION['inv_operaciones'][$operacion]) || $_SESSION['inv_operaciones'][$operacion]<time()-3600) throw new RuntimeException('El formulario venció. Recarga el inventario.');
    $motivo=trim($motivo);
    if (!mb_check_encoding($motivo,'UTF-8') || mb_strlen($motivo)<5 || mb_strlen($motivo)>200 || preg_match('/[\x00-\x1f\x7f]/',$motivo)) throw new RuntimeException('Escribe un motivo de 5 a 200 caracteres, sin saltos de línea.');
    try {
        $pdo->beginTransaction();
        $q=$pdo->prepare('SELECT * FROM productos WHERE id=? FOR UPDATE'); $q->execute([$id]); $p=$q->fetch();
        if (!$p) throw new RuntimeException('El producto ya no existe.');
        $q=$pdo->prepare('SELECT id FROM movimientos_inventario WHERE operacion=?'); $q->execute([$operacion]);
        if ($q->fetch()) { $pdo->commit(); return 'Esta operación ya se registró; no se repitió.'; }
        $antes=(int)$p['stock'];
        if ($antes<0 || ($tipo==='entrada' && $antes>2147483647-$cantidad)) throw new RuntimeException('La cantidad supera la capacidad del inventario.');
        $saldo=$tipo==='entrada' ? $antes+$cantidad : ($tipo==='ajuste'?$cantidad:$antes);
        if ($tipo==='minimo') { $q=$pdo->prepare('UPDATE productos SET stock_minimo=? WHERE id=?'); $q->execute([$cantidad,$id]); $motivo='Mínimo '.$p['stock_minimo'].' -> '.$cantidad.'. '.$motivo; $motivo=mb_substr($motivo,0,200); }
        else { $q=$pdo->prepare('UPDATE productos SET stock=? WHERE id=?'); $q->execute([$saldo,$id]); }
        invMovimiento($pdo,$p,$tipo,$antes,$saldo,$motivo,null,$operacion);
        registrarBitacora($pdo,'Inventario #'.$id.': '.$tipo.', '.$antes.' -> '.$saldo);
        $pdo->commit(); return 'Operación de inventario registrada correctamente.';
    } catch (Throwable $e) { if ($pdo->inTransaction()) $pdo->rollBack(); throw $e; }
}
