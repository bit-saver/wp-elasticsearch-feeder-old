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
            $this->proxy = 'http://localhost:3000/api/elasticsearch';
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

            add_action('save_post', array($this, 'save_post'), 10, 2);
            // add_action('before_delete_post', array($this, 'before_delete_post'), 10, 1);
            // add_action('trash_post', array($this, 'before_delete_post'), 10, 2);
            // add_action('transition_post_status', array($this, 'transition_post'), 10, 3);
        }

        public function post_type_picker($str) {
          $temp = array(
            'post' => 'posts',
            'page' => 'pages',
            'attachment' => 'media'
          );

          if ($temp[$str]) {
            return $temp[$str];
          }

          return $str;
        }

        public function save_post($id, $post)
        {

          if ($post == null) {
            return;
          }

          if ($post->post_status == 'publish') {
            $this -> addOrUpdate($post);
          } else {
            // delete
          }
        }

        public function addOrUpdate($post) {
          $type = $this -> post_type_picker($post -> post_type);
          $opt = get_option($this->plugin_name);

          $api = get_bloginfo('wpurl').'/wp-json/elasticsearch/v1/'.$type.'/'.$post -> ID;
          $data = file_get_contents($api);
          if (!$data) return;

          $check_url = $opt['es_url'].'/'.$opt['es_index'].'/'.$post -> post_type
            .'/_search?q=id:'.$post -> ID;

          $es_data = json_decode(file_get_contents($check_url));
          if (!$es_data) return;

          $isFound = $es_data -> hits -> total;

          if ((int) $isFound == (int) 0) {
            $options = array(
              'http' => array(
                'method' => 'POST',
                'header' => 'content-type: application/json',
                'content' => json_encode(array(
                  'url' => $opt['es_url'].'/'.$opt['es_index'].'/'.$post -> post_type,
                  'auth' => array(
                    'accessKeyId' => '',
                    'secrectAccessKey' => 'bob'
                  ),
                  'options' => array(
                    'method' => 'POST',
                    'content-type' => 'application/json',
                    'body' => json_decode($data)
                  )
                )
              ))
            );

            $context = stream_context_create($options);
            return file_get_contents($this -> proxy, false, $context);
          }

          $es_id = $es_data -> hits -> hits[0] -> _id;
          $put_url = $opt['es_url'].'/'.$opt['es_index'].'/'.$post->post_type.'/'.$es_id;

          $options = array(
            'http' => array(
              'method' => 'POST',
              'header' => 'content-type: application/json',
              'content' => json_encode(array(
                'url' => $put_url,
                'auth' => array(
                  'accessKeyId' => '',
                  'secrectAccessKey' => 'bob'
                ),
                'options' => array(
                  'method' => 'PUT',
                  'content-type' => 'application/json',
                  'body' => json_decode($data)
                )
              )
            ))
          );

          $context = stream_context_create($options);
          return file_get_contents($this -> proxy, false, $context);

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
