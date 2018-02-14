<?php
if ( !class_exists( 'wp_es_feeder' ) ) {
  class wp_es_feeder {
    protected $loader;
    protected $plugin_name;
    protected $version;
    public $proxy;
    public $error;

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
      require_once plugin_dir_path( dirname( __FILE__ ) ) . 'vendor/autoload.php';
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
      add_action( 'wp_ajax_es_sync_status', array($this, 'get_sync_status') );
      add_action( 'wp_ajax_es_initiate_sync', array($this, 'es_initiate_sync') );
      add_action( 'wp_ajax_es_process_next', array($this, 'es_process_next') );
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

    /**
     * Triggered via AJAX, retreives a post's sync status for display.
     */
    public function get_sync_status() {
      $post_id = $_POST['post_id'];
      $status = get_post_meta($post_id, '_cdp_sync_status', true);
      echo ES_FEEDER_SYNC::display($status);
      exit;
    }

    /**
     * Triggered via AJAX, clears out old sync data and initiates a new sync process.
     */
    public function es_initiate_sync() {
      check_admin_referer();
      global $wpdb;
      $wpdb->delete($wpdb->postmeta, array('meta_value' => '_cdp_sync_queue'));
      $opts = get_option( $this->plugin_name );
      $post_types = $opts[ 'es_post_types' ];
      $formats = implode(',', array_fill(0, count($post_types), '%s'));
      $query = "SELECT p.ID FROM $wpdb->posts p 
                  LEFT JOIN (SELECT post_id, meta_value FROM $wpdb->postmeta WHERE meta_key = '_iip_index_post_to_cdp_option') m
                    ON p.ID = m.post_id 
                  WHERE p.post_type IN ($formats) AND p.post_status = 'publish' AND (m.meta_value IS NULL OR m.meta_value != 'no')";
      $query = $wpdb->prepare($query, array_keys($post_types));
      $post_ids = $wpdb->get_col($query);
      if (!count($post_ids)) {
        echo json_encode(array('error' => true, 'message' => 'No posts found.', 'query' => $query));
        exit;
      }
      foreach ($post_ids as $post_id)
        update_post_meta($post_id, '_cdp_sync_queue', 1);
      $this->es_process_next();
    }

    /**
     * Grabs the next post in the queue and sends it to the API.
     * Updates the postmeta indicating that this post has been synced.
     * Returns a JSON object containing the API response for the current post
     * as well as stats on the sync queue.
     */
    public function es_process_next() {
      check_admin_referer();
      global $wpdb;
      $query = "SELECT post_id FROM $wpdb->postmeta WHERE meta_key = '_cdp_sync_queue' AND meta_value = 1";
      $post_id = $wpdb->get_var($query);
      if (!$post_id) {
        $query = "SELECT COUNT(*) as total, SUM(meta_value) as incomplete FROM $wpdb->postmeta WHERE meta_key = '_cdp_sync_queue'";
        $row = $wpdb->get_row($query);
        $wpdb->delete($wpdb->postmeta, array('meta_key' => '_cdp_sync_queue'));
        echo json_encode(array('done' => 1, 'total' => $row->total, 'complete' => ($row->total - $row->incomplete)));
        exit;
      }
      update_post_meta($post_id, '_cdp_sync_queue', "0");
      $post = get_post($post_id);
      $resp = $this->addOrUpdate($post, false);
      $query = "SELECT COUNT(*) as total, SUM(meta_value) as incomplete FROM $wpdb->postmeta WHERE meta_key = '_cdp_sync_queue'";
      $row = $wpdb->get_row($query);
      echo json_encode(array('done' => 0, 'response' => $resp, 'total' => $row->total, 'complete' => $row->total - $row->incomplete));
      exit;
    }

    public function save_post( $id, $post ) {
      $settings  = get_option( $this->plugin_name );
      $post_type = $post->post_type;

      if (array_key_exists('index_post_to_cdp_option', $_POST)) {
        update_post_meta(
          $id,
          '_iip_index_post_to_cdp_option',
          $_POST[ 'index_post_to_cdp_option' ]
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

    /**
     * Determines if a post can be synced or not. Syncable means that it is not in the process
     * of being synced. If it is not syncable, update the sync status to inform the user that
     * they needs to wait until the sync is complete and then resync.
     *
     * @param $post
     * @return bool
     */
    public function is_syncable( $post ) {
      // check sync status
      $sync_status = get_post_meta($post->ID, '_cdp_sync_status', true);
      if (!ES_FEEDER_SYNC::sync_allowed($sync_status)) {
        update_post_meta($post->ID, '_cdp_sync_status', ES_FEEDER_SYNC::SYNC_WHILE_SYNCING);
        return false;
      }
      return true;
    }

    public function addOrUpdate( $post, $print = true ) {
      if ( !$this->is_syncable( $post ) ) {
        $response = ['error' => 1, 'message' => 'Could not sync while sync in progress.'];
        if (!$print)
          wp_send_json($response);
        return $response;
      }

      // plural form of post type
      $post_type_name = ES_API_HELPER::get_post_type_label( $post->post_type, 'name' );
      // settings and configuration for plugin
      $config = get_option( $this->plugin_name );

      // api endpoint for wp-json
      $wp_api_url = '/'.ES_API_HELPER::NAME_SPACE.'/'.rawurlencode($post_type_name).'/'.$post->ID;
      $request = new WP_REST_Request('GET', $wp_api_url);
      $api_response = rest_do_request( $request );
      $api_response = $api_response->data;

      if ( !$api_response || isset( $api_response[ 'code' ] ) ) {
        error_log( print_r( $this->error . 'addOrUpdate() calling wp rest failed', true ) );
        $api_response['error'] = true;
        $api_response['url'] = $wp_api_url;
        if ( $print ) {
          wp_send_json( $api_response );
        }
        return $api_response;
      }

      // create callback for this post
      global $wpdb;
      do {
        $uid = uniqid();
        $query = "SELECT post_id FROM $wpdb->postmeta WHERE meta_key = '_cdp_sync_uid' AND meta_value = '$uid'";
      } while ($wpdb->get_var($query));
      $callback = get_rest_url(null, ES_API_HELPER::NAME_SPACE . '/callback/' . $uid);
      update_post_meta($post->ID, '_cdp_sync_uid', $uid);
      update_post_meta($post->ID, '_cdp_sync_status', ES_FEEDER_SYNC::SYNCING);

      $options = array(
        'url' => $config[ 'es_url' ] . '/' . $post->post_type,
        'method' => 'POST',
        'body' => $api_response,
        'print' => $print
      );

      $response = $this->es_request( $options, $callback );
      if ( !$response ) {
        error_log( print_r( $this->error . 'addOrUpdate()[add] request failed', true ) );
      }

      return $response;
    }

    public function delete( $post ) {
      if ( !$this->is_syncable( $post ) ) return;
      $opt = get_option( $this->plugin_name );

      $uuid = $this->get_uuid($post);
      $delete_url = $opt[ 'es_url' ] . '/' . $post->post_type;

      $options = array(
         'url' => $delete_url,
         'method' => 'DELETE',
         'body' => array(
           'post_id' => $post->ID,
           'type' => $post->post_type,
           'site' => $this->get_site()
         ),
         'print' => false
      );

      $response = $this->es_request( $options );
      if (!isset($response['error']) || !$response['error']) {
        update_post_meta( $post->ID, '_cdp_sync_status', ES_FEEDER_SYNC::NOT_SYNCED );
        delete_post_meta( $post->ID, '_cdp_sync_uid' );
      }
    }

    public function es_request($request, $callback = null) {
      $is_internal = false;
      if (!$request) {
        $request = $_POST['data'];
      } else {
        $is_internal = true;
      }

      $headers = [];
      if ($callback) $headers['callback'] = $callback;

      $client = new GuzzleHttp\Client();

      // TODO: Investigate alternative methods to accomplish this URL check. The API gets mad since we don't have a GET route for this yet.
      try {
        $client->get($request['url'], ['http_errors' => false, 'timeout' => '5']);
      } catch (GuzzleHttp\Exception\ConnectException $e) {
        $error = json_encode($e->getHandlerContext());
      }

      if ( isset($error) ){
        $results = $error;
      } else {
        // if a body is provided
        if ( isset($request['body']) ) {
          // unwrap the post data from ajax call
          if (!$is_internal) {
            $body = urldecode(base64_decode($request['body']));
          } else {
            $body = json_encode($request['body']);
            $headers['Content-Type'] = 'application/json';
          }

          $body = $this->is_domain_mapped($body);

          $response = $client->request($request['method'], $request['url'], ['body' => $body, 'http_errors' => false, 'headers' => $headers]);
        } else {
          $response = $client->request($request['method'], $request['url'], ['http_errors' => false, 'headers' => $headers]);
        }

        $body = $response->getBody();
        $results = $body->getContents();
      }

      if ($is_internal || (isset($request['print']) && !$request['print'])) {
        return json_decode($results);
      } else {
        wp_send_json(json_decode($results));
        return null;
      }
    }

    private function is_domain_mapped( $body ) {
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

      return $body;
    }

    /**
     * Construct UUID which is site domain delimited by dashes and not periods, underscore, and post ID.
     *
     * @param $post
     * @return string
     */
    public function get_uuid($post) {
      $opt = get_option( $this->plugin_name );
      $url = $opt['es_wpdomain'];
      $args = parse_url($url);
      $host = $url;
      if (array_key_exists('host', $args))
        $host = $args['host'];
      else
        $host = str_ireplace('https://', '', str_ireplace('http://', '', $host));

      $host = str_replace('.', '-', $host);
      return "{$host}_{$post->ID}";
    }

    public function get_site() {
      $opt = get_option( ES_API_HELPER::PLUGIN_NAME );
      $url = $opt[ 'es_wpdomain' ];
      $args = parse_url( $url );
      $host = $url;
      if ( array_key_exists( 'host', $args ) )
        $host = $args[ 'host' ];
      else
        $host = str_ireplace( 'https://', '', str_ireplace( 'http://', '', $host ) );
      return $host;
    }
  }
}
