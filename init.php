<?php

/**
 * Plugin Name: Cursos Certificados
 * Description: Añade un catalogo de cursos y genera certificados para tus estudiantes.
 * Version: 1.3.0
 * Author: Darwin Avendaño
 */
// Evitar el acceso directo al archivo

if (!defined('ABSPATH')) {
    exit;
}
// reference the Dompdf namespace
use Dompdf\Dompdf;
use Dompdf\Options;
// Función para encolar estilos y scripts de Bootstrap y Select2
function cc_enqueue_styles_and_scripts()
{
    wp_enqueue_style('bootstrap-css', plugin_dir_url(__FILE__) . 'css/bootstrap.min.css');

    // Encolar Bootstrap JS (asegúrate de la ruta correcta del archivo)
    wp_enqueue_script('bootstrap-js', plugin_dir_url(__FILE__) . 'js/bootstrap.min.js', array('jquery'), null, true);

    // Encolar Bootstrap CSS
    wp_enqueue_style('bootstrap-css', plugin_dir_url(__FILE__) . 'css/bootstrap.min.css');

    // Encolar Select2 CSS
    wp_enqueue_style('select2-css', plugin_dir_url(__FILE__) . 'css/select2.min.css');

    // Encolar Select2 JS
    wp_enqueue_script('select2-js', plugin_dir_url(__FILE__) . 'js/select2.min.js', array('jquery'), null, true);

    // Encolar archivo JS personalizado para inicializar Select2 en el campo
    wp_enqueue_script('select2-js', plugin_dir_url(__FILE__) . 'js/select2.js', array('jquery', 'select2-js'), null, true);
}
add_action('admin_enqueue_scripts', 'cc_enqueue_styles_and_scripts');
//Fin
// Importa el autoloader de Composer
require __DIR__ . '/vendor/autoload.php';
// Registra los tipos de publicaciones personalizadas

// Registrar el tipo de publicación "Cursos"
function cc_registrar_post_types()
{
    // Registrar el tipo de publicación "Cursos"
    $args_cursos = array(
        'labels' => array(
            'name' => 'Cursos-Salud',
            'singular_name' => 'Curso',
        ),
        'public' => true,
        'has_archive' => true,
        'rewrite' => array('slug' => 'cursos-salud'),
        'supports' => array('title', 'editor', 'thumbnail', 'excerpt'),
    );
    register_post_type('cursos-salud', $args_cursos);

    // Registrar el tipo de publicación "Certificados"
    $args_certificados = array(
        'labels' => array(
            'name' => 'Certificados',
            'singular_name' => 'Certificado',
        ),
        'public' => true,
        'has_archive' => true,
        'rewrite' => array('slug' => 'certificados'),
        'supports' => array('title'),
    );
    register_post_type('certificado', $args_certificados);

    // Registrar el tipo de publicación "Empresas de Salud"
    $args_empresas = array(
        'public' => true,
        'show_ui' => true,
        'show_in_menu' => true,
        'label' => 'Empresas de Salud',
        'supports' => array('title', 'editor', 'thumbnail'),
        'menu_icon' => 'dashicons-businessperson',
        'rewrite' => array('slug' => 'empresa'),
        'menu_position' => 20,
    );
    register_post_type('empresa', $args_empresas);
}
add_action('init', 'cc_registrar_post_types'); // Registrar los post types al hook init
//** crear tabla en BD  */
function cc_crear_tabla_certificados_salud()
{
    global $wpdb;
    $tabla_certificados = $wpdb->prefix . 'certificados_generales';
    $charset_collate = $wpdb->get_charset_collate();

    $sql = "CREATE TABLE $tabla_certificados (
        id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        curso_id BIGINT UNSIGNED NOT NULL,
        curso_nombre VARCHAR(255) NOT NULL,
        tipo_certificado VARCHAR(255) DEFAULT '',
        intensidad_horaria VARCHAR(255) DEFAULT '',
        descripcion_tiempo_certificado VARCHAR(255) DEFAULT '',
        duracion_certificado_tiempo VARCHAR(255) DEFAULT '',
        descripcion_duracion_certificado VARCHAR(255) DEFAULT '',
        nombre VARCHAR(255),
        cedula VARCHAR(50),
        email VARCHAR(255),
        tipo_documento VARCHAR(50),
        empresa_id BIGINT UNSIGNED,
        empresa_nombre VARCHAR(255),
        fecha_expedicion DATE,
        pdf_url TEXT,
        enviado BOOLEAN DEFAULT 0,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    ) $charset_collate;";

    require_once ABSPATH . 'wp-admin/includes/upgrade.php';
    dbDelta($sql);
}
register_activation_hook(__FILE__, 'cc_crear_tabla_certificados_salud');


/* Fin bd  */

function agregar_metabox_certificado_campus()
{
    add_meta_box(
        'certificado_campus_fondo',
        'Imagen de Fondo del Certificado',
        'renderizar_metabox_certificado_campus',
        'certificados_campus',
        'normal',
        'high'
    );
}
add_action('add_meta_boxes', 'agregar_metabox_certificado_campus');


function renderizar_metabox_certificado_campus($post)
{
    // Obtener el valor actual del fondo del certificado
    $imagen_fondo = get_post_meta($post->ID, '_imagen_fondo_certificado', true);

?>
    <div>
        <label for="imagen_fondo_certificado"><strong>Imagen de Fondo Actual:</strong></label><br>
        <?php if ($imagen_fondo): ?>
            <img src="<?php echo esc_url($imagen_fondo); ?>" alt="Fondo Actual" style="max-width: 100%; height: auto; margin-top: 10px;"><br><br>
        <?php else: ?>
            <p>No hay imagen de fondo seleccionada.</p>
        <?php endif; ?>

        <label for="imagen_fondo_certificado_update"><strong>Actualizar Imagen de Fondo:</strong></label><br>
        <input type="file" id="imagen_fondo_certificado_update" name="imagen_fondo_certificado_update" accept="image/*">
        <p style="font-size: 12px; color: #666;">Sube una nueva imagen para el certificado. Se renombrará automáticamente.</p>
    </div>
<?php
}


function guardar_metadatos_certificado_campus($post_id)
{
    // Verificar si es un guardado automático
    if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
        return;
    }

    // Verificar permisos
    if (!current_user_can('edit_post', $post_id)) {
        return;
    }

    // Verificar si se subió una imagen
    if (isset($_FILES['imagen_fondo_certificado_update']) && !empty($_FILES['imagen_fondo_certificado_update']['name'])) {
        $archivo = $_FILES['imagen_fondo_certificado_update'];

        // Verificar si el archivo es una imagen válida
        if (strpos($archivo['type'], 'image') !== false) {
            // Obtener la información del archivo
            $nombre_original = $archivo['name'];
            $nombre_formateado = sanitize_file_name(pathinfo($nombre_original, PATHINFO_FILENAME));
            $extension = pathinfo($nombre_original, PATHINFO_EXTENSION);

            // Formatear el nombre
            $nombre_final = $nombre_formateado . '-' . time() . '.' . $extension;

            // Directorio de destino
            $upload_dir = wp_upload_dir();
            $ruta_destino = $upload_dir['path'] . '/' . $nombre_final;
            $url_destino = $upload_dir['url'] . '/' . $nombre_final;

            // Mover el archivo a la carpeta de uploads
            if (move_uploaded_file($archivo['tmp_name'], $ruta_destino)) {
                // Guardar la URL de la imagen como metadato del certificado
                update_post_meta($post_id, '_imagen_fondo_certificado', $url_destino);
            }
        }
    }
}
add_action('save_post', 'guardar_metadatos_certificado_campus');



