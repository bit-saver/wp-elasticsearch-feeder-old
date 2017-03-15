<?php

class Wp_Es_Feeder_Admin {

    private $plugin_name;
    private $version;

    public function __construct($plugin_name, $version) {
        $this->plugin_name = $plugin_name;
        $this->version = $version;
    }

    public function enqueue_styles() {
        wp_enqueue_style( $this->plugin_name, plugin_dir_url( __FILE__ ) . 'css/wp-es-feeder-admin.css', array(), $this->version, 'all' );
    }

    public function enqueue_scripts() {
        wp_enqueue_script( $this->plugin_name, plugin_dir_url( __FILE__ ) . 'js/wp-es-feeder-admin.js', array( 'jquery' ), false, false );
    }

    // Register the administration menu
    public function add_plugin_admin_menu() {
        add_options_page( 'WP Elasticsearch Feeder Settings', 'WP ES Feeder', 'manage_options', $this->plugin_name, array($this, 'display_plugin_setup_page'));
    }

    // Add settings action link to the plugins page.$_COOKIE
    public function add_action_links($links) {
        $mylinks = array('<a href="' . admin_url( 'options-general.php?page=myplugin' ) . '">Settings</a>',);
        return array_merge( $links, $mylinks );
    }

    // Render the settings page for this plugin.
    public function display_plugin_setup_page() {
        include_once( 'partials/wp-es-feeder-admin-display.php' );
    }

    public function validate($input) {
        $valid = array(
        'es_url' => sanitize_text_field($input['es_url']),
        'es_index' => sanitize_text_field($input['es_index']),
        'es_access_key' => sanitize_text_field($input['es_access_key']),
        'es_secret_key' => sanitize_text_field($input['es_secret_key']),
        'es_wp_domain' => sanitize_text_field($input['es_wp_domain'])
        );

        $post_types = get_post_types(array('show_in_rest' => true));

        $types = array();
        foreach($post_types as $key => $value) {
            $types[$value] = (isset($input['es_post_type_'.$value]) && !empty($input['es_post_type_'.$value])) ? 1: 0;
        }

        $valid['es_post_types'] = $types;

        return $valid;
    }

    public function options_update() {
        register_setting($this->plugin_name, $this->plugin_name, array($this, 'validate'));
    }
}
