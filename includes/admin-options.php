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

// Registrar opciones
add_action('admin_init', function () {
    register_setting('cc_certificados_settings_group', 'cc_certificados_nombre_empresa');
    register_setting('cc_certificados_settings_group', 'cc_certificados_nit_empresa');
    register_setting('cc_certificados_settings_group', 'cc_certificados_gcs_enabled');
    register_setting('cc_certificados_settings_group', 'cc_certificados_gcs_bucket');
    register_setting('cc_certificados_settings_group', 'cc_certificados_gcs_key_path');

    if (class_exists('WooCommerce')) {
        register_setting('cc_certificados_settings_group', 'cc_certificados_woo_enabled');
    }
});

// Procesar el archivo JSON al guardar
add_action('admin_init', function () {
    error_log('[GCS] Iniciando hook admin_init para procesar archivo JSON...');

    if (
        isset($_POST['option_page']) &&
        $_POST['option_page'] === 'cc_certificados_settings_group' &&
        check_admin_referer('cc_certificados_settings_group-options')
    ) {
        error_log('[GCS] Formulario de settings detectado, procesando...');
        error_log('[GCS] _FILES: ' . print_r($_FILES, true));

        // Solo procesar si realmente hay un archivo subido
        if (!empty($_FILES['cc_certificados_gcs_key']['tmp_name'])) {
            $uploaded_file = $_FILES['cc_certificados_gcs_key'];
            $ext = pathinfo($uploaded_file['name'], PATHINFO_EXTENSION);

            error_log('[GCS] Archivo detectado: ' . $uploaded_file['name']);
            error_log('[GCS] Extensión detectada: ' . $ext);

            if (strtolower($ext) === 'json') {
                $upload_dir = wp_upload_dir();
                $safe_dir = $upload_dir['basedir'] . '/cc_certificados_keys/';
                error_log('[GCS] Directorio destino: ' . $safe_dir);

                if (!file_exists($safe_dir)) {
                    if (wp_mkdir_p($safe_dir)) {
                        error_log('[GCS] Directorio creado con éxito.');
                    } else {
                        error_log('[GCS][ERROR] No se pudo crear el directorio.');
                    }
                }

                // Borrar el anterior solo si vamos a reemplazarlo
                $prev = get_option('cc_certificados_gcs_key_path');
                error_log('[GCS] Archivo anterior registrado en opciones: ' . $prev);

                if ($prev && file_exists($prev)) {
                    if (@unlink($prev)) {
                        error_log('[GCS] Archivo anterior eliminado correctamente.');
                    } else {
                        error_log('[GCS][ERROR] No se pudo eliminar el archivo anterior.');
                    }
                } else {
                    error_log('[GCS] No había archivo anterior o no existe en disco.');
                }

                $target = $safe_dir . 'service-account-' . time() . '.json';
                error_log('[GCS] Ruta final donde se guardará: ' . $target);

                if (move_uploaded_file($uploaded_file['tmp_name'], $target)) {
                    update_option('cc_certificados_gcs_key_path', $target);
                    error_log('[GCS] Archivo movido y opción actualizada con éxito.');
                } else {
                    error_log('[GCS][ERROR] Falló move_uploaded_file desde ' . $uploaded_file['tmp_name']);
                }
            } else {
                error_log('[GCS][ERROR] El archivo no es .json, extensión: ' . $ext);
            }
        } else {
            error_log('[GCS] No se subió un archivo nuevo, manteniendo el existente.');
        }
    } else {
        error_log('[GCS] No es el formulario de ajustes de certificados o nonce inválido.');
    }
});


// Renderizar settings page
function cc_certificados_settings_page_cb() {
    $nombre_empresa = get_option('cc_certificados_nombre_empresa');
    $nit_empresa    = get_option('cc_certificados_nit_empresa');
    $gcs_enabled    = get_option('cc_certificados_gcs_enabled', 0);
    $bucket         = get_option('cc_certificados_gcs_bucket');
    $key_path       = get_option('cc_certificados_gcs_key_path');
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
                        <label>
                            <input type="checkbox" name="cc_certificados_woo_enabled" value="1" <?php checked(1, get_option('cc_certificados_woo_enabled', 0)); ?> />
                            Activar generación de certificados desde WooCommerce
                        </label>
                    </td>
                </tr>
                <?php endif; ?>
                <tr valign="top">
                    <th scope="row">Almacenar en Google Cloud Storage (GCS)</th>
                    <td>
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
                                try {
                                    require_once __DIR__ . '/../vendor/autoload.php';
                                    $storage = new Google\Cloud\Storage\StorageClient(['keyFilePath' => $key_path]);
                                    $bucket_obj = $storage->bucket($bucket);
                                    if ($bucket_obj && $bucket_obj->exists()) {
                                        echo '<br><span style="color:green;font-weight:bold;">Autenticado con GCS ✅</span>';
                                        try {
                                            $object = $bucket_obj->upload('test-content', ['name' => 'test-file.txt']);
                                            echo '<br><span style="color:green;">Test de subida exitoso</span>';
                                            $object->delete();
                                        } catch (Exception $e) {
                                            echo '<br><span style="color:red;">Error al subir test: ' . esc_html($e->getMessage()) . '</span>';
                                        }
                                    } else {
                                        echo '<br><span style="color:red;font-weight:bold;">El bucket no existe o no hay permisos.</span>';
                                    }
                                } catch (Exception $e) {
                                    echo '<br><span style="color:red;font-weight:bold;">Error de autenticación: ' . esc_html($e->getMessage()) . '</span>';
                                }
                            }
                            ?>
                        </td>
                    </tr>
                </table>
            </div>

            <script>
            document.addEventListener('DOMContentLoaded', function() {
                const gcsCheck = document.getElementById('cc_certificados_gcs_enabled');
                const gcsOptions = document.getElementById('gcs_extra_options');
                gcsCheck.addEventListener('change', function() {
                    gcsOptions.style.display = this.checked ? '' : 'none';
                });
            });
            </script>

            <?php submit_button(); ?>
        </form>
    </div>
    <?php
}

