<?php
// Agregar la página al menú de administración
add_action('admin_menu', 'cc_agregar_pagina_certificados');

function cc_agregar_pagina_certificados(){
    add_menu_page(
        'Generar Certificados',             // Título de la página
        'Generar Certificados',             // Texto del menú
        'manage_options',                   // Capacidad requerida
        'cc_generar_certificados',          // Slug de la página
        'cc_renderizar_pagina_certificados' // Función para renderizar la página
    );
 }

// Función para renderizar la página de generar certificados
function cc_renderizar_pagina_certificados(){
    /*Formatear imagenes para dompdf */
    function image_to_base64($image_path)
    {
        $image_type = pathinfo($image_path, PATHINFO_EXTENSION);
        $image_data = file_get_contents($image_path);
        $base64 = 'data:image/' . $image_type . ';base64,' . base64_encode($image_data);
        return $base64;
    }

    // Verificar si el formulario ha sido enviado y procesar los datos
    if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['submit'])) {
        // Lógica para procesar el formulario
        if (isset($_POST['cursos']) && is_array($_POST['cursos'])) {
            $cursos_seleccionados = $_POST['cursos'];
            $mensajes = array();

            foreach ($cursos_seleccionados as $curso_id) {

                //Obtener intensidad horaria y tiempo de expiracion segun el tipo de curso
                $terms = get_the_terms($curso_id, 'categoria-curso');

                if ($terms && !is_wp_error($terms)) {

                    // Iteramos sobre los términos
                    foreach ($terms as $term) {
                        // Comprobamos el nombre del término y asignamos el valor correspondiente
                        switch ($term->slug) {
                            case 'basico':
                                $intensidad_horaria = 45;
                                $exp = '+1 year';
                                $años_expiracion = '1 AÑO';
                                break;
                            case 'avanzado':
                                $intensidad_horaria = 70;
                                $exp = '+2 year';
                                $años_expiracion = '2 AÑOS';
                                break;
                            case 'diplomado':
                                $intensidad_horaria = 160;
                                $exp = '+3 year';
                                $años_expiracion = '3 AÑOS';
                                break;
                            default:
                                // Si el término no coincide con ninguno de los casos anteriores, asignamos un valor por defecto
                                $intensidad_horaria = 45;
                                break;
                        }
                    }
                }

                // Crear un nuevo post en el tipo de publicación personalizada "Certificados"
                $post_args = array(
                    'post_title'    => $_POST['nombre'] . " - " . get_the_title($curso_id), // Título del post
                    'post_type'     => 'certificado', // Tipo de publicación personalizada
                    'post_status'   => 'publish', // Estado del post
                    'post_date' => date('Y-m-d H:i:s', strtotime(sanitize_text_field($_POST['fecha_expedicion_certificado']))),
                );

                $post_id = wp_insert_post($post_args); // Insertar el post y obtener su ID

                if ($post_id) {

                    // Llenar el campo de nombre
                    if (!empty($_POST['nombre'])) {
                        update_post_meta($post_id, 'nombre_certificado', sanitize_text_field($_POST['nombre']));
                    }

                    // Llenar el campo de cédula
                    if (!empty($_POST['cedula'])) {
                        update_post_meta($post_id, 'cedula_certificado', sanitize_text_field($_POST['cedula']));
                    }

                    // Llenar el campo de email
                    if (!empty($_POST['email'])) {
                        update_post_meta($post_id, 'email_certificado', sanitize_email($_POST['email']));
                    }

                    // Llenar el campo del curso
                    update_post_meta($post_id, 'curso_certificado', get_the_title($curso_id));

                    // Llenar el campo de intensidad horaria
                    update_post_meta($post_id, 'horas', $intensidad_horaria);

                    //Llenar el campo de fecha de expiracion
                    $post = get_post($post_id);
                    $post_date = $post->post_date;
                    $fecha_expiracion = date('Y-m-d H:i:s', strtotime($exp, strtotime($post_date)));
                    update_post_meta($post_id, 'fecha_expiracion_certificado', $fecha_expiracion);

                    /*GENERAR CERTIFICADO PDF */

                    switch (get_site_domain()) {
                        case 'medicplus.com':
                            $image_path = plugin_dir_path(__FILE__) . 'assets/certificado-blank_page-0001.jpg';
                            break;
                        case 'certificadosensaludplus.com':
                            $image_path = plugin_dir_path(__FILE__) . 'assets/certificado-blank_page-0002.jpg';
                            break;
                        case 'certimedicas.com':
                            $image_path = plugin_dir_path(__FILE__) . 'assets/certificado-blank_page-0003.jpg';
                            break;
                        case 'certisaludplus.com':
                            $image_path = plugin_dir_path(__FILE__) . 'assets/certificado-blank_page-0004.jpg';
                            break;
                        default:
                            $image_path = plugin_dir_path(__FILE__) . 'assets/certificado-blank_page-0001.jpg';
                            break;
                    }

                    $image_base64 = image_to_base64($image_path);
                    $original_date = $_POST['fecha_expedicion_certificado'];
                    $date_format = date('d/m/Y', strtotime($original_date));

                    switch (get_site_domain()) {
                        case 'medicplus.com':
                            $dir = plugin_dir_path(__FILE__) . 'fonts/';
                            $files = glob($dir . '*.ttf');

                            $css = '';
                            foreach ($files as $file) {
                                $fontFamily = basename($file, '.ttf');
                                $css .= "@font-face {
                                    font-family: '{$fontFamily}';
                                    src: url('{$file}') format('truetype');
                                }\n";
                            }

                            $html_content = '
                                <!DOCTYPE html>
                                <html lang="en">
                                <head>
                                    <meta charset="UTF-8">
                                    <meta name="viewport" content="width=device-width, initial-scale=1.0">
                                    <title>Document</title>
                                    <style>

                                    ' . $css . '
                                        @page {
                                            margin: 0;
                                        }
                                        </style>
                                </head>
                                <body style="width: 100%">
                                    <img style="width: 100%; position: absolute;" src="' . $image_base64 . '" alt=" " />
                                    <div style="width: 100%; z-index:999;" class="main">
                                        <p style="font-family: Britannic Bold Regular; text-align: center; font-size: 52px; margin-bottom: 0px;" >MEDICPLUS SAS</p>
                                        <p style="font-family: Britannic Bold Regular; text-align: center; font-size: 32px; margin-top: -20px;" >NIT: 901800420-1</p>
                                        <p style="font-family: Britannic Bold Regular; text-align: center; font-size: 32px; margin-top: -20px;" >CERTIFICA QUE:</p>
                                        <p style="font-family: Britannic Bold Regular; text-align: center; font-size: 40px; margin-top: -20px; text-transform: uppercase;" >' . $_POST['nombre'] . '</p>
                                        <p style="font-family: Britannic Bold Regular; text-align: center; font-size: 30px; margin-top: -20px;" >Identificado(a) con ' . $_POST['documento'] . ' N°' . $_POST['cedula'] . '</p>
                                        <p style="font-family: Britannic Bold Regular; text-align: center; font-size: 32px; margin-top: -20px;" >Asistió al curso de:</p>
                                        <p style="font-family: LucidaHandwritingStdRg; text-align: center; font-size: 22px; margin: -20px auto; width: 90%; color: rgb(79 129 189);" >' . get_the_title($curso_id) . '</p>
                                        <p style="font-family: Britannic Bold Regular; text-align: center; font-size: 32px; color: rgb(5, 0, 48);" > Con una intensidad horaria de ' . $intensidad_horaria . ' horas</p>
                                        <p style="width: 75%; font-size: 12px; text-align: center; margin: -20px auto 0; color: rgb(5, 0, 48);">ESTE CERTIFICADO ES EXPEDIDO EN LA CIUDAD DE BOGOTA EL ' . $date_format . ', LA PRESENTE CERTIFICACIÓN SE EXPIDE MEDIANTE MARCO NORMATIVO PARA LA EDUCACIÓN INFORMAL Y NO CONDUCE A TITULO ALGUNO O CERTIFICACIÓN DE APTITUD OCUPACIONAL, ESTA CERTIFICACIÓN TIENE VIGENCIA DE ' . $años_expiracion . ' A PARTIR DE LA GENERACIÓN DE LA MISMA.</p>
                                    </div>
                                </body>
                                </html>';
                            $mensaje_email = '
                            <p>Hola   CORDIAL SALUDO DE MEDIC PLUS<br>
                            Gracias por confiar en nosotros y hacernos parte de tu proceso de formacion.<br><br>

                            Te queremos contar que adjunto encontraras tus certificados en formato PDF para que los puedas descargar e imprimir<br><br>

                            Adicional tu plataforma ACADEMICA Q10 quedara habilitada al FINALIZAR EL DÍA.<br><br>

                            <strong>IMPORTANTE</strong>
                            Al finalizar el día cuando llegue el link<br>
                            Sigue estos pasos para poder acceder a tus cursos:</p>
                            <ol>
                                <li>Te va a llegar a tu CORREO un mensaje directamente de PLATAFORMA Q10 en el asunto "ACTIVAR MI CUENTA"</li>
                                <li>Presiona el botón ACTIVAR MI CUENTA establece tu contraseña para ingreso a plataforma (5 caracteres como mínimo)</li>
                                <li>Regresas de nuevo al correo de Q10 y le das click en INGRESAR A Q10</li>
                                <li>Usuario (Correo que escribiste en la inscripción)</li>
                                <li>Contraseña la que estableciste en el paso #2</li>
                            </ol>
                            <p>¡Listo! Comienza a tomar tus cursos<br><br>
                            ¡Muchas gracias por preferirnos!                        
                            </p>
                            ';
                            break;
                        case 'certificadosensaludplus.com':
                            $dir = plugin_dir_path(__FILE__) . 'fonts/';
                            $files = glob($dir . '*.ttf');

                            $css = '';
                            foreach ($files as $file) {
                                $fontFamily = basename($file, '.ttf');
                                $css .= "@font-face {
                                    font-family: '{$fontFamily}';
                                    src: url('{$file}') format('truetype');
                                }\n";
                            }

                            $html_content = '
                                <!DOCTYPE html>
                                <html lang="en">
                                <head>
                                    <meta charset="UTF-8">
                                    <meta name="viewport" content="width=device-width, initial-scale=1.0">
                                    <title>Document</title>
                                    <style>

                                    ' . $css . '
                                        @page {
                                            margin: 0;
                                        }
                                        </style>
                                </head>
                                <body style="width: 100%">
                                    <img style="width: 100%; position: absolute;" src="' . $image_base64 . '" alt=" " />
                                    <div style="width: 100%; z-index:999;" class="main">
                                        <p style="font-family: Britannic Bold Regular; text-align: center; font-size: 52px; margin-bottom: 0px;" >CERTIFICADOS EN SALUD SAS</p>
                                        <p style="font-family: Britannic Bold Regular; text-align: center; font-size: 32px; margin-top: -20px;" >NIT: 901.804.852-8</p>
                                        <p style="font-family: Britannic Bold Regular; text-align: center; font-size: 32px; margin-top: -20px;" >CERTIFICA QUE:</p>
                                        <p style="font-family: Britannic Bold Regular; text-align: center; font-size: 40px; margin-top: -20px; text-transform: uppercase;" >' . $_POST['nombre'] . '</p>
                                        <p style="font-family: Britannic Bold Regular; text-align: center; font-size: 30px; margin-top: -20px;" >Identificado(a) con ' . $_POST['documento'] . ' N°' . $_POST['cedula'] . '</p>
                                        <p style="font-family: Britannic Bold Regular; text-align: center; font-size: 32px; margin-top: -20px;" >Asistió al curso de:</p>
                                        <p style="font-family: LucidaHandwritingStdRg; text-align: center; font-size: 22px; margin: -20px auto; width: 90%; color: rgb(79 129 189);" >' . get_the_title($curso_id) . '</p>
                                        <p style="font-family: Britannic Bold Regular; text-align: center; font-size: 32px; color: rgb(5, 0, 48);" > Con una intensidad horaria de ' . $intensidad_horaria . ' </p>
                                        <p style="width: 75%; font-size: 12px; text-align: center; margin: -20px auto 0; color: rgb(5, 0, 48);">ESTE CERTIFICADO ES EXPEDIDO EN LA CIUDAD DE BOGOTA EL ' . $date_format . ', LA PRESENTE CERTIFICACIÓN SE EXPIDE MEDIANTE MARCO NORMATIVO PARA LA EDUCACIÓN INFORMAL Y NO CONDUCE A TITULO ALGUNO O CERTIFICACIÓN DE APTITUD OCUPACIONAL, ESTA CERTIFICACIÓN TIENE VIGENCIA DE ' . $años_expiracion . ' A PARTIR DE LA GENERACIÓN DE LA MISMA.</p>
                                    </div>
                                </body>
                                </html>
                                ';
                            $mensaje_email = '
                            <p>Cordial saludo amig@ - Adjunto encontraras archivo pdf con tu certificados, DESCARGA E IMPRIME<br><br>

                            AQUI ENCUENTRAS LOS PASOS A SEGUIR PARA CUANDO TE LLEGUE EL LINK EMITIDO DIRECTAMENTE DE LA PLATAFORMA Q10 (4 horas habiles) - tu Usuario es : (correo electrónico).<br><br>

                            <strong>IMPORTANTE</strong>
                            Te invitamos a que sigas los siguientes pasos para crear la contraseña:</p>
                            <ol>
                                <li>Al correo electrónico llego la información de activación de la plataforma Q10 con un ASUNTO : (CONFIRME SU CORREO ELECTRÓNICO PARA ACTIVAR SU CUENTA) revisar correo spam o no deseado.</li>
                                <li>Cuando ingreses al correo presiona el botón  (ACTIVAR MI CUENTA) oprime en restablecer contraseña para así ingresar a la plataforma Q10 (la contraseña debe tener mínimo 5 caracteres).</li>
                                <li>Oprimes confirmar.</li>
                                <li>Y repites el ingreso con la nueva contraseña y el usuario.</li>
                            </ol>
                            <p>Cualquier duda o inquietud no dudes en escribirnos quedamos atentos. Bienvenido a la Familia CERTIFICADOS EN SALUDs</p>
                            ';
                            break;
                        case 'certimedicas.com':
                            $dir = plugin_dir_path(__FILE__) . 'fonts/';
                            $files = glob($dir . '*.ttf');

                            $css = '';
                            foreach ($files as $file) {
                                $fontFamily = basename($file, '.ttf');
                                $css .= "@font-face {
                                    font-family: '{$fontFamily}';
                                    src: url('{$file}') format('truetype');
                                }\n";
                            }

                            $html_content = '
                                <!DOCTYPE html>
                                <html lang="en">
                                <head>
                                    <meta charset="UTF-8">
                                    <meta name="viewport" content="width=device-width, initial-scale=1.0">
                                    <title>Document</title>
                                    <style>

                                    ' . $css . '
                                        @page {
                                            margin: 0;
                                        }
                                        </style>
                                </head>
                                <body style="width: 100%">
                                    <img style="width: 100%; position: absolute;" src="' . $image_base64 . '" alt=" " />
                                    <div style="width: 100%; z-index:999;" class="main">
                                        <p style="font-family: Britannic Bold Regular; text-align: center; font-size: 52px; margin-bottom: 0px; color: rgb(5, 0, 48);" >CERTIMEDICAS</p>
                                        <p style="font-family: Britannic Bold Regular; text-align: center; font-size: 32px; margin-top: -20px; color: rgb(5, 0, 48);" >NIT: 901 732 310-8</p>
                                        <p style="font-family: Britannic Bold Regular; text-align: center; font-size: 32px; margin-top: -20px; color: rgb(5, 0, 48);" >CERTIFICA QUE:</p>
                                        <p style="font-family: LucidaHandwritingStdRg; text-align: center; font-size: 40px; margin-top: -20px; color: rgb(79 129 189); text-transform: uppercase;" >' . $_POST['nombre'] . '</p>
                                        <p style="font-family: Britannic Bold Regular; text-align: center; font-size: 30px; margin-top: -20px; color: rgb(5, 0, 48);" >Identificado(a) con ' . $_POST['documento'] . ' N°' . $_POST['cedula'] . '</p>
                                        <p style="font-family: Britannic Bold Regular; text-align: center; font-size: 32px; margin-top: -20px; color: rgb(5, 0, 48);" >Asistió al curso de:</p>
                                        <p style="font-family: LucidaHandwritingStdRg; text-align: center; font-size: 22px; margin: -20px auto; width: 90%; color: rgb(79 129 189);" >' . get_the_title($curso_id) . '</p>
                                        <p style="font-family: Britannic Bold Regular; text-align: center; font-size: 32px; color: rgb(5, 0, 48);" > Con una intensidad horaria de ' . $intensidad_horaria . ' horas</p>
                                        <p style="width: 75%; font-size: 12px; text-align: center; margin: -20px auto 0; color: rgb(5, 0, 48);">ESTE CERTIFICADO ES EXPEDIDO EN LA CIUDAD DE BOGOTA EL ' . $date_format . ', LA PRESENTE CERTIFICACIÓN SE EXPIDE MEDIANTE MARCO NORMATIVO PARA LA EDUCACIÓN INFORMAL Y NO CONDUCE A TITULO ALGUNO O CERTIFICACIÓN DE APTITUD OCUPACIONAL, ESTA CERTIFICACIÓN TIENE VIGENCIA DE ' . $años_expiracion . ' A PARTIR DE LA GENERACIÓN DE LA MISMA.</p>
                                    </div>
                                </body>
                                </html>
                                ';
                            $mensaje_email = '
                            <p>De antemano agradecerte por elegirnos, para hacer parte de tu proceso de formación, adjunto encontraras archivo con tu certificado.<br><br>

                            CUANDO RECIBAS EL LINK (2 HORAS APROX)<br><br>

                            <strong>PARA TENER EN CUENTA:</strong>
                            Para activar debes seguir los siguientes pasos:</p>
                            <ol>
                                <li>Te llega un correo electrónico directamente de la plataforma educativa Q10, asunto "CONFIRME SU CORREO ELECTRÓNICO PARA ACTIVAR SU CUENTA"</li>
                                <li>Presiona el botón "ACTIVAR MI CUENTA" escribes o estableces tu contraseña para ingresar a la plataforma educativa (mínimo 5 carácteres), confirmamos repetimos la contraseña.</li>
                                <li>Regresamos nuevamente al correo electrónico de Q10, y damos click en INGRESAR A Q10.</li>
                                <li>USUARIO:  TU CORREO SUMINISTRADO</li>
                                <li>CONTRASEÑA: la establecida en el paso 2.</li>
                            </ol>
                            <p>Quedó atenta a cualquier inquietud o duda respecto a la plataforma educativa.<br>
                            <strong>NOTA:</strong> los certificados se enviarán por correo electrónico en el transcurso del día.<br><br>
                            
                            <strong>IMPORTANTE</strong><br><br>

                            Te recuerdo que una vez habilitada la plataforma tienes 90 días para realizar los cursos si pasado ese tiempo no los realizas estos quedan inhabilitados.<br><br>

                            Te enviamos los certificados porque entendemos que sean para temas laborales con el compromiso que estos se realicen y culmines el proceso de auto-formación.<br><br>

                            Cordialmente,Felipe Villafañe<br>
                            Certimedicas Colombia S.A.S.Cel 320 268 23 83</p>
                            ';
                            break;
                        case 'certisaludplus.com':
                            $dir = plugin_dir_path(__FILE__) . 'fonts/';
                            $files = glob($dir . '*.ttf');

                            $css = '';
                            foreach ($files as $file) {
                                $fontFamily = basename($file, '.ttf');
                                $css .= "@font-face {
                                    font-family: '{$fontFamily}';
                                    src: url('{$file}') format('truetype');
                                }\n";
                            }

                            $html_content = '
                                <!DOCTYPE html>
                                <html lang="en">
                                <head>
                                    <meta charset="UTF-8">
                                    <meta name="viewport" content="width=device-width, initial-scale=1.0">
                                    <title>Document</title>
                                    <style>

                                    ' . $css . '
                                        @page {
                                            margin: 0;
                                        }
                                        </style>
                                </head>
                                <body style="width: 100%">
                                    <img style="width: 100%; position: absolute;" src="' . $image_base64 . '" alt=" " />
                                    <div style="width: 100%; z-index:999;" class="main">
                                        <p style="font-family: Britannic Bold Regular; text-align: center; font-size: 52px; margin-bottom: 0px; color: rgb(5, 0, 48);" >CERTISALUD</p>
                                        <p style="font-family: Britannic Bold Regular; text-align: center; font-size: 32px; margin-top: -20px; color: rgb(5, 0, 48);" >NIT: 901760895-3</p>
                                        <p style="font-family: Britannic Bold Regular; text-align: center; font-size: 32px; margin-top: -20px; color: rgb(5, 0, 48);" >CERTIFICA QUE:</p>
                                        <p style="font-family: LucidaHandwritingStdRg; text-align: center; font-size: 40px; margin-top: -20px; color: rgb(79 129 189); text-transform: uppercase;" >' . $_POST['nombre'] . '</p>
                                        <p style="font-family: Britannic Bold Regular; text-align: center; font-size: 30px; margin-top: -20px; color: rgb(5, 0, 48);" >Identificado(a) con ' . $_POST['documento'] . ' N°' . $_POST['cedula'] . '</p>
                                        <p style="font-family: Britannic Bold Regular; text-align: center; font-size: 32px; margin-top: -20px; color: rgb(5, 0, 48);" >Asistió al curso de:</p>
                                        <p style="font-family: LucidaHandwritingStdRg; text-align: center; font-size: 22px; margin: -20px auto; width: 90%; color: rgb(79 129 189);" >' . get_the_title($curso_id) . '</p>
                                        <p style="font-family: Britannic Bold Regular; text-align: center; font-size: 32px; color: rgb(5, 0, 48);" > Con una intensidad horaria de ' . $intensidad_horaria . ' horas</p>
                                        <p style="width: 75%; font-size: 12px; text-align: center; margin: -20px auto 0; color: rgb(5, 0, 48);">ESTE CERTIFICADO ES EXPEDIDO EN LA CIUDAD DE BOGOTA EL ' . $date_format . ', LA PRESENTE CERTIFICACIÓN SE EXPIDE MEDIANTE MARCO NORMATIVO PARA LA EDUCACIÓN INFORMAL Y NO CONDUCE A TITULO ALGUNO O CERTIFICACIÓN DE APTITUD OCUPACIONAL, ESTA CERTIFICACIÓN TIENE VIGENCIA DE ' . $años_expiracion . ' A PARTIR DE LA GENERACIÓN DE LA MISMA.</p>
                                    </div>
                                </body>
                                </html>
                                ';
                            $mensaje_email = '
                            <p>Hola Somos CertiSalud+ tu mejor opción para educarte.¡Gracias por confiar en nosotros!<br><br>

                            Te queremos contar que adjunto encontraras tus certificados en formato PDF para que los puedas descargar e imprimir<br><br>

                            Adicional tu plataforma ACADEMICA Q10 quedará habilitada al FINALIZAR EL DÍA.<br><br>

                            <strong>IMPORTANTE:</strong>
                            Al finalizar el día cuando te llegue el link.Sigue estos pasos para poder acceder a tus cursos:</p>
                            <ol>
                                <liTe va a llegar a tu CORREO un mensaje directamente de PLATAFORMA Q10 en el asunto "ACTIVAR MI CUENTA"</li>
                                <li>Presiona el botón ACTIVAR MI CUENTA establece tu contraseña para ingreso a plataforma (5 caracteres como mínimo)</li>
                                <li>Regresas de nuevo al correo de Q10 y le das click en INGRESAR A Q10</li>
                                <li>Usuario (Correo que escribiste en la inscripción)</li>
                                <li>Contraseña la que estableciste en el paso #2</li>
                            </ol>
                            <p>¡Listo! Comienza a tomar tus cursos<br><br>
                            
                            ¡Muchas gracias por preferirnos! Esperamos poder servirte nuevamente, Recuerda seguirnos en nuestras redes sociales y regalarnos un like en <a href="https://www.instagram.com/certisalud_plus/">Instagram</a> y <a href="https://www.facebook.com/people/CERTI-SALUD/61551608173626/">Facebook</a><br><br>

                            No respondas a este correo, en caso de que tengas alguna duda o requieras otra información contáctanos por el medio desde el que realizaste tu compra para darte la mejor atención o al WhatsApp 3133027845.<br><br>

                            Cordialmente;Equipo Certisalud+Número de contacto: 3133027845</p>
                            ';
                            break;
                        default:
                            $image_path = plugin_dir_path(__FILE__) . 'assets/certificado-blank_page-0001.jpg';

                            $dir = plugin_dir_path(__FILE__) . 'fonts/';
                            $files = glob($dir . '*.ttf');

                            $css = '';
                            foreach ($files as $file) {
                                $fontFamily = basename($file, '.ttf');
                                $css .= "@font-face {
                                    font-family: '{$fontFamily}';
                                    src: url('{$file}') format('truetype');
                                }\n";
                            }

                            $html_content = '
                                <!DOCTYPE html>
                                <html lang="en">
                                <head>
                                    <meta charset="UTF-8">
                                    <meta name="viewport" content="width=device-width, initial-scale=1.0">
                                    <title>Document</title>
                                    <style>

                                    ' . $css . '
                                        @page {
                                            margin: 0;
                                        }
                                        </style>
                                </head>
                                <body style="width: 100%">
                                    <img style="width: 100%; position: absolute;" src="' . $image_base64 . '" alt=" " />
                                    <div style="width: 100%; z-index:999;" class="main">
                                        <p style="font-family: Britannic Bold Regular; text-align: center; font-size: 52px; margin-bottom: 0px;" >MEDICPLUS SAS</p>
                                        <p style="font-family: Britannic Bold Regular; text-align: center; font-size: 32px; margin-top: -20px;" >NIT: 901800420-1</p>
                                        <p style="font-family: Britannic Bold Regular; text-align: center; font-size: 32px; margin-top: -20px;" >CERTIFICA QUE:</p>
                                        <p style="font-family: Britannic Bold Regular; text-align: center; font-size: 40px; margin-top: -20px; text-transform: uppercase;" >' . $_POST['nombre'] . '</p>
                                        <p style="font-family: Britannic Bold Regular; text-align: center; font-size: 30px; margin-top: -20px;" >Identificado(a) con ' . $_POST['documento'] . ' N°' . $_POST['cedula'] . '</p>
                                        <p style="font-family: Britannic Bold Regular; text-align: center; font-size: 32px; margin-top: -20px;" >Asistió al curso de:</p>
                                        <p style="font-family: LucidaHandwritingStdRg; text-align: center; font-size: 22px; margin: -20px auto; width: 90%; color: rgb(79 129 189);" >' . get_the_title($curso_id) . '</p>
                                        <p style="font-family: Britannic Bold Regular; text-align: center; font-size: 32px; color: rgb(5, 0, 48);" > Con una intensidad horaria de ' . $intensidad_horaria . ' horas</p>
                                        <p style="width: 75%; font-size: 12px; text-align: center; margin: -20px auto 0; color: rgb(5, 0, 48);">ESTE CERTIFICADO ES EXPEDIDO EN LA CIUDAD DE BOGOTA EL ' . $date_format . ', LA PRESENTE CERTIFICACIÓN SE EXPIDE MEDIANTE MARCO NORMATIVO PARA LA EDUCACIÓN INFORMAL Y NO CONDUCE A TITULO ALGUNO O CERTIFICACIÓN DE APTITUD OCUPACIONAL, ESTA CERTIFICACIÓN TIENE VIGENCIA DE ' . $años_expiracion . ' A PARTIR DE LA GENERACIÓN DE LA MISMA.</p>
                                    </div>
                                </body>
                                </html>
                                ';
                            $mensaje_email = '
                            <p>Hola   CORDIAL SALUDO DE MEDIC PLUS<br>
                            Gracias por confiar en nosotros y hacernos parte de tu proceso de formacion.<br><br>

                            Te queremos contar que adjunto encontraras tus certificados en formato PDF para que los puedas descargar e imprimir<br><br>

                            Adicional tu plataforma ACADEMICA Q10 quedara habilitada al FINALIZAR EL DÍA.<br><br>

                            <strong>IMPORTANTE</strong>
                            Al finalizar el día cuando llegue el link<br>
                            Sigue estos pasos para poder acceder a tus cursos:</p>
                            <ol>
                                <li>Te va a llegar a tu CORREO un mensaje directamente de PLATAFORMA Q10 en el asunto "ACTIVAR MI CUENTA"</li>
                                <li>Presiona el botón ACTIVAR MI CUENTA establece tu contraseña para ingreso a plataforma (5 caracteres como mínimo)</li>
                                <li>Regresas de nuevo al correo de Q10 y le das click en INGRESAR A Q10</li>
                                <li>Usuario (Correo que escribiste en la inscripción)</li>
                                <li>Contraseña la que estableciste en el paso #2</li>
                            </ol>
                            <p>¡Listo! Comienza a tomar tus cursos<br><br>
                            ¡Muchas gracias por preferirnos!                        
                            </p>
                            ';
                            break;
                    }



                    // Configura las opciones de dompdf
                    $options = new Options();
                    $options->set('tempDir', plugin_dir_path(__FILE__) . 'tmp');
                    $options->set('fontCache', plugin_dir_path(__FILE__) . 'fonts');
                    $options->set('isRemoteEnabled', true);
                    $options->set('pdfBackend', 'CPDF');
                    $options->set('chroot', [plugin_dir_path(__FILE__) . 'resources/views', plugin_dir_path(__FILE__) . 'fonts']);

                    // instantiate and use the dompdf class
                    $dompdf = new Dompdf($options);

                    $dompdf->loadHtml($html_content);

                    // (Optional) Setup the paper size and orientation
                    $dompdf->setPaper('A4', 'landscape');

                    // Render the HTML as PDF
                    $dompdf->render();

                    //$dompdf->stream('document.pdf');
                    $pdf_content = $dompdf->output();

                    // Guardar el PDF en la galería de medios y actualizar meta box
                    $upload = wp_upload_bits('certificado_' . $curso_id . '.pdf', null, $pdf_content);
                    if (!$upload['error']) {
                        $pdf_file_url = $upload['url'];

                        // Actualizar el meta campo con la URL del archivo subido
                        update_post_meta($post_id, 'pdf_file', $pdf_file_url);
                    } else {
                        echo 'Error al cargar el archivo PDF en la galería de medios.';
                    }

                    // Guardar el PDF en el email
                    //$pdf_file = tempnam(sys_get_temp_dir() . '.pdf', 'certificado_');
                    $pdf_file = 'certificado_' . $curso_id . '.pdf';
                    file_put_contents($pdf_file, $pdf_content);

                    $pdf_adjuntos[] = $pdf_file;
                }
            }

            // Enviar correo electrónico
            $to = $_POST['email']; // Dirección de correo electrónico del destinatario
            $subject = '¡Aquí están tus certificados!'; // Asunto del correo
            $message = $mensaje_email;
            $headers = array('Content-Type: text/html; charset=UTF-8'); // Cabeceras del correo
            $attachments = $pdf_adjuntos; // Adjuntar PDFs al correo electrónico

            $enviado = wp_mail($to, $subject, $message, $headers, $attachments); // Envía el correo electrónico

            if ($enviado) {
                echo '<div class="notice notice-success is-dismissible"><p>Certificado generado - Email enviado exitosamente!</p></div>';
                //return;//print_r($terms);
            }
        } else {
            echo '<div class="error notice"><p>No se ha enviado el formulario, vuelve a intentarlo.</p></div>';
            //return;
        }
    }

    ?>
        <!-- Bootstrap CSS -->
        <link rel="stylesheet" href="<?php echo plugin_dir_url(__FILE__) . 'css/bootstrap.min.css'; ?>">
        <!-- <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet"> -->
        <!-- Select2 CSS -->
        <link rel="stylesheet" href="<?php echo plugin_dir_url(__FILE__) . 'css/select2.min.css'; ?>">
        <!-- <link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-beta.1/dist/css/select2.min.css" rel="stylesheet" />-->


        <!-- Estilos personalizados -->
        <style>
            #wpwrap {
                background: -webkit-linear-gradient(left, #0072ff, #00c6ff);
            }

            /* Animaciones de entrada para el contenedor */
            @keyframes fadeIn {
                from {
                    opacity: 0;
                }

                to {
                    opacity: 1;
                }
            }

            .container {
                animation: fadeIn 1s ease-in-out;
            }

            /* Cambio de color en los inputs al enfocar */
            .form-control:focus {
                border-color: #80bdff;
                box-shadow: 0 0 0 0.2rem rgba(0, 123, 255, 0.25);
            }

            /* Estilos personalizados para Select2 */
            .select2-container--default .select2-selection--single {
                border-radius: 5px;
            }

            .select2-container--default .select2-selection--multiple {
                border-radius: 5px;
            }

            .select2-container--default .select2-selection--multiple .select2-selection__choice {
                background-color: #007bff52;
                border: 1px solid #007bff;
            }

            .select2-container--default .select2-selection--multiple .select2-selection__choice,
            .select2-container--default .select2-selection--multiple .select2-selection__choice__remove {
                color: black;
            }

            .select2-container--default .select2-selection--multiple .select2-selection__choice__display {
                padding-left: 10px;
            }

            /* Transiciones suaves */
            .form-control,
            .form-select,
            .btn-primary,
            .select2-container--default .select2-selection--multiple .select2-selection__choice {
                transition: all 0.3s ease-in-out;
            }


            body {
                background-color: #f1f1f1;
            }

            .container {
                max-width: 500px;
                background-color: #fff;
                padding: 20px;
                border-radius: 10px;
                /* Bordes redondeados */
                box-shadow: 0 4px 8px rgba(0, 0, 0, 0.1), 0 6px 20px rgba(0, 0, 0, 0.1);
                /* Sombras más pronunciadas */
            }

            .form-title {
                text-align: center;
                color: #000;
                text-transform: uppercase;
            }

            .form-control,
            .form-select {
                width: 100%;
                border-radius: 5px;
            }


            .form-check-label {
                display: block;
                margin-bottom: 0.5rem;
            }

            /* Estilos para los iconos de Bootstrap */
            .icono {
                padding-right: 10px;
                /* Espacio entre el icono y el texto */
            }

            /* Si prefieres usar Dashicons de WordPress */
            .dashicons {
                vertical-align: middle;
            }

            .form-control,
            .form-check-input {
                border-radius: 0.25rem;
            }

            .btn-primary {
                background-color: #007bff;
                border-color: #007bff;
            }
        </style>
        <div class="container py-5 my-5">
            <h2 class="form-title">Generar Certificados</h2>
            <form method="post" action="">
                <div class="mb-3">
                    <label for="nombre" class="form-label">Nombre:</label>
                    <input type="text" class="form-control" id="nombre" name="nombre">
                </div>
                <div class="mb-3">
                    <label for="cedula" class="form-label">Cédula:</label>
                    <input type="text" id="cedula" name="cedula" class="form-control" required>
                </div>

                <div class="mb-3">
                    <label for="curso" class="form-label">Curso:</label>
                    <select class="form-select" multiple name="cursos[]">
                        <?php
                        // Obtenemos todos los cursos
                        $cursos = get_posts(array(
                            'post_type' => 'curso-salud', // Tipo de publicación "Curso-salud"
                            'posts_per_page' => -1, // Todos los posts
                        ));

                        // Si hay cursos, los mostramos como opciones
                        if ($cursos) {
                            foreach ($cursos as $curso) {
                                echo '<option value="' . $curso->ID . '">' . $curso->post_title . '</option>';
                            }
                        } else {
                            echo '<option>No hay cursos disponibles.</option>';
                        }
                        ?>
                    </select>
                </div>

                <div class="mb-3">
                    <label for="email" class="form-label">Email:</label>
                    <input type="text" id="email" name="email" class="form-control" required>
                </div>

                <div class="mb-3">
                    <label>Tipo de documento:</label><br>
                    <div class="row">
                        <div class="col">
                            <input type="radio" id="doc-c" name="documento" value="cedula de ciudadania" checked>
                            <label for="doc-c">cedula de ciudadania</label>
                        </div>
                        <div class="col">
                            <input type="radio" id="doc-v" name="documento" value="PPT">
                            <label for="doc-v">PPT</label>
                        </div>
                    </div>
                </div>

                <?php
                // Si no existe una fecha de expiración, se establece una fecha por defecto (un año después de la creación del formulario)
                $fecha_actual = current_time('mysql');
                if (empty($fecha_expiracion)) {
                    $fecha_expedicion = date('Y-m-d H:i:s', strtotime($fecha_actual));
                } ?>
                <div class="mb-3">
                    <label for="fecha_expedicion" class="form-label">Fecha de Expedición:</label>
                    <input type="datetime-local" id="fecha_expedicion" name="fecha_expedicion_certificado" value="<?php echo esc_attr($fecha_expedicion); ?>" class="form-control" required>
                </div>

                <button type="submit" name="submit" class="btn btn-primary">Generar Certificado</button>
            </form>
        </div>

        <script>
            jQuery(document).ready(function($) {
                $('.form-select').select2({
                    placeholder: "Selecciona los cursos",
                    allowClear: true,
                    width: '100%'
                });
            });
        </script>

        <!-- Select2 JS -->
        <script src="<?php echo plugin_dir_url(__FILE__) . 'js/select2.min.js'; ?>"></script>
        <!-- <script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-beta.1/dist/js/select2.min.js"></script> -->



    <?php

}

