<?php
if (!class_exists(Wp_Es_Feeder)) {
    class Wp_Es_Feeder
    {
        protected $loader;
        protected $plugin_name;
        protected $version;

        public function __construct() {
            $this->plugin_name = 'wp-es-feeder';
            $this->version = '1.0.0';
            $this->load_dependencies();
            $this->define_admin_hooks();
        }

        private function load_dependencies() {
            require_once plugin_dir_path( dirname( __FILE__ ) ) . 'includes/class-wp-es-feeder-loader.php';
            require_once plugin_dir_path( dirname( __FILE__ ) ) . 'admin/class-wp-es-feeder-admin.php';
            $this->loader = new Wp_Es_Feeder_Loader();
        }

        private function define_admin_hooks() {
            $plugin_admin = new Wp_Es_Feeder_Admin( $this->get_plugin_name(), $this->get_version() );
            $this->loader->add_action( 'admin_enqueue_scripts', $plugin_admin, 'enqueue_styles' );
            $this->loader->add_action( 'admin_enqueue_scripts', $plugin_admin, 'enqueue_scripts' );
            // add menu item
            $this->loader->add_action( 'admin_menu', $plugin_admin, 'add_plugin_admin_menu' );
            // add Settings link to the plugin
            $plugin_basename = plugin_basename( plugin_dir_path( __DIR__ ) . $this->plugin_name . '.php' );
            $this->loader->add_filter( 'plugin_action_links_' . $plugin_basename, $plugin_admin, 'add_action_links' );
            // save/update our plugin options
            $this->loader->add_action('admin_init', $plugin_admin, 'options_update');
        }

        public function run() {
            $this->loader->run();
        }

        public function get_plugin_name() {
            return $this->plugin_name;
        }

        public function get_loader() {
            return $this->loader;
        }

        public function get_version() {
            return $this->version;
        }
    }
}