//guardar intensidad horaria
// Registrar el metabox
function cc_agregar_metabox_cursos_campus()
{
    add_meta_box(
        'cc_cursos_campus',
        'Configuración del Curso',
        'cc_renderizar_metabox_cursos_campus',
        'product', // Cambia 'product' por el tipo de publicación deseado si es necesario
        'normal',
        'default'
    );
}
add_action('add_meta_boxes', 'cc_agregar_metabox_cursos_campus');

// Renderizar el metabox con los campos
function cc_renderizar_metabox_cursos_campus($post)
{
    $intensidad_horaria = get_post_meta($post->ID, '_intensidad_horaria', true);
    $vigencia_certificado = get_post_meta($post->ID, 'fecha_expiracion_certificado', true);
    ?>
        <label for="intensidad_horaria">Intensidad Horaria:</label>
        <input type="text" id="intensidad_horaria" name="intensidad_horaria" value="<?php echo esc_attr(strtolower($intensidad_horaria)); ?>" style="width: 100%; text-transform: lowercase;" />

        <label for="fecha_expiracion_certificado" style="margin-top: 10px; display: block;">Vigencia del Certificado:</label>
        <input type="text" id="fecha_expiracion_certificado" name="fecha_expiracion_certificado" value="<?php echo esc_attr(strtolower($vigencia_certificado)); ?>" style="width: 100%; text-transform: lowercase;" />
    <?php
}

// Guardar los campos del metabox
function cc_guardar_metabox_cursos_campus($post_id)
{
    // Verificar y guardar Intensidad Horaria
    if (isset($_POST['intensidad_horaria'])) {
        $intensidad_horaria = sanitize_text_field(strtolower($_POST['intensidad_horaria']));
        update_post_meta($post_id, '_intensidad_horaria', $intensidad_horaria);
    }

    // Verificar y guardar Vigencia del Certificado
    if (isset($_POST['fecha_expiracion_certificado'])) {
        $vigencia_certificado = sanitize_text_field(strtolower($_POST['fecha_expiracion_certificado']));
        update_post_meta($post_id, 'fecha_expiracion_certificado', $vigencia_certificado);
    }
}
add_action('save_post', 'cc_guardar_metabox_cursos_campus');

//inicio email content

// Crear un metabox para el contenido del correo electrónico en el post tipo "empresa"
function cc_agregar_metabox_email_empresa() {
    add_meta_box(
        'empresa_email_contenido',              // ID del metabox
        'Contenido del Correo Electrónico',     // Título del metabox
        'cc_metabox_email_contenido_empresa',   // Función para mostrar el contenido del metabox
        'empresa',                              // Tipo de publicación
        'normal',                               // Contexto (normal, side)
        'high'                                  // Prioridad
    );
}
add_action('add_meta_boxes', 'cc_agregar_metabox_email_empresa');

// Función para mostrar el campo de contenido del correo electrónico en el metabox
function cc_metabox_email_contenido_empresa($post) {
    // Obtener el valor del contenido del correo si existe
    $contenido_email = get_post_meta($post->ID, '_contenido_email', true);

    // Agregar el editor de WordPress
    wp_editor($contenido_email, 'contenido_email', array(
        'textarea_name' => 'contenido_email',  // Nombre del campo
        'editor_height' => 200,                // Altura del editor
        'media_buttons' => false,              // Opcional: deshabilitar el botón de medios (para imágenes)
        'textarea_rows' => 10,                 // Número de filas en el editor
        'tinymce' => array(
            'theme_advanced_buttons1' => 'bold,italic,underline,strikethrough,alignleft,aligncenter,alignright,link,unlink,bullist,numlist,blockquote,formatselect,fontselect,fontsizeselect',
            'theme_advanced_buttons2' => '',
            'theme_advanced_buttons3' => '',
        ),
    ));
}

// Guardar el valor del contenido del correo electrónico al guardar la entrada
function cc_guardar_metabox_email_contenido_empresa($post_id) {
    // Verificar que el campo no esté vacío
    if (isset($_POST['contenido_email'])) {
        $contenido_email = sanitize_textarea_field($_POST['contenido_email']);
        update_post_meta($post_id, '_contenido_email', $contenido_email);
    }
}
add_action('save_post', 'cc_guardar_metabox_email_contenido_empresa');


//fin email content 


//metacampos 

//init
function registrar_metabox_cursos_salud() {
    try {
        error_log("Iniciando el registro del metabox para el tipo de publicación 'cursos-salud'.");
        add_meta_box(
            'metabox_cursos_salud',
            'Configuración del Curso',
            'renderizar_metabox_cursos_salud',
            'cursos-salud',
            'side',
            'high'
        );
        error_log("Metabox registrado exitosamente.");
    } catch (Exception $e) {
        error_log("Error al registrar el metabox: " . $e->getMessage());
    }
}
add_action('add_meta_boxes', 'registrar_metabox_cursos_salud');

// Al crear un nuevo post del tipo curso-salud, asignar valores predeterminados
function asignar_valores_predeterminados_curso_salud($post_id, $post) {
    try {
        if ($post->post_type === 'cursos-salud' && $post->post_status === 'auto-draft') {
            error_log("Asignando valores predeterminados al post ID {$post_id}.");
            
            $opciones_basico = [
                'tipo_certificado' => 'Curso básico',
                'tiempo_certificado' => 45,
                'unidad_tiempo_certificado' => 'horas',
                'duracion_certificado_tiempo' => 1,
                'unidad_duracion_certificado' => 'año',
            ];

            update_post_meta($post_id, '_tipo_certificado', $opciones_basico['tipo_certificado']);
            update_post_meta($post_id, '_tiempo_certificado', $opciones_basico['tiempo_certificado']);
            update_post_meta($post_id, '_descripcion_tiempo_certificado', $opciones_basico['unidad_tiempo_certificado']);
            update_post_meta($post_id, '_duracion_certificado_tiempo', $opciones_basico['duracion_certificado_tiempo']);
            update_post_meta($post_id, '_descripcion_duracion_certificado', $opciones_basico['unidad_duracion_certificado']);

            $intensidad_horaria = $opciones_basico['tiempo_certificado'] . ' ' . $opciones_basico['unidad_tiempo_certificado'];
            $vigencia_certificado = $opciones_basico['duracion_certificado_tiempo'] . ' ' . $opciones_basico['unidad_duracion_certificado'];

            update_post_meta($post_id, 'horas', $intensidad_horaria);
            update_post_meta($post_id, 'fecha_expiracion_certificado', $vigencia_certificado);

            error_log("Valores predeterminados asignados: " . print_r($opciones_basico, true));
        }
    } catch (Exception $e) {
        error_log("Error al asignar valores predeterminados: " . $e->getMessage());
    }
}
add_action('wp_insert_post', 'asignar_valores_predeterminados_curso_salud', 10, 2);

