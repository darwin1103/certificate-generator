<?php

//Importar las dependencias necesarias
use Dompdf\Dompdf;
use Dompdf\Options;

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
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Certificado</title>
        <style>
            @page {
                margin: 0;
                padding: 0;
            }
            body {
                margin: 0;
                padding: 0;
                font-family: 'constan', sans-serif;
                color: #2e2e2e;
                width: 100%;
                height: 100vh;
                position: relative;
            }
            .main {
                position: relative;
                z-index: 2;
                width: 50%;
                margin-top: 15%;
                margin-left: 12%;
            }
            .titulo-campus {
                font-size: 26px;
                font-weight: bold;
                margin-bottom: 5px;
                text-align: left;
                color: #162466;
            }
            .nit-campus {
                font-size: 16px;
                font-weight: 400;
                margin-bottom: 32px;
                text-align: left;
                color: #555;
            }
            .certifica {
                font-size: 19px;
                margin-bottom: 12px;
                font-weight: bold;
                color: #152256;
            }
            .nombre {
                font-size: 32px;
                text-transform: uppercase;
                font-weight: bold;
                color: #1c315e;
                border-bottom: 2px solid #1c315e;
                display: inline-block;
                margin-bottom: 8px;
            }
            .dato, .documento {
                font-size: 16px;
                margin: 3px 0;
            }
            .etiqueta {
                font-weight: 600;
            }
            .nombre_curso {
                font-size: 20px;
                font-weight: bold;
                margin: 6px 0 12px 0;
                color: #235397;
            }
            .intensidad {
                margin-bottom: 6px;
                font-size: 16px;
            }
            .aviso {
                font-size: 13px;
                color: #383838;
                margin-top: 16px;
                margin-bottom: 10px;
            }
            .vigencia {
                font-size: 13px;
                color: #005521;
                font-weight: 600;
            }
        </style>
    </head>
    <body>
        <!-- Si quieres la imagen de fondo como <img>, descomenta la siguiente línea, pero dompdf prefiere CSS en @page -->
        <img style="width: 100%; position: absolute; z-index:1;" src="<?php echo esc_url($imagen_fondo); ?>" alt="" />

        <div class="main">
            <div class="titulo-campus">FUNDACIÓN EDUCATIVA CAMPUS</div>
            <div class="nit-campus">NIT: 901386251-7</div>
            <div class="certifica">CERTIFICA QUE:</div>
            <div class="nombre"><?php echo esc_html($nombre); ?></div>
            <div class="dato">Identificado(a) con <span class="etiqueta"><?php echo esc_html($tipo_documento); ?></span></div>
            <div class="documento">No° : <span class="etiqueta"><?php echo esc_html($documento); ?></span></div>
            <div class="dato">Realizó y aprobó el <b><?php echo esc_html($curso_tipo); ?></b> de:</div>
            <div class="nombre_curso"><?php echo esc_html($curso_nombre); ?></div>
            <div class="intensidad">Con una intensidad horaria de: <b><?php echo esc_html($intensidad_horaria); ?></b></div>

            <div class="aviso">
                ESTE CERTIFICADO ES EXPEDIDO EN LA CIUDAD DE FUSAGASUGÁ EL <b><?php echo esc_html($fecha_expedicion); ?></b>.<br>
                LA PRESENTE CERTIFICACIÓN SE EXPIDE MEDIANTE MARCO NORMATIVO PARA LA EDUCACIÓN INFORMAL Y NO CONDUCE A TÍTULO ALGUNO O CERTIFICACIÓN DE APTITUD OCUPACIONAL.
            </div>
            <div class="vigencia">
                VIGENCIA DE LA PRESENTE CERTIFICACIÓN DE ASISTENCIA ES DE <?php echo esc_html($vigencia_certificado); ?> A PARTIR DE LA GENERACIÓN DE LA MISMA.
            </div>
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
