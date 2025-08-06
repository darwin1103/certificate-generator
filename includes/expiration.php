<?php

/**
 * Borra un archivo de Google Cloud Storage usando la URL pública.
 */
function cc_borrar_archivo_gcs_desde_url($public_url) {
    $bucket_name = get_option('cc_certificados_gcs_bucket');
    $keyfile     = get_option('cc_certificados_gcs_key_path');

    // Extraer el nombre del archivo del URL
    $pattern = "#https://storage.googleapis.com/([^/]+)/(.+)#";
    if (preg_match($pattern, $public_url, $matches)) {
        $bucket_url  = $matches[1];
        $object_name = urldecode($matches[2]); // <-- Decodifica espacios y caracteres

        error_log("[CERTIFICADOS][GCS] Intentando borrar: $object_name en $bucket_url");

        if ($bucket_url !== $bucket_name) {
            error_log("[CERTIFICADOS][GCS] Bucket name mismatch: {$bucket_url} vs {$bucket_name}");
            return false;
        }
        if (!$keyfile || !file_exists($keyfile)) {
            error_log("[CERTIFICADOS][GCS] Keyfile GCS no encontrado para borrar archivo.");
            return false;
        }
        require_once __DIR__ . '/../vendor/autoload.php';
        $storage = new Google\Cloud\Storage\StorageClient([
            'keyFilePath' => $keyfile,
        ]);
        $bucket = $storage->bucket($bucket_name);

        // Debug: Lista objetos con ese prefijo
        $objects = $bucket->objects(['prefix' => $object_name]);
        foreach ($objects as $obj) {
            error_log("[CERTIFICADOS][GCS] Preview: Encontrado en bucket: " . $obj->name());
        }

        $object = $bucket->object($object_name);
        if ($object->exists()) {
            $object->delete();
            error_log("[CERTIFICADOS][GCS] PDF en GCS eliminado: $public_url");
            return true;
        } else {
            error_log("[CERTIFICADOS][GCS] El archivo no existe en GCS: $object_name");
        }
    }
    return false;
}

/**
 * Borra un archivo local dado su URL pública.
 */
function cc_borrar_archivo_local_desde_url($file_url) {
    $upload_dir = wp_upload_dir();
    if (strpos($file_url, $upload_dir['baseurl']) === 0) {
        $relative = str_replace($upload_dir['baseurl'], '', $file_url);
        $local_path = $upload_dir['basedir'] . $relative;

        if (file_exists($local_path)) {
            unlink($local_path);
            error_log("[CERTIFICADOS] Certificado local eliminado correctamente: $local_path");
            return true;
        } else {
            error_log("[CERTIFICADOS] No se encontró el archivo local para borrar: $local_path");
        }
    } else {
        error_log("[CERTIFICADOS] La URL no está dentro de uploads: $file_url");
    }
    return false;
}

add_action('before_delete_post', 'cc_eliminar_certificado_pdf_al_borrar_post');
function cc_eliminar_certificado_pdf_al_borrar_post($post_id) {
    error_log("[CERTIFICADOS] Ejecutando hook before_delete_post para post $post_id");

    if (get_post_type($post_id) !== 'certificado') return;

    $pdf_url = get_post_meta($post_id, 'pdf_file', true);

    if (!$pdf_url) {
        error_log("[CERTIFICADOS] No se encontró la URL del certificado para el post $post_id");
        return;
    }

    error_log("[CERTIFICADOS] Eliminando certificado asociado al post $post_id: $pdf_url");

    if (strpos($pdf_url, 'storage.googleapis.com') !== false) {
        cc_borrar_archivo_gcs_desde_url($pdf_url);
    } else {
        cc_borrar_archivo_local_desde_url($pdf_url);
    }
}

/**
 * Limpia certificados expirados y borra el PDF correspondiente.
 */
function cc_eliminar_pdfs_certificados_expirados() {
    $args = array(
        'post_type'      => 'certificado',
        'posts_per_page' => -1,
        'post_status'    => 'any',
    );
    $certificados = get_posts($args);
    $hoy = date('Y-m-d');

    foreach ($certificados as $certificado) {
        $post_id         = $certificado->ID;
        $fecha_expedicion = get_post_meta($post_id, 'fecha_expedicion', true);
        $vigencia         = get_post_meta($post_id, 'fecha_expiracion_certificado', true);
        $pdf_url          = get_post_meta($post_id, 'pdf_file', true);

        // Calcular fecha de vencimiento (acepta vigencia en años o meses)
        $solo_numeros = intval(preg_replace('/\D/', '', $vigencia));
        if (stripos($vigencia, 'mes') !== false) {
            $fecha_vencimiento = date('Y-m-d', strtotime("+{$solo_numeros} months", strtotime($fecha_expedicion)));
        } else {
            $fecha_vencimiento = date('Y-m-d', strtotime("+{$solo_numeros} years", strtotime($fecha_expedicion)));
        }

        if ($fecha_expedicion && $vigencia && strtotime($fecha_vencimiento) < strtotime($hoy) && $pdf_url) {
            error_log("[CERTIFICADOS][EXPIRADOS] Eliminando certificado expirado post $post_id: $pdf_url");
            if (strpos($pdf_url, 'storage.googleapis.com') !== false) {
                cc_borrar_archivo_gcs_desde_url($pdf_url);
            } else {
                cc_borrar_archivo_local_desde_url($pdf_url);
            }
            delete_post_meta($post_id, 'pdf_file');
            error_log("[CERTIFICADOS][EXPIRADOS] Meta eliminado post $post_id.");
        }
    }
}

// Intervalo personalizado cada 15 días
add_filter('cron_schedules', function($schedules) {
    $schedules['cc_quincenal'] = array(
        'interval' => 15 * 24 * 60 * 60,
        'display'  => __('Cada 15 días')
    );
    return $schedules;
});

// Registra el evento cron personalizado si no existe
if (!wp_next_scheduled('cc_cron_borrar_certificados_expirados')) {
    wp_schedule_event(time(), 'cc_quincenal', 'cc_cron_borrar_certificados_expirados');
}

// Hook del cron a la función de limpieza
add_action('cc_cron_borrar_certificados_expirados', 'cc_eliminar_pdfs_certificados_expirados');