function renderizar_metabox_cursos_salud($post) {
    try {
        // Generar un campo nonce para seguridad
        wp_nonce_field('guardar_metadatos_cursos_salud_nonce', 'metabox_nonce');

        // Opciones predefinidas
        $opciones_predefinidas = [
            'basico' => [
                'tipo_certificado' => 'Curso básico',
                'tiempo_certificado' => 45,
                'unidad_tiempo_certificado' => 'horas',
                'duracion_certificado_tiempo' => 1,
                'unidad_duracion_certificado' => 'año',
            ],
            'avanzado' => [
                'tipo_certificado' => 'Curso avanzado',
                'tiempo_certificado' => 70,
                'unidad_tiempo_certificado' => 'horas',
                'duracion_certificado_tiempo' => 2,
                'unidad_duracion_certificado' => 'años',
            ],
            'diplomado' => [
                'tipo_certificado' => 'Diplomado en salud',
                'tiempo_certificado' => 160,
                'unidad_tiempo_certificado' => 'horas',
                'duracion_certificado_tiempo' => 3,
                'unidad_duracion_certificado' => 'años',
            ],
        ];

        // Obtener los valores actuales de los metadatos
        $categoria_seleccionada = get_post_meta($post->ID, '_categoria_certificado', true) ?: 'basico';
        $tipo_certificado = get_post_meta($post->ID, '_tipo_certificado', true);
        $tiempo_certificado = get_post_meta($post->ID, '_tiempo_certificado', true);
        $unidad_tiempo_certificado = get_post_meta($post->ID, '_descripcion_tiempo_certificado', true);
        $duracion_certificado_tiempo = get_post_meta($post->ID, '_duracion_certificado_tiempo', true);
        $unidad_duracion_certificado = get_post_meta($post->ID, '_descripcion_duracion_certificado', true);

        // Calcular valores derivados
        $intensidad_horaria = $tiempo_certificado . ' ' . $unidad_tiempo_certificado;
        $vigencia_certificado = $duracion_certificado_tiempo . ' ' . $unidad_duracion_certificado;

        // Log de datos actuales
        $log_data = [
            'categoria_seleccionada' => $categoria_seleccionada,
            'tipo_certificado' => $tipo_certificado,
            'tiempo_certificado' => $tiempo_certificado,
            'unidad_tiempo_certificado' => $unidad_tiempo_certificado,
            'duracion_certificado_tiempo' => $duracion_certificado_tiempo,
            'unidad_duracion_certificado' => $unidad_duracion_certificado,
            'intensidad_horaria' => $intensidad_horaria,
            'vigencia_certificado' => $vigencia_certificado,
        ];
        error_log("Datos actuales del Post ID {$post->ID}: " . print_r($log_data, true));

        // Renderizar el formulario del metabox
        ?>
        <div class="wrap">
            <label for="certificado_categoria_predefinida">Selecciona una Categoría:</label>
            <select name="certificado_categoria_predefinida" id="certificado_categoria_predefinida" class="widefat">
                <?php foreach ($opciones_predefinidas as $key => $opcion): ?>
                    <option value="<?php echo esc_attr($key); ?>" <?php selected($key, $categoria_seleccionada); ?>>
                        <?php echo esc_html($opcion['tipo_certificado']); ?>
                    </option>
                <?php endforeach; ?>
            </select>

            <h4>Detalles del Certificado</h4>

            <label for="certificado_tipo">Tipo de Categoría:</label>
            <input type="text" id="certificado_tipo" class="widefat" value="<?php echo esc_attr($tipo_certificado); ?>" readonly>

            <label for="certificado_intensidad_horaria">Intensidad Horaria:</label>
            <input type="text" id="certificado_intensidad_horaria" class="widefat" value="<?php echo esc_attr($intensidad_horaria); ?>" readonly>

            <label for="certificado_vigencia">Vigencia del Certificado:</label>
            <input type="text" id="certificado_vigencia" class="widefat" value="<?php echo esc_attr($vigencia_certificado); ?>" readonly>
        </div>

        <script>
            document.addEventListener('DOMContentLoaded', function () {
                const selectCategoria = document.getElementById('certificado_categoria_predefinida');
                const predefinedValues = <?php echo json_encode($opciones_predefinidas); ?>;

                // Actualiza los campos según la selección
                selectCategoria.addEventListener('change', function () {
                    const selectedOption = this.value;

                    if (predefinedValues[selectedOption]) {
                        const selectedData = predefinedValues[selectedOption];

                        // Actualizar valores dinámicamente
                        document.getElementById('certificado_tipo').value = selectedData.tipo_certificado || '';
                        document.getElementById('certificado_intensidad_horaria').value = (selectedData.tiempo_certificado || '') + ' ' + (selectedData.unidad_tiempo_certificado || '');
                        document.getElementById('certificado_vigencia').value = (selectedData.duracion_certificado_tiempo || '') + ' ' + (selectedData.unidad_duracion_certificado || '');
                    } else {
                        console.warn('Opción seleccionada no válida.');
                    }
                });
            });
        </script>
        <?php
    } catch (Exception $e) {
        error_log("Error al renderizar el metabox: " . $e->getMessage());
    }
}

function guardar_metadatos_cursos_salud($post_id) {
    try {
        // Verifica el nonce para seguridad
        if (!isset($_POST['metabox_nonce']) || !wp_verify_nonce($_POST['metabox_nonce'], 'guardar_metadatos_cursos_salud_nonce')) {
            throw new Exception("Nonce no válido.");
        }

        // Verifica si es un guardado automático
        if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
            throw new Exception("Guardado automático detectado, no se guardan datos.");
        }

        // Verifica permisos del usuario
        if (!current_user_can('edit_post', $post_id)) {
            throw new Exception("Permisos insuficientes para editar el post ID {$post_id}.");
        }

        // Opciones predefinidas
        $opciones_predefinidas = [
            'basico' => [
                'tipo_certificado' => 'Curso básico',
                'tiempo_certificado' => 45,
                'unidad_tiempo_certificado' => 'horas',
                'duracion_certificado_tiempo' => 1,
                'unidad_duracion_certificado' => 'año',
            ],
            'avanzado' => [
                'tipo_certificado' => 'Curso avanzado',
                'tiempo_certificado' => 70,
                'unidad_tiempo_certificado' => 'horas',
                'duracion_certificado_tiempo' => 2,
                'unidad_duracion_certificado' => 'años',
            ],
            'diplomado' => [
                'tipo_certificado' => 'Diplomado en salud',
                'tiempo_certificado' => 160,
                'unidad_tiempo_certificado' => 'horas',
                'duracion_certificado_tiempo' => 3,
                'unidad_duracion_certificado' => 'años',
            ],
        ];

        // Guardar la categoría seleccionada
        if (isset($_POST['certificado_categoria_predefinida'])) {
            $categoria_seleccionada = sanitize_text_field($_POST['certificado_categoria_predefinida']);
            update_post_meta($post_id, '_categoria_certificado', $categoria_seleccionada);

            // Actualizar los valores basados en la selección
            $seleccion = $opciones_predefinidas[$categoria_seleccionada];
            update_post_meta($post_id, '_tipo_certificado', $seleccion['tipo_certificado']);
            update_post_meta($post_id, '_tiempo_certificado', $seleccion['tiempo_certificado']);
            update_post_meta($post_id, '_descripcion_tiempo_certificado', $seleccion['unidad_tiempo_certificado']);
            update_post_meta($post_id, '_duracion_certificado_tiempo', $seleccion['duracion_certificado_tiempo']);
            update_post_meta($post_id, '_descripcion_duracion_certificado', $seleccion['unidad_duracion_certificado']);

            // Calcular valores derivados
            $intensidad_horaria = $seleccion['tiempo_certificado'] . ' ' . $seleccion['unidad_tiempo_certificado'];
            $vigencia_certificado = $seleccion['duracion_certificado_tiempo'] . ' ' . $seleccion['unidad_duracion_certificado'];
            update_post_meta($post_id, 'horas', $intensidad_horaria);
            update_post_meta($post_id, 'fecha_expiracion_certificado', $vigencia_certificado);

            // Log de guardado
            error_log("Metadatos actualizados para el Post ID {$post_id}: " . print_r($seleccion, true));
        }
    } catch (Exception $e) {
        error_log("Error al guardar los metadatos: " . $e->getMessage());
    }
}
add_action('save_post', 'guardar_metadatos_cursos_salud');
//end init



