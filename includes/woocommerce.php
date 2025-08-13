<?php
// Seguridad
if ( ! defined( 'ABSPATH' ) ) { exit; }

/**
 * Carga de hooks SOLO si WooCommerce está activo y la integración está habilitada.
 */
add_action('plugins_loaded', function () {
    if ( ! class_exists('WooCommerce') ) {
        error_log('CC_CERT: WooCommerce no está activo. Integración deshabilitada.');
        return;
    }

    $enabled = get_option('cc_certificados_woo_enabled', '0') === '1';
    if ( ! $enabled ) {
        error_log('CC_CERT: Integración WooCommerce desactivada por option.');
        return;
    }

    // Campos checkout
    add_filter('woocommerce_checkout_fields', 'cc_woo_cert_checkout_fields');
    add_action('woocommerce_checkout_process', 'cc_woo_cert_checkout_validate');

    // ✅ Guardar metadatos vía CRUD (compatible con HPOS)
    add_action('woocommerce_checkout_create_order', 'cc_woo_cert_checkout_save_crud', 10, 2);

    // Mostrar en admin (orden)
    add_action('woocommerce_admin_order_data_after_billing_address', 'cc_woo_cert_admin_show_checkout_meta');

    // Disparadores de generación (idempotentes)
    add_action('woocommerce_payment_complete', 'cc_woo_cert_maybe_generate_for_order', 20, 1);
    add_action('woocommerce_order_status_processing', 'cc_woo_cert_maybe_generate_for_order', 20, 1); // pagos manuales
});

/**
 * Campos adicionales en checkout: tipo y número de documento
 */
function cc_woo_cert_checkout_fields( $fields ) {
    $fields['billing']['cc_doc_type'] = [
        'type'        => 'select',
        'label'       => __('Tipo de documento', 'certificados-plugin'),
        'required'    => true,
        'priority'    => 120,
        'options'     => [
            ''    => __('Selecciona…', 'certificados-plugin'),
            'CC'  => 'Cédula de ciudadanía',
            'CE'  => 'Cédula de extranjería',
            'TI'  => 'Tarjeta de identidad',
            'PP'  => 'Pasaporte',
        ],
    ];

    $fields['billing']['cc_doc_number'] = [
        'type'        => 'text',
        'label'       => __('Número de documento', 'certificados-plugin'),
        'required'    => true,
        'priority'    => 121,
        'maxlength'   => 40,
    ];

    return $fields;
}

/**
 * Validación de los campos de documento en checkout.
 */
function cc_woo_cert_checkout_validate() {
    if ( empty($_POST['cc_doc_type']) ) {
        wc_add_notice( __('Selecciona el tipo de documento.', 'certificados-plugin'), 'error' );
    }
    if ( empty($_POST['cc_doc_number']) ) {
        wc_add_notice( __('Ingresa el número de documento.', 'certificados-plugin'), 'error' );
    }
}

/**
 * ✅ Guardar metadatos del pedido usando CRUD (HPOS-safe).
 */
function cc_woo_cert_checkout_save_crud( $order, $data ) {
    $type   = isset($_POST['cc_doc_type'])   ? sanitize_text_field($_POST['cc_doc_type'])   : '';
    $number = isset($_POST['cc_doc_number']) ? sanitize_text_field($_POST['cc_doc_number']) : '';

    // Guarda en meta del pedido (no uses update_post_meta con HPOS)
    if ( $type !== '' )   { $order->update_meta_data( '_cc_doc_type',   $type ); }
    if ( $number !== '' ) { $order->update_meta_data( '_cc_doc_number', $number ); }

    $oid = $order->get_id() ? $order->get_id() : 0;
    error_log('CC_CERT: Checkout doc meta (CRUD) ' . wp_json_encode(['order_id' => $oid, 'type' => $type, 'number' => $number]));
}

/**
 * Mostrar en admin (edición de pedido).
 */
function cc_woo_cert_admin_show_checkout_meta( $order ) {
    $type   = $order->get_meta('_cc_doc_type');
    $number = $order->get_meta('_cc_doc_number');
    if ( $type || $number ) {
        echo '<p><strong>'.esc_html__('Documento', 'certificados-plugin').':</strong> '
            . esc_html($type.' '.$number) . '</p>';
    }
}

/**
 * Punto de entrada: generar certificados si procede. Idempotente.
 */
