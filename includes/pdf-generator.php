<?php

// 1. Captura y sanitiza los datos del formulario
function cc_capturar_datos_certificado($post_data, $contexto = 'campus') {
    $datos = [];
    $datos['nombre']           = sanitize_text_field($post_data['nombre_salud']);
    $datos['documento']        = sanitize_text_field($post_data['cedula_salud']);
    $datos['email']            = sanitize_email($post_data['email_salud']);
    $datos['tipo_documento']   = isset($post_data['tipo_documento']) ? sanitize_text_field($post_data['tipo_documento']) : 'Cédula de Ciudadanía';
    $datos['fecha_expedicion'] = sanitize_text_field($post_data['fecha_expedicion_certificado']);
    $datos['cursos']           = isset($post_data['curso_salud']) ? (array) $post_data['curso_salud'] : [];
    // Empresa depende del contexto
    if ($contexto === 'campus') {
        $datos['empresa_id']  = sanitize_text_field($post_data['empresa_dev']);
        $datos['empresa_tipo'] = 'empresa_dev';
        $datos['email_meta']   = '_js_dev_contenido_email';
    } else {
        $datos['empresa_id']  = sanitize_text_field($post_data['empresa_salud']);
        $datos['empresa_tipo'] = 'empresa';
        $datos['email_meta']   = '_contenido_email';
    }
    return $datos;
}

// 2. Renderiza el HTML del certificado
function cc_generar_html_certificado($datos_curso, $datos_persona, $empresa_info, $imagen_fondo) {
    extract($datos_persona);
    extract($datos_curso);
    extract($empresa_info);

    ob_start();
    ?>
    <!DOCTYPE html>
    <html lang="es">
    <head>
        <meta charset="UTF-8">
        <title>Certificado</title>
        <style>
            body { margin: 0; padding: 0; }
            .main { margin-left: 12%; margin-top: 15%; }
            .nombre { font-size: 20px; text-transform: uppercase; border-bottom: 2px solid #000; }
            /* ...más estilos aquí... */
        </style>
    </head>
    <body>
        <img style="width: 100%; position: absolute;" src="<?php echo esc_url($imagen_fondo); ?>" alt="" />
        <div class="main">
            <p>FUNDACIÓN EDUCATIVA CAMPUS <br>NIT: 901386251-7</p>
            <p class="certifica">CERTIFICA QUE:</p>
            <p class="nombre"><?php echo esc_html($nombre); ?></p>
            <p>Identificado(a) con <?php echo esc_html($tipo_documento); ?></p>
            <p>No° : <?php echo esc_html($documento); ?></p>
            <p>Realizó y aprobó el <?php echo esc_html($empresa_titulo ?? $tipo_certificado); ?> de:</p>
            <p class="nombre_curso"><?php echo esc_html($curso_nombre); ?></p>
            <p>Con una intensidad horaria de: <?php echo esc_html($intensidad_horaria); ?></p>
            <p>ESTE CERTIFICADO ES EXPEDIDO EN LA CIUDAD DE FUSAGASUGÁ EL <?php echo esc_html($fecha_expedicion); ?>, ...</p>
            <p>VIGENCIA DE LA PRESENTE CERTIFICACIÓN DE ASISTENCIA ES DE <?php echo esc_html($vigencia_certificado); ?> A PARTIR DE LA GENERACIÓN DE LA MISMA</p>
        </div>
    </body>
    </html>
    <?php
    return ob_get_clean();
}

// 3. Genera y guarda el PDF
function cc_guardar_pdf_certificado($html, $directorio, $nombre_archivo) {
    $options = new Options();
    $options->set('isHtml5ParserEnabled', true);
    $options->set('isRemoteEnabled', true);

    $dompdf = new Dompdf($options);
    $dompdf->loadHtml($html);
    $dompdf->setPaper('A4', 'landscape');
    $dompdf->render();

    if (!file_exists($directorio)) {
        mkdir($directorio, 0755, true);
    }

    $pdf_path = $directorio . $nombre_archivo;
    file_put_contents($pdf_path, $dompdf->output());
    return $pdf_path;
}

// 4. Crea el post certificado y guarda metadatos
function cc_crear_post_certificado($datos_persona, $datos_curso, $pdf_url, $empresa_info) {
    $certificado_id = wp_insert_post([
        'post_type'    => 'certificado',
        'post_title'   => $datos_persona['nombre'] . ' - ' . $datos_curso['curso_nombre'],
        'post_content' => 'Certificado generado automáticamente.',
        'post_status'  => 'publish',
    ]);
    if ($certificado_id) {
        update_post_meta($certificado_id, 'nombre_certificado', $datos_persona['nombre']);
        update_post_meta($certificado_id, 'cedula_certificado', $datos_persona['documento']);
        update_post_meta($certificado_id, 'curso_certificado', $datos_curso['curso_nombre']);
        update_post_meta($certificado_id, 'email_certificado', $datos_persona['email']);
        update_post_meta($certificado_id, 'empresa_certificado', $empresa_info['empresa_titulo']);
        update_post_meta($certificado_id, 'horas', $datos_curso['intensidad_horaria']);
        update_post_meta($certificado_id, 'pdf_file', $pdf_url);
        update_post_meta($certificado_id, 'fecha_expedicion', $datos_persona['fecha_expedicion']);
        update_post_meta($certificado_id, 'fecha_expiracion_certificado', $datos_curso['vigencia_certificado']);
    }
    return $certificado_id;
}

// 5. Envía el correo con los certificados
function cc_enviar_certificados($email, $contenido_email, $pdf_files, $asunto) {
    $headers = ['Content-Type: text/html; charset=UTF-8'];
    return wp_mail($email, $asunto, nl2br($contenido_email), $headers, $pdf_files);
}
