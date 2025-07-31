<?php

// Crear la página de consulta al activar el plugin
function crear_pagina_consulta_certificados() {
    if (!get_page_by_path('consulta-certificados')) {
        $pagina_datos = array(
            'post_title'     => 'Consulta de Certificados',
            'post_name'      => 'consulta-certificados',
            'post_content'   => '[consulta_certificados_shortcode]', // Shortcode para el contenido
            'post_status'    => 'publish',
            'post_type'      => 'page',
        );
        wp_insert_post($pagina_datos);
    }
}
register_activation_hook(__FILE__, 'crear_pagina_consulta_certificados');

// Shortcode para consultar certificados por cédula
function consulta_certificados_shortcode() {
    ob_start();
    ?>
    <form style="margin-bottom: 20px;" method="get" action="">
        <label for="cedula">Ingrese su número de cédula:</label>
        <input type="text" id="cedula" name="cedula" required>
        <button style="margin-top: 20px;" type="submit">Consultar Certificados</button>
    </form>
    <?php

    if (!empty($_GET['cedula'])) {
        $cedula = sanitize_text_field($_GET['cedula']);

        $args = array(
            'post_type'      => 'certificado',
            'meta_query'     => array(
                array(
                    'key'   => 'cedula_certificado',
                    'value' => $cedula,
                ),
            ),
            'posts_per_page' => -1,
        );
        $query = new WP_Query($args);

        if ($query->have_posts()) {
            echo '<h2 style="margin-bottom:20px;">Resultados de la consulta:</h2><ul>';
            while ($query->have_posts()) {
                $query->the_post();

                // Obtener valores para estado
                $fecha_expedicion = get_post_meta(get_the_ID(), 'fecha_expedicion', true);
                $vigencia         = get_post_meta(get_the_ID(), 'fecha_expiracion_certificado', true);
                $anios            = intval(preg_replace('/\D/', '', $vigencia));
                $fecha_actual     = date('Y-m-d');
                $fecha_vencimiento = ($fecha_expedicion && $anios) ? date('Y-m-d', strtotime("+{$anios} years", strtotime($fecha_expedicion))) : '';

                $estado = ($fecha_vencimiento && strtotime($fecha_vencimiento) > strtotime($fecha_actual))
                    ? '<span style="color: green;">VIGENTE</span>'
                    : '<span style="color: red;">EXPIRADO</span>';

                echo '<li><a href="' . esc_url(get_permalink()) . '">' . esc_html(get_the_title()) . '</a> - ' . $estado . '</li>';
            }
            echo '</ul>';
            wp_reset_postdata();
        } else {
            echo '<p>No se encontraron certificados para la cédula ingresada.</p>';
        }
    }
    return ob_get_clean();
}
add_shortcode('consulta_certificados_shortcode', 'consulta_certificados_shortcode');

// Shortcode para mostrar el estado del certificado en una página individual
function estado_certificados_shortcode() {
    ob_start();
    $fecha_expedicion = get_post_meta(get_the_ID(), 'fecha_expedicion', true);
    $vigencia         = get_post_meta(get_the_ID(), 'fecha_expiracion_certificado', true);
    $anios            = intval(preg_replace('/\D/', '', $vigencia));
    $fecha_actual     = date('Y-m-d');
    $fecha_vencimiento = ($fecha_expedicion && $anios) ? date('Y-m-d', strtotime("+{$anios} years", strtotime($fecha_expedicion))) : '';

    $estado = ($fecha_vencimiento && strtotime($fecha_vencimiento) > strtotime($fecha_actual))
        ? '<span style="color: green;">VIGENTE</span>'
        : '<span style="color: red;">EXPIRADO</span>';

    echo '<p>' . $estado . '</p>';
    return ob_get_clean();
}
add_shortcode('estado_certificados_shortcode', 'estado_certificados_shortcode');
