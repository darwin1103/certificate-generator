<?php

// META BOX - PRODUCTS

// Registrar el metabox para productos (WooCommerce)
function cc_registrar_metabox_productos() {
    add_meta_box(
        'cc_metabox_intensidad_horaria',
        'Intensidad Horaria',
        'cc_renderizar_metabox_intensidad_horaria',
        'product',
        'side',
        'default'
    );
    add_meta_box(
        'cc_metabox_vigencia_certificado',
        'Vigencia del Certificado',
        'cc_renderizar_metabox_vigencia_certificado',
        'product',
        'side',
        'default'
    );
}
add_action('add_meta_boxes', 'cc_registrar_metabox_productos');

// Renderizar Intensidad Horaria (numérico)
function cc_renderizar_metabox_intensidad_horaria($post) {
    wp_nonce_field('cc_metabox_productos_nonce_action', 'cc_metabox_productos_nonce');
    $valor = get_post_meta($post->ID, '_intensidad_horaria', true);
    ?>
    <label for="intensidad_horaria">Intensidad Horaria (en horas):</label>
    <input type="number" id="intensidad_horaria" name="intensidad_horaria" value="<?php echo esc_attr($valor); ?>" min="1" step="1" style="width: 100%;" />
    <?php
}

// Renderizar Vigencia del Certificado (select)
function cc_renderizar_metabox_vigencia_certificado($post) {
    $opciones = array(
        '1 año' => '1 AÑO',
        '2 años' => '2 AÑOS',
        '3 años' => '3 AÑOS'
    );
    $valor = get_post_meta($post->ID, 'fecha_expiracion_certificado', true);
    ?>
    <label for="fecha_expiracion_certificado">Vigencia del Certificado:</label>
    <select id="fecha_expiracion_certificado" name="fecha_expiracion_certificado" style="width: 100%;">
        <?php foreach ($opciones as $k => $label) : ?>
            <option value="<?php echo esc_attr($k); ?>" <?php selected($valor, $k); ?>><?php echo esc_html($label); ?></option>
        <?php endforeach; ?>
    </select>
    <?php
}

// Guardar los valores de los meta boxes
function cc_guardar_metabox_productos($post_id) {
    // Validaciones básicas
    if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) return;
    if (!isset($_POST['cc_metabox_productos_nonce']) || !wp_verify_nonce($_POST['cc_metabox_productos_nonce'], 'cc_metabox_productos_nonce_action')) return;
    if (!current_user_can('edit_post', $post_id)) return;
    if (get_post_type($post_id) !== 'product') return;

    // Guardar Intensidad Horaria (numérico)
    if (isset($_POST['intensidad_horaria'])) {
        $intensidad = intval($_POST['intensidad_horaria']);
        update_post_meta($post_id, '_intensidad_horaria', $intensidad);
    }

    // Guardar Vigencia del Certificado (select)
    if (isset($_POST['fecha_expiracion_certificado'])) {
        $vigencia = sanitize_text_field($_POST['fecha_expiracion_certificado']);
        update_post_meta($post_id, 'fecha_expiracion_certificado', $vigencia);
    }
}
add_action('save_post', 'cc_guardar_metabox_productos');


// META BOX - EMPRESAS

// Registrar el metabox para contenido de email en 'empresa'
function cc_agregar_metabox_email_empresa() {
    add_meta_box(
        'empresa_email_contenido',              // ID del metabox
        'Contenido del Correo Electrónico',     // Título del metabox
        'cc_metabox_email_contenido_empresa',   // Callback de renderizado
        array('empresa_campus', 'empresa'),                              // Tipo de post
        'normal',                               // Contexto
        'high'                                  // Prioridad
    );
}
add_action('add_meta_boxes', 'cc_agregar_metabox_email_empresa');

// Renderizar el campo usando wp_editor y un nonce de seguridad
function cc_metabox_email_contenido_empresa($post) {
    // Nonce para seguridad
    wp_nonce_field('cc_email_empresa_nonce_action', 'cc_email_empresa_nonce');
    // Obtener el valor actual
    $contenido_email = get_post_meta($post->ID, '_contenido_email', true);

    wp_editor($contenido_email, 'contenido_email', array(
        'textarea_name' => 'contenido_email',
        'editor_height' => 200,
        'media_buttons' => false,
        'textarea_rows' => 10,
    ));
}

// Guardar el valor del contenido del correo electrónico al guardar la entrada
function cc_guardar_metabox_email_contenido_empresa($post_id) {
    // Validar autosave
    if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) return;
    // Validar nonce
    if (!isset($_POST['cc_email_empresa_nonce']) || !wp_verify_nonce($_POST['cc_email_empresa_nonce'], 'cc_email_empresa_nonce_action')) return;
    // Validar permisos
    if (!current_user_can('edit_post', $post_id)) return;
    // Validar tipo de post
    if (get_post_type($post_id) !== 'empresa') return;

    if (isset($_POST['contenido_email'])) {
        // Usa wp_kses_post para permitir HTML seguro de editor visual
        $contenido_email = wp_kses_post($_POST['contenido_email']);
        update_post_meta($post_id, '_contenido_email', $contenido_email);
    }
}
add_action('save_post', 'cc_guardar_metabox_email_contenido_empresa');


