<?php

/**
 * The admin-specific functionality of the plugin.
 *
 * @link       http://github.com/MaxOrelus
 * @since      1.0.0
 *
 * @package    Wp_Es_Feeder
 * @subpackage Wp_Es_Feeder/admin
 */

/**
 * The admin-specific functionality of the plugin.
 *
 * Defines the plugin name, version, and two examples hooks for how to
 * enqueue the admin-specific stylesheet and JavaScript.
 *
 * @package    Wp_Es_Feeder
 * @subpackage Wp_Es_Feeder/admin
 * @author     Max Orelus <me@maxorelus.com>
 */
class Wp_Es_Feeder_Admin
{

    /**
     * The ID of this plugin.
     *
     * @since    1.0.0
     * @access   private
     * @var      string    $plugin_name    The ID of this plugin.
     */
    private $plugin_name;

    /**
     * The version of this plugin.
     *
     * @since    1.0.0
     * @access   private
     * @var      string    $version    The current version of this plugin.
     */
    private $version;

    /**
     * Initialize the class and set its properties.
     *
     * @since    1.0.0
     * @param      string    $plugin_name       The name of this plugin.
     * @param      string    $version    The version of this plugin.
     */
    public function __construct($plugin_name, $version)
    {

        $this->plugin_name = $plugin_name;
        $this->version = $version;
    }

    /**
     * Register the stylesheets for the admin area.
     *
     * @since    1.0.0
     */
    public function enqueue_styles()
    {

        /**
         * This function is provided for demonstration purposes only.
         *
         * An instance of this class should be passed to the run() function
         * defined in Wp_Es_Feeder_Loader as all of the hooks are defined
         * in that particular class.
         *
         * The Wp_Es_Feeder_Loader will then create the relationship
         * between the defined hooks and the functions defined in this
         * class.
         */

        wp_enqueue_style( $this->plugin_name, plugin_dir_url( __FILE__ ) . 'css/wp-es-feeder-admin.css', array(), $this->version, 'all' );
    }

    /**
     * Register the JavaScript for the admin area.
     *
     * @since    1.0.0
     */
    public function enqueue_scripts()
    {

        /**
         * This function is provided for demonstration purposes only.
         *
         * An instance of this class should be passed to the run() function
         * defined in Wp_Es_Feeder_Loader as all of the hooks are defined
         * in that particular class.
         *
         * The Wp_Es_Feeder_Loader will then create the relationship
         * between the defined hooks and the functions defined in this
         * class.
         */
        wp_enqueue_script( $this->plugin_name, plugin_dir_url( __FILE__ ) . 'js/wp-es-feeder-admin.js', array( 'jquery' ), $this->version, false );


    }

    // Register the administration menu
    public function add_plugin_admin_menu()
    {
            /*
			* Add a settings page for this plugin to the Settings menu.
			* NOTE:  Alternative menu locations are available via WordPress administration menu functions.
			* Administration Menus: http://codex.wordpress.org/Administration_Menus
			*/
        add_options_page( 'WP Elasticsearch Feeder Settings', 'WP ES Feeder', 'manage_options', $this->plugin_name, array($this, 'display_plugin_setup_page'));
    }

    // Add settings action link to the plugins page.$_COOKIE
    public function add_action_links($links)
    {
        /*
				*  Documentation : https://codex.wordpress.org/Plugin_API/Filter_Reference/plugin_action_links_(plugin_file_name)
				*/
        $mylinks = array('<a href="' . admin_url( 'options-general.php?page=myplugin' ) . '">Settings</a>',);
        return array_merge( $links, $mylinks );
    }

		// Render the settings page for this plugin.
    public function display_plugin_setup_page()
    {
        include_once( 'partials/wp-es-feeder-admin-display.php' );
    }

    public function validate($input)
    {
        $valid = array();

        // validate
        $valid['es_url'] = sanitize_text_field($input['es_url']);
        $valid['es_index'] = sanitize_text_field($input['es_index']);
        $valid['es_auth_required'] = (isset($input['es_auth_required']) && !empty($input['es_auth_required'])) ? 1: 0;
        $valid['es_username'] = sanitize_text_field($input['es_username']);
        $valid['es_password'] = sanitize_text_field($input['es_password']);

        $post_types = get_post_types(array( 'public' => true ));
        $types = new stdClass();
        foreach($post_types as $key => $value) {
             $types -> $value= (isset($input['es_post_type_'.$value]) && !empty($input['es_post_type_'.$value])) ? 1: 0;
        }
        $valid['es_post_types'] = $types;

        return $valid;
    }

    public function options_update() {
        register_setting($this->plugin_name, $this->plugin_name, array($this, 'validate'));
    }
}