function cc_woo_cert_maybe_generate_for_order( $order_id ) {
    try {
        $order = wc_get_order( $order_id );
        if ( ! $order ) {
            error_log('CC_CERT: Orden no encontrada ' . wp_json_encode(['order_id' => $order_id]));
            return;
        }

        // Evitar duplicados
        if ( $order->get_meta('_cc_certs_generated') ) {
            error_log('CC_CERT: Certificados ya generados para la orden ' . wp_json_encode(['order_id' => $order_id]));
            return;
        }

        // Empresa/tipo configurado
        $empresa_id = absint( get_option('cc_certificados_woo_tipo_cert_empresa_id', 0) );
        if ( ! $empresa_id ) {
            error_log('CC_CERT: Option empresa para Woo no definida');
            return;
        }

        $result = cc_woo_cert_generate_for_order( $order, $empresa_id );

        if ( ! empty($result['ok']) ) {
            // Marca de idempotencia
            $order->update_meta_data('_cc_certs_generated', time());
            // Completar orden si aún no está completada
            if ( $order->get_status() !== 'completed' ) {
                $order->update_status('completed', 'Certificados generados y enviados.');
            }
            $order->save();
            error_log('CC_CERT: Certificados generados y orden completada ' . wp_json_encode(['order_id' => $order_id]));
        } else {
            error_log('CC_CERT: Fallo al generar/enviar certificados ' . wp_json_encode(['order_id' => $order_id, 'error' => $result['error'] ?? 'unknown']));
        }

    } catch ( \Throwable $e ) {
        error_log('CC_CERT: Excepción en maybe_generate_for_order ' . wp_json_encode(['order_id' => $order_id, 'msg' => $e->getMessage()]));
    }
}

/**
 * Lógica principal de generación para una orden (Woo).
 * Aporta los campos que la plantilla espera: tipo_documento, documento, fecha_expedicion, curso_tipo, vigencia_certificado, intensidad_horaria.
 */
