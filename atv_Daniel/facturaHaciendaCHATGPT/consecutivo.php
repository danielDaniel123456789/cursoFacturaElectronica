<?php
function obtenerConsecutivo() {
    $rutaArchivo = __DIR__ . '/consecutivo.txt';

    $fp = fopen($rutaArchivo, 'c+'); // Abrir para lectura y escritura; crear si no existe
    if (!$fp) {
        throw new Exception("No se pudo abrir el archivo de consecutivo");
    }

    // Bloqueo exclusivo para evitar concurrencia
    if (flock($fp, LOCK_EX)) {
        // Leer valor actual
        $consecutivo = trim(fread($fp, filesize($rutaArchivo)));
        if ($consecutivo === '') {
            $consecutivo = 1;
        } else {
            $consecutivo = (int)$consecutivo;
        }

        // Valor a devolver es el actual antes de incrementar
        $valorDevolver = $consecutivo;

        // Incrementar y guardar
        $nuevoConsecutivo = $consecutivo + 1;
        // Volver al inicio para sobrescribir
        ftruncate($fp, 0);
        rewind($fp);
        fwrite($fp, (string)$nuevoConsecutivo);

        // Liberar bloqueo y cerrar
        flock($fp, LOCK_UN);
        fclose($fp);

        return (string)$valorDevolver;
    } else {
        fclose($fp);
        throw new Exception("No se pudo obtener bloqueo para el archivo de consecutivo");
    }
}
