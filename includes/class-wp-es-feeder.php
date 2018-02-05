<?php
if ( !class_exists( 'wp_es_feeder' ) ) {
  class wp_es_feeder {
    protected $loader;
    protected $plugin_name;
    protected $version;

    public function __construct() {
      $this->plugin_name = 'wp-es-feeder';
      $this->version = '1.0.0';
      $this->proxy = get_option($this->plugin_name)['es_url']; // proxy
      $this->error = '[WP_ES_FEEDER] [:LOG] ';
      $this->load_api();
      $this->load_dependencies();
      $this->define_admin_hooks();
    }

    function load_api() {
      require plugin_dir_path( dirname( __FILE__ ) ) . 'includes/class-es-api-helpers.php';
      require plugin_dir_path( dirname( __FILE__ ) ) . 'includes/class-language-config.php';
      require plugin_dir_path( dirname( __FILE__ ) ) . 'includes/class-elasticsearch-wp-rest-api-controller.php';
    }

    private function load_dependencies() {
      require_once plugin_dir_path( dirname( __FILE__ ) ) . 'includes/class-wp-es-feeder-loader.php';
      require_once plugin_dir_path( dirname( __FILE__ ) ) . 'admin/class-wp-es-feeder-admin.php';
      $this->loader = new wp_es_feeder_Loader();
    }

    private function define_admin_hooks() {
      $plugin_admin = new wp_es_feeder_Admin( $this->get_plugin_name(), $this->get_version() );
      $this->loader->add_action( 'admin_enqueue_scripts', $plugin_admin, 'enqueue_styles' );
      $this->loader->add_action( 'admin_enqueue_scripts', $plugin_admin, 'enqueue_scripts' );

      // add menu item
      $this->loader->add_action( 'admin_menu', $plugin_admin, 'add_plugin_admin_menu' );

      // add "Do not index" box to posts and pages
      $this->loader->add_action( 'add_meta_boxes', $plugin_admin, 'add_admin_index_to_cdp' );

      // add settings link to plugin
      $plugin_basename = plugin_basename( plugin_dir_path( __DIR__ ) . $this->plugin_name . '.php' );
      $this->loader->add_filter( 'plugin_action_links_' . $plugin_basename, $plugin_admin, 'add_action_links' );

      // save/update our plugin options
      $this->loader->add_action( 'admin_init', $plugin_admin, 'options_update' );

      // elasticsearch indexing hook actions
      add_action( 'save_post', array( $this, 'save_post' ), 10, 2 );
      add_action( 'delete_post', array( &$this, 'delete_post' ), 10, 1 );
      add_action( 'trash_post', array( &$this, 'delete_post' ) );
      add_action( 'wp_ajax_es_request', array( $this, 'es_request') );
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

    public function get_proxy_server() {
      return $this->proxy;
    }

    public function save_post( $id, $post ) {
      $settings  = get_option( $this->plugin_name );
      $post_type = $post->post_type;

      if (array_key_exists('index_post_to_cdp_option', $_POST)) {
        update_post_meta(
            $id,
            '_iip_index_post_to_cdp_option',
            $_POST['index_post_to_cdp_option']
        );
      }

      // return early if missing parameters
      if ( $post == null || !$settings[ 'es_post_types' ][ $post_type ] ) {
        return;
      }

      // switch operation based on post status
      if ( $post->post_status === 'publish' ) {
      
        // check to see if post should be indexed or removed from index
        $shouldIndex = $_POST['index_post_to_cdp_option'];
        
        // default to indexing - post has to be specifically set to 'no'
        if( $shouldIndex === 'no' ) { 
          $this->delete( $post );
        } else {
          $this->addOrUpdate( $post );
        }
      } else {
        $this->delete( $post );
      }
    }

    public function delete_post( $id ) {
      if ( is_object( $id ) ) {
        $post = $id;
      } else {
        $post = get_post( $id );
      }

      $settings  = get_option( $this->plugin_name );
      $post_type = $post->post_type;

      if ( $post == null || !$settings[ 'es_post_types' ][ $post_type ] ) {
        return;
      }

      $this->delete( $post );
    }

    public function addOrUpdate( $post ) {
      // plural form of post type
      $post_type_name = ES_API_HELPER::get_post_type_label( $post->post_type, 'name' );
      // settings and configuration for plugin
      $config = get_option( $this->plugin_name );

      // api endpoint for wp-json
      $wp_api_url = '/elasticsearch/v1/'.rawurlencode($post_type_name).'/'.$post->ID;
      $request = new WP_REST_Request('GET', $wp_api_url);
      $api_response = rest_do_request( $request );
      $api_response = $api_response->data;

      if (!$api_response) {
        error_log( print_r( $this->error . 'addOrUpdate() calling wp rest failed', true ) );
        return;
      }

      // form elasticsearch search url to find existing record in elastic
      $exists_url = $this->proxy.'/'
        .$config[ 'es_index' ].'/'.$post->post_type
        .'/_search?q=id:' . $post->ID;

      $options = array(
        'url' => $exists_url,
        'method' => 'GET'
      );

      $es_response = $this->es_request( $options );

      if ( !$es_response ) {
        error_log( print_r( $this->error . 'addOrUpdate() elasticsearch threw error', true ) );
        return;
      }

      $existing_record_found = $es_response->hits->total;

      // create new record in elastic if record doesn't exist
      if ( (int)$existing_record_found == 0 ) {

        $options = array(
          'url' => $config[ 'es_url' ] . '/' . $post->post_type,
          'method' => 'POST',
          'body' => $api_response
        );

        $response = $this->es_request( $options );
        if ( !$response ) {
          error_log( print_r( $this->error . 'addOrUpdate()[add] request failed', true ) );
        }
        return; // end create
      }

      // update existing document
      $_id   = $es_response->hits->hits[ 0 ]->_id;
      $put_url = $config[ 'es_url' ] . '/' . $post->post_type . '/' . $_id;

      $options = array(
        'url' => $put_url,
        'method' => 'PUT',
        'body' => $api_response
      );

      $response = $this->es_request( $options );
      if ( !$response ) {
        error_log( print_r( $this->error . 'addOrUpdate()[update] file_get_contents failed', true ) );
      }
    }

    public function delete( $post ) {
      $opt = get_option( $this->plugin_name );
      $exists_url = $opt[ 'es_url' ] . '/' . $post->post_type
        . '/_search?q=id:' . $post->ID;

      $options = array(
        'url' => $exists_url,
        'method' => 'GET'
      );

      $es_response = $this->es_request( $options );
      if ( !$es_response ) {
        error_log( print_r( $this->error . 'delete() get request failed', true ) );
        return;
      }

      $existing_record_found = $es_response->hits->total;
      if ( (int)$existing_record_found == 1 ) {
        $_id = $es_response->hits->hits[ 0 ]->_id;
        $delete_url = $opt[ 'es_url' ] . '/' . $post->post_type . '/' . $_id;

        $options = array(
           'url' => $delete_url,
           'method' => 'DELETE'
        );

        $this->es_request( $options );
      }
    }

    public function es_request($request) {
      if (!$request) {
        $request = $_POST['data'];
      } else {
        $is_internal = true;
      }

      $curl = curl_init();
      curl_setopt($curl, CURLOPT_URL, $request['url']);
      curl_setopt($curl, CURLOPT_SSL_VERIFYHOST, 0);
      curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, 0);
      curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1);
      curl_setopt($curl, CURLOPT_TIMEOUT, 10);
      curl_setopt($curl, CURLOPT_CONNECTTIMEOUT, 10);

      // if a body is provided
      if ($request['body']) {
        // unwrap the post data from ajax call
        if (!$is_internal) {
          $body = urldecode(base64_decode($request['body']));
        } else {
          $body = json_encode($request['body']);
        }

        // check if domain is mapped
        $opt = get_option( $this->plugin_name );
        $protocol = is_ssl() ? 'https://' : 'http://';
        $opt_url = $opt['es_wpdomain'];
        $opt_url = str_replace($protocol, '', $opt_url);
        $site_url = site_url();
        $site_url = str_replace($protocol, '', $site_url);

        if ($opt_url !== $site_url) {
          $body = str_replace($site_url, $opt_url, $body);
        }

        // obtain string length
        $length = strlen($body);

        // curl options
        curl_setopt($curl, CURLOPT_CUSTOMREQUEST, $request['method']);
        curl_setopt($curl, CURLOPT_POSTFIELDS, $body);
        curl_setopt($curl, CURLOPT_HTTPHEADER, array(
          'Content-Type: application/json',
          'Content-Length: ' . $length
        ));
      } else {
        curl_setopt($curl, CURLOPT_CUSTOMREQUEST, $request['method']);
        curl_setopt($curl, CURLOPT_HTTPHEADER, array(
          'Content-Type: application/json'
        ));
      }

      $results = curl_exec($curl);
      curl_close($curl);

      if ($is_internal) {
        return json_decode($results);
      } else {
        return wp_send_json(json_decode($results));
      }
    }
  }
}
