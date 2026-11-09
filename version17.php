<?php
function requerirVersion17(PDO $pdo): void {
    static $listos=[]; $id=spl_object_id($pdo); if(isset($listos[$id]))return;
    try {
        $pdo->query('SELECT p.stock_minimo,m.id,c.id,t.pedido_id FROM productos p LEFT JOIN movimientos_inventario m ON 1=0 LEFT JOIN correos_pendientes c ON 1=0 LEFT JOIN tickets_cache t ON 1=0 LIMIT 0')->closeCursor();
        $listos[$id]=true;
    } catch (PDOException $e) {
        error_log('Version17: esquema pendiente o base no disponible.');
        if(PHP_SAPI==='cli') throw new RuntimeException('Completa la migración 1.7 y revisa la base antes de ejecutar la cola.');
        http_response_code(503); header('Content-Type: text/plain; charset=UTF-8');
        exit('La tienda tiene una actualización pendiente. El administrador debe completar los pasos de LEEME_1_7.md antes de continuar.');
    }
}
