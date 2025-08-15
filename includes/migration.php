<?php
if ( ! defined('ABSPATH') ) { exit; }

/**
 * MIGRACIÓN DE CERTIFICADOS LOCALES -> GCS
 *
 * Requisitos:
 *  - Options:
 *      cc_certificados_gcs_bucket
 *      cc_certificados_gcs_key_path
 *  - Composer autoload en: plugin-root/vendor/autoload.php
 *
 * Meta origen/destino:
 *  - Lee:   pdf_file  (URL local o GCS)
 *  - Escribe trazas por post:
 *      gcs_migrated_at      (timestamp)
 *      gcs_migration_error  (último error o '')
 *      gcs_source           ('local' -> 'gcs')
 *
 * State global (options):
 *  - cc_mig_running        ('1'|'0')
 *  - cc_mig_last_id        (int; cursor por ID)
 *  - cc_mig_batch_size     (int; por defecto 300)
 *  - cc_mig_started_at     (timestamp)
 *  - cc_mig_done_count     (int)
 *  - cc_mig_error_count    (int)
 *  - cc_mig_lock           (transient lock)
 */

// ---------- CRON SCHEDULE ---------- //

add_filter('cron_schedules', function($schedules) {
    if ( ! isset($schedules['cc_every_minute']) ) {
        $schedules['cc_every_minute'] = [
            'interval' => 60,
            'display'  => __('Cada minuto (CC MIG)', 'certificados-plugin'),
        ];
    }
    return $schedules;
});

// Evento del cron
add_action('cc_cert_migrate_gcs_event', 'cc_migration_cron_tick');

/**
 * Tick de cron: procesa un batch si está corriendo.
 */
function cc_migration_cron_tick() {
    $running = get_option('cc_mig_running', '0') === '1';
    if ( ! $running ) {
        return;
    }
    cc_migration_process_batch();
}

// ---------- ADMIN-POST ENDPOINTS (opcionales) ---------- //

add_action('admin_init', function() {
    if ( current_user_can('manage_options') ) {
        $start_url  = wp_nonce_url( admin_url('admin-post.php?action=cc_mig_start'),  'cc_mig_nonce' );
        $stop_url   = wp_nonce_url( admin_url('admin-post.php?action=cc_mig_stop'),   'cc_mig_nonce' );
        $status_url = wp_nonce_url( admin_url('admin-post.php?action=cc_mig_status'), 'cc_mig_nonce' );
        error_log('CC_MIG: Admin URLs -> start: '.$start_url.' | stop: '.$stop_url.' | status: '.$status_url);
    }
});

add_action('admin_post_cc_mig_start', function() {
    if ( ! current_user_can('manage_options') || ! wp_verify_nonce( $_GET['nonce'] ?? '', 'cc_mig_nonce') ) {
        wp_die('Not allowed');
    }
    cc_migration_start();
    wp_safe_redirect( admin_url('edit.php?post_type=certificado&page=cc_certificados_settings') );
    exit;
});

add_action('admin_post_cc_mig_stop', function() {
    if ( ! current_user_can('manage_options') || ! wp_verify_nonce( $_GET['nonce'] ?? '', 'cc_mig_nonce') ) {
        wp_die('Not allowed');
    }
    cc_migration_stop();
    wp_safe_redirect( admin_url('edit.php?post_type=certificado&page=cc_certificados_settings') );
    exit;
});

add_action('admin_post_cc_mig_status', function() {
    if ( ! current_user_can('manage_options') || ! wp_verify_nonce( $_GET['nonce'] ?? '', 'cc_mig_nonce') ) {
        wp_die('Not allowed');
    }
    cc_migration_log_status();
    wp_safe_redirect( admin_url('edit.php?post_type=certificado&page=cc_certificados_settings') );
    exit;
});

// ---------- START/STOP/STATUS ---------- //

/**
 * Inicia la migración y programa el cron cada minuto.
 */
function cc_migration_start( $batch_size = null ) {
    $bucket   = get_option('cc_certificados_gcs_bucket', '');
    $key_path = get_option('cc_certificados_gcs_key_path', '');

    if ( ! $bucket || ! $key_path || ! file_exists($key_path) ) {
        error_log('CC_MIG[ERROR]: Config GCS incompleta. bucket='.$bucket.' | key_path='.$key_path);
        return false;
    }

    if ( $batch_size === null ) {
        $batch_size = (int) get_option('cc_mig_batch_size', 300);
        if ( $batch_size <= 0 ) $batch_size = 300;
    } else {
        $batch_size = max(1, (int) $batch_size);
        update_option('cc_mig_batch_size', $batch_size);
    }

    update_option('cc_mig_running', '1');
    update_option('cc_mig_started_at', time());
    update_option('cc_mig_done_count', 0);
    update_option('cc_mig_error_count', 0);

    // Si no hay cursor previo, empieza desde 0
    $last_id = (int) get_option('cc_mig_last_id', 0);
    if ( $last_id < 0 ) update_option('cc_mig_last_id', 0);

    if ( ! wp_next_scheduled('cc_cert_migrate_gcs_event') ) {
        wp_schedule_event( time() + 10, 'cc_every_minute', 'cc_cert_migrate_gcs_event' );
    }
    error_log('CC_MIG: START ok | batch_size='.$batch_size.' | last_id='.get_option('cc_mig_last_id', 0));
    return true;
}

