<?php
if ( !class_exists( wp_es_feeder ) ) {
  class wp_es_feeder {
    protected $loader;
    protected $plugin_name;
    protected $version;

    public function __construct() {
      $this->plugin_name = 'wp-es-feeder';
      $this->version = '1.0.0';
      $this->proxy = 'http://localhost:3000/api/elasticsearch'; // proxy
      $this->error = '[WP_ES_FEEDER] [:LOG] ';
      $this->load_api();
      $this->load_dependencies();
      $this->define_admin_hooks();
    }

    function load_api() {
      require plugin_dir_path( dirname( __FILE__ ) ) . 'includes/class-es-api-helpers.php';
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

      // add settings link to plugin
      $plugin_basename = plugin_basename( plugin_dir_path( __DIR__ ) . $this->plugin_name . '.php' );
      $this->loader->add_filter( 'plugin_action_links_' . $plugin_basename, $plugin_admin, 'add_action_links' );

      // save/update our plugin options
      $this->loader->add_action( 'admin_init', $plugin_admin, 'options_update' );

      // elasticsearch indexing hook actions
      add_action( 'save_post', array( $this, 'save_post' ), 10, 2 );
      add_action( 'delete_post', array( &$this, 'delete_post' ), 10, 1 );
      add_action( 'trash_post', array( &$this, 'delete_post' ) );
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

      if ( $post == null || !$settings[ 'es_post_types' ][ $post_type ] ) {
        return;
      }

      if ( $post->post_status === 'publish' ) {
        $this->addOrUpdate( $post );
      } else {
        if ( $post->post_status === 'trash' ) {
          $this->delete( $post );
        }
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
      $post_type_name = ES_API_HELPER::get_post_type_label( $post->post_type, 'name' );
      $opt = get_option( $this->plugin_name );

      $api = get_bloginfo( 'wpurl' ) . '/wp-json/elasticsearch/v1/' . $post_type_name . '/'
        . $post->ID;

      $data = @file_get_contents( $api );
      if ( !$data ) {
        error_log( print_r( $this->error . 'transition_post() file_get_contents failed.', true ) );
        return;
      }

      $record_exists_url = $opt[ 'es_url' ] . '/' . $opt[ 'es_index' ] . '/' . $post->post_type
        . '/_search?q=id:' . $post->ID;

      $es_data = @file_get_contents( $record_exists_url );
      if ( !$es_data ) {
        error_log( print_r( $this->error . 'addOrUpdate() file_get_contents failed', true ) );
        return;
      }

      $es_data = json_decode( $es_data );
      $isRecordFound = $es_data->hits->total;

      if ( (int) $isRecordFound == (int) 0 ) {

        $options = array(
           'http' => array(
             'method' => 'POST',
            'header' => 'content-type: application/json',
            'content' => json_encode( array(
               'url' => $opt[ 'es_url' ] . '/' . $opt[ 'es_index' ] . '/' . $post->post_type,
              'auth' => array(
                 'accessKeyId' => '',
                'secrectAccessKey' => 'bob'
              ),
              'options' => array(
                 'method' => 'POST',
                'content-type' => 'application/json',
                'body' => json_decode( $data )
              )
            ) )
          )
        );

        $context  = stream_context_create( $options );
        $response = @file_get_contents( $this->get_proxy_server(), false, $context );
        if ( !$response ) {
          error_log( print_r( $this->error . 'addOrUpdate()[add] file_get_contents failed', true ) );
        }
      }

      $es_id   = $es_data->hits->hits[ 0 ]->_id;
      $put_url = $opt[ 'es_url' ] . '/' . $opt[ 'es_index' ] . '/' . $post->post_type . '/' . $es_id;

      $options = array(
         'http' => array(
           'method' => 'POST',
          'header' => 'content-type: application/json',
          'content' => json_encode( array(
             'url' => $put_url,
            'auth' => array(
               'accessKeyId' => '',
              'secrectAccessKey' => 'bob'
            ),
            'options' => array(
               'method' => 'PUT',
              'content-type' => 'application/json',
              'body' => json_decode( $data )
            )
          ) )
        )
      );

      $context = stream_context_create( $options );
      $response = @file_get_contents( $this->get_proxy_server(), false, $context );
      if ( !$response ) {
        error_log( print_r( $this->error . 'addOrUpdate()[update] file_get_contents failed', true ) );
      }
    }

    public function delete( $post ) {
      $opt = get_option( $this->plugin_name );
      $record_exists_url = $opt[ 'es_url' ] . '/' . $opt[ 'es_index' ] . '/' . $post->post_type
        . '/_search?q=id:' . $post->ID;

      $es_data = @file_get_contents( $record_exists_url );
      if ( !$es_data ) {
        error_log( print_r( $this->error . '8=====D delete() file_get_contents failed', true ) );
        return;
      }

      $es_data = json_decode( $es_data );
      $isFound = $es_data->hits->total;
      error_log( print_r( $this->error . '8=====D Delete() Record found ' . $isFound, true ) );

      if ( (int) $isFound == (int) 1 ) {
        $es_id = $es_data->hits->hits[ 0 ]->_id;
        $delete_url = $opt[ 'es_url' ] . '/' . $opt[ 'es_index' ] . '/' . $post->post_type . '/' . $es_id;

        $options = array(
           'http' => array(
             'method' => 'POST',
            'header' => 'content-type: application/json',
            'content' => json_encode( array(
               'url' => $delete_url,
              'auth' => array(
                 'accessKeyId' => '',
                'secrectAccessKey' => 'bob'
              ),
              'options' => array(
                 'method' => 'DELETE',
                'content-type' => 'application/json'
              )
            ) )
          )
        );

        $context = stream_context_create( $options );
        return @file_get_contents( $this->proxy, false, $context );
      }
    }
  }
}