///////////Antiguo codigo
//Metacampos salud
// 1. Función para guardar los metadatos de los cursos de salud
function guardar_metadatos_cursos_salud($post_id){
    // Verificar si es un guardado automático o si el usuario tiene permisos
    if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
        return;
    }

    if (!current_user_can('edit_post', $post_id)) {
        return;
    }

    // Verificar que la nueva categoría ha sido enviada correctamente
    if (isset($_POST['nueva_categoria_nombre']) && isset($_POST['nueva_categoria_tiempo']) && isset($_POST['nueva_categoria_duracion'])) {
        $nueva_categoria_nombre = sanitize_text_field($_POST['nueva_categoria_nombre']);
        $nueva_categoria_tiempo = sanitize_text_field($_POST['nueva_categoria_tiempo']);
        $nueva_categoria_duracion = sanitize_text_field($_POST['nueva_categoria_duracion']);
        $log = array(
            '_tipo_certificado' => $nueva_categoria_nombre,
            '_tiempo_certificado' => $nueva_categoria_tiempo,
            '_descripcion_tiempo_certificado' => $nueva_categoria_unidad_tiempo,
            '_duracion_certificado_tiempo' => $nueva_categoria_duracion,
            '_descripcion_duracion_certificado' => $nueva_categoria_unidad_duracion,
            '_intensidad_horaria' => $intensidad_horaria,
        );
        
        // Opcional: Guarda el log en el registro de depuración de WordPress
        error_log(print_r($log, true));
        
        // Log para verificar si se está recibiendo correctamente la nueva categoría
        error_log("Nueva categoría recibida: Nombre: $nueva_categoria_nombre, Tiempo: $nueva_categoria_tiempo, Duración: $nueva_categoria_duracion");

        // Guardar en la base de datos
        update_post_meta($post_id, '_nueva_categoria_nombre', $nueva_categoria_nombre);
        update_post_meta($post_id, '_nueva_categoria_tiempo', $nueva_categoria_tiempo);
        update_post_meta($post_id, '_nueva_categoria_duracion', $nueva_categoria_duracion);

        // Log para verificar que los datos se han guardado
        error_log("Datos de la nueva categoría guardados en los metadatos del curso con ID $post_id.");
    }
}
add_action('save_post', 'guardar_metadatos_cursos_salud');
// 2. Función para registrar el metabox en los cursos de salud
function registrar_metabox_cursos_salud(){
    add_meta_box(
        'metabox_cursos_salud',                // ID del metabox
        'Configuración del Curso',             // Título del metabox
        'renderizar_metabox_cursos_salud',     // Función que renderiza el contenido
        'cursos-salud',                        // Tipo de publicación
        'normal',                              // Contexto
        'high'                                 // Prioridad
    );
}
add_action('add_meta_boxes', 'registrar_metabox_cursos_salud');
function guardar_nueva_categoria_curso($post_id){
    // Verificar si el formulario de nueva categoría fue enviado
    if (isset($_POST['nueva_categoria_nombre'])) {
        // Obtener los datos del formulario
        $nueva_categoria_nombre = sanitize_text_field($_POST['nueva_categoria_nombre']);
        $nueva_categoria_tiempo = sanitize_text_field($_POST['nueva_categoria_tiempo']);
        $nueva_categoria_unidad_tiempo = sanitize_text_field($_POST['nueva_categoria_unidad_tiempo']);
        $nueva_categoria_duracion = sanitize_text_field($_POST['nueva_categoria_duracion']);
        $nueva_categoria_unidad_duracion = sanitize_text_field($_POST['nueva_categoria_unidad_duracion']);
        $intensidad_horaria = $nueva_categoria_tiempo + " " + $nueva_categoria_unidad_tiempo;
        // Log para verificar los datos
        error_log("Nueva categoría recibida: Nombre: $nueva_categoria_nombre, Tiempo: $nueva_categoria_tiempo, Duración: $nueva_categoria_duracion");

        // Guardar los datos en los metadatos del curso
        update_post_meta($post_id, '_tipo_certificado', $nueva_categoria_nombre);
        update_post_meta($post_id, '_tiempo_certificado', $nueva_categoria_tiempo);
        update_post_meta($post_id, '_descripcion_tiempo_certificado', $nueva_categoria_unidad_tiempo);
        update_post_meta($post_id, '_duracion_certificado_tiempo', $nueva_categoria_duracion);
        update_post_meta($post_id, '_descripcion_duracion_certificado', $nueva_categoria_unidad_duracion);
        update_post_meta($post_id, '_intensidad_horaria',  $intensidad_horaria);
        // Log para confirmar que los datos fueron guardados
        $log = array(
            '_tipo_certificado' => $nueva_categoria_nombre,
            '_tiempo_certificado' => $nueva_categoria_tiempo,
            '_descripcion_tiempo_certificado' => $nueva_categoria_unidad_tiempo,
            '_duracion_certificado_tiempo' => $nueva_categoria_duracion,
            '_descripcion_duracion_certificado' => $nueva_categoria_unidad_duracion,
            '_intensidad_horaria' => $intensidad_horaria,
        );
        
        // Opcional: Guarda el log en el registro de depuración de WordPress
        error_log(print_r($log, true));
        
        error_log("Datos de la nueva categoría guardados en los metadatos del curso con ID $post_id.");
    }
}
add_action('save_post', 'guardar_nueva_categoria_curso');


