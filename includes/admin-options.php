<?php
// Agregar subpáginas al menú de "Certificados"
function cc_agregar_subpaginas_certificados() {
    add_submenu_page(
        'edit.php?post_type=certificado',
        'Certificados de Salud',
        'Generar certificados Salud',
        'manage_options',
        'certificados-salud',
        'cc_formulario_salud'
    );

    add_submenu_page(
        'edit.php?post_type=certificado',
        'Certificados de Campus',
        'Generar certificados Campus',
        'manage_options',
        'certificados-campus',
        'cc_formulario_campus'
    );

    add_submenu_page(
        'edit.php?post_type=certificado',
        'Ajustes de Certificados',
        'Ajustes',
        'manage_options',
        'cc_certificados_settings',
        'cc_certificados_settings_page_cb'
    );
}
add_action('admin_menu', 'cc_agregar_subpaginas_certificados');

/**
 * Encola wp-color-picker solo en la pantalla de ajustes del plugin.
 */
add_action('admin_enqueue_scripts', function( $hook ) {
    // Ejemplos de $hook: 'certificado_page_cc_certificados_settings'
    // Habilitamos por hook o por query arg como respaldo.
    $is_settings =
        $hook === 'certificado_page_cc_certificados_settings'
        || ( isset($_GET['page']) && $_GET['page'] === 'cc_certificados_settings' );

    if ( $is_settings ) {
        wp_enqueue_style('wp-color-picker');
        wp_enqueue_script('wp-color-picker');
        error_log('CC_CERT: Enqueued wp-color-picker on settings page. Hook='.$hook);
    }
});

/**
 * Filtro para forzar el directorio de subida del key JSON (sin recursión).
 */
function cc_cert_filter_upload_dir_keys( $dirs ) {
    // $dirs ya trae: basedir, baseurl, path, url, subdir, error
    $subfolder = 'cc_certificados_keys';

    // Construye path/url a partir de basedir/baseurl del mismo $dirs (no uses wp_upload_dir() aquí)
    $dirs['path']   = trailingslashit( $dirs['basedir'] ) . $subfolder;
    $dirs['url']    = trailingslashit( $dirs['baseurl'] ) . $subfolder;
    $dirs['subdir'] = '/' . $subfolder;

    if ( ! is_dir( $dirs['path'] ) ) {
        // Crea el directorio si no existe
        if ( ! wp_mkdir_p( $dirs['path'] ) ) {
            error_log('[GCS][ERROR] No se pudo crear el directorio: ' . $dirs['path']);
        }
    }

    return $dirs;
}


/**
 * Saneador/almacenador del JSON de GCS (se ejecuta al guardar opciones)
 * - Usa wp_handle_upload()
 * - Elimina el anterior si lo reemplazas
 * - Devuelve la ruta final para persistir en el option
 */
function cc_sanitize_and_store_gcs_key( $current_value ) {
    error_log('[GCS] sanitize callback inicio');

    if ( empty( $_FILES['cc_certificados_gcs_key'] ) || ! is_array( $_FILES['cc_certificados_gcs_key'] ) ) {
        error_log('[GCS] No hay $_FILES para el key. Mantener valor previo.');
        return get_option('cc_certificados_gcs_key_path');
    }

    $f = $_FILES['cc_certificados_gcs_key'];

    if ( isset($f['error']) && (int) $f['error'] === UPLOAD_ERR_NO_FILE ) {
        error_log('[GCS] No se subió archivo nuevo. Mantener valor previo.');
        return get_option('cc_certificados_gcs_key_path');
    }

    if ( ! isset($f['error']) || (int) $f['error'] !== UPLOAD_ERR_OK ) {
        error_log('[GCS][ERROR] Código de error de subida: ' . ( isset($f['error']) ? $f['error'] : 'desconocido' ));
        return get_option('cc_certificados_gcs_key_path');
    }

    $ext = strtolower( pathinfo( $f['name'], PATHINFO_EXTENSION ) );
    if ( $ext !== 'json' ) {
        error_log('[GCS][ERROR] El archivo no es .json, extensión: ' . $ext);
        return get_option('cc_certificados_gcs_key_path');
    }

    // Forzar directorio propio
    add_filter('upload_dir', 'cc_cert_filter_upload_dir_keys');
    $overrides = [
        'test_form' => false,
        'mimes'     => [ 'json' => 'application/json' ],
    ];
    $result = wp_handle_upload( $f, $overrides );
    remove_filter('upload_dir', 'cc_cert_filter_upload_dir_keys');

    if ( isset($result['error']) ) {
        error_log('[GCS][ERROR] wp_handle_upload: ' . $result['error']);
        return get_option('cc_certificados_gcs_key_path');
    }

    $new_path = isset($result['file']) ? $result['file'] : '';
    if ( ! $new_path || ! file_exists($new_path) ) {
        error_log('[GCS][ERROR] Ruta final vacía o no existe en disco.');
        return get_option('cc_certificados_gcs_key_path');
    }

    // Eliminar anterior si existe
    $prev = get_option('cc_certificados_gcs_key_path');
    if ( $prev && $prev !== $new_path && file_exists($prev) ) {
        if ( @unlink($prev) ) {
            error_log('[GCS] Archivo anterior eliminado: ' . $prev);
        } else {
            error_log('[GCS][WARN] No se pudo eliminar el archivo anterior: ' . $prev);
        }
    }

    error_log('[GCS] Key guardada en: ' . $new_path);
    return $new_path;
}

