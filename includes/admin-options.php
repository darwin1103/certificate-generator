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
add_action('admin_init', 'cc_certificados_registrar_settings');
function cc_certificados_registrar_settings() {
    register_setting('cc_certificados_settings_group', 'cc_certificados_nombre_empresa');
    register_setting('cc_certificados_settings_group', 'cc_certificados_nit_empresa');
    register_setting('cc_certificados_settings_group', 'cc_certificados_gcs_enabled');
    register_setting('cc_certificados_settings_group', 'cc_certificados_gcs_api_key');
}

// 3. Callback para el contenido de la página
function cc_certificados_settings_page_cb() {
    ?>
    <div class="wrap">
        <h1>Ajustes de Certificados</h1>
        <form method="post" action="options.php">
            <?php settings_fields('cc_certificados_settings_group'); ?>
            <?php do_settings_sections('cc_certificados_settings_group'); ?>

            <table class="form-table">
                <tr valign="top">
                    <th scope="row">Nombre de la empresa</th>
                    <td>
                        <input type="text" name="cc_certificados_nombre_empresa" value="<?php echo esc_attr(get_option('cc_certificados_nombre_empresa')); ?>" class="regular-text" />
                    </td>
                </tr>
                <tr valign="top">
                    <th scope="row">NIT de la empresa</th>
                    <td>
                        <input type="text" name="cc_certificados_nit_empresa" value="<?php echo esc_attr(get_option('cc_certificados_nit_empresa')); ?>" class="regular-text" />
                    </td>
                </tr>
                <tr valign="top">
                    <th scope="row">Almacenar certificados en Google Cloud Storage (GCS)</th>
                    <td>
                        <label>
                            <input type="checkbox" id="cc_certificados_gcs_enabled" name="cc_certificados_gcs_enabled" value="1" <?php checked(1, get_option('cc_certificados_gcs_enabled', 0)); ?> />
                            Activar almacenamiento en GCS
                        </label>
                    </td>
                </tr>
                <tr valign="top" id="gcs_api_row" style="<?php echo get_option('cc_certificados_gcs_enabled', 0) ? '' : 'display:none;'; ?>">
                    <th scope="row">API Key de GCS</th>
                    <td>
                        <input type="text" name="cc_certificados_gcs_api_key" value="<?php echo esc_attr(get_option('cc_certificados_gcs_api_key')); ?>" class="regular-text" />
                    </td>
                </tr>
            </table>
            <script>
            document.addEventListener('DOMContentLoaded', function() {
                const gcsCheck = document.getElementById('cc_certificados_gcs_enabled');
                const gcsRow = document.getElementById('gcs_api_row');
                gcsCheck.addEventListener('change', function() {
                    gcsRow.style.display = this.checked ? '' : 'none';
                });
            });
            </script>
            <?php submit_button(); ?>
        </form>
    </div>
    <?php
}