<?php

// Agregar subpáginas al menú de "Certificados"
function cc_agregar_subpaginas_certificados()
{
    // Subpágina para "Certificados de Salud"
    add_submenu_page(
        'edit.php?post_type=certificado', // El menú principal
        'Certificados de Salud',          // Título de la subpágina
        'Generar certificados Salud',          // Título en el menú
        'manage_options',                 // Permisos requeridos
        'certificados-salud',             // Slug de la subpágina
        'cc_formulario_salud'   // Función que se ejecutará
    );

    // Subpágina para "Certificados de Campus"
    add_submenu_page(
        'edit.php?post_type=certificado', // El menú principal
        'Certificados de Campus',         // Título de la subpágina
        'Generar certificados Campus',         // Título en el menú
        'manage_options',                 // Permisos requeridos
        'certificados-campus',            // Slug de la subpágina
        'cc_formulario_campus'  // Función que se ejecutará
    );

    // subpagina ajustes
    add_submenu_page(
        'edit.php?post_type=certificado',   // Menú padre (CPT Certificados)
        'Ajustes de Certificados',          // Título de la página
        'Ajustes',                          // Texto del submenú
        'manage_options',                   // Capacidad
        'cc_certificados_settings',         // Slug
        'cc_certificados_settings_page_cb'  // Callback de contenido
    );
}
add_action('admin_menu', 'cc_agregar_subpaginas_certificados'); // Añadir las subpáginas al hook admin_menu

// 2. Registrar opciones
add_action('admin_init', function() {
    register_setting('cc_certificados_settings_group', 'cc_certificados_nombre_empresa');
    register_setting('cc_certificados_settings_group', 'cc_certificados_nit_empresa');
    register_setting('cc_certificados_settings_group', 'cc_certificados_gcs_enabled');
    register_setting('cc_certificados_settings_group', 'cc_certificados_gcs_bucket');
    register_setting('cc_certificados_settings_group', 'cc_certificados_gcs_key_path');
});
// Añade la opción de woo en la página de settings
add_action('admin_init', function() {
    if (class_exists('WooCommerce')) {
        register_setting('cc_certificados_settings_group', 'cc_certificados_woo_enabled');
    }
});

// 3. Procesar el archivo JSON al guardar la página de settings
add_action('admin_init', function() {
    if (
        isset($_POST['cc_certificados_gcs_enabled']) && 
        isset($_POST['option_page']) && 
        $_POST['option_page'] === 'cc_certificados_settings_group' && 
        check_admin_referer('cc_certificados_settings_group-options')
    ) {
        if (!empty($_FILES['cc_certificados_gcs_key']['tmp_name'])) {
            $uploaded_file = $_FILES['cc_certificados_gcs_key'];
            $ext = pathinfo($uploaded_file['name'], PATHINFO_EXTENSION);
            if ($ext === 'json') {
                $upload_dir = wp_upload_dir();
                $safe_dir = $upload_dir['basedir'] . '/cc_certificados_keys/';
                if (!file_exists($safe_dir)) {
                    wp_mkdir_p($safe_dir);
                }
                // Borra el anterior si existe
                $prev = get_option('cc_certificados_gcs_key_path');
                if ($prev && file_exists($prev)) @unlink($prev);

                $target = $safe_dir . 'service-account-' . time() . '.json';
                move_uploaded_file($uploaded_file['tmp_name'], $target);
                update_option('cc_certificados_gcs_key_path', $target);
            }
        }
    }
});