/**
 * Sanear color hex. Acepta #RRGGBB o #RGB. Si no es válido, conserva el valor previo.
 */
function cc_sanitize_hex_color_option( $value ) {
    $value = is_string($value) ? trim($value) : '';
    $san   = sanitize_hex_color( $value ); // devuelve '#xxxxxx' o null
    if ( $san === null ) {
        $prev = get_option('cc_certificados_color_empresa', '');
        error_log('CC_CERT[WARN]: Color inválido recibido: '.$value.' | Se conserva: '.$prev);
        return $prev;
    }
    return $san;
}

// Registrar opciones
add_action('admin_init', function () {
    // Texto simple
    register_setting('cc_certificados_settings_group', 'cc_certificados_nombre_empresa', [
        'sanitize_callback' => 'sanitize_text_field',
    ]);
    register_setting('cc_certificados_settings_group', 'cc_certificados_nit_empresa', [
        'sanitize_callback' => 'sanitize_text_field',
    ]);

    // Checkboxes -> 0/1
    register_setting('cc_certificados_settings_group', 'cc_certificados_gcs_enabled', [
        'sanitize_callback' => function( $v ) { return ! empty($v) ? '1' : '0'; },
    ]);

    // WooCommerce (solo si está activo)
    if ( class_exists('WooCommerce') ) {
        register_setting('cc_certificados_settings_group', 'cc_certificados_woo_enabled', [
            'sanitize_callback' => function( $v ) { return ! empty($v) ? '1' : '0'; },
        ]);

        // Select que guarda el ID de un post del CPT "empresa_campus"
        register_setting('cc_certificados_settings_group', 'cc_certificados_woo_tipo_cert_empresa_id', [
            'sanitize_callback' => 'absint',
        ]);
    }

    // Bucket
    register_setting('cc_certificados_settings_group', 'cc_certificados_gcs_bucket', [
        'sanitize_callback' => 'sanitize_text_field',
    ]);

    // Ruta del JSON (se procesa vía sanitize_callback con wp_handle_upload)
    register_setting('cc_certificados_settings_group', 'cc_certificados_gcs_key_path', [
        'sanitize_callback' => 'cc_sanitize_and_store_gcs_key',
    ]);

    // Color empresa (hex)
    register_setting('cc_certificados_settings_group', 'cc_certificados_color_empresa', [
        'sanitize_callback' => 'cc_sanitize_hex_color_option',
        'default'           => '#000000',
    ]);

    // Tamaño de lote de la migración (por cron)
    register_setting('cc_certificados_settings_group', 'cc_mig_batch_size', [
        'sanitize_callback' => function( $v ) {
            $n = absint($v);
            return $n > 0 ? $n : 300; // default 300
        },
        'default' => 300,
    ]);
});