// 3. Función para renderizar el contenido del metabox
function renderizar_metabox_cursos_salud($post){
    global $wpdb;

    // Obtener los metadatos del curso
    $tipo_certificado = get_post_meta($post->ID, '_tipo_certificado', true);
    $tiempo_certificado = get_post_meta($post->ID, '_tiempo_certificado', true);
    $descripcion_tiempo_certificado = get_post_meta($post->ID, '_descripcion_tiempo_certificado', true);
    $duracion_certificado_tiempo = get_post_meta($post->ID, '_duracion_certificado_tiempo', true);
    $descripcion_duracion_certificado = get_post_meta($post->ID, '_descripcion_duracion_certificado', true);
    $intensidad_horaria = get_post_meta($post->ID, '_intensidad_horaria', true);
    // Opciones predefinidas (solo si los campos están vacíos)
    $opciones_predefinidas = [
        'basico' => [
            'tipo_certificado' => 'Curso básico',
            'tiempo_certificado' => 45,
            'descripcion_tiempo_certificado' => 'horas',
            'duracion_certificado_tiempo' => 1,
            'descripcion_duracion_certificado' => 'año',
        ],
        'avanzado' => [
            'tipo_certificado' => 'Curso avanzado',
            'tiempo_certificado' => 70,
            'descripcion_tiempo_certificado' => 'horas',
            'duracion_certificado_tiempo' => 2,
            'descripcion_duracion_certificado' => 'años',
        ],
        'diplomado' => [
            'tipo_certificado' => 'Diplomado en salud',
            'tiempo_certificado' => 160,
            'descripcion_tiempo_certificado' => 'horas',
            'duracion_certificado_tiempo' => 3,
            'descripcion_duracion_certificado' => 'años',
        ],
    ];

    // Si los campos están vacíos, asignar las opciones predefinidas
    if (!$tipo_certificado) {
        $tipo_certificado = $opciones_predefinidas['basico']['tipo_certificado'];
    }
    if (!$tiempo_certificado) {
        $tiempo_certificado = $opciones_predefinidas['basico']['tiempo_certificado'];
    }
    if (!$descripcion_tiempo_certificado) {
        $descripcion_tiempo_certificado = $opciones_predefinidas['basico']['descripcion_tiempo_certificado'];
    }
    if (!$duracion_certificado_tiempo) {
        $duracion_certificado_tiempo = $opciones_predefinidas['basico']['duracion_certificado_tiempo'];
    }
    if (!$descripcion_duracion_certificado) {
        $descripcion_duracion_certificado = $opciones_predefinidas['basico']['descripcion_duracion_certificado'];
    }
    
    // Detectar la categoría actual seleccionada
    $categoria_actual = array_search($tipo_certificado, array_column($opciones_predefinidas, 'tipo_certificado'));

    ?>
    <div class="wrap" style="width: 50%; margin: 0;">
        <h4>Seleccionar Categoría del Certificado</h4>
        <label for="certificado_categoria_predefinida">Selecciona la Categoría:</label>
        <select name="certificado_categoria_predefinida" id="certificado_categoria_predefinida" class="widefat">
            <option value="">Selecciona una opción</option>
            <?php foreach ($opciones_predefinidas as $key => $value): ?>
                <option value="<?php echo esc_attr($key); ?>" <?php selected($key, $categoria_actual); ?>>
                    <?php echo esc_html($value['tipo_certificado']); ?>
                </option>
            <?php endforeach; ?>
        </select>

        <h4>Detalles del Certificado</h4>
        <label for="certificado_tipo">Tipo de Categoría:</label>
        <input type="text" name="certificado_tipo" id="certificado_tipo" class="widefat" value="<?php echo esc_attr($tipo_certificado); ?>" readonly>

        <label for="certificado_tiempo_formacion">Tiempo de Formación:</label>
        <input type="number" name="certificado_tiempo_formacion" id="certificado_tiempo_formacion" class="widefat" value="<?php echo esc_attr($tiempo_certificado); ?>" readonly>

        <label for="certificado_tiempo_descripcion">Detalle del Tiempo en Formación:</label>
        <input type="text" name="certificado_tiempo_descripcion" id="certificado_tiempo_descripcion" class="widefat" value="<?php echo esc_attr($descripcion_tiempo_certificado); ?>" readonly>

        <label for="certificado_duracion">Duración del Certificado:</label>
        <input type="number" name="certificado_duracion" id="certificado_duracion" class="widefat" value="<?php echo esc_attr($duracion_certificado_tiempo); ?>" readonly>

        <label for="certificado_duracion_descripcion">Detalle de la Duración:</label>
        <input type="text" name="certificado_duracion_descripcion" id="certificado_duracion_descripcion" class="widefat" value="<?php echo esc_attr($descripcion_duracion_certificado); ?>" readonly>
         
        <label for="intensidad_horaria">Intensidad Horaria:</label>
        <input type="text" name="intensidad_horaria" id="intensidad_horaria" class="widefat" value="<?php echo esc_attr($intensidad_horaria); ?>" readonly>
    </div>

    <script>
        // JavaScript para asignar valores dinámicamente al seleccionar una opción predefinida
        document.getElementById('certificado_categoria_predefinida').addEventListener('change', function() {
            const predefinedValues = <?php echo json_encode($opciones_predefinidas); ?>;
            const selectedOption = this.value;

            if (predefinedValues[selectedOption]) {
                document.getElementById('certificado_tipo').value = predefinedValues[selectedOption].tipo_certificado;
                document.getElementById('certificado_tiempo_formacion').value = predefinedValues[selectedOption].tiempo_certificado;
                document.getElementById('certificado_tiempo_descripcion').value = predefinedValues[selectedOption].descripcion_tiempo_certificado;
                document.getElementById('certificado_duracion').value = predefinedValues[selectedOption].duracion_certificado_tiempo;
                document.getElementById('certificado_duracion_descripcion').value = predefinedValues[selectedOption].descripcion_duracion_certificado;
            }
        });
    </script>
    <?php
}