// 4. Renderizar settings page
// --- Settings callback con upload de keyfile, logs y verificación ---
function cc_certificados_settings_page_cb() {
    // Procesar subida del keyfile si hay un archivo seleccionado
    if (!empty($_FILES['cc_certificados_gcs_key']['tmp_name'])) {
        $uploads_dir = WP_CONTENT_DIR . '/uploads/cc_gcs_keys/';
        if (!file_exists($uploads_dir)) {
            if (mkdir($uploads_dir, 0755, true)) {
                error_log("Directorio para claves GCS creado: $uploads_dir");
            } else {
                error_log("ERROR al crear directorio para claves GCS: $uploads_dir");
            }
        }
        $tmp_name = $_FILES['cc_certificados_gcs_key']['tmp_name'];
        $name = basename($_FILES['cc_certificados_gcs_key']['name']);
        $dest_path = $uploads_dir . $name;
        if (move_uploaded_file($tmp_name, $dest_path)) {
            update_option('cc_certificados_gcs_key_path', $dest_path);
            error_log("Archivo keyfile GCS subido correctamente: $dest_path");
        } else {
            error_log("ERROR al mover el archivo keyfile GCS: $tmp_name => $dest_path");
        }
    }

    $nombre_empresa = get_option('cc_certificados_nombre_empresa');
    $nit_empresa = get_option('cc_certificados_nit_empresa');
    $gcs_enabled = get_option('cc_certificados_gcs_enabled', 0);
    $bucket = get_option('cc_certificados_gcs_bucket');
    $key_path = get_option('cc_certificados_gcs_key_path');

    // LOGS de valores obtenidos
    error_log("Settings - bucket: " . print_r($bucket, true));
    error_log("Settings - key_path: " . print_r($key_path, true));
    error_log("Settings - keyfile exists: " . (file_exists($key_path) ? "SI" : "NO"));

    ?>
    <div class="wrap">
        <h1>Ajustes de Certificados</h1>
        <form method="post" action="" enctype="multipart/form-data">
            <?php settings_fields('cc_certificados_settings_group'); ?>
            <?php do_settings_sections('cc_certificados_settings_group'); ?>

            <table class="form-table">
                <tr valign="top">
                    <th scope="row">Nombre de la empresa</th>
                    <td>
                        <input type="text" name="cc_certificados_nombre_empresa" value="<?php echo esc_attr($nombre_empresa); ?>" class="regular-text" />
                    </td>
                </tr>
                <tr valign="top">
                    <th scope="row">NIT de la empresa</th>
                    <td>
                        <input type="text" name="cc_certificados_nit_empresa" value="<?php echo esc_attr($nit_empresa); ?>" class="regular-text" />
                    </td>
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
                    <th scope="row">Almacenar certificados en Google Cloud Storage (GCS)</th>
                    <td>
                        <label>
                            <input type="checkbox" id="cc_certificados_gcs_enabled" name="cc_certificados_gcs_enabled" value="1" <?php checked(1, $gcs_enabled); ?> />
                            Activar almacenamiento en GCS
                        </label>
                    </td>
                </tr>
                <tbody id="gcs_extra_options" style="<?php echo $gcs_enabled ? '' : 'display:none;'; ?>">
                    <tr valign="top">
                        <th scope="row">Nombre del bucket</th>
                        <td>
                            <input type="text" name="cc_certificados_gcs_bucket" value="<?php echo esc_attr($bucket); ?>" class="regular-text" />
                        </td>
                    </tr>
                    <tr valign="top">
                        <th scope="row">Archivo credenciales JSON (Service Account)</th>
                        <td>
                            <input type="file" name="cc_certificados_gcs_key" accept=".json" />
                            <?php if ($key_path): ?>
                                <br><small>Subido: <code><?php echo esc_html(basename($key_path)); ?></code></small>
                            <?php endif; ?>
                            <?php
                            // Verificación de autenticación si existe el keyfile y bucket
                            if ($key_path && file_exists($key_path) && $bucket) {
                                try {
                                    require_once __DIR__ . '/../vendor/autoload.php';
                                    $storage = new Google\Cloud\Storage\StorageClient([
                                        'keyFilePath' => $key_path,
                                    ]);
                                    error_log('Intentando conectar con el bucket: ' . $bucket);
                                    $bucket_obj = $storage->bucket($bucket);

                                    if ($bucket_obj && $bucket_obj->exists()) {
                                        echo '<br><span style="color:green;font-weight:bold;">Autenticado correctamente con GCS ✅</span>';
                                        // Prueba de subida sin predefinedAcl
                                        try {
                                            $object = $bucket_obj->upload('test-content', [
                                                'name' => 'test-file.txt',
                                            ]);
                                            echo '<br><span style="color:green;">¡Test de subida exitoso!</span>';
                                            $object->delete();
                                        } catch (Exception $e) {
                                            echo '<br><span style="color:red;">Error al subir archivo de prueba: ' . esc_html($e->getMessage()) . '</span>';
                                        }
                                    } else {
                                        echo '<br><span style="color:red;font-weight:bold;">El bucket no existe o no tienes permisos suficientes.</span>';
                                    }
                                } catch (Exception $e) {
                                    echo '<br><span style="color:red;font-weight:bold;">Error de autenticación: ' . esc_html($e->getMessage()) . ' ❌</span>';
                                }

                            } else {
                                if (!$key_path) error_log("No se encontró opción cc_certificados_gcs_key_path");
                                if ($key_path && !file_exists($key_path)) error_log("El key_path no existe en el filesystem: $key_path");
                                if (!$bucket) error_log("No se encontró opción cc_certificados_gcs_bucket");
                            }
                            ?>
                        </td>
                    </tr>
                </tbody>
            </table>
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
