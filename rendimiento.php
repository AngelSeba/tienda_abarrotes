<?php
function rtTiempo(string $tarea,float $inicio,array $extra=[]): void {
    error_log('PERF17 '.json_encode(array_merge(['tarea'=>$tarea,'ms'=>round((microtime(true)-$inicio)*1000,2)],$extra),JSON_UNESCAPED_UNICODE));
}