//fin metacampos salud
//generar certificados
function cc_generar_certificados_salud(){
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['generar_pdf_salud'])) {
        
        // Capturar datos del formulario
        $nombre = sanitize_text_field($_POST['nombre_salud']);
        $num_documento = sanitize_text_field($_POST['cedula_salud']);
        $cursos = isset($_POST['curso_salud']) ? (array) $_POST['curso_salud'] : [];
        $email = sanitize_email($_POST['email_salud']);
        $empresa_id = sanitize_text_field($_POST['empresa_salud']);
        $contenido_email = get_post_meta($empresa_id, '_contenido_email', true);
        $tipo_documento = isset($_POST['tipo_documento']) ? sanitize_text_field($_POST['tipo_documento']) : 'Cédula de Ciudadanía';
        $fecha_expedicion = sanitize_text_field($_POST['fecha_expedicion_certificado']);;
        $empresa_titulo = get_the_title($empresa_id);
        $empresa_imagen = get_the_post_thumbnail_url($empresa_id, 'full');
        $imagen_fondo = $empresa_imagen;
        if (!$imagen_fondo) {
            $imagen_fondo = plugins_url('assets/certificados_salud/default_background.jpg', __FILE__);
        }
        
        error_log('Tipo de documento POST: ' . print_r($_POST['tipo_documento'], true));
        error_log('Tipo de documento capturado: ' . $tipo_documento);

        // Configurar Dompdf
        $options = new Options();
        $options->set('isHtml5ParserEnabled', true);
        $options->set('isRemoteEnabled', true);

        // Crear directorio para certificados si no existe
        $plugin_dir = plugin_dir_path(__FILE__);
        $certificados_dir = $plugin_dir . 'certificados_salud/';
        if (!file_exists($certificados_dir)) {
            mkdir($certificados_dir, 0755, true);
        }

        // Array para almacenar los archivos PDF y enlaces
        $pdf_files = [];
        $pdf_links = [];

        // Iterar sobre los cursos seleccionados
        foreach ($cursos as $index => $curso_id) {
                $curso_nombre = get_the_title($curso_id);
                $intensidad_horaria = get_post_meta($curso_id, 'horas', true);
                $tipo_certificado = get_post_meta($curso_id, '_tipo_certificado', true);
                $vigencia_certificado = get_post_meta($curso_id, 'fecha_expiracion_certificado', true);
                $log_data = [
                'curso_nombre' => $curso_nombre,
                'intensidad_horaria' => $intensidad_horaria,
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
                                            FUNDACIÓN EDUCATIVA CAMPUS <br>NIT: 901386251-7
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
                    update_post_meta($certificado_id, 'fecha_expiracion_certificado', $vigencia_certificado);
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

        // Mostrar los enlaces para descargar
        echo '<div class="notice notice-success"><p>Se generaron los certificados correctamente:</p><ul>';
        foreach ($pdf_links as $link) {
            echo '<li><a href="' . esc_url($link) . '" target="_blank">Descargar Certificado</a></li>';
        }
        echo '</ul></div>';
    }
    ?>
                <div class="wrap" style="width: 50%; text-align: left;">
                    <h1>Generar Certificados de Salud</h1>
                    <form method="post" action="">
                        <!-- Nombre -->
                        <div class="mb-3">
                            <label for="nombre_salud">Nombre:</label>
                            <input type="text" name="nombre_salud" id="nombre_salud" class="form-control" required>
                        </div>
                        <!-- Cédula -->
                        <div class="mb-3">
                            <label for="cedula_salud">Numero documento:</label>
                            <input type="text" name="cedula_salud" id="cedula_salud" class="form-control" required>
                        </div>
                        <!-- Tipo de Documento -->
                        <div class="mb-3">
                            <label>Tipo de Documento:</label><br>
                            <div class="row">
                                <div class="col">
                                    <input type="radio" id="doc-cc" name="tipo_documento" value="Cédula de Ciudadanía" checked>
                                    <label for="doc-cc">Cédula de Ciudadanía</label>
                                </div>
                                <div class="col">
                                    <input type="radio" id="doc-ppt" name="tipo_documento" value="PPT">
                                    <label for="doc-ppt">PPT</label>
                                </div>
                                <div class="col">
                                        <input type="radio" id="doc-ti" name="tipo_documento" value="Tarjeta de identidad">
                                        <label for="doc-ti">Tarjeta de identidad</label>
                                    </div>
                            </div>
                        </div>
                        <!-- Selección de cursos -->
                        <div class="mb-3">
                            <label for="curso_salud">Cursos:</label>
                            <select name="curso_salud[]" id="curso_salud" class="form-select" multiple required>
                                <?php
                                $cursos = get_posts(array(
                                    'post_type' => 'cursos-salud',
                                    'posts_per_page' => -1,
                                    'post_status' => 'publish',
                                ));
                                foreach ($cursos as $curso) {
                                    echo '<option value="' . esc_attr($curso->ID) . '">' . esc_html($curso->post_title) . '</option>';
                                }
                                ?>
                            </select>
                        </div>
                        <!-- Selección de empresa -->
                        <div class="mb-3">
                            <label for="empresa_salud">Empresa:</label>
                            <select name="empresa_salud" id="empresa_salud" class="form-control" required>
                                <?php
                                $empresas = get_posts(array(
                                    'post_type' => 'empresa',
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
                            <input type="date" id="fecha_expedicion" name="fecha_expedicion_certificado" value="<?php echo esc_attr(date('Y-m-d')); ?>" class="form-control" style="width: 100%;" required>
                        </div>
                        <button type="submit" name="generar_pdf_salud" class="btn btn-primary">Generar Certificados</button>
                    </form>
                </div>
                <!-- JavaScript para Select2 -->
                <script>
                    jQuery(document).ready(function($) {
                        $('.form-select').select2({
                            placeholder: "Selecciona los cursos",
                            allowClear: true,
                            width: '100%'
                        });
                    });
                </script>
    <?php
        
}

///////////////////////////// FUNCIONA NO TOCAR /////////////////////////////
    function cc_generar_certificados_campus(){
        if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['generar_pdf_salud'])) {
            // Capturar datos del formulario
            $nombre = sanitize_text_field($_POST['nombre_salud']);
            $documento = sanitize_text_field($_POST['cedula_salud']);
            $cursos = isset($_POST['curso_salud']) ? (array) $_POST['curso_salud'] : [];
            $email = sanitize_email($_POST['email_salud']);
            $tipo_documento = isset($_POST['tipo_documento']) ? sanitize_text_field($_POST['tipo_documento']) : 'Cédula de Ciudadanía';
            $fecha_expedicion = sanitize_text_field($_POST['fecha_expedicion_certificado']);;
            //empresa_dev
            $empresa_id = sanitize_text_field($_POST['empresa_dev']);
            // Obtener el contenido del correo asociado a la empresa
            $contenido_email = get_post_meta($empresa_id, '_js_dev_contenido_email', true);
            // Obtener el título de la empresa
            $empresa_titulo = get_the_title($empresa_id);
            // Obtener la URL de la imagen destacada de la empresa
            $empresa_imagen = get_the_post_thumbnail_url($empresa_id, 'full');
            //fin empresa_dev
            $imagen_fondo = $empresa_imagen;
            $test_meta = get_post_meta(679, '_js_dev_contenido_email', true); // Reemplaza 679 con el ID de la empresa
            // Crear un array con los datos para el log
            $log_data = array(
                'nombre' => $nombre,
                'Numero Doc' => $documento,
                'cursos' => implode(', ', $cursos),
                'email' => $email,
                'tipo_documento' => $tipo_documento,
                'fecha_expedicion' => $fecha_expedicion,
                'empresa_dev' => array(
                    'empresa_id' => $empresa_id,
                    'contenido_email' => $contenido_email,
                    'empresa_titulo' => $empresa_titulo,
                    'empresa_imagen' => $empresa_imagen,
                ),
                "ID del sitio actual: " . get_current_blog_id(),
                
                "Test de metadato: " . print_r($test_meta, true)
                
            );
            error_log("Datos capturados: " . print_r($log_data, true));
            if (isset($_POST['contenido_email'])) {
                update_post_meta($post_id, '_js_dev_contenido_email', sanitize_text_field($_POST['contenido_email']));
                error_log("Contenido del correo actualizado: " . sanitize_text_field($_POST['contenido_email']));
            }
            // Escribir en el log
            //error_log("Datos capturados: " . print_r($log_data, true));

            // Configurar Dompdf
            $options = new Options();
            $options->set('isHtml5ParserEnabled', true);
            $options->set('isRemoteEnabled', true);

            // Crear directorio para certificados si no existe
            $plugin_dir = plugin_dir_path(__FILE__);
            $certificados_dir = $plugin_dir . 'certificados/';
            if (!file_exists($certificados_dir)) {
                mkdir($certificados_dir, 0755, true);
            }

            // Array para almacenar los archivos PDF y enlaces
            $pdf_files = [];
            $pdf_links = [];

            // Iterar sobre los cursos seleccionados
            foreach ($cursos as $index => $curso_id) {
                $metadatos_curso = get_post_meta($curso_id);
                $curso_nombre = get_the_title($curso_id);
                $categorias = wp_get_post_terms($curso_id, 'product_cat', ['fields' => 'names']);
                $tipo_certificado = !empty($categorias) ? implode(', ', $categorias) : 'Sin categorías';
                $intensidad_horaria = get_post_meta($curso_id, '_intensidad_horaria', true) ;
                $vigencia_certificado = get_post_meta($curso_id, 'fecha_expiracion_certificado', true);
                // HTML dinámico para el PDF
                error_log('Curso Nombre: ' . $curso_nombre);
                error_log("Categorías: $tipo_certificado");
                error_log('Intensidad Horaria: ' . $intensidad_horaria);
                error_log('Vigencia Certificado: ' . $vigencia_certificado);
                 // Obtener categorías
                
             
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
                                        FUNDACIÓN EDUCATIVA CAMPUS <br>NIT: 901386251-7 
                                    </p>
                                    <div>
                                    <p class="certifica" style="font-family: constan; text-align: left; font-size: 20px;">CERTIFICA QUE:</p>   
                                    <p class="nombre" style="color: rgb(5, 0, 48);">' . $nombre . '</p>
                                    <p class="documento">Identificado(a) con ' . $tipo_documento . '</p>
                                    <p class="cedula">No° : ' . $documento . '</p>
                                    </div>
                                    <p class="realiza">Realizó y aprobó el ' .$empresa_titulo.' de:</p>
                                    <p class="nombre_curso"> '. $curso_nombre . ' </p>
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
                    $pdf_filename = "certificado_campus_{$documento}_{$curso_id}.pdf";
                    $pdf_path = $certificados_dir . $pdf_filename;
                    file_put_contents($pdf_path, $dompdf->output());
                    $pdf_url = plugins_url('certificados/' . $pdf_filename, __FILE__);
                    //error_log("PDF guardado correctamente en $pdf_path.");
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
                    update_post_meta($certificado_id, 'fecha_expiracion_certificado', $vigencia_certificado);
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
            
                try {
                            // Enviar correo con los PDFs generados como adjuntos
                            if (!empty($pdf_files)) {
                                $asunto = "Certificados Generados para $nombre";
                                $mensaje = nl2br($contenido_email);
                                $headers = ['Content-Type: text/html; charset=UTF-8'];
                                error_log("Enviando correo electrónico...");
                                
                                // Log de argumentos del correo
                                error_log("Detalles del correo a enviar: 
                                    Asunto: $asunto, 
                                    Destinatario: $email, 
                                    Mensaje: mensaje, 
                                    cotenido_email: contenido_email,
                                    Archivos adjuntos: " . implode(', ', $pdf_files));
                                
                                $correo_enviado = wp_mail(
                                    $email,
                                    $asunto,
                                    $mensaje,
                                    $headers,
                                    $pdf_files // Archivos adjuntos
                                );
                        
                                if (!$correo_enviado) {
                                    throw new Exception('El correo no pudo ser enviado.');
                                }
                        
                                error_log("Correo electrónico enviado correctamente.");
                            }
                        } catch (Exception $e) {
                            error_log("Error al enviar el correo: " . $e->getMessage());
                            echo '<div class="notice notice-error"><p>Error al enviar el correo electrónico.</p></div>';
                            return;
                        }
        
                        try {
                            if ($correo_enviado) {
                                echo '<div class="notice notice-success"><p>Certificados generados correctamente y enviados por correo electrónico.</p></div>';
                            } else {
                                echo '<div class="notice notice-error"><p>Hubo un error al enviar el correo.</p></div>';
                            }
                        } catch (Exception $e) {
                            error_log("Error al mostrar el mensaje de éxito: " . $e->getMessage());
                            echo '<div class="notice notice-error"><p>Error al finalizar el proceso.</p></div>';
                        }
                        echo '<div class="notice notice-success"><p>Se generaron los certificados correctamente:</p><ul>';
                        foreach ($pdf_links as $link) {
                            echo '<li><a href="' . esc_url($link) . '" target="_blank">Descargar Certificado</a></li>';
                        }
                        echo '</ul></div>';
    
            }        
        ?>
                    <div class="wrap" style="width: 50%; text-align: left;">
                        <h1>Generar Certificados Campus</h1>
                        <form method="post" action="">
                            <!-- Nombre -->
                            <div class="mb-3">
                                <label for="nombre_salud">Nombre:</label>
                                <input type="text" name="nombre_salud" id="nombre_salud" class="form-control" required>
                            </div>
                            <!-- Cédula -->
                            <div class="mb-3">
                                <label for="cedula_salud">Numero documento:</label>
                                <input type="text" name="cedula_salud" id="cedula_salud" class="form-control" required>
                            </div>
                            <!-- Tipo de Documento -->
                            <div class="mb-3">
                                <label>Tipo de Documento:</label><br>
                                <div class="row">
                                    <div class="col">
                                        <input type="radio" id="doc-cc" name="tipo_documento" value="Cédula de Ciudadanía" checked>
                                        <label for="doc-cc">Cédula de Ciudadanía</label>
                                    </div>
                                    <div class="col">
                                        <input type="radio" id="doc-ppt" name="tipo_documento" value="PPT">
                                        <label for="doc-ppt">PPT</label>
                                    </div>
                                    <div class="col">
                                        <input type="radio" id="doc-ti" name="tipo_documento" value="Tarjeta de identidad">
                                        <label for="doc-ti">Tarjeta de identidad</label>
                                    </div>
                                </div>
                            </div>
                            <!-- Selección de cursos -->
                            <div class="mb-3">
                                <label for="curso_salud">Cursos:</label>
                                <select name="curso_salud[]" id="curso_salud" class="form-select" multiple required>
                                    <?php
                                    $productos = wc_get_products(array(
                                        'limit' => -1, // Trae todos los productos
                                        'status' => 'publish',
                                    ));
                                    foreach ($productos as $producto) {
                                        echo '<option value="' . esc_attr($producto->get_id()) . '">' . esc_html($producto->get_name()) . '</option>';
                                    }
                                    ?>
                                </select>
                            </div>

                            <!-- Selección de empresa_Dev -->
                            <div class="mb-3">
                            <label for="empresa_dev">Certificados Disponibles:</label>
                            <select name="empresa_dev" id="empresa_dev" class="form-control" required>
                                <?php
                                // Obtener todas las entradas del tipo de publicación 'empresa_dev'
                                $empresas = get_posts(array(
                                    'post_type' => 'empresa_dev', // Cambiar a empresa_dev
                                    'posts_per_page' => -1,
                                    'post_status' => 'publish',
                                ));
                                // Generar las opciones del select
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
                                <input type="date" id="fecha_expedicion" name="fecha_expedicion_certificado" value="<?php echo esc_attr(date('Y-m-d')); ?>" class="form-control" style="width: 100%;" required>
                            </div>
                            <button type="submit" name="generar_pdf_salud" class="btn btn-primary">Generar Certificados</button>
                        </form>
                    </div>
                    <!-- JavaScript para Select2 -->
                    <script>
                        jQuery(document).ready(function($) {
                            $('.form-select').select2({
                                placeholder: "Selecciona los cursos",
                                allowClear: true,
                                width: '100%'
                            });
                        });
                    </script>
        <?php
            
    }
/////////////////////////////////METADATA 
        //js_dev_email_campus_init_

//js_dev_email_campus_init_
    // Registrar el tipo de publicación "Empresas de Salud Dev"
        function js_dev_register_post_type_empresa_dev() {
            $args_empresas_dev = array(
                'public' => true,
                'show_ui' => true,
                'show_in_menu' => true,
                'label' => 'Campus Certificados',
                'supports' => array('title', 'editor', 'thumbnail'),
                'menu_icon' => 'dashicons-businessperson',
                'rewrite' => array('slug' => 'empresa_dev'),
                'menu_position' => 20,
            );
            register_post_type('empresa_dev', $args_empresas_dev);
        }
        add_action('init', 'js_dev_register_post_type_empresa_dev');

        // Agregar metabox para contenido de correo electrónico
        function js_dev_agregar_metabox_email_empresa() {
            add_meta_box(
                'empresa_email_contenido',              // ID del metabox
                'Contenido del Correo Electrónico',     // Título del metabox
                'js_dev_metabox_email_contenido_empresa',   // Callback para mostrar el contenido del metabox
                'empresa_dev',                              // Tipo de publicación
                'normal',                               // Contexto (normal, side)
                'high'                                  // Prioridad
            );
        }
        add_action('add_meta_boxes', 'js_dev_agregar_metabox_email_empresa');

        // Mostrar el editor de contenido de correo electrónico en el metabox
        function js_dev_metabox_email_contenido_empresa($post) {
            // Obtener el valor actual del metacampo
            $contenido_email = get_post_meta($post->ID, '_js_dev_contenido_email', true);

            // Añadir un nonce para seguridad
            wp_nonce_field('js_dev_guardar_email', 'js_dev_email_nonce');

            // Mostrar el editor
            wp_editor($contenido_email, 'js_dev_contenido_email', array(
                'textarea_name' => 'js_dev_contenido_email',  // Nombre del campo
                'editor_height' => 200,                      // Altura del editor
                'media_buttons' => false,                    // Deshabilitar botón de medios
                'textarea_rows' => 10                        // Número de filas en el editor
            ));
        }

        // Guardar contenido del correo electrónico
        function js_dev_guardar_contenido_email($post_id) {
            // Verificar el tipo de post
            if (get_post_type($post_id) !== 'empresa_dev') {
                return; // Salir si no es del tipo esperado
            }

            // Verificar el nonce para seguridad
            if (!isset($_POST['js_dev_email_nonce']) || !wp_verify_nonce($_POST['js_dev_email_nonce'], 'js_dev_guardar_email')) {
                return; // Salir si el nonce no es válido
            }

            // Evitar ejecución innecesaria en autosave
            if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
                return;
            }

            // Verificar permisos del usuario actual
            if (!current_user_can('edit_post', $post_id)) {
                return;
            }

            // Guardar el contenido del correo
            if (isset($_POST['js_dev_contenido_email'])) {
                $contenido_email = sanitize_textarea_field($_POST['js_dev_contenido_email']);
                update_post_meta($post_id, '_js_dev_contenido_email', $contenido_email);
            }
        }
        add_action('save_post', 'js_dev_guardar_contenido_email');

    //js_dev_email_campus end_




/////////////////////////////////END METADATA




//////////////////////// campus end 
////////////////////////Shortcode para mostrar a los usuarios sus certificados VIGENTES o vencidos

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
                    $solo_numeros = preg_replace('/\D/', '', $fecha_certificado);                
                    //$fecha_actual = date('Y-m-dTH:i:s'); 
                    $fecha_actual = current_time('mysql');
                    $fecha_expedicion = get_post_meta(get_the_ID(), 'fecha_expedicion', true);
                    $fecha_calculada = date('Y-m-d', strtotime("+{$solo_numeros} years", strtotime($fecha_expedicion)));
                   
                    error_log('Fecha de expiración: ' . $fecha_certificado);
                    error_log('Fecha actual: ' . $fecha_actual);
                    error_log('Fecha de expedición: ' . $fecha_expedicion);
                    error_log('Calcular fecha de vencimiento: ' . $fecha_calculada);
                    // Comparar las fechas

                    if (strtotime($fecha_calculada) > strtotime($fecha_actual)) {
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

        // Obtener el valor de la fecha desde el meta box
        $fecha_certificado = get_post_meta(get_the_ID(), 'fecha_expiracion_certificado', true);
        $solo_numeros = preg_replace('/\D/', '', $fecha_certificado);                
        //$fecha_actual = date('Y-m-dTH:i:s'); 
        $fecha_actual = current_time('mysql');
        $fecha_expedicion = get_post_meta(get_the_ID(), 'fecha_expedicion', true);
        $fecha_calculada = date('Y-m-d', strtotime("+{$solo_numeros} years", strtotime($fecha_expedicion)));

        error_log('Fecha de expiración: ' . $fecha_certificado);
        error_log('Fecha actual: ' . $fecha_actual);
        error_log('Fecha de expedición: ' . $fecha_expedicion);
        error_log('Calcular fecha de vencimiento: ' . $fecha_calculada);
        // Comparar las fechas

        if (strtotime($fecha_calculada) > strtotime($fecha_actual)) {
            $estado = '<span style="color: green;">VIGENTE</span>';
        } else {
            $estado = '<span style="color: red;">EXPIRADO</span>';
        }

        echo '<p>' . $estado . '</p>';

        return ob_get_clean(); // Obtener el contenido del buffer y limpiarlo
    }
    add_shortcode('estado_certificados_shortcode', 'estado_certificados_shortcode');


/////////////////////// fin shortcode






        
//fin generar certificados
add_action('wp_ajax_guardar_imagen_certificado', function () {
    if (!current_user_can('manage_options')) {
        wp_send_json_error('No tienes permisos suficientes.');
    }

    if (isset($_FILES['imagen_fondo_certificado'])) {
        $upload = wp_handle_upload($_FILES['imagen_fondo_certificado'], array('test_form' => false));
        if (!isset($upload['error']) && isset($upload['url'])) {
            update_option('imagen_fondo_certificado', $upload['url']);
            wp_send_json_success();
        } else {
            wp_send_json_error($upload['error']);
        }
    } else {
        wp_send_json_error('No se proporcionó ninguna imagen.');
    }
});
//end


function cc_buscar_cursos()
{
    if (isset($_POST['search_term'])) {
        $search_term = sanitize_text_field($_POST['search_term']);

        // Consultar productos con el término de búsqueda
        $args = array(
            'post_type' => 'product',
            'posts_per_page' => 50, // Limitar resultados a 50
            's' => $search_term, // Filtrar por el término ingresado
            'tax_query' => array(
                array(
                    'taxonomy' => 'product_tag',
                    'field'    => 'slug',
                    'terms'    => 'curso', // Asegurarse de que solo se busquen los productos con etiqueta "curso"
                ),
            ),
        );
        $query = new WP_Query($args);

        if ($query->have_posts()) {
            while ($query->have_posts()) {
                $query->the_post();
                echo '<option value="' . get_the_ID() . '">' . get_the_title() . '</option>';
            }
        } else {
            echo '<option value="">No se encontraron cursos</option>';
        }
        wp_reset_postdata();
    }
    wp_die(); // Terminar el proceso AJAX correctamente
}
// Registrar la acción AJAX
add_action('wp_ajax_buscar_cursos', 'cc_buscar_cursos');
add_action('wp_ajax_nopriv_buscar_cursos', 'cc_buscar_cursos');
// buscar cursos dinamicamente en caso de que sean muchos, esto es para optimizar el plugin y que no tenga problemas con la memoria 

//Cargar el logo de la empresa seleccionada durante la eleccion del certificado
function cc_mostrar_logo_empresa()
{
    if (isset($_POST['empresa_id']) && !empty($_POST['empresa_id'])) {
        $empresa_id = intval($_POST['empresa_id']);

        // Obtener la imagen destacada (logo) de la empresa
        $logo_url = get_the_post_thumbnail_url($empresa_id, 'full');

        // Si existe un logo, lo retornamos, si no, retornamos un string vacío
        echo $logo_url ? $logo_url : '';
    }

    wp_die(); // Necesario para finalizar la ejecución de la acción AJAX
}
add_action('wp_ajax_mostrar_logo_empresa', 'cc_mostrar_logo_empresa');
add_action('wp_ajax_nopriv_mostrar_logo_empresa', 'cc_mostrar_logo_empresa');


//end
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
        'cc_generar_certificados_salud'   // Función que se ejecutará
    );

    // Subpágina para "Certificados de Campus"
    add_submenu_page(
        'edit.php?post_type=certificado', // El menú principal
        'Certificados de Campus',         // Título de la subpágina
        'Generar certificados Campus',         // Título en el menú
        'manage_options',                 // Permisos requeridos
        'certificados-campus',            // Slug de la subpágina
        'cc_generar_certificados_campus'  // Función que se ejecutará
    );
}
add_action('admin_menu', 'cc_agregar_subpaginas_certificados'); // Añadir las subpáginas al hook admin_menu


