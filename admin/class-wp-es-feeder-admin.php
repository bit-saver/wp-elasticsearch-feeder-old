<?php

class wp_es_feeder_Admin {

  private $plugin_name;
  private $version;

  public function __construct( $plugin_name, $version ) {
    $this->plugin_name = $plugin_name;
    $this->version = $version;
  }

  public function enqueue_styles() {
    wp_enqueue_style( $this->plugin_name, plugin_dir_url( __FILE__ ) . 'css/wp-es-feeder-admin.css',
      array(), $this->version, 'all' );
  }

  public function enqueue_scripts() {
    wp_enqueue_script( $this->plugin_name, plugin_dir_url( __FILE__ ) . 'js/wp-es-feeder-admin.js',
      array( 'jquery' ), false, false );
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
    include_once( 'partials/wp-es-feeder-index-to-cdp-display.php' );
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
}
