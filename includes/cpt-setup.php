<?php

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

    //Registrar el tipo de publicación "Campus certificados"
    $args_empresas_dev = array(
                'public' => true,
                'show_ui' => true,
                'show_in_menu' => true,
                'label' => 'Campus Certificados',
                'supports' => array('title', 'editor', 'thumbnail'),
                'menu_icon' => 'dashicons-businessperson',
                'rewrite' => array('slug' => 'empresa_campus'),
                'menu_position' => 20,
            );
            register_post_type('empresa_campus', $args_empresas_dev);
}
add_action('init', 'cc_registrar_post_types'); // Registrar los post types al hook init