//Fin codigo 

// Registra la taxonomía "Estado" para el tipo de publicación "Cursos"
function cc_registrar_taxonomia_estado()
{
    $labels = array(
        'name'              => _x('Estados', 'taxonomy general name'),
        'singular_name'     => _x('Estado', 'taxonomy singular name'),
        'search_items'      => __('Buscar Estados'),
        'all_items'         => __('Todos los Estados'),
        'parent_item'       => __('Estado Padre'),
        'parent_item_colon' => __('Estado Padre:'),
        'edit_item'         => __('Editar Estado'),
        'update_item'       => __('Actualizar Estado'),
        'add_new_item'      => __('Añadir Nuevo Estado'),
        'new_item_name'     => __('Nuevo Nombre de Estado'),
        'menu_name'         => __('Estados'),
    );

    $args = array(
        'hierarchical'      => true, // Si la taxonomía debe tener una estructura jerárquica como las categorías
        'labels'            => $labels,
        'show_ui'           => true,
        'show_admin_column' => true,
        'query_var'         => true,
        'rewrite'           => array('slug' => 'estado'), // Slug para las URLs de la taxonomía
    );

    register_taxonomy('estado', array('curso'), $args);
}

add_action('init', 'cc_registrar_taxonomia_estado');

// Registra la taxonomía "Categoria" para el tipo de publicación "Cursos"
function cc_registrar_taxonomia_categoria()
{
    $labels = array(
        'name'              => _x('Categorías', 'taxonomy general name'),
        'singular_name'     => _x('Categoría', 'taxonomy singular name'),
        'search_items'      => __('Buscar Categorías'),
        'all_items'         => __('Todos los Categorías'),
        'parent_item'       => __('Categoría Padre'),
        'parent_item_colon' => __('Categoría Padre:'),
        'edit_item'         => __('Editar Categoría'),
        'update_item'       => __('Actualizar Categoría'),
        'add_new_item'      => __('Añadir Nueva Categoría'),
        'new_item_name'     => __('Nuevo Nombre de Categoría'),
        'menu_name'         => __('Categorías'),
    );

    $args = array(
        'hierarchical'      => true, // Si la taxonomía debe tener una estructura jerárquica como las categorías
        'labels'            => $labels,
        'show_ui'           => true,
        'show_admin_column' => true,
        'query_var'         => true,
        'rewrite'           => array('slug' => 'categoria-curso'), // Slug para las URLs de la taxonomía
    );

    register_taxonomy('categoria-curso', array('curso'), $args);
}

