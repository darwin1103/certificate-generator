<?php

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
        'cc_formulario_salud'   // Función que se ejecutará
    );

    // Subpágina para "Certificados de Campus"
    add_submenu_page(
        'edit.php?post_type=certificado', // El menú principal
        'Certificados de Campus',         // Título de la subpágina
        'Generar certificados Campus',         // Título en el menú
        'manage_options',                 // Permisos requeridos
        'certificados-campus',            // Slug de la subpágina
        'cc_formulario_campus'  // Función que se ejecutará
    );
}
add_action('admin_menu', 'cc_agregar_subpaginas_certificados'); // Añadir las subpáginas al hook admin_menu