/////////////////////////////////////////////////Save foreach
foreach ($cursos as $index => $curso_id) {
    $curso_nombre = get_the_title($curso_id);
    $intensidad_horaria = get_post_meta($curso_id, 'horas', true);
    $tipo_certificado = get_post_meta($curso_id, '_tipo_certificado', true);
    $vigencia_certificado = get_post_meta($curso_id, 'fecha_expiracion_certificado', true);
    $log_data = [
    'curso_nombre' => $curso_nombre,
    'intensidad_horaria' => $intensidad_horaria,
    'descripcion_intensidad' => $descripcion_intensidad,
    'duracion_certificado_tiempo' => $duracion_certificado_tiempo,
    'descripcion_duracion_certificado' => $descripcion_duracion_certificado,
    'vigencia_certificado' => $vigencia_certificado,
    'tipo_certificado' => $tipo_certificado,
 ];
        $metadatos = get_post_meta($curso_id);

        // Registrar en el log todos los metadatos
        error_log("Metadatos del Curso ID {$curso_id}: " . print_r($metadatos, true));
        // Registrar el log
        error_log("Datos del Curso ID {$curso_id}: " . print_r($log_data, true));
        // HTML dinámico para el PDF
        // Generar el HTML del certificado
        //inicio html
        $html = '
        <!DOCTYPE html>
        <html lang="en">
        <head>
            <meta charset="UTF-8">
            <meta name="viewport" content="width=device-width, initial-scale=1.0">
            <title>Document</title>
            <style>
            @page {
                margin: 0;
                background-image: url('.$imagen_fondo.');
                background-size: cover;
                background-repeat: no-repeat;
                background-position: center;
            }
            body {
                margin: 0;
                padding: 0;
            }
            .contenido {
                position: relative;
                z-index: 1;
                text-align: center;
                margin-top: 50%;
            }

                    /* Eliminar márgenes por defecto de los párrafos */
                    p {
                        margin: 0;
                    }

                    /* Espacio entre los dos párrafos específicos */
                    .certifica {
                        margin-bottom: 5px; /* Ajusta este valor según lo que necesites */
                    }

                    .nombre {
                        margin-top: 0px; /* Ajusta este valor si es necesario */
                    }

                    /* Estilos adicionales */
                    .main {
                        /* Tu estilo actual */
                    }

                    .certifica,
                    .nombre,
                    .documento {
                    margin-top: 5px;
                    },
                    .cedula,
                    .realiza,
                    .nombre_curso,
                    .intensidad,
                    .intensidad_desc,
                    .aviso,
                    .vigencia {
                        font-family: constan;
                        text-align: left;
                        font-size: 15px;
                        margin-top: 5px;
                        max-width: 50%;
                    }

                    .nombre {
                        font-size: 20px;
                        text-transform: uppercase;
                        border-bottom: 2px solid #000;
                        display: inline-block;
                    }

                    .aviso {
                        width: 50%;
                        font-size: 12px;
                        text-align: left;
                        margin-top: 15px;
                        }

                    .vigencia {
                        font-size: 12px;
                        margin-top: 15px;
                    }
                        </head>
                    <body style="width: 100%">
                    <img style="width: 100%; position: absolute;" src="' . $imagen_fondo . '" alt=" " />
                            <div  style="width: 100%; margin-left: 12%; margin-top: 15%; z-index:999;" class="main" >
                                    <p style="font-family: constan; text-align: left; font-size: 25px; margin-top: 45px;" >
                                FUNDACIÓN EDUCATIVA CAMPUS <br>NIT: 901386251-1 
                            </p>
                            <div>
                            <p class="certifica" style="font-family: constan; text-align: left; font-size: 20px;">CERTIFICA QUE:</p>   
                            <p class="nombre" style="color: rgb(5, 0, 48);">' . $nombre . '</p>
                            <p class="documento">Identificado(a) con ' . $tipo_documento . '</p>
                            <p class="cedula">No° : ' . $num_documento . '</p>
                            </div>
                            <p class="realiza">Realizó y aprobó el  '.$tipo_certificado.' de:</p>
                            <p class="nombre_curso">' . $curso_nombre . '</p>
                            <p class="intensidad">Con una intensidad horaria de:</p>
                            <p class="intensidad_desc"> ' . $intensidad_horaria . '</p>

                            <p class="aviso">
                                ESTE CERTIFICADO ES EXPEDIDO EN LA CIUDAD DE FUSAGASUGÁ EL ' . $fecha_expedicion . ', LA PRESENTE CERTIFICACIÓN SE EXPIDE MEDIANTE MARCO NORMATIVO PARA LA EDUCACIÓN INFORMAL Y NO CONDUCE A TÍTULO ALGUNO O CERTIFICACIÓN DE APTITUD OCUPACIONAL
                            </p>

                            <p class="vigencia">
                                VIGENCIA DE LA PRESENTE CERTIFICACIÓN DE ASISTENCIA ES DE ' .$vigencia_certificado. ' A PARTIR DE LA GENERACIÓN DE LA MISMA 
                            </p>
                                                            </div>
                                                    </body>
                                                    </html>';
        //fin html
        // Crear PDF
        $dompdf = new Dompdf($options);
        $dompdf->loadHtml($html);
        $dompdf->setPaper('A4', 'landscape');
        $dompdf->render();

        // Guardar el PDF en el directorio
        $pdf_filename = "certificado_salud_{$num_documento}_{$curso_id}.pdf";
        $pdf_path = $certificados_dir . $pdf_filename;
        file_put_contents($pdf_path, $dompdf->output());

        // Convertir el path a un URL accesible
        $pdf_url = plugins_url('certificados_salud/' . $pdf_filename, __FILE__);

        // Agregar a las listas
        $pdf_files[] = $pdf_path;
        $pdf_links[] = $pdf_url;

        // Registrar cada certificado como un post type
        $certificado_id = wp_insert_post([
            'post_type'    => 'certificado',
            'post_title'   => "$nombre - $curso_nombre",
            'post_content' => 'Certificado generado automáticamente.',
            'post_status'  => 'publish',
        ]);

        // Actualizar los metadatos del post type
        update_post_meta($certificado_id, 'nombre_certificado', $nombre);
        update_post_meta($certificado_id, 'cedula_certificado', $num_documento);
        update_post_meta($certificado_id, 'curso_certificado', $curso_nombre);
        update_post_meta($certificado_id, 'email_certificado', $email);
        update_post_meta($certificado_id, 'empresa_certificado', $empresa_titulo);
        update_post_meta($certificado_id, 'horas', $intensidad_horaria);
        update_post_meta($certificado_id, 'pdf_file', $pdf_url);
        update_post_meta($certificado_id, 'fecha_expedicion', $fecha_expedicion);
    }

    // Enviar correo con los PDFs generados como adjuntos
    if (!empty($pdf_files)) {
        $asunto = "Fundacion Campus Salud - Certificados Generados";
        $mensaje = nl2br($contenido_email);
        $headers = ['Content-Type: text/html; charset=UTF-8'];

        $correo_enviado = wp_mail(
            $email,
            $asunto,
            $mensaje,
            $headers,
            $pdf_files // Archivos adjuntos
        );

        if ($correo_enviado) {
            echo '<div class="notice notice-success"><p>Certificados generados correctamente y enviados por correo electrónico.</p></div>';
        } else {
            echo '<div class="notice notice-error"><p>Hubo un error al enviar el correo.</p></div>';
        }
}




