<?php
// Carga condicional de la lógica de integración
add_action('plugins_loaded', function() {
    if (
        class_exists('WooCommerce') &&
        get_option('cc_certificados_woo_enabled', 0)
    ) {
        add_filter('woocommerce_checkout_fields', 'cc_certificados_add_checkout_fields');
        add_action('woocommerce_checkout_update_order_meta', 'cc_certificados_save_checkout_fields');
        add_action('woocommerce_order_status_processing', 'cc_certificados_woocommerce_generate_certificado');
        add_filter('woocommerce_payment_complete_order_status', 'cc_certificados_wc_auto_complete', 10, 2);
    }
});

//Añadir campos necesarios al checkout de WooCommerce
function cc_certificados_add_checkout_fields($fields) {
    $fields['billing']['certificado_tipo_documento'] = [
        'type'    => 'select',
        'label'   => 'Tipo de documento para certificado',
        'required'=> true,
        'options' => [
            '' => 'Selecciona...',
            'cc' => 'Cédula de Ciudadanía',
            'ti' => 'Tarjeta de Identidad',
            'ppt'=> 'PPT',
        ]
    ];
    $fields['billing']['certificado_num_documento'] = [
        'type'    => 'text',
        'label'   => 'Número de documento para certificado',
        'required'=> true,
    ];
    return $fields;
}

// Marcar la orden como completada automáticamente después del pago
function cc_certificados_wc_auto_complete($status, $order_id) {
    return 'completed';
}

// Generar y enviar el certificado al completar la orden
function cc_certificados_woocommerce_generate_certificado($order_id) {
    $order = wc_get_order($order_id);
    if (!$order) return;

    // Obtén los datos necesarios
    $nombre   = $order->get_billing_first_name() . ' ' . $order->get_billing_last_name();
    $email    = $order->get_billing_email();
    $tipo_doc = get_post_meta($order_id, 'certificado_tipo_documento', true);
    $num_doc  = get_post_meta($order_id, 'certificado_num_documento', true);
    $cursos   = []; // Opcional: puedes mapear los productos a cursos aquí

    foreach ($order->get_items() as $item) {
        $cursos[] = $item->get_product_id(); // O el ID del CPT de tu curso si lo tienes relacionado
    }

    // Arma el array $datos como en tus formularios manuales
    $datos = [
        'nombre'       => $nombre,
        'email'        => $email,
        'tipo_documento' => $tipo_doc,
        'documento'    => $num_doc,
        'cursos'       => $cursos,
        // ... otros datos necesarios ...
    ];

    // Opcional: selecciona la empresa/certificado de alguna forma lógica
    $empresa_id = get_option('cc_certificados_empresa_default'); // o busca por ID fijo o custom logic
    $empresa_titulo = get_the_title($empresa_id);
    $empresa_imagen = get_the_post_thumbnail_url($empresa_id, 'full');
    $contenido_email = get_post_meta($empresa_id, '_contenido_email', true);

    $empresa_info = [
        'empresa_titulo' => $empresa_titulo,
        'empresa_imagen' => $empresa_imagen,
        'contenido_email'=> $contenido_email,
    ];

    // Generación y envío (puedes reutilizar tus funciones)
    $pdf_links = [];
    foreach ($cursos as $curso_id) {
        $curso_nombre = get_the_title($curso_id);
        $curso_tipo = get_post_meta($curso_id, '_tipo_certificado', true);
        $intensidad_horaria = get_post_meta($curso_id, 'horas', true);
        $vigencia_certificado = get_post_meta($curso_id, 'fecha_expiracion_certificado', true);

        $datos_curso = [
            'curso_nombre' => $curso_nombre,
            'curso_tipo' => $curso_tipo,
            'intensidad_horaria' => $intensidad_horaria,
            'vigencia_certificado' => $vigencia_certificado,
        ];

        $html = cc_generar_html_certificado($datos_curso, $datos, $empresa_info, $empresa_imagen);
        $timestamp = date('Ymd_His');
        $pdf_filename = "woo_certificado_{$num_doc}_{$curso_id}_{$timestamp}.pdf";
        $upload_dir = wp_upload_dir();
        $certificados_dir = $upload_dir['basedir'] . '/certificados_woocommerce/';
        $pdf_url = cc_guardar_pdf_certificado($html, $certificados_dir, $pdf_filename);
        $pdf_links[] = $pdf_url;
    }

    // Envía el email con los enlaces (usa tu función actual)
    $asunto = "Certificados de compra - $nombre";
    cc_enviar_certificados($email, $contenido_email, $pdf_links, $asunto);
}


