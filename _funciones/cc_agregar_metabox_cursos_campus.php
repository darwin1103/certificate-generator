<?php
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
?>