add_action('init', 'cc_registrar_taxonomia_categoria');


// Registrar el metabox para el campo personalizado "descripcion" en el tipo de publicación "Cursos"
add_action('add_meta_boxes', 'cc_agregar_metabox_descripcion');

function cc_agregar_metabox_descripcion()
{
    add_meta_box(
        'cc_curso_descripcion',               // ID único del metabox
        'Descripción del curso',              // Título del metabox
        'cc_renderizar_metabox_descripcion',  // Función para renderizar el contenido del metabox
        'curso',                         // Tipo de publicación al que se aplica el metabox
        'normal',                        // Contexto del metabox (normal, advanced, side)
        'core'                        // Prioridad del metabox (high, core, default, low)
    );
}

// Función para renderizar el contenido del metabox de descripcion
function cc_renderizar_metabox_descripcion($post)
{
    // Obtiene el valor actual del campo personalizado "descripcion_curso" si existe
    $descripcion = get_post_meta($post->ID, 'descripcion_curso', true);
?>
    <label for="descripcion_curso">Descripción del curso:</label>
    <?php wp_editor($descripcion, 'descripcion_curso', array(
        'textarea_name' => 'descripcion_curso', // Asegura que el nombre sea 'descripcion_curso'
        'textarea_rows' => 10, // Ajusta el número de filas visibles del editor
        'editor_class' => 'descripcion_curso_editor', // Opcional: añade una clase CSS personalizada
        'editor_height' => 200 // Ajusta la altura del editor si es necesario
    )); ?>
<?php
}

