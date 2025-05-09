<?php

class My_Plugin_Assets {

    public function __construct() {
        add_action('wp_enqueue_scripts', [$this, 'da_utilities_enqueue_styles']);
    }

    public function da_utilities_enqueue_styles() {
        wp_enqueue_style('my-plugin-styles', plugin_dir_url(__FILE__) . '../assets/css/styles.css');
    }
}
