<?php

class wp_es_feeder_Admin {

  private $plugin_name;
  private $version;

  public function __construct( $plugin_name, $version ) {
    $this->plugin_name = $plugin_name;
    $this->version = $version;
  }

  public function enqueue_styles($hook) {
    global $post, $feeder;
    wp_enqueue_style( $this->plugin_name, plugin_dir_url( __FILE__ ) . 'css/wp-es-feeder-admin.css',
      array(), $this->version, 'all' );
    if ( $hook == 'post.php' && in_array($post->post_type, $feeder->get_allowed_post_types()) ) {
      wp_enqueue_style('chosen', plugin_dir_url(__FILE__) . 'css/chosen.css');
    }

  }

  public function enqueue_scripts($hook) {
    global $wpdb, $post, $feeder;

    wp_register_script( $this->plugin_name, plugin_dir_url( __FILE__ ) . 'js/wp-es-feeder-admin.js',
      array( 'jquery' ), false, false );

    $query = "SELECT COUNT(*) as total, SUM(meta_value) as incomplete FROM $wpdb->postmeta WHERE meta_key = '_cdp_sync_queue'";
    $row = $wpdb->get_row($query);
    $sync = array(
      'complete' => 0,
      'total' => $row->total,
      'paused' => false,
      'post' => null
    );
    if ($row->total) {
      $sync['complete'] = $row->total - $row->incomplete;
      $sync['paused'] = true;
    }
    wp_localize_script($this->plugin_name, 'es_feeder_sync', $sync);
    wp_enqueue_script($this->plugin_name);


    if ( $hook == 'post.php' && in_array($post->post_type, $feeder->get_allowed_post_types()) ) {
      wp_enqueue_script('chosen', plugin_dir_url(__FILE__) . 'js/chosen.jquery.min.js', array('jquery'), null);
      $handle = $this->plugin_name . '-sync-status';
      wp_register_script( $handle, plugin_dir_url( __FILE__ ) . 'js/wp-es-feeder-admin-post.js',
        array( 'jquery' ), false, false );
      wp_localize_script($handle, 'es_feeder_sync_status_post_id', $post->ID);
      wp_enqueue_script($handle);
    }
  }

  // Register the administration menu
  public function add_plugin_admin_menu() {
    add_options_page( 'WP Elasticsearch Feeder Settings', 'WP ES Feeder', 'manage_options',
      $this->plugin_name, array( $this, 'display_plugin_setup_page' ) );
  }

  // Add settings action link to the plugins page.$_COOKIE
  public function add_action_links( $links ) {
    $mylinks = array(
       '<a href="' . admin_url( 'options-general.php?page=myplugin' ) . '">Settings</a>'
    );
    return array_merge( $links, $mylinks );
  }

  function add_admin_index_to_cdp() {

    $options = get_option($this->plugin_name);
    $es_post_types = $options['es_post_types']?$options['es_post_types']:null;
    $screens = array();
    if ( $es_post_types ) {
      foreach($es_post_types as $key=>$value){
        if ($value) {
          array_push($screens, $key);
        }
      }
    }
    foreach( $screens as $screen ) {
      add_meta_box(
          'index-to-cdp-mb',           // Unique ID
          'Index Post to CDP',  // Box title
          array($this, 'index_to_cdp_display'),  // Content callback, must be of type callable
          $screen,                   // Post type
          'side',
          'high'
      );
    }
  }

  function index_to_cdp_display($post) {
    global $feeder;
    include_once( 'partials/wp-es-feeder-index-to-cdp-display.php' );
  }

  function add_admin_cdp_taxonomy() {

    $options = get_option($this->plugin_name);
    $es_post_types = $options['es_post_types']?$options['es_post_types']:null;
    $screens = array();
    if ( $es_post_types ) {
      foreach($es_post_types as $key=>$value){
        if ($value) {
          array_push($screens, $key);
        }
      }
    }
    foreach( $screens as $screen ) {
      add_meta_box(
          'cdp-taxonomy',           // Unique ID
          'Categories',  // Box title
          array($this, 'cdp_taxonomy_display'),  // Content callback, must be of type callable
          $screen,                   // Post type
          'side',
          'high'
      );
    }
  }

  function cdp_taxonomy_display($post) {
    global $feeder;
    $taxonomy = $feeder->get_taxonomy();
    include_once( 'partials/wp-es-feeder-cdp-taxonomy-display.php' );
  }

  // Render the settings page for this plugin.
  public function display_plugin_setup_page() {
    include_once( 'partials/wp-es-feeder-admin-display.php' );
  }

  public function validate( $input ) {

    $valid = array(
      'es_wpdomain' => sanitize_text_field( $input[ 'es_wpdomain' ] ),
      'es_url' => sanitize_text_field( $input[ 'es_url' ] )
    );

    $types = array();
    $post_types = get_post_types( array('public' => true));

    if ( isset( $input['es_post_types'] ) ) { 

      $types = $input['es_post_types'];

    } else {

      foreach ( $post_types as $key => $value ) {
        if (!isset( $input[ 'es_post_type_' . $value ] ) || !$input[ 'es_post_type_' . $value ] ) continue;
        $types[ $value ] = ( isset( $input[ 'es_post_type_' . $value ] ) ) ? 1 : 0;
      }

    }

    $valid[ 'es_post_types' ] = $types;
    
    return $valid;
  }

  public function options_update() {
    register_setting( $this->plugin_name, $this->plugin_name, array(
       $this,
      'validate'
    ) );
  }

  /**
   * Checks for sync errors and displays an admin notice if there are errors and the notice
   * wasn't dismissed in the last 24 hours.
   */
  public function sync_errors_notice() {
    global $feeder;
    if (!current_user_can('manage_options') || isset($_COOKIE['cdp-feeder-notice-dismissed'])) return;
    $errors = $feeder->check_sync_errors();
    if ($errors['errors']) { ?>
      <div class="notice notice-error feeder-notice is-dismissible">
        <p>WP ES Feeder has enountered <?=$errors['errors']?> error(s). Click <a href="<?=admin_url('options-general.php?page=wp-es-feeder')?>">here</a> to attempt a fix.</p>
      </div>
      <script type="text/javascript">
        jQuery(function($) {
          $(document).on('click', '.feeder-notice .notice-dismiss', function() {
            var today = new Date();
            var expire = new Date();
            expire.setTime(today.getTime() + 3600000*24); // 1 day
            document.cookie = 'cdp-feeder-notice-dismissed=1;expires=' + expire.toGMTString();
          });
        });
      </script>
      <?php
    }
  }

  public function columns_head( $defaults ) {
    global $feeder;
    if (in_array(get_post_type(), $feeder->get_allowed_post_types()))
        $defaults[ 'sync_status' ] = 'Sync Status';
    return $defaults;
  }

  public function columns_content( $column_name, $post_ID ) {
    global $feeder;
    if ( $column_name == 'sync_status' ) {
      $status = get_post_meta( $post_ID,'_cdp_sync_status', true );
      $feeder->sync_status_indicator($status, false);
    }
  }

  public function sortable_columns( $columns ) {
    $columns['sync_status'] = '_cdp_sync_status';
    return $columns;
  }
}