// META BOX - CURSOS-SALUD

// Registrar el metabox para cursos-salud
function registrar_metabox_cursos_salud() {
    add_meta_box(
        'metabox_cursos_salud',
        'Configuración del Curso',
        'renderizar_metabox_cursos_salud',
        'cursos-salud',
        'side',
        'high'
    );
}
add_action('add_meta_boxes', 'registrar_metabox_cursos_salud');

// Renderizar el metabox de configuración del curso
function renderizar_metabox_cursos_salud($post) {
    // Opciones predefinidas
    $opciones = [
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

    // Valores actuales o valores por defecto
    $categoria = get_post_meta($post->ID, '_categoria_certificado', true) ?: 'basico';

    // Si es nuevo, usar valores por defecto de la categoría seleccionada
    $sel = $opciones[$categoria];

    $tipo = get_post_meta($post->ID, '_tipo_certificado', true) ?: $sel['tipo_certificado'];
    $horas = get_post_meta($post->ID, '_tiempo_certificado', true) ?: $sel['tiempo_certificado'];
    $unidad_horas = get_post_meta($post->ID, '_descripcion_tiempo_certificado', true) ?: $sel['unidad_tiempo_certificado'];
    $duracion = get_post_meta($post->ID, '_duracion_certificado_tiempo', true) ?: $sel['duracion_certificado_tiempo'];
    $unidad_duracion = get_post_meta($post->ID, '_descripcion_duracion_certificado', true) ?: $sel['unidad_duracion_certificado'];

    $intensidad_horaria = $horas . ' ' . $unidad_horas;
    $vigencia_certificado = $duracion . ' ' . $unidad_duracion;

    wp_nonce_field('guardar_metadatos_cursos_salud_nonce', 'metabox_nonce');
    ?>
    <div class="wrap">
        <label for="certificado_categoria_predefinida">Selecciona una Categoría:</label>
        <select name="certificado_categoria_predefinida" id="certificado_categoria_predefinida" class="widefat">
            <?php foreach ($opciones as $key => $opcion): ?>
                <option value="<?php echo esc_attr($key); ?>" <?php selected($key, $categoria); ?>>
                    <?php echo esc_html($opcion['tipo_certificado']); ?>
                </option>
            <?php endforeach; ?>
        </select>

        <h4>Detalles del Certificado</h4>

        <label for="certificado_tipo">Tipo de Categoría:</label>
        <input type="text" id="certificado_tipo" class="widefat" value="<?php echo esc_attr($tipo); ?>" readonly>

        <label for="certificado_intensidad_horaria">Intensidad Horaria:</label>
        <input type="text" id="certificado_intensidad_horaria" class="widefat" value="<?php echo esc_attr($intensidad_horaria); ?>" readonly>

        <label for="certificado_vigencia">Vigencia del Certificado:</label>
        <input type="text" id="certificado_vigencia" class="widefat" value="<?php echo esc_attr($vigencia_certificado); ?>" readonly>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function () {
            const selectCategoria = document.getElementById('certificado_categoria_predefinida');
            const predefinedValues = <?php echo json_encode($opciones); ?>;

            selectCategoria.addEventListener('change', function () {
                const selected = this.value;
                if (predefinedValues[selected]) {
                    const data = predefinedValues[selected];
                    document.getElementById('certificado_tipo').value = data.tipo_certificado || '';
                    document.getElementById('certificado_intensidad_horaria').value = (data.tiempo_certificado || '') + ' ' + (data.unidad_tiempo_certificado || '');
                    document.getElementById('certificado_vigencia').value = (data.duracion_certificado_tiempo || '') + ' ' + (data.unidad_duracion_certificado || '');
                }
            });
        });
    </script>
    <?php
}


// Guardar los metadatos del curso
function guardar_metadatos_cursos_salud($post_id) {
    // Seguridad y permisos
    if (!isset($_POST['metabox_nonce']) || !wp_verify_nonce($_POST['metabox_nonce'], 'guardar_metadatos_cursos_salud_nonce')) return;
    if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) return;
    if (!current_user_can('edit_post', $post_id)) return;

    $opciones = [
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

    if (isset($_POST['certificado_categoria_predefinida']) && isset($opciones[$_POST['certificado_categoria_predefinida']])) {
        $cat = sanitize_text_field($_POST['certificado_categoria_predefinida']);
        update_post_meta($post_id, '_categoria_certificado', $cat);

        $sel = $opciones[$cat];
        update_post_meta($post_id, '_tipo_certificado', $sel['tipo_certificado']);
        update_post_meta($post_id, '_tiempo_certificado', $sel['tiempo_certificado']);
        update_post_meta($post_id, '_descripcion_tiempo_certificado', $sel['unidad_tiempo_certificado']);
        update_post_meta($post_id, '_duracion_certificado_tiempo', $sel['duracion_certificado_tiempo']);
        update_post_meta($post_id, '_descripcion_duracion_certificado', $sel['unidad_duracion_certificado']);

        $intensidad = $sel['tiempo_certificado'] . ' ' . $sel['unidad_tiempo_certificado'];
        $vigencia = $sel['duracion_certificado_tiempo'] . ' ' . $sel['unidad_duracion_certificado'];
        update_post_meta($post_id, 'horas', $intensidad);
        update_post_meta($post_id, 'fecha_expiracion_certificado', $vigencia);
    }
}
add_action('save_post', 'guardar_metadatos_cursos_salud');


