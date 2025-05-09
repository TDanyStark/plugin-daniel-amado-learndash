<?php
/*
Plugin Name: Funciones y shortcodes para LearnDash y woocommerce
Description: Plugin con shortcodes interesantes para learndash, para poder usarlos con cualquier builder.
Version: 2.0
Author: Daniel Amado
*/


// Evitar el acceso directo
if (!defined('ABSPATH')) {
  exit;
}

// Incluir archivos necesarios
require_once plugin_dir_path(__FILE__) . 'includes/class-my-plugin-loader.php';

// Inicializar el plugin
function my_plugin_initialize() {
    $loader = new My_Plugin_Loader();
    $loader->iniciar_plugin();
}
add_action('plugins_loaded', 'my_plugin_initialize');