///////////////////////// Foreach campus

foreach ($cursos as $index => $curso_id) {
    $curso_nombre = get_the_title($curso_id);
    $intensidad_horaria = get_post_meta($curso_id, '_intensidad_horaria', true) ?: 45 ;
    $descripcion_intensidad = get_post_meta($curso_id, '_descripcion_intensidad', true) ?: 'horas';
    $duracion_certificado_tiempo = get_post_meta($curso_id, '_duracion_certificado_tiempo', true) ?: 1;
    $descripcion_duracion_certificado = get_post_meta($curso_id, '_descripcion_duracion_certificado', true) ?: 'año';
    $log_data = [
        'curso_nombre' => $curso_nombre,
        'intensidad_horaria' => $intensidad_horaria,
        'descripcion_intensidad' => $descripcion_intensidad,
        'duracion_certificado_tiempo' => $duracion_certificado_tiempo,
        'descripcion_duracion_certificado' => $descripcion_duracion_certificado,
        'vigencia_certificado' => $vigencia_certificado,
        'tipo_certificado' => $tipo_certificado,
     ];
            $metadatos = get_post_meta($curso_id);
    
            // Registrar en el log todos los metadatos
            error_log("Metadatos del Curso ID {$curso_id}: " . print_r($metadatos, true));
            // Registrar el log
            error_log("Datos del Curso ID {$curso_id}: " . print_r($log_data, true));
    
    
    
    // HTML dinámico para el PDF
    // Generar el HTML del certificado
    //inicio html
    $html = '
    <!DOCTYPE html>
    <html lang="en">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Document</title>
        <style>
        @page {
            margin: 0;
            background-image: url('.$imagen_fondo.');
            background-size: cover;
            background-repeat: no-repeat;
            background-position: center;
        }
        body {
            margin: 0;
            padding: 0;
        }
        .contenido {
            position: relative;
            z-index: 1;
            text-align: center;
            margin-top: 50%;
        }

                /* Eliminar márgenes por defecto de los párrafos */
                p {
                    margin: 0;
                }

                /* Espacio entre los dos párrafos específicos */
                .certifica {
                    margin-bottom: 5px; /* Ajusta este valor según lo que necesites */
                }

                .nombre {
                    margin-top: 0px; /* Ajusta este valor si es necesario */
                }

                /* Estilos adicionales */
                .main {
                    /* Tu estilo actual */
                }

                .certifica,
                .nombre,
                .documento {
                margin-top: 5px;
                },
                .cedula,
                .realiza,
                .nombre_curso,
                .intensidad,
                .intensidad_desc,
                .aviso,
                .vigencia {
                    font-family: constan;
                    text-align: left;
                    font-size: 15px;
                    margin-top: 5px;
                    max-width: 50%;
                }

                .nombre {
                    font-size: 20px;
                    text-transform: uppercase;
                    border-bottom: 2px solid #000;
                    display: inline-block;
                }

                .aviso {
                    width: 50%;
                    font-size: 12px;
                    text-align: left;
                    margin-top: 15px;
                    }

                .vigencia {
                    font-size: 12px;
                    margin-top: 15px;
                }
                    </head>
                <body style="width: 100%">
                <img style="width: 100%; position: absolute;" src="' . $imagen_fondo . '" alt=" " />
                        <div  style="width: 100%; margin-left: 12%; margin-top: 15%; z-index:999;" class="main" >
                                <p style="font-family: constan; text-align: left; font-size: 25px; margin-top: 45px;" >
                            FUNDACIÓN EDUCATIVA CAMPUS <br>NIT: 901386251-1 
                        </p>
                        <div>
                        <p class="certifica" style="font-family: constan; text-align: left; font-size: 20px;">CERTIFICA QUE:</p>   
                        <p class="nombre" style="color: rgb(5, 0, 48);">' . $nombre . '</p>
                        <p class="documento">Identificado(a) con ' . $documento . '</p>
                        <p class="cedula">No° : ' . $tipo_documento . '</p>
                        </div>
                        <p class="realiza">Realizó y aprobó el curso de:</p>
                        <p class="nombre_curso">' . $curso_nombre . '</p>
                        <p class="intensidad">Con una intensidad horaria de:</p>
                        <p class="intensidad_desc"> ' . $intensidad_horaria . '</p>

                        <p class="aviso">
                            ESTE CERTIFICADO ES EXPEDIDO EN LA CIUDAD DE FUSAGASUGÁ EL ' . $fecha_expedicion . ', LA PRESENTE CERTIFICACIÓN SE EXPIDE MEDIANTE MARCO NORMATIVO PARA LA EDUCACIÓN INFORMAL Y NO CONDUCE A TÍTULO ALGUNO O CERTIFICACIÓN DE APTITUD OCUPACIONAL
                        </p>

                        <p class="vigencia">
                            VIGENCIA DE LA PRESENTE CERTIFICACIÓN DE ASISTENCIA ES DE ' . $duracion_certificado_tiempo . '  ' . $descripcion_duracion_certificado . ' A PARTIR DE LA GENERACIÓN DE LA MISMA 
                        </p>
                                                        </div>
                                                </body>
                                                </html>';
    //fin html
    
    try {
        // Configurar Dompdf
        $dompdf = new Dompdf($options);
        $dompdf->loadHtml($html);
        $dompdf->setPaper('A4', 'landscape');
        $dompdf->render();
        error_log("Dompdf configurado y renderizado correctamente.");
    } catch (Exception $e) {
        error_log("Error al configurar y renderizar Dompdf: " . $e->getMessage());
        echo '<div class="notice notice-error"><p>Error al generar el PDF.</p></div>';
        return;
    }
    try {
        // Guardar el PDF en el directorio
        $pdf_filename = "certificado_salud_{$documento}_{$curso_id}.pdf";
        $pdf_path = $certificados_dir . $pdf_filename;
        file_put_contents($pdf_path, $dompdf->output());
        $pdf_url = plugins_url('certificados_salud/' . $pdf_filename, __FILE__);
        error_log("PDF guardado correctamente en $pdf_path.");
    } catch (Exception $e) {
        error_log("Error al guardar el PDF: " . $e->getMessage());
        echo '<div class="notice notice-error"><p>Error al guardar el PDF generado.</p></div>';
        return;
    }
    try {
        // Registrar el certificado como un post type
        $certificado_id = wp_insert_post([
            'post_type'    => 'certificado',
            'post_title'   => "$nombre - $curso_nombre",
            'post_content' => 'Certificado generado automáticamente.',
            'post_status'  => 'publish',
        ]);
        if (!$certificado_id) {
            throw new Exception('El ID del post generado es inválido.');
        }
        error_log("Certificado registrado correctamente con el ID $certificado_id.");
    } catch (Exception $e) {
        error_log("Error al registrar el post type del certificado: " . $e->getMessage());
        echo '<div class="notice notice-error"><p>Error al registrar el certificado.</p></div>';
        return;
    }
    
    try {
        // Actualizar los metadatos del post type
        update_post_meta($certificado_id, 'nombre_certificado', $nombre);
        update_post_meta($certificado_id, 'cedula_certificado', $documento);
        update_post_meta($certificado_id, 'curso_certificado', $curso_nombre);
        update_post_meta($certificado_id, 'email_certificado', $email);
        update_post_meta($certificado_id, 'empresa_certificado', $empresa_titulo);
        update_post_meta($certificado_id, 'horas', $intensidad_horaria);
        update_post_meta($certificado_id, 'pdf_file', $pdf_url);
        update_post_meta($certificado_id, 'fecha_expedicion', $fecha_expedicion);
        error_log("Metadatos del certificado actualizados correctamente.");
    } catch (Exception $e) {
        error_log("Error al actualizar los metadatos del certificado: " . $e->getMessage());
        echo '<div class="notice notice-error"><p>Error al actualizar los metadatos del certificado.</p></div>';
        return;
    }
    
    try {
        // Añadir el PDF a las listas
        $pdf_files[] = $pdf_path;
        $pdf_links[] = $pdf_url;
        if (empty($pdf_files)) {
            throw new Exception('La lista de archivos PDF está vacía.');
        }
        error_log("PDF añadido a las listas correctamente.");
    } catch (Exception $e) {
        error_log("Error al procesar las listas de PDFs: " . $e->getMessage());
        echo '<div class="notice notice-error"><p>Error al procesar los certificados generados.</p></div>';
        return;
    }
} //end for each


