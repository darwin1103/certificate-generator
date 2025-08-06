<?php

// Solo cargar si Composer existe
if (!class_exists('Google\Cloud\Storage\StorageClient')) {
    require_once __DIR__ . '/../vendor/autoload.php';
}

use Google\Cloud\Storage\StorageClient;

/**
 * Subir archivo a Google Cloud Storage
 * @param string $local_path Ruta local al PDF.
 * @param string $nombre_destino Nombre final en el bucket (ej: certificados/archivo.pdf)
 * @return string|false URL pública o false en caso de error.
 */
function cc_gcs_subir_archivo($contenido, $nombre_archivo, $bucket, $key_path) {
    // Incluir el autoload de Google Cloud
    if (!class_exists('Google\Cloud\Storage\StorageClient')) {
        require_once __DIR__ . '/../vendor/autoload.php';
    }

    try {
        // Crear el cliente de Storage
        $storage = new Google\Cloud\Storage\StorageClient([
            'keyFilePath' => $key_path,
        ]);
        $bucket_obj = $storage->bucket($bucket);

        // Subir el archivo
        $object = $bucket_obj->upload($contenido, [
            'name' => $nombre_archivo,
        ]);

        // Construir la URL pública
        $public_url = "https://storage.googleapis.com/$bucket/$nombre_archivo";
        error_log("[GCS] Archivo subido exitosamente: $public_url");
        return $public_url;

    } catch (Exception $e) {
        error_log('[GCS] Error al subir archivo: ' . $e->getMessage());
        return false;
    }
}

