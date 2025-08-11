<?php

//Importar las dependencias necesarias
use Dompdf\Dompdf;
use Dompdf\Options;

// Importar fuentes
$font_path = plugin_dir_path(__FILE__) . 'assets/fonts/constan.ttf';
// Para windows, cambia las \ por /
$font_path = str_replace('\\', '/', $font_path);
// Agrega el prefijo "file://"
$font_path_uri = 'file://'.$font_path;



// 1. Captura y sanitiza los datos del formulario
function cc_capturar_datos_certificado($post_data, $contexto = 'campus') {
    $datos = [];
    $datos['nombre']           = sanitize_text_field($post_data['nombre_salud']);
    $datos['documento']        = sanitize_text_field($post_data['cedula_salud']);
    $datos['email']            = sanitize_email($post_data['email_salud']);
    $datos['tipo_documento']   = isset($post_data['tipo_documento']) ? sanitize_text_field($post_data['tipo_documento']) : 'Cédula de Ciudadanía';
    $datos['fecha_expedicion'] = sanitize_text_field($post_data['fecha_expedicion_certificado']);
    $datos['cursos']           = isset($post_data['curso_salud']) ? (array) $post_data['curso_salud'] : [];
    $datos['email_meta']   = '_contenido_email';
    $datos['email_subject'] = '_email_subject';

    // Empresa depende del contexto
    if ($contexto === 'campus') {
        $datos['empresa_id']  = sanitize_text_field($post_data['empresa_campus']);
        $datos['empresa_tipo'] = 'empresa_campus';
        
    } else {
        $datos['empresa_id']  = sanitize_text_field($post_data['empresa_salud']);
        $datos['empresa_tipo'] = 'empresa';
    }
    return $datos;
}

