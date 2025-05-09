<?php

require_once plugin_dir_path(__FILE__) . 'class-shortcodes-da.php';
require_once plugin_dir_path(__FILE__) . 'class-my-plugin-assets.php';
require_once plugin_dir_path(__FILE__) . 'class-my-functions.php';

class My_Plugin_Loader {
    public function register_shortcodes() {
        new My_Plugin_Shortcodes();
    }

    public function register_assets() {
        new My_Plugin_Assets();
    }

    public function register_functions() {
        new My_functions();
    }

    public function iniciar_plugin() {
        $this->register_shortcodes();
        $this->register_assets();
        $this->register_functions();
    }
}