/**
 * Detiene la migración y limpia el cron.
 */
function cc_migration_stop() {
    update_option('cc_mig_running', '0');
    $ts = wp_next_scheduled('cc_cert_migrate_gcs_event');
    if ( $ts ) {
        wp_unschedule_event( $ts, 'cc_cert_migrate_gcs_event' );
    }
    delete_transient('cc_mig_lock');
    error_log('CC_MIG: STOP');
}

/**
 * Loguea status actual.
 */
function cc_migration_log_status() {
    $status = [
        'running'     => get_option('cc_mig_running', '0'),
        'last_id'     => (int) get_option('cc_mig_last_id', 0),
        'batch_size'  => (int) get_option('cc_mig_batch_size', 300),
        'done_count'  => (int) get_option('cc_mig_done_count', 0),
        'error_count' => (int) get_option('cc_mig_error_count', 0),
        'started_at'  => (int) get_option('cc_mig_started_at', 0),
    ];
    error_log('CC_MIG: STATUS ' . wp_json_encode($status));
}

// ---------- CORE: BATCH PROCESS ---------- //

/**
 * Procesa un batch (respeta lock y tiempo).
 */
function cc_migration_process_batch() {
    // Lock para evitar ejecuciones simultáneas
    if ( get_transient('cc_mig_lock') ) {
        error_log('CC_MIG: Lock activo. Saltando tick.');
        return;
    }
    set_transient('cc_mig_lock', 1, 60); // 1 minuto

    $started  = microtime(true);
    $max_secs = 50; // margen para cron de 60s

    $batch_size = (int) get_option('cc_mig_batch_size', 300);
    if ( $batch_size <= 0 ) { $batch_size = 300; }

    $last_id = (int) get_option('cc_mig_last_id', 0);

    $bucket   = get_option('cc_certificados_gcs_bucket', '');
    $key_path = get_option('cc_certificados_gcs_key_path', '');
    if ( ! $bucket || ! $key_path || ! file_exists($key_path) ) {
        error_log('CC_MIG[ERROR]: Config GCS incompleta en tick. Deteniendo.');
        cc_migration_stop();
        delete_transient('cc_mig_lock');
        return;
    }

    // Autoload Google SDK
    $autoload = dirname(__DIR__) . '/vendor/autoload.php';
    if ( file_exists($autoload) ) {
        require_once $autoload;
    } else {
        error_log('CC_MIG[ERROR]: Composer autoload no encontrado en ' . $autoload);
        cc_migration_stop();
        delete_transient('cc_mig_lock');
        return;
    }

    if ( ! class_exists('\\Google\\Cloud\\Storage\\StorageClient') ) {
        error_log('CC_MIG[ERROR]: Google StorageClient no disponible.');
        cc_migration_stop();
        delete_transient('cc_mig_lock');
        return;
    }

    global $wpdb;

    // Obtener siguiente lote de IDs por ID > last_id (eficiente)
    $post_ids = $wpdb->get_col( $wpdb->prepare(
        "SELECT ID
         FROM {$wpdb->posts}
         WHERE post_type = %s
           AND post_status NOT IN ('trash','auto-draft','inherit','revision')
           AND ID > %d
         ORDER BY ID ASC
         LIMIT %d",
        'certificado', $last_id, $batch_size
    ) );

    if ( empty($post_ids) ) {
        error_log('CC_MIG: No hay más posts. Deteniendo.');
        cc_migration_stop();
        delete_transient('cc_mig_lock');
        return;
    }

    // Cliente y bucket
    try {
        $storage = new \Google\Cloud\Storage\StorageClient(['keyFilePath' => $key_path]);
        $bucketObj = $storage->bucket($bucket);
        if ( ! $bucketObj || ! $bucketObj->exists() ) {
            throw new \Exception('Bucket no accesible: '.$bucket);
        }
    } catch (\Throwable $e) {
        error_log('CC_MIG[ERROR]: Init GCS: '.$e->getMessage());
        cc_migration_stop();
        delete_transient('cc_mig_lock');
        return;
    }

    $done_count  = (int) get_option('cc_mig_done_count', 0);
    $error_count = (int) get_option('cc_mig_error_count', 0);

    foreach ( $post_ids as $pid ) {
        // Avanza cursor SIEMPRE (aunque falle), para no quedarte pegado
        update_option('cc_mig_last_id', (int) $pid);

        try {
            $url = get_post_meta($pid, 'pdf_file', true);

            if ( empty($url) ) {
                // nada que migrar
                continue;
            }

            if ( cc_migration_is_gcs_url($url) ) {
                // ya en GCS: marca trazabilidad si quieres
                if ( ! get_post_meta($pid, 'gcs_migrated_at', true) ) {
                    update_post_meta($pid, 'gcs_migrated_at', time());
                    update_post_meta($pid, 'gcs_source', 'gcs');
                    update_post_meta($pid, 'gcs_migration_error', '');
                }
                continue;
            }

            // Solo migramos si el archivo es local y existe en disco
            $local_path = cc_migration_local_path_from_url($url);
            if ( ! $local_path || ! file_exists($local_path) ) {
                $error_count++;
                update_post_meta($pid, 'gcs_migration_error', 'Archivo local no encontrado: '.$url);
                error_log('CC_MIG[WARN]: Archivo no encontrado pid='.$pid.' url='.$url);
                continue;
            }

            // Nombre de objeto en GCS (prefijo para agrupar migrados)
            $base      = basename($local_path);
            $object    = 'legacy/' . $base;

            // Evita colisiones: si existe, añade sufijo con post ID
            $obj = $bucketObj->object($object);
            if ( $obj->exists() ) {
                $object = 'legacy/' . pathinfo($base, PATHINFO_FILENAME) . '-' . $pid . '.' . pathinfo($base, PATHINFO_EXTENSION);
                $obj = $bucketObj->object($object);
            }

            // Subir via stream; sin ACLs (UBLA)
            $source = fopen($local_path, 'r');
            $obj = $bucketObj->upload($source, [
                'name'       => $object,
                'metadata'   => ['contentType' => 'application/pdf'],
            ]);

            if ( ! $obj || ! $obj->exists() ) {
                throw new \Exception('Upload fallido a GCS para '.$object);
            }

            // Nueva URL pública (depende de política del bucket)
            $new_url = 'https://storage.googleapis.com/' . $bucket . '/' . $object;

            // Actualiza meta y trazas
            update_post_meta($pid, 'pdf_file', $new_url);
            update_post_meta($pid, 'gcs_migrated_at', time());
            update_post_meta($pid, 'gcs_source', 'local');
            update_post_meta($pid, 'gcs_migration_error', '');

            // Borra local
            if ( @unlink($local_path) ) {
                error_log('CC_MIG: Migrado y borrado pid='.$pid.' -> '.$new_url);
            } else {
                error_log('CC_MIG[WARN]: Migrado pero no se pudo borrar local pid='.$pid.' path='.$local_path);
            }

            $done_count++;

        } catch (\Throwable $e) {
            $error_count++;
            update_post_meta($pid, 'gcs_migration_error', $e->getMessage());
            error_log('CC_MIG[ERROR]: pid='.$pid.' msg='.$e->getMessage());
        }

        // Respetar ventana de tiempo
        if ( (microtime(true) - $started) > $max_secs ) {
            error_log('CC_MIG: Corte por tiempo. last_id='.$pid.' done_total='.$done_count.' err_total='.$error_count);
            break;
        }
    }

    update_option('cc_mig_done_count',  $done_count);
    update_option('cc_mig_error_count', $error_count);

    delete_transient('cc_mig_lock');
}