// 2. Renderiza el HTML del certificado
function cc_generar_html_certificado($datos_curso, $datos_persona, $empresa_info, $imagen_fondo) {
    
    // Fuente DOMPDF: calcula aquí para que exista en el scope
    $font_path = plugin_dir_path(__FILE__) . 'assets/fonts/constan.ttf';
    $font_path = str_replace('\\', '/', $font_path);
    $font_path_uri = 'file://' . $font_path;
    
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
            @font-face {
                font-family: 'constan';
                src: url('<?php echo $font_path_uri; ?>') format('truetype');
                font-weight: normal;
                font-style: normal;
            }
            @page {
                margin: 0;
                padding: 0;
            }
            body {
                margin: 0;
                padding: 0;
                font-family: constan, sans-serif;
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
            .titulo-campus,
            .nit-campus,
            .certifica,
            .nombre,
            .dato,
            .etiqueta,
            .documento,
            .nombre_curso,
            .intensidad,
            .aviso,
            .vigencia {
            font-family: 'constan' !important;
            }
            .titulo-campus {
                font-size: 24px;
                text-transform: uppercase !important;
                font-weight: bold;
                margin-bottom: 5px;
                text-align: left;
            }
            .nit-campus {
                font-size: 16px;
                font-weight: 400;
                margin-bottom: 32px;
                text-align: left;
                color: #555;
            }
            .certifica {
                font-size: 20px;
                margin-bottom: 12px;
                font-weight: bold;
            }
            .nombre {
                font-size: 24px;
                text-transform: uppercase;
                font-weight: bold;
                border-bottom: 2px solid;
                display: inline-block;
                margin-bottom: 8px;
            }
            .dato, .documento {
                font-size: 16px;
                margin: 3px 0;
            }
            .nombre_curso {
                font-size: 20px;
                font-weight: bold;
                margin: 6px 0 12px 0;
            }
            .intensidad {
                margin-bottom: 6px;
                font-size: 16px;
            }
            .aviso {
                font-size: 12px;
                margin-top: 16px;
                margin-bottom: 8px;
            }
            .vigencia {
                font-size: 12px;
                text-transform: uppercase !important;
            }
        </style>
    </head>
    <body>
        <img style="width: 100%; position: absolute; z-index:1;" src="<?php echo esc_url($imagen_fondo); ?>" alt="" />

        <div class="main">
            <div class="titulo-campus"><?php echo get_option('cc_certificados_nombre_empresa'); ?></div>
            <div class="nit-campus">NIT: <?php echo get_option('cc_certificados_nit_empresa'); ?></div>
            <div class="certifica">CERTIFICA QUE:</div>
            <div class="nombre"><?php echo esc_html($nombre); ?></div>
            <div class="dato">Identificado(a) con <span class="etiqueta"><?php echo esc_html($tipo_documento); ?></span></div>
            <div class="documento">No° : <span class="etiqueta"><?php echo esc_html($documento); ?></span></div>
            <div class="dato">Realizó y aprobó el <b><?php echo esc_html($curso_tipo); ?></b> de:</div>
            <div class="nombre_curso" style="font-family: constan;"><?php echo esc_html($curso_nombre); ?></div>
            <div class="intensidad">Con una intensidad horaria de: <b><?php echo esc_html($intensidad_horaria); ?></b></div>

            <div class="aviso">
                ESTE CERTIFICADO ES EXPEDIDO EN LA CIUDAD DE FUSAGASUGÁ EL <?php echo esc_html($fecha_expedicion); ?>.<br>
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
    // 1. Renderizar el PDF
    $options = new Options();
    $options->set('isHtml5ParserEnabled', true);
    $options->set('isRemoteEnabled', true);

    $dompdf = new Dompdf($options);
    $dompdf->loadHtml($html);
    $dompdf->setPaper('A4', 'landscape');
    $dompdf->render();
    $pdf_output = $dompdf->output();

    // 2. Revisar settings GCS
    $usar_gcs    = get_option('cc_certificados_gcs_enabled', false);
    $bucket      = get_option('cc_certificados_gcs_bucket');
    $key_path    = get_option('cc_certificados_gcs_key_path'); // Ojo al nombre correcto aquí

    if ($usar_gcs && $bucket && $key_path && file_exists($key_path)) {
        // --- GUARDAR EN GCS ---
        $public_url = cc_gcs_subir_archivo($pdf_output, $nombre_archivo, $bucket, $key_path);
        if ($public_url) {
            return $public_url;
        }
        // Si hay error, seguir con local como fallback
    }

    // --- GUARDAR LOCAL ---
    if (!file_exists($directorio)) {
        mkdir($directorio, 0755, true);
    }
    $pdf_path = $directorio . $nombre_archivo;
    file_put_contents($pdf_path, $pdf_output);

    // Devuelve la URL pública local (solo si está en uploads)
    $upload_dir = wp_upload_dir();
    if (strpos($pdf_path, $upload_dir['basedir']) === 0) {
        $relative = str_replace($upload_dir['basedir'], '', $pdf_path);
        return $upload_dir['baseurl'] . $relative;
    }
    // Como último recurso, devuelve la ruta absoluta
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
/**
 * Envía un email con enlaces de descarga en botones (no adjuntos).
 * @param string $to           Destinatario
 * @param string $mensaje_base Contenido base del email (puedes usar HTML)
 * @param array  $pdf_links    Array de URLs públicas de certificados
 * @param string $asunto       Asunto del email
 * @return bool
 */
function cc_enviar_certificados($to, $mensaje_base, $pdf_links, $asunto = 'Certificados Generados') {
    // Construir el bloque de botones
    $botones = '';
    foreach ($pdf_links as $i => $url) {
        $botones .= '
        <div style="margin:18px 0;">
            <a href="'.esc_url($url).'" 
                style="
                    display:inline-block;
                    background-color:#2e77e5;
                    color:#fff;
                    padding:12px 24px;
                    border-radius:6px;
                    text-decoration:none;
                    font-size:16px;
                    font-weight:bold;
                    margin-bottom: 8px;
                " 
                target="_blank"
            >Descargar Certificado '.($i+1).'</a>
        </div>';
    }

    // Armar el mensaje final (puedes personalizar el saludo y el footer)
    $mensaje = '
        <div style="font-family:sans-serif;">
            <p>¡Hola!<br>Tu(s) certificado(s) han sido generados exitosamente.</p>
            '.wpautop($mensaje_base).'
            '.$botones.'
            <p style="margin-top:32px;color:#888;font-size:13px;">Si tienes dudas, responde a este correo.<br>Equipo de Certificados</p>
        </div>
    ';

    // Preparar headers
    $headers = ['Content-Type: text/html; charset=UTF-8'];

    // Enviar el correo (sin adjuntos)
    return wp_mail($to, $asunto, $mensaje, $headers);
}
