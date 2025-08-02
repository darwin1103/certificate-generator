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

// Función para encolar estilos y scripts de Bootstrap y Select2
function cc_enqueue_styles_and_scripts() {
    wp_enqueue_style('bootstrap-css', plugin_dir_url(__FILE__) . 'assets/css/bootstrap.min.css');

    // Encolar Bootstrap JS (asegúrate de la ruta correcta del archivo)
    wp_enqueue_script('bootstrap-js', plugin_dir_url(__FILE__) . 'assets/js/bootstrap.min.js', array('jquery'), null, true);

    // Encolar Bootstrap CSS
    wp_enqueue_style('bootstrap-css', plugin_dir_url(__FILE__) . 'assets/css/bootstrap.min.css');

    // Encolar Select2 CSS
    wp_enqueue_style('select2-css', plugin_dir_url(__FILE__) . 'assets/css/select2.min.css');

    // Encolar Select2 JS
    wp_enqueue_script('select2-js', plugin_dir_url(__FILE__) . 'assets/js/select2.min.js', array('jquery'), null, true);

    // Encolar archivo JS personalizado para inicializar Select2 en el campo
    wp_enqueue_script('select2-js', plugin_dir_url(__FILE__) . 'assets/js/select2.js', array('jquery', 'select2-js'), null, true);
}
add_action('admin_enqueue_scripts', 'cc_enqueue_styles_and_scripts');

// Importa el autoloader de Composer
if (file_exists(__DIR__ . '/vendor/autoload.php')) {
    require_once __DIR__ . '/vendor/autoload.php';
}

// Cargar automáticamente todos los archivos PHP del directorio 'includes'
foreach (glob(__DIR__ . '/includes/*.php') as $file) {
    require_once $file;
}