/// fin antiguo codigo





////////////////////////////////////////Metacampo de intensidad horaria
//guardar intensidad horaria
function cc_agregar_metabox_cursos_campus()
{
    add_meta_box(
        'cc_cursos_campus',
        'Intensidad Horaria del Curso',
        'cc_renderizar_metabox_cursos_campus',
        'product',
        'normal',
        'default'
    );
}
add_action('add_meta_boxes', 'cc_agregar_metabox_cursos_campus');

function cc_renderizar_metabox_cursos_campus($post)
{
    $intensidad_horaria = get_post_meta($post->ID, '_intensidad_horaria', true);
?>
    <label for="intensidad_horaria">Intensidad Horaria:</label>
    <input type="text" id="intensidad_horaria" name="intensidad_horaria" value="<?php echo esc_attr($intensidad_horaria); ?>" style="width: 100%;">
<?php
}

function cc_guardar_metabox_cursos_campus($post_id)
{
    if (isset($_POST['intensidad_horaria'])) {
        update_post_meta($post_id, '_intensidad_horaria', sanitize_text_field($_POST['intensidad_horaria']));
    }
}
add_action('save_post', 'cc_guardar_metabox_cursos_campus');

//fin guardar intensidad horaria




////////////////////////////////////////Metacampo de duracion certificado

