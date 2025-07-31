<?php

function cc_formulario_campus() {
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['generar_pdf_salud'])) {
        $datos = cc_capturar_datos_certificado($_POST, 'campus');

        $empresa_id    = $datos['empresa_id'];
        $empresa_titulo = get_the_title($empresa_id);
        $empresa_imagen = get_the_post_thumbnail_url($empresa_id, 'full') ?: plugins_url('assets/certificados_salud/default_background.jpg', __FILE__);
        $contenido_email = get_post_meta($empresa_id, $datos['email_meta'], true);

        $empresa_info = [
            'empresa_titulo' => $empresa_titulo,
            'empresa_imagen' => $empresa_imagen,
            'contenido_email'=> $contenido_email,
        ];

        $pdf_files = [];
        $pdf_links = [];

        foreach ($datos['cursos'] as $curso_id) {
            $curso_nombre = get_the_title($curso_id);
            $intensidad_horaria = get_post_meta($curso_id, '_intensidad_horaria', true);
            $vigencia_certificado = get_post_meta($curso_id, 'fecha_expiracion_certificado', true);

            $datos_curso = [
                'curso_nombre' => $curso_nombre,
                'intensidad_horaria' => $intensidad_horaria,
                'vigencia_certificado' => $vigencia_certificado,
            ];

            $html = cc_generar_html_certificado($datos_curso, $datos, $empresa_info, $empresa_imagen);
            $pdf_filename = "campus_certificado_{$datos['documento']}_{$curso_id}.pdf";
            $plugin_dir = plugin_dir_path(__FILE__);
            $certificados_dir = $plugin_dir . "certificados_campus/";
            $pdf_path = cc_guardar_pdf_certificado($html, $certificados_dir, $pdf_filename);
            $pdf_url = plugins_url("certificados_campus/{$pdf_filename}", __FILE__);

            cc_crear_post_certificado($datos, $datos_curso, $pdf_url, $empresa_info);

            $pdf_files[] = $pdf_path;
            $pdf_links[] = $pdf_url;
        }

        $asunto = "Certificados Campus - " . $datos['nombre'];
        $enviado = cc_enviar_certificados($datos['email'], $empresa_info['contenido_email'], $pdf_files, $asunto);

        if ($enviado) {
            echo '<div class="notice notice-success"><p>Certificados generados y enviados correctamente.</p></div>';
        } else {
            echo '<div class="notice notice-error"><p>Hubo un error al enviar el correo.</p></div>';
        }
        echo '<div class="notice notice-success"><p>Enlaces de descarga:</p><ul>';
        foreach ($pdf_links as $link) {
            echo '<li><a href="' . esc_url($link) . '" target="_blank">Descargar Certificado</a></li>';
        }
        echo '</ul></div>';
    } ?>
    <!-- formulario HTML de campus -->
    <div class="wrap" style="width: 100%; max-width: 600px; text-align: left;">
    <h1>Generar Certificados Campus</h1>
    <form method="post" action="" autocomplete="off">
        <!-- Nombre -->
        <div class="mb-3">
            <label for="nombre_salud">Nombre:</label>
            <input type="text" name="nombre_salud" id="nombre_salud" class="form-control" required>
        </div>
        <!-- Cédula -->
        <div class="mb-3">
            <label for="cedula_salud">Número de documento:</label>
            <input type="text" name="cedula_salud" id="cedula_salud" class="form-control" required>
        </div>
        <!-- Tipo de Documento -->
        <div class="mb-3">
            <label>Tipo de Documento:</label><br>
            <input type="radio" id="doc-cc" name="tipo_documento" value="Cédula de Ciudadanía" checked>
            <label for="doc-cc">Cédula de Ciudadanía</label>
            <input type="radio" id="doc-ppt" name="tipo_documento" value="PPT">
            <label for="doc-ppt">PPT</label>
            <input type="radio" id="doc-ti" name="tipo_documento" value="Tarjeta de Identidad">
            <label for="doc-ti">Tarjeta de Identidad</label>
        </div>
        <!-- Selección de cursos -->
        <div class="mb-3">
            <label for="curso_salud">Cursos:</label>
            <select name="curso_salud[]" id="curso_salud" class="form-select" multiple required>
                <?php
                // Obtener productos (ajusta post_type si usas CPT o Woo)
                $productos = wc_get_products(array(
                    'limit' => -1,
                    'status' => 'publish',
                ));
                foreach ($productos as $producto) {
                    echo '<option value="' . esc_attr($producto->get_id()) . '">' . esc_html($producto->get_name()) . '</option>';
                }
                ?>
            </select>
            <small>Puedes seleccionar varios cursos con Ctrl/Cmd.</small>
        </div>
        <!-- Selección de empresa_dev -->
        <div class="mb-3">
            <label for="empresa_dev">Empresa (Campus):</label>
            <select name="empresa_dev" id="empresa_dev" class="form-control" required>
                <?php
                $empresas = get_posts(array(
                    'post_type' => 'empresa_dev',
                    'posts_per_page' => -1,
                    'post_status' => 'publish',
                ));
                foreach ($empresas as $empresa) {
                    echo '<option value="' . esc_attr($empresa->ID) . '">' . esc_html($empresa->post_title) . '</option>';
                }
                ?>
            </select>
        </div>
        <!-- Email -->
        <div class="mb-3">
            <label for="email_salud">Email:</label>
            <input type="email" name="email_salud" id="email_salud" class="form-control" required>
        </div>
        <div class="mb-3">
            <label for="fecha_expedicion" class="form-label">Fecha de Expedición:</label>
            <input type="date" id="fecha_expedicion" name="fecha_expedicion_certificado" value="<?php echo esc_attr(date('Y-m-d')); ?>" class="form-control" required>
        </div>
        <button type="submit" name="generar_pdf_salud" class="btn btn-primary">Generar Certificados</button>
    </form>
    </div>
    <script>
    jQuery(document).ready(function($) {
        $('#curso_salud').select2({
            placeholder: "Selecciona los cursos",
            allowClear: true,
            width: '100%'
        });
    });
    </script>
<?php
}