function cc_woo_cert_generate_for_order( WC_Order $order, $empresa_id ) {
    $pdf_links = [];

    // Persona (desde meta del pedido)
    $doc_type_raw = (string) $order->get_meta('_cc_doc_type');   // CC / CE / TI / PP
    $doc_number   = (string) $order->get_meta('_cc_doc_number'); // puede venir con puntos/espacios

    // Limpia el número a solo dígitos
    $doc_number_clean = preg_replace('/\D+/', '', $doc_number);

    // Etiquetas consistentes
    $doc_map = [
        'CC' => 'Cédula de ciudadanía',
        'CE' => 'Cédula de extranjería',
        'TI' => 'Tarjeta de identidad',
        'PP' => 'Pasaporte',
    ];
    $tipo_documento   = isset($doc_map[$doc_type_raw]) ? $doc_map[$doc_type_raw] : $doc_type_raw;

    // Formato requerido: dd/mm/yyyy
    $fecha_expedicion = date_i18n('d/m/Y', current_time('timestamp'));

    $persona = [
        'nombre'           => trim($order->get_billing_first_name() . ' ' . $order->get_billing_last_name()),
        'documento'        => $doc_number_clean,
        'tipo_documento'   => $tipo_documento,
        'fecha_expedicion' => $fecha_expedicion,
        'email'            => $order->get_billing_email(),
    ];

    // Empresa
    $empresa_titulo  = get_the_title($empresa_id);
    $empresa_asunto  = cc_meta_first($empresa_id, ['_email_subject', 'email_subject'], 'Certificados - '.$empresa_titulo);
    $contenido_email = cc_meta_first($empresa_id, ['_contenido_email','contenido_email'], 'Adjuntamos tus certificados.');
    $empresa_imagen  = get_the_post_thumbnail_url($empresa_id, 'full');

    if ( ! $empresa_imagen ) {
        $plugin_root_url = trailingslashit( dirname( plugin_dir_url( __FILE__ ) ) );
        $empresa_imagen  = $plugin_root_url . 'assets/certificados_campus/default_background.jpg';
    }

    $empresa = [
        'empresa_titulo' => $empresa_titulo,
        'empresa_asunto' => $empresa_asunto,
        'contenido_email'=> $contenido_email,
        'empresa_imagen' => $empresa_imagen,
    ];

    // Base de escritura
    $uploads   = wp_upload_dir();
    $base_pdfs = trailingslashit( $uploads['basedir'] ) . 'certificados-plugin/pdfs/';
    if ( ! file_exists( $base_pdfs ) ) {
        wp_mkdir_p( $base_pdfs );
    }

    // Recorrer items del pedido
    foreach ( $order->get_items( 'line_item' ) as $item_id => $item ) {
        $product = $item->get_product();
        if ( ! $product ) { continue; }

        $product_id = $product->get_id();

        // Permitir filtrar si un producto genera certificado
        $should = apply_filters('cc_cert_should_generate_for_product', true, $product_id, $order);
        if ( ! $should ) { continue; }

        // === CURSO: toma 'curso_tipo' del post meta _tipo_certificado del PRODUCTO ===
        $curso_tipo_meta = get_post_meta( $product_id, '_tipo_certificado', true );
        $curso_tipo = $curso_tipo_meta !== '' ? sanitize_text_field( $curso_tipo_meta ) : $empresa_titulo;

        // Resto de metadatos del curso
        $int_num              = get_post_meta( $product_id, '_intensidad_horaria', true );
        $intensidad_horaria   = $int_num ? ( $int_num . ' horas' ) : '';
        $vigencia_certificado = get_post_meta( $product_id, 'fecha_expiracion_certificado', true );

        $curso = [
            'curso_id'             => $product_id,
            'curso_nombre'         => $product->get_name(),
            'curso_tipo'           => $curso_tipo,            // <- ahora viene de _tipo_certificado
            'intensidad_horaria'   => $intensidad_horaria,
            'vigencia_certificado' => $vigencia_certificado,
        ];

        try {
            // DEBUG: verifica exactamente lo que se envía a la plantilla
            error_log('CC_CERT: Datos para plantilla ' . wp_json_encode([
                'persona' => $persona,
                'curso'   => $curso,
            ]));

            // 1) HTML del PDF
            if ( function_exists('cc_generar_html_certificado') ) {
                $html = cc_generar_html_certificado( $curso, $persona, $empresa, $empresa['empresa_imagen'] );
            } else {
                error_log('CC_CERT: cc_generar_html_certificado no existe');
                continue;
            }

            // 2) Guardar (GCS o local)
            $slug_prod = sanitize_title( $curso['curso_nombre'] ?: ('producto-'.$product_id) );
            $slug_user = sanitize_title( $persona['nombre'] );
            $filename  = sanitize_file_name( 'certificado-' . $slug_user . '-' . $slug_prod . '-' . time() . '.pdf' );

            if ( function_exists('cc_guardar_pdf_certificado') ) {
                $pdf_url = cc_guardar_pdf_certificado( $html, $base_pdfs, $filename );
            } else {
                error_log('CC_CERT: cc_guardar_pdf_certificado no existe');
                continue;
            }

            if ( ! $pdf_url ) {
                error_log('CC_CERT: cc_guardar_pdf_certificado devolvió vacío ' . wp_json_encode(['product_id' => $product_id]));
                continue;
            }

            // 3) Crear post tipo "certificado"
            if ( function_exists('cc_crear_post_certificado') ) {
                cc_crear_post_certificado( $persona, $curso, $pdf_url, $empresa );
            } else {
                error_log('CC_CERT: cc_crear_post_certificado no existe');
            }

            $pdf_links[] = $pdf_url;

        } catch ( \Throwable $e ) {
            error_log('CC_CERT: Excepción generando certificado para item ' . wp_json_encode([
                'order_id'   => $order->get_id(),
                'product_id' => $product_id,
                'msg'        => $e->getMessage()
            ]));
        }
    }

    // Enviar email
    if ( $pdf_links && function_exists('cc_enviar_certificados') ) {
        $ok = cc_enviar_certificados( $persona['email'], $empresa['contenido_email'], $pdf_links, $empresa['empresa_asunto'] );
        return [ 'ok' => (bool) $ok, 'pdf_links' => $pdf_links ];
    }

    return [ 'ok' => false, 'error' => 'Sin PDFs o cc_enviar_certificados() no disponible' ];
}

/**
 * Utilidad: obtener el primer meta que exista de una lista de claves.
 */
function cc_meta_first( $post_id, array $keys, $default = '' ) {
    foreach ( $keys as $k ) {
        $v = get_post_meta( $post_id, $k, true );
        if ( $v !== '' && $v !== null ) { return $v; }
    }
    return $default;
}
