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
 * Filtro temporal para forzar el directorio de subida del key JSON
 */
function cc_cert_filter_upload_dir_keys( $dirs ) {
    $uploads = wp_upload_dir();
    $subdir  = '/cc_certificados_keys';
    $dirs['path']   = $uploads['basedir'] . $subdir;
    $dirs['url']    = $uploads['baseurl'] . $subdir;
    $dirs['subdir'] = $subdir;

    if ( ! file_exists( $dirs['path'] ) ) {
        wp_mkdir_p( $dirs['path'] );
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

        // NUEVO: select que guarda el ID de un post del CPT "empresa"
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

                <!-- NUEVO: Tipo de certificado - woo (select de CPT empresa) -->
                <tr id="woo_tipo_cert_row" valign="top" style="<?php echo $woo_enabled ? '' : 'display:none;'; ?>">
                    <th scope="row">Tipo de certificado - woo</th>
                    <td>
                        <select name="cc_certificados_woo_tipo_cert_empresa_id" class="regular-text">
                            <option value="0">— Selecciona —</option>
                            <?php
                            // Obtener posts del CPT "empresa"
                            $emp_query = new WP_Query([
                                'post_type'              => 'empresa',
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

            <script>
            document.addEventListener('DOMContentLoaded', function() {
                const gcsCheck   = document.getElementById('cc_certificados_gcs_enabled');
                const gcsOptions = document.getElementById('gcs_extra_options');
                if (gcsCheck && gcsOptions) {
                    gcsCheck.addEventListener('change', function() {
                        gcsOptions.style.display = this.checked ? '' : 'none';
                    });
                }

                // Toggle para opciones de WooCommerce
                const wooCheck = document.getElementById('cc_certificados_woo_enabled');
                const wooRow   = document.getElementById('woo_tipo_cert_row');
                if (wooCheck && wooRow) {
                    wooCheck.addEventListener('change', function() {
                        wooRow.style.display = this.checked ? '' : 'none';
                    });
                }
            });
            </script>

            <?php submit_button(); ?>
        </form>
    </div>
    <?php
}