function cc_renderizar_metabox_fecha_expiracion($post)
{
    // Obtiene la fecha actual
    $fecha_actual = current_time('mysql');

    // Obtiene la fecha de expiración actual si existe
    $fecha_expiracion = get_post_meta($post->ID, 'fecha_expiracion_certificado', true);

    // Si no existe una fecha de expiración, se establece una fecha por defecto (un año después de la creación del formulario)
    if (empty($fecha_expiracion)) {
        $fecha_expiracion = date('Y-m-d H:i:s', strtotime('+1 year', strtotime($fecha_actual)));
    }
?>
    <label for="fecha_expiracion">Fecha de Expiración:</label><br>
    <input type="datetime-local" id="fecha_expiracion" name="fecha_expiracion_certificado" value="<?php echo esc_attr($fecha_expiracion); ?>" /><br><br>
<?php
}


//////////////////////////////////////////////////////////short code consultar certificado

//Generar pagina de consultas
function crear_pagina_consulta_certificados()
{
    // Verificar si la página ya existe
    $pagina = get_page_by_path('consulta-certificados');
    if (!$pagina) {
        // Configurar los datos de la página
        $pagina_datos = array(
            'post_title' => 'Consulta de Certificados',
            'post_name' => 'consulta-certificados',
            'post_content' => '[consulta_certificados_shortcode]', // Shortcode para agregar el contenido
            'post_status' => 'publish',
            'post_type' => 'page',
        );

        // Insertar la página en la base de datos
        $pagina_id = wp_insert_post($pagina_datos);
    }
}