// Guarda el valor del campo personalizado "descripcion" al guardar el post
add_action('save_post', 'cc_guardar_curso_descripcion');

function cc_guardar_curso_descripcion($post_id)
{
    // Verifica si el campo "curso_descripcion" está presente en $_POST
    if (isset($_POST['curso_descripcion'])) {
        // Actualiza el valor del campo personalizado "descripcion"
        update_post_meta($post_id, 'descripcion_curso', sanitize_text_field($_POST['curso_descripcion']));
    }
}



// Registrar los campos personalizados para el tipo de publicación "Certificados"
add_action('add_meta_boxes', 'cc_agregar_campos_personalizados_certificados');

function cc_agregar_campos_personalizados_certificados()
{
    add_meta_box(
        'cc_certificado_campos_personalizados',        // ID único del metabox
        'Campos Personalizados',                       // Título del metabox
        'cc_renderizar_metabox_campos_personalizados', // Función para renderizar el contenido del metabox
        'certificado',                                 // Tipo de publicación al que se aplica el metabox
        'normal',                                      // Contexto del metabox (normal, advanced, side)
        'default'                                      // Prioridad del metabox (high, core, default, low)
    );
}


// Función para renderizar el contenido del metabox de campos personalizados de Certificados
function cc_renderizar_metabox_campos_personalizados($post)
{
    // Obtiene el valor actual de los campos personalizados si existen
    $nombre = get_post_meta($post->ID, 'nombre_certificado', true);
    $cedula = get_post_meta($post->ID, 'cedula_certificado', true);
    $curso = get_post_meta($post->ID, 'curso_certificado', true);
    $email = get_post_meta($post->ID, 'email_certificado', true);
    $pdf_content = get_post_meta($post->ID, 'pdf_file', true);
    $horas = get_post_meta($post->ID, 'horas', true);
    $fecha_expedicion = get_post_meta($post->ID, 'fecha_expedicion', true);
    $vigencia_certificado = get_post_meta($post->ID, 'fecha_expiracion_certificado', true);
    //Vigencia
    $fecha_actual = current_time('mysql'); // Fecha actual en formato 'Y-m-d H:i:s'
    $fecha_actual = date('Y-m-d', strtotime($fecha_actual)); // Convertir a solo 'Y-m-d'
    
    $fecha_expedicion = get_post_meta($post->ID, 'fecha_expedicion', true);
    $vigencia_certificado = get_post_meta($post->ID, 'fecha_expiracion_certificado', true); // Asegúrate de usar el mismo $post->ID
    
    $solo_numeros = preg_replace('/\D/', '', $vigencia_certificado); // Solo números
    $fecha_calculada = date('Y-m-d', strtotime("+{$solo_numeros} years", strtotime($fecha_expedicion))); // Fecha con vigencia añadida
    
    $estatus_vencimiento = strtotime($fecha_calculada) < strtotime($fecha_actual) ? 'EXPIRADO' : 'Vigente';




?>
        <label for="nombre">Nombre:</label><br>
        <input type="text" id="nombre" name="nombre_certificado" value="<?php echo esc_attr($nombre); ?>" readonly /><br><br>

        <label for="cedula">Cédula:</label><br>
        <input type="text" id="cedula" name="cedula_certificado" value="<?php echo esc_attr($cedula); ?>" readonly /><br><br>

        <label for="curso">Curso:</label><br>
        <input type="text" id="curso" name="curso_certificado" value="<?php echo esc_attr($curso); ?>" readonly /><br><br>

        <label for="email">Email:</label><br>
        <input type="email" id="email" name="email_certificado" value="<?php echo esc_attr($email); ?>" readonly /><br><br>

        <label for="horas">Intensidad horaria:</label><br>
        <input type="text" id="horas" name="horas" value="<?php echo esc_attr($horas); ?>" readonly /><br><br>


        <label for="fecha_expedicion">Fecha Expedicion Certificado:</label><br>
        <input type="text" id="vigencia_certificado" name="vigencia_certificado" value="<?php echo esc_attr($fecha_expedicion); ?>" readonly /><br><br>

        <label for="fecha_expedicion">Vigencia Certificado:</label><br>
        <input type="text" id="fecha_expedicion" name="fecha_expedicion" value="<?php echo esc_attr($vigencia_certificado); ?>" readonly /><br><br>


        <label for="pdf_file">Certificado PDF:</label><br>
        <?php if (!empty($pdf_content)) : ?>
            <a href="<?php echo esc_url($pdf_content); ?>" target="_blank" class="button">Descargar Certificado PDF</a>
        <?php else : ?>
            <span>No hay un PDF asociado.</span>
        <?php endif; ?>
        <br><br>
        
        
<?php

}



