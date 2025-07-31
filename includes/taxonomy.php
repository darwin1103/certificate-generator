<?php

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

    register_taxonomy('estado', array('cursos-salud'), $args);
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

    register_taxonomy('categoria-curso', array('cursos-salud'), $args);
}

add_action('init', 'cc_registrar_taxonomia_categoria');