//META BOX - CERTIFICADOS
// Registrar el metabox para campos personalizados en certificados
function cc_agregar_campos_personalizados_certificados() {
    add_meta_box(
        'cc_certificado_campos_personalizados',
        'Campos Personalizados',
        'cc_renderizar_metabox_campos_personalizados',
        'certificado',
        'normal',
        'default'
    );
}
add_action('add_meta_boxes', 'cc_agregar_campos_personalizados_certificados');

// Renderizar el metabox de campos personalizados
function cc_renderizar_metabox_campos_personalizados($post) {
    // Nonce para seguridad
    wp_nonce_field('cc_certificado_campos_nonce_action', 'cc_certificado_campos_nonce');

    // Obtener los valores actuales
    $nombre  = get_post_meta($post->ID, 'nombre_certificado', true);
    $cedula  = get_post_meta($post->ID, 'cedula_certificado', true);
    $curso   = get_post_meta($post->ID, 'curso_certificado', true);
    $email   = get_post_meta($post->ID, 'email_certificado', true);
    $pdf_url = get_post_meta($post->ID, 'pdf_file', true);
    $horas   = get_post_meta($post->ID, 'horas', true);

    $fecha_expedicion      = get_post_meta($post->ID, 'fecha_expedicion', true);
    $vigencia_certificado  = get_post_meta($post->ID, 'fecha_expiracion_certificado', true);

    // Calcular fecha actual y fecha de vencimiento
    $fecha_actual = date('Y-m-d');
    // Extrae el número de años (por ejemplo, '1 año', '2 años' -> 1, 2)
    $solo_numeros = intval(preg_replace('/\D/', '', $vigencia_certificado));
    $fecha_vencimiento = '';
    if ($fecha_expedicion && $solo_numeros) {
        $fecha_vencimiento = date('Y-m-d', strtotime("+{$solo_numeros} years", strtotime($fecha_expedicion)));
    }
    $estatus_vencimiento = ($fecha_vencimiento && strtotime($fecha_vencimiento) < strtotime($fecha_actual)) ? 'EXPIRADO' : 'Vigente';

    ?>
    <p><label for="nombre_certificado">Nombre:</label><br>
    <input type="text" id="nombre_certificado" value="<?php echo esc_attr($nombre); ?>" readonly></p>

    <p><label for="cedula_certificado">Cédula:</label><br>
    <input type="text" id="cedula_certificado" value="<?php echo esc_attr($cedula); ?>" readonly></p>

    <p><label for="curso_certificado">Curso:</label><br>
    <input type="text" id="curso_certificado" value="<?php echo esc_attr($curso); ?>" readonly></p>

    <p><label for="email_certificado">Email:</label><br>
    <input type="email" id="email_certificado" value="<?php echo esc_attr($email); ?>" readonly></p>

    <p><label for="horas">Intensidad horaria:</label><br>
    <input type="text" id="horas" value="<?php echo esc_attr($horas); ?>" readonly></p>

    <p><label for="fecha_expedicion">Fecha Expedición:</label><br>
    <input type="text" id="fecha_expedicion" value="<?php echo esc_attr($fecha_expedicion); ?>" readonly></p>

    <p><label for="fecha_expiracion_certificado">Vigencia del Certificado:</label><br>
    <input type="text" id="fecha_expiracion_certificado" value="<?php echo esc_attr($vigencia_certificado); ?>" readonly></p>

    <p><label>Fecha de Vencimiento:</label><br>
    <input type="text" value="<?php echo esc_attr($fecha_vencimiento); ?>" readonly></p>

    <p><label>Estado:</label><br>
    <input type="text" value="<?php echo esc_attr($estatus_vencimiento); ?>" readonly></p>

    <p><label for="pdf_file">Certificado PDF:</label><br>
    <?php if (!empty($pdf_url)) : ?>
        <a href="<?php echo esc_url($pdf_url); ?>" target="_blank" class="button">Descargar Certificado PDF</a>
    <?php else : ?>
        <span>No hay un PDF asociado.</span>
    <?php endif; ?>
    </p>
    <?php
}