// Guarda los valores de los campos personalizados al guardar el post de Certificados

// Registrar el campo personalizado de "Fecha de Expiración" para el tipo de publicación "Certificados"
add_action('add_meta_boxes', 'cc_agregar_campo_fecha_expiracion');

function cc_agregar_campo_fecha_expiracion()
{
    add_meta_box(
        'cc_certificado_fecha_expiracion',           // ID único del metabox
        'Fecha de Expiración',                       // Título del metabox
        'cc_renderizar_metabox_fecha_expiracion',    // Función para renderizar el contenido del metabox
        'certificado',                               // Tipo de publicación al que se aplica el metabox
        'normal',                                    // Contexto del metabox (normal, advanced, side)
        'default'                                    // Prioridad del metabox (high, core, default, low)
    );
}

// Función para renderizar el contenido del metabox de "Fecha de Expiración"
function cc_renderizar_metabox_fecha_expiracion($post)
{
/*     $fecha_actual = current_time('mysql');
    $fecha_expedicion = get_post_meta($post->ID, 'fecha_expedicion', true);
    $vigencia_certificado = get_post_meta($post_id, 'vigencia_certificado', true);
    $solo_numeros = preg_replace('/\D/', '', $vigencia_certificado); 
    $fecha_calculada = date('Y-m-d', strtotime("+{$solo_numeros} years", strtotime($fecha_expedicion))); */
        //js_datetime
        $fecha_actual = current_time('mysql'); // Fecha actual en formato 'Y-m-d H:i:s'
        $fecha_actual = date('Y-m-d', strtotime($fecha_actual)); // Convertir a solo 'Y-m-d'
        
        $fecha_expedicion = get_post_meta($post->ID, 'fecha_expedicion', true);
        $vigencia_certificado = get_post_meta($post->ID, 'fecha_expiracion_certificado', true); // Asegúrate de usar el mismo $post->ID
        
        $solo_numeros = preg_replace('/\D/', '', $vigencia_certificado); // Solo números
        $fecha_calculada = date('Y-m-d', strtotime("+{$solo_numeros} years", strtotime($fecha_expedicion))); // Fecha con vigencia añadida
        
        $estatus_vencimiento = strtotime($fecha_calculada) < strtotime($fecha_actual) ? 'EXPIRADO' : 'Vigente';

    ?>
    <label for="fecha_expiracion">Fecha de Expiración:</label><br>
    <input type="date" id="fecha_expiracion" name="fecha_expiracion_certificado" 
        value="<?php echo esc_attr(date('Y-m-d', strtotime($fecha_calculada))); ?>"readonly /><br><br>
    <p><strong>Estado:</strong> <?php echo $estatus_vencimiento; ?></p>
    <?php
}


// Guarda el valor del campo personalizado de "Fecha de Expiración" al guardar el post



function get_site_domain()
{
    $home_url = home_url();
    $parsed_url = parse_url($home_url);
    return $parsed_url['host'];
}




/* ///////////////////////////////////////////////////////////////////////////////////////
// manejador de errores en servidor /////////


///////////////////////////////////////////////////////////////////////////////////////
/////// FIN MANEJADOR DE ERRORES EN SERVIDOR ////////////////////////////////////////// */