function consulta_certificados_shortcode()
{
    ob_start(); // Iniciar el buffer de salida
    ?>
        <form style="margin-bottom: 20px;" method="get" action="">
            <label for="cedula">Ingrese su número de cédula:</label>
            <input type="text" id="cedula" name="cedula">
            <button style="margin-top: 20px;" type="submit">Consultar Certificados</button>
        </form>
    <?php

    // Verificar si se ha enviado el formulario
    if (isset($_GET['cedula']) && !empty($_GET['cedula'])) {
        $cedula = sanitize_text_field($_GET['cedula']);

        // Consultar los posts de certificados que coincidan con la cédula
        $args = array(
            'post_type' => 'certificado',
            'meta_query' => array(
                array(
                    'key' => 'cedula_certificado',
                    'value' => $cedula,
                ),
            ),
            'posts_per_page' => -1,
        );
        $query = new WP_Query($args);

        // Mostrar los resultados
        if ($query->have_posts()) {
            echo '<h2 style="margin-bottom:20px;">Resultados de la consulta:</h2>';
            echo '<ul>';
            while ($query->have_posts()) {
                $query->the_post();

                // Obtener el valor de la fecha desde el meta box
                $fecha_certificado = get_post_meta(get_the_ID(), 'fecha_expiracion_certificado', true);
                //$fecha_actual = date('Y-m-dTH:i:s'); 
                $fecha_actual = current_time('mysql');

                //print_r($fecha_certificado);
                //print_r($fecha_actual);

                // Comparar las fechas
                if (strtotime($fecha_certificado) > strtotime($fecha_actual)) {
                    $estado = '<span style="color: green;">VIGENTE</span>';
                } else {
                    $estado = '<span style="color: red;">EXPIRADO</span>';
                }

                echo '<li><a href="' . get_permalink() . '">' . get_the_title() . '</a> - ' . $estado . '</li>';
            }
            echo '</ul>';
        } else {
            echo '<p>No se encontraron certificados para la cédula ingresada.</p>';
        }
    }

    return ob_get_clean(); // Obtener el contenido del buffer y limpiarlo
}
add_shortcode('consulta_certificados_shortcode', 'consulta_certificados_shortcode');


// Llamar a la función para crear la página cuando se active el plugin
register_activation_hook(__FILE__, 'crear_pagina_consulta_certificados');


/* Shortcode para el estado de los certificados */
function estado_certificados_shortcode()
{
    ob_start(); // Iniciar el buffer de salida

    $fecha_certificado = get_post_meta(get_the_ID(), 'fecha_expiracion_certificado', true);

    $fecha_actual = current_time('mysql');

    // Comparar las fechas
    if (strtotime($fecha_certificado) > strtotime($fecha_actual)) {
        $estado = '<span style="color: green;">VIGENTE</span>';
    } else {
        $estado = '<span style="color: red;">EXPIRADO</span>';
    }

    echo '<p>' . $estado . '</p>';

    return ob_get_clean(); // Obtener el contenido del buffer y limpiarlo
}
add_shortcode('estado_certificados_shortcode', 'estado_certificados_shortcode');



///////////////////////////////////////////////////////////end short code consultar certificado




?>