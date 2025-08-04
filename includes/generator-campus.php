<?php

function cc_formulario_campus() {
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['generar_pdf_campus'])) {
        error_log("---- INICIO SUBMIT FORMULARIO CAMPUS ----");

        $datos = cc_capturar_datos_certificado($_POST, 'campus');
        error_log("Datos capturados: " . print_r($datos, true));

        $empresa_id    = $datos['empresa_id'];
        $empresa_titulo = get_the_title($empresa_id);
        $empresa_imagen = get_the_post_thumbnail_url($empresa_id, 'full') ?: plugins_url('assets/certificados_campus/default_background.jpg', __FILE__);
        $contenido_email = get_post_meta($empresa_id, $datos['email_meta'], true);

        $empresa_info = [
            'empresa_titulo' => $empresa_titulo,
            'empresa_imagen' => $empresa_imagen,
            'contenido_email'=> $contenido_email,
        ];
        error_log("Empresa info: " . print_r($empresa_info, true));

        $pdf_files = [];
        $pdf_links = [];

        foreach ($datos['cursos'] as $curso_id) {
            $curso_nombre = get_the_title($curso_id);
            $curso_tipo = $empresa_titulo;
            $intensidad_num = get_post_meta($curso_id, '_intensidad_horaria', true);
            $intensidad_horaria = $intensidad_num . ' horas';
            $vigencia_certificado = get_post_meta($curso_id, 'fecha_expiracion_certificado', true);

            $datos_curso = [
                'curso_nombre' => $curso_nombre,
                'curso_tipo' => $curso_tipo,
                'intensidad_horaria' => $intensidad_horaria,
                'vigencia_certificado' => $vigencia_certificado,
            ];
            error_log("Datos curso ID $curso_id: " . print_r($datos_curso, true));

            // ----- GENERACIÓN DE HTML -----
            $html = cc_generar_html_certificado($datos_curso, $datos, $empresa_info, $empresa_imagen);
            error_log("HTML generado para $curso_id");

            $timestamp = date('Ymd_His');
            $pdf_filename = "campus_certificado_{$datos['documento']}_{$curso_id}_{$timestamp}.pdf";
            $upload_dir = wp_upload_dir();
            $certificados_dir = $upload_dir['basedir'] . '/certificados_campus/';
            $certificados_url = $upload_dir['baseurl'] . '/certificados_campus/';
            $pdf_path = cc_guardar_pdf_certificado($html, $certificados_dir, $pdf_filename);
            error_log("PDF generado: $pdf_path");

            cc_crear_post_certificado($datos, $datos_curso, $pdf_path, $empresa_info);

            $pdf_files[] = $pdf_path;
            $pdf_links[] = $pdf_url;
        }

        $asunto = "Certificados Campus - " . $datos['nombre'];
        error_log("Enviando correo a {$datos['email']} con archivos: " . print_r($pdf_files, true));
        foreach ($pdf_files as $file) {
            error_log("¿El archivo existe?: $file => " . (file_exists($file) ? 'Sí' : 'No'));
        }
        $enviado = cc_enviar_certificados($datos['email'], $empresa_info['contenido_email'], $pdf_files, $asunto);

        if ($enviado) {
            error_log("Correo enviado correctamente.");
            echo '<div class="notice notice-success"><p>Certificados generados y enviados correctamente.</p>';
            echo '<p>Enlaces de descarga:</p><ul>';
            foreach ($pdf_links as $link) {
                echo '<li><a href="' . esc_url($link) . '" target="_blank">Descargar Certificado</a></li>';
            }
            echo '</ul></div>';
        } else {
            error_log("ERROR al enviar correo.");
            echo '<div class="notice notice-error"><p>Hubo un error al enviar el correo.</p></div>';
        }
        error_log("---- FIN SUBMIT FORMULARIO CAMPUS ----");
    }
    ?>
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
            <label for="empresa_campus">Tipo de certificado:</label>
            <select name="empresa_campus" id="empresa_campus" class="form-control" required>
                <?php
                $empresas = get_posts(array(
                    'post_type' => 'empresa_campus',
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
            <input type="date" id="fecha_expedicion" name="fecha_expedicion_certificado" value="<?php echo esc_attr(date('Y-m-d', current_time('timestamp'))); ?>" class="form-control" required>
        </div>
        <button type="submit" name="generar_pdf_campus" class="btn btn-primary">Generar Certificados</button>
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