// Renderizar settings page
function cc_certificados_settings_page_cb() {
    $nombre_empresa = get_option('cc_certificados_nombre_empresa');
    $nit_empresa    = get_option('cc_certificados_nit_empresa');
    $gcs_enabled    = get_option('cc_certificados_gcs_enabled', 0);
    $bucket         = get_option('cc_certificados_gcs_bucket');
    $key_path       = get_option('cc_certificados_gcs_key_path');

    $woo_enabled    = class_exists('WooCommerce') ? get_option('cc_certificados_woo_enabled', 0) : 0;
    $woo_tipo_id    = class_exists('WooCommerce') ? absint( get_option('cc_certificados_woo_tipo_cert_empresa_id', 0) ) : 0;

    $color_empresa  = get_option('cc_certificados_color_empresa', '#000000');

    // ===== Datos de MIGRACIÓN (status actual) =====
    $mig_running   = get_option('cc_mig_running', '0') === '1';
    $mig_last_id   = (int) get_option('cc_mig_last_id', 0);
    $mig_batch     = (int) get_option('cc_mig_batch_size', 300);
    $mig_done      = (int) get_option('cc_mig_done_count', 0);
    $mig_errors    = (int) get_option('cc_mig_error_count', 0);
    $mig_started   = (int) get_option('cc_mig_started_at', 0);
    $mig_started_s = $mig_started ? date_i18n('Y-m-d H:i:s', $mig_started) : '—';
    $next_ts       = wp_next_scheduled('cc_cert_migrate_gcs_event');
    $next_s        = $next_ts ? date_i18n('Y-m-d H:i:s', $next_ts) : '—';
    $counts        = wp_count_posts('certificado');
    $total_cert    = $counts && isset($counts->publish) ? (int) $counts->publish : 0;
    $cfg_ok        = $bucket && $key_path && file_exists($key_path);

    // URLs de control admin-post (con nonce)
    $nonce      = wp_create_nonce('cc_mig_nonce');
    $start_url  = admin_url('admin-post.php?action=cc_mig_start&nonce='  . $nonce);
    $stop_url   = admin_url('admin-post.php?action=cc_mig_stop&nonce='   . $nonce);
    $status_url = admin_url('admin-post.php?action=cc_mig_status&nonce=' . $nonce);
    ?>
    <div class="wrap">
        <h1>Ajustes de Certificados</h1>
        <form method="post" action="options.php" enctype="multipart/form-data">
            <?php settings_fields('cc_certificados_settings_group'); ?>
            <?php do_settings_sections('cc_certificados_settings_group'); ?>

            <table class="form-table">
                <tr valign="top">
                    <th scope="row">Nombre de la empresa</th>
                    <td><input type="text" name="cc_certificados_nombre_empresa" value="<?php echo esc_attr($nombre_empresa); ?>" class="regular-text" /></td>
                </tr>
                <tr valign="top">
                    <th scope="row">NIT de la empresa</th>
                    <td><input type="text" name="cc_certificados_nit_empresa" value="<?php echo esc_attr($nit_empresa); ?>" class="regular-text" /></td>
                </tr>

                <!-- Color empresa -->
                <tr valign="top">
                    <th scope="row">Color empresa</th>
                    <td>
                        <input
                            type="text"
                            name="cc_certificados_color_empresa"
                            id="cc_certificados_color_empresa"
                            value="<?php echo esc_attr( $color_empresa ); ?>"
                            class="cc-color-field"
                            data-default-color="#000000"
                            />
                        <p class="description">Selecciona el color corporativo (hex). Se guardará como #RRGGBB.</p>
                    </td>
                </tr>

                <?php if (class_exists('WooCommerce')) : ?>
                <tr valign="top">
                    <th scope="row">Integración con WooCommerce</th>
                    <td>
                        <!-- hidden para asegurar 0 cuando no está checked -->
                        <input type="hidden" name="cc_certificados_woo_enabled" value="0" />
                        <label>
                            <input type="checkbox" id="cc_certificados_woo_enabled" name="cc_certificados_woo_enabled" value="1" <?php checked(1, $woo_enabled); ?> />
                            Activar generación de certificados desde WooCommerce
                        </label>
                    </td>
                </tr>

                <!-- Tipo de certificado - woo (select de CPT empresa_campus) -->
                <tr id="woo_tipo_cert_row" valign="top" style="<?php echo $woo_enabled ? '' : 'display:none;'; ?>">
                    <th scope="row">Tipo de certificado - woo</th>
                    <td>
                        <select name="cc_certificados_woo_tipo_cert_empresa_id" class="regular-text">
                            <option value="0">— Selecciona —</option>
                            <?php
                            // Obtener posts del CPT "empresa_campus"
                            $emp_query = new WP_Query([
                                'post_type'              => 'empresa_campus',
                                'post_status'            => 'publish',
                                'posts_per_page'         => -1,
                                'orderby'                => 'title',
                                'order'                  => 'ASC',
                                'no_found_rows'          => true,
                                'update_post_meta_cache' => false,
                                'update_post_term_cache' => false,
                            ]);

                            if ( $emp_query->have_posts() ) :
                                while ( $emp_query->have_posts() ) : $emp_query->the_post();
                                    $eid   = get_the_ID();
                                    $etitle = get_the_title();
                                    ?>
                                    <option value="<?php echo esc_attr($eid); ?>" <?php selected( $woo_tipo_id, $eid ); ?>>
                                        <?php echo esc_html( $etitle ); ?>
                                    </option>
                                    <?php
                                endwhile;
                                wp_reset_postdata();
                            else :
                                ?>
                                <option value="0" disabled>No hay empresas publicadas</option>
                                <?php
                            endif;
                            ?>
                        </select>
                        <p class="description">Selecciona la “empresa/tipo” que se usará para los certificados generados desde WooCommerce.</p>
                    </td>
                </tr>
                <?php endif; ?>

                <tr valign="top">
                    <th scope="row">Almacenar en Google Cloud Storage (GCS)</th>
                    <td>
                        <!-- hidden para asegurar 0 cuando no está checked -->
                        <input type="hidden" name="cc_certificados_gcs_enabled" value="0" />
                        <label>
                            <input type="checkbox" id="cc_certificados_gcs_enabled" name="cc_certificados_gcs_enabled" value="1" <?php checked(1, $gcs_enabled); ?> />
                            Activar almacenamiento en GCS
                        </label>
                    </td>
                </tr>
            </table>

            <div id="gcs_extra_options" style="<?php echo $gcs_enabled ? '' : 'display:none;'; ?>">
                <table class="form-table">
                    <tr valign="top">
                        <th scope="row">Nombre del bucket</th>
                        <td><input type="text" name="cc_certificados_gcs_bucket" value="<?php echo esc_attr($bucket); ?>" class="regular-text" /></td>
                    </tr>
                    <tr valign="top">
                        <th scope="row">Archivo credenciales JSON</th>
                        <td>
                            <input type="file" name="cc_certificados_gcs_key" accept=".json" />
                            <?php if ($key_path): ?>
                                <br><small>Subido: <code><?php echo esc_html(basename($key_path)); ?></code></small>
                            <?php endif; ?>

                            <?php
                            // Verificación
                            if ($key_path && file_exists($key_path) && $bucket) {
                                $autoload = dirname(__DIR__) . '/vendor/autoload.php'; // desde /includes hacia raíz del plugin
                                if ( ! class_exists('\\Google\\Cloud\\Storage\\StorageClient') && file_exists($autoload) ) {
                                    require_once $autoload;
                                }

                                if ( class_exists('\\Google\\Cloud\\Storage\\StorageClient') ) {
                                    try {
                                        $storage    = new \Google\Cloud\Storage\StorageClient(['keyFilePath' => $key_path]);
                                        $bucket_obj = $storage->bucket($bucket);

                                        if ($bucket_obj && $bucket_obj->exists()) {
                                            echo '<br><span style="color:green;font-weight:bold;">Autenticado con GCS ✅</span>';
                                            try {
                                                $testName = 'cc-cert-test-' . time() . '.txt';
                                                $object   = $bucket_obj->upload('test-content', ['name' => $testName]);
                                                echo '<br><span style="color:green;">Test de subida exitoso (' . esc_html($testName) . ')</span>';
                                                if ($object) { $object->delete(); }
                                            } catch (\Throwable $e) {
                                                echo '<br><span style="color:#d63638;">Error al subir test: ' . esc_html($e->getMessage()) . '</span>';
                                            }
                                        } else {
                                            echo '<br><span style="color:#d63638;font-weight:bold;">El bucket no existe o no hay permisos.</span>';
                                        }
                                    } catch (\Throwable $e) {
                                        echo '<br><span style="color:#d63638;font-weight:bold;">Error de autenticación: ' . esc_html($e->getMessage()) . '</span>';
                                    }
                                } else {
                                    echo '<br><span style="color:#d63638;">Librería de Google Cloud no disponible. Instala dependencias con Composer.</span>';
                                }
                            }
                            ?>
                        </td>
                    </tr>
                </table>
            </div>

            <!-- ===== MIGRACIÓN A GCS ===== -->
            <hr>
            <h2 style="margin-top:24px;">Migración de PDFs a Google Cloud Storage</h2>

            <?php if ( ! $cfg_ok ) : ?>
                <div class="notice notice-error" style="padding:12px;">
                    <p><strong>Config incompleta:</strong> verifica el <em>Bucket</em> y el <em>Key JSON</em> (archivo existente en el servidor) antes de iniciar.</p>
                    <p>Bucket actual: <code><?php echo esc_html($bucket ?: '—'); ?></code><br>
                       Key path: <code><?php echo esc_html($key_path ?: '—'); ?></code></p>
                </div>
            <?php endif; ?>

            <table class="form-table" role="presentation">
                <tr>
                    <th scope="row">Estado</th>
                    <td>
                        <?php if ( $mig_running ) : ?>
                            <span style="display:inline-block;background:#2271b1;color:#fff;padding:2px 8px;border-radius:4px;">En ejecución</span>
                        <?php else : ?>
                            <span style="display:inline-block;background:#777;color:#fff;padding:2px 8px;border-radius:4px;">Detenido</span>
                        <?php endif; ?>
                    </td>
                </tr>
                <tr>
                    <th scope="row">Último ID procesado</th>
                    <td><code><?php echo esc_html( (string) $mig_last_id ); ?></code></td>
                </tr>
                <tr>
                    <th scope="row">Procesados / Errores</th>
                    <td><?php echo esc_html($mig_done); ?> ok &nbsp;|&nbsp; <?php echo esc_html($mig_errors); ?> errores</td>
                </tr>
                <tr>
                    <th scope="row">Total certificados (publicados)</th>
                    <td><?php echo esc_html( (string) $total_cert ); ?></td>
                </tr>
                <tr>
                    <th scope="row">Tamaño del lote</th>
                    <td>
                        <input type="number" min="1" name="cc_mig_batch_size" value="<?php echo esc_attr( $mig_batch ); ?>" />
                        <p class="description">Cantidad de posts procesados por tick de cron (cada ~1 min). Guarda la página para aplicar el cambio.</p>
                    </td>
                </tr>
                <tr>
                    <th scope="row">Iniciado</th>
                    <td><?php echo esc_html($mig_started_s); ?></td>
                </tr>
                <tr>
                    <th scope="row">Siguiente ejecución (cron)</th>
                    <td><?php echo esc_html($next_s); ?></td>
                </tr>
            </table>

            <p style="margin: 16px 0;">
                <a href="<?php echo esc_url( $start_url ); ?>"
                   class="button button-primary"
                   <?php echo $cfg_ok ? '' : 'aria-disabled="true" onclick="return false;"'; ?>>
                    Iniciar migración
                </a>
                <a href="<?php echo esc_url( $stop_url ); ?>" class="button">Detener</a>
                <a href="<?php echo esc_url( $status_url ); ?>" class="button button-secondary">Ver estado (log)</a>
            </p>
            <!-- ===== FIN MIGRACIÓN A GCS ===== -->

            <script>
            document.addEventListener('DOMContentLoaded', function() {
                // Toggle GCS
                const gcsCheck   = document.getElementById('cc_certificados_gcs_enabled');
                const gcsOptions = document.getElementById('gcs_extra_options');
                if (gcsCheck && gcsOptions) {
                    gcsCheck.addEventListener('change', function() {
                        gcsOptions.style.display = this.checked ? '' : 'none';
                    });
                }

                // Toggle Woo opciones
                const wooCheck = document.getElementById('cc_certificados_woo_enabled');
                const wooRow   = document.getElementById('woo_tipo_cert_row');
                if (wooCheck && wooRow) {
                    wooCheck.addEventListener('change', function() {
                        wooRow.style.display = this.checked ? '' : 'none';
                    });
                }

                // Inicializar color picker
                if (window.jQuery && jQuery.fn.wpColorPicker) {
                    jQuery('.cc-color-field').wpColorPicker();
                }
            });
            </script>

            <?php submit_button(); ?>
        </form>
    </div>
    <?php
}