// ---------- HELPERS ---------- //

/**
 * Detecta si la URL ya es de GCS.
 */
function cc_migration_is_gcs_url( $url ) {
    $h = parse_url($url, PHP_URL_HOST);
    if ( ! $h ) return false;
    $h = strtolower($h);
    return (
        $h === 'storage.googleapis.com' ||
        str_ends_with($h, '.storage.googleapis.com') ||
        $h === 'storage.cloud.google.com' // por si acaso
    );
}

/**
 * Mapea URL local -> path absoluto en el filesystem.
 * Para URLs como:
 *  https://dominio/wp-content/plugins/cursos-certificados/certificados_salud/archivo.pdf
 */
function cc_migration_local_path_from_url( $url ) {
    $path = parse_url($url, PHP_URL_PATH);
    if ( ! $path ) return '';
    // ABSPATH ya incluye '/voyager/' en tu entorno, por lo que concatenar funciona.
    $abs = ABSPATH . ltrim($path, '/');
    // Normaliza separadores
    $abs = wp_normalize_path($abs);
    return $abs;
}

// ---------- OPCIONAL: AUTO-START AL ACTIVAR (comenta si no quieres) ---------- //
// add_action('plugins_loaded', function() {
//     // Descomenta para arrancar automáticamente en cuanto el plugin cargue:
//     // cc_migration_start();
// });
