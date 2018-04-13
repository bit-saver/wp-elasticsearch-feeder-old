<?php
// abort if not called via Wordpress
if ( !defined( 'ABSPATH' ) ) {
  exit;
}

if ( !class_exists( 'WP_ES_FEEDER_REST_Controller' ) ) {
  class WP_ES_FEEDER_REST_Controller extends WP_REST_Controller {
    public $resource;
    public $type;

    public function __construct( $post_type ) {
      $this->resource = ES_API_HELPER::get_post_type_label( $post_type, 'name' );
      $this->type = $post_type;
    }

    // _iip_index_post_to_cdp_option is meta data
    public function shouldIndex( $post ) {
      return ES_API_HELPER::get_index_to_cdp( $post->ID );
    }

    public function register_routes() {
      register_rest_route( ES_API_HELPER::NAME_SPACE, '/' . rawurlencode( $this->resource ), array(
        array(
          'methods' => WP_REST_Server::READABLE,
          'callback' => array(
            $this,
            'get_items'
          ),
          'args' => array(
            'per_page' => array(
              'validate_callback' => function ( $param, $request, $key ) {
                return is_numeric( $param );
              }
            ),
            'page' => array(
              'validate_callback' => function ( $param, $request, $key ) {
                return is_numeric( $param );
              }
            )
          ),
          'permission_callback' => array(
            $this,
            'get_items_permissions_check'
          )
        )
      ) );

      register_rest_route( ES_API_HELPER::NAME_SPACE, '/' . rawurlencode( $this->resource ) . '/(?P<id>[\d]+)', array(
        array(
          'methods' => WP_REST_Server::READABLE,
          'callback' => array(
            $this,
            'get_item'
          ),
          'args' => array(
            'id' => array(
              'validate_callback' => function ( $param, $request, $key ) {
                return is_numeric( $param );
              }
            )
          ),
          'permission_callback' => array(
            $this,
            'get_item_permissions_check'
          )
        )
      ) );
    }

    public function get_items( $request ) {
      $args[ 'post_type' ] = $this->type;
      $page = (int)$request->get_param( 'page' );
      $per_page = (int)$request->get_param( 'per_page' );

      if ( $per_page ) {
        $args[ 'posts_per_page' ] = $per_page;
      } else {
        $args[ 'posts_per_page' ] = 25;
      }

      if ( is_numeric( $page ) ) {
        if ( $page == 1 ) {
          $args[ 'offset' ] = 0;
        } elseif ( $page > 1 ) {
          $args[ 'offset' ] = ($page * $args[ 'posts_per_page' ]) - $args[ 'posts_per_page' ];
        }
      }

      $args[ 'meta_query' ] = array(
        'relation' => 'OR',
        array(
          'key' => '_iip_index_post_to_cdp_option',
          'compare' => 'NOT EXISTS'
        ),
        array(
          'key' => '_iip_index_post_to_cdp_option',
          'value' => 'no',
          'compare' => '!='
        ),
      );

      $posts = get_posts( $args );

      if ( empty( $posts ) ) {
        return rest_ensure_response( array() );
      }

      foreach ( $posts as $post ) {
        $response = $this->prepare_item_for_response( $post, $request );
        $data[] = $this->prepare_response_for_collection( $response );
      }

      return rest_ensure_response( $data );
    }

    public function get_item( $request ) {
      $id = (int)$request[ 'id' ];
      $response = array();

      $post = get_post( $id );

      if ( empty( $post ) ) {
        return rest_ensure_response( array() );
      }

      if ( $this->shouldIndex( $post ) ) {
        $response = $this->prepare_item_for_response( $post, $request );
        $data = $response->get_data();

        $categories = array_map( function ($cat) { return $cat['name']; }, ES_API_HELPER::get_categories( $post->ID ) );
        $tags = array_map( function ($tag) { return $tag['name']; }, ES_API_HELPER::get_tags( $post->ID ) );
        foreach ( $categories as $cat ) {
          $cat = strtolower( $cat );
          if ( !in_array( $cat, $tags ) ) $tags[] = $cat;
        }
        foreach ( $tags as $tag ) {
          $tag = strtolower( $tag );
          if ( !in_array( $tag, $tags ) ) $tags[] = $tag;
        }
        $data[ 'tags' ] = $tags;
        $categories = get_post_meta($id, '_iip_taxonomy_terms', true) ?: array();
        $cat_ids = array();
        foreach ($categories as $cat) {
          $args = explode('<', $cat);
          if (!in_array($args[0], $cat_ids))
            $cat_ids[] = $args[0];
        }
        $data['categories'] = $cat_ids;
        $response->set_data($data);
      }

      return $response;
    }

    public function prepare_response_for_collection( $response ) {
      if ( !($response instanceof WP_REST_Response) ) {
        return $response;
      }

      $data = (array)$response->get_data();
      $server = rest_get_server();

      if ( method_exists( $server, 'get_compact_response_links' ) ) {
        $links = call_user_func( array(
          $server,
          'get_compact_response_links'
        ), $response );
      } else {
        $links = call_user_func( array(
          $server,
          'get_response_links'
        ), $response );
      }

      if ( !empty( $links ) ) {
        $data[ '_links' ] = $links;
      }

      return $data;
    }

    public function prepare_item_for_response( $post, $request ) {
      return rest_ensure_response( $this->baseline( $post, $request ) );
    }

    public function baseline( $post, $request ) {
      $post_data = array();

      // if atachment return right away
      if ( $post->post_type == 'attachment' ) {
        $post_data = wp_prepare_attachment_for_js( $post->ID );
        $post_data[ 'site' ] = $this->get_site();
        return rest_ensure_response( $post_data );
      }

      // We are also renaming the fields to more understandable names.
      if ( isset( $post->ID ) ) {
        $post_data[ 'post_id' ] = (int)$post->ID;
      }

      $post_data[ 'type' ] = $this->type;

      $post_data[ 'site' ] = $this->get_site();

      $post_data[ 'owner' ] = get_bloginfo('name');

      if ( isset( $post->post_date ) ) {
        $post_data[ 'published' ] = get_the_date( 'c', $post->ID );
      }

      if ( isset( $post->post_modified ) ) {
        $post_data[ 'modified' ] = get_the_modified_date( 'c', $post->ID );
      }

      if ( isset( $post->post_author ) ) {
        $post_data[ 'author' ] = ES_API_HELPER::get_author( $post->post_author );
      }

      // pre-approved
      $opt = get_option( ES_API_HELPER::PLUGIN_NAME );
      $opt_url = $opt[ 'es_wpdomain' ];
      $post_data[ 'link' ] = str_replace( site_url(), $opt_url, get_permalink( $post->ID ) );

      if ( isset( $post->post_title ) ) {
        $post_data[ 'title' ] = $post->post_title;
      }

      if ( isset( $post->post_name ) ) {
        $post_data[ 'slug' ] = $post->post_name;
      }

      if ( isset( $post->post_content ) ) {
        $post_data[ 'content' ] = ES_API_HELPER::render_vs_shortcodes( $post );
      }

      if ( isset( $post->post_excerpt ) ) {
        $post_data[ 'excerpt' ] = $post->post_excerpt;
      }

      $post_data[ 'language' ] = ES_API_HELPER::get_language( $post->ID );
      $post_data[ 'translations' ] = ES_API_HELPER::get_related_translated_posts( $post->ID, $post->post_type );

      $custom_taxonomies = ES_API_HELPER::get_custom_taxonomies( $post->ID );
      if ( count( $custom_taxonomies ) ) {
        $post_data[ 'taxonomies' ] = $custom_taxonomies;
      }

      $feature_image_exists = has_post_thumbnail( $post->ID );
      if ( $feature_image_exists ) {
        $post_data[ 'featured_image' ] = ES_API_HELPER::get_featured_image( get_post_thumbnail_id( $post->ID ) );
      }

      if ( isset( $post->comment_count ) ) {
        $post_data[ 'comment_count' ] = (int)$post->comment_count;
      }

      return $post_data;
    }

    public function get_items_permissions_check( $request ) {
      return true;
    }

    public function get_item_permissions_check( $request ) {
      return true;
    }

    public function authorization_status_code() {
      $status = 401;
      if ( is_user_logged_in() ) {
        $status = 403;
      }
      return $status;
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

/**
 * Class WP_ES_FEEDER_Callback_Controller
 *
 * Handles the callback from the ES API when the sync of a post completes or fails.
 */
class WP_ES_FEEDER_Callback_Controller {

  public function register_routes() {
    register_rest_route( ES_API_HELPER::NAME_SPACE, '/callback/(?P<uid>[0-9a-zA-Z]+)', array(
      array(
        'methods' => WP_REST_Server::ALLMETHODS,
        'callback' => array(
          $this,
          'processResponse'
        ),
        'args' => array(
          'uid' => array(
            'validate_callback' => function ( $param, $request, $key ) {
              return true;
            }
          )
        ),
        'permission_callback' => array(
          $this,
          'get_items_permissions_check'
        )
      )
    ) );
  }

  /**
   * @param $request WP_REST_Request
   * @return array
   */
  public function processResponse( $request ) {
    global $wpdb, $feeder;
    $data = $request->get_json_params();
    if (!$data)
      $data = $request->get_body_params();

    $uid = $request->get_param('uid');
    $post_id = null;
    if (!$data['error'])
      $post_id = $data['doc']['post_id'];
    else
      $post_id = $data['request']['post_id'];

    $feeder->log("INCOMING CALLBACK FOR UID: $uid\r\n" . print_r( $data, 1 ) . "\r\n", 'callback.log');

    if ($post_id == $wpdb->get_var("SELECT post_id FROM $wpdb->postmeta WHERE meta_key = '_cdp_sync_uid' AND meta_value = '" . $wpdb->_real_escape($uid) . "'")) {
      $sync_status = get_post_meta($post_id, '_cdp_sync_status', true);
      if (!$data['error']) {
        if ($sync_status == ES_FEEDER_SYNC::SYNC_WHILE_SYNCING)
          update_post_meta($post_id,'_cdp_sync_status', ES_FEEDER_SYNC::RESYNC);
        else
          update_post_meta($post_id,'_cdp_sync_status', ES_FEEDER_SYNC::SYNCED);
      } else {
        update_post_meta($post_id,'_cdp_sync_status', ES_FEEDER_SYNC::ERROR);
      }
      $wpdb->delete($wpdb->postmeta, array('meta_key' => '_cdp_sync_uid', 'meta_value' => $uid));
    }

    return ['status' => 'ok'];

  }

  public function get_items_permissions_check( $request ) {
    return true;
  }

  public function get_item_permissions_check( $request ) {
    return true;
  }
}

/*
* Creates API endpoints for all public post-types. If you have a custom post-type, you must follow
* the class convention "WP_ES_FEEDER_EXT_{TYPE}_Controller" if you want to customize the output
* If no class convention is found, plugin will create default API routes for custom post types
*/
function register_post_types( $type ) {
  $base_types = array(
    'post' => true,
    'page' => true,
    'attachment' => true
  );

  $is_base_type = array_key_exists( $type, $base_types );
  if ( (int)$is_base_type ) {
    $controller = new WP_ES_FEEDER_REST_Controller( $type );
    $controller->register_routes();
    return;
  } else if ( !$is_base_type && !class_exists( 'WP_ES_FEEDER_EXT_' . strtoupper( $type ) . '_Controller' ) ) {
    $controller = new WP_ES_FEEDER_REST_Controller( $type );
    $controller->register_routes();
    return;
  }
}

function register_elasticsearch_rest_routes() {
  $post_types = get_post_types( array(
    'public' => true
  ) );

  if ( is_array( $post_types ) && count( $post_types ) > 0 ) {
    foreach ( $post_types as $type ) {
      register_post_types( $type );
    }
  }

  $controller = new WP_ES_FEEDER_Callback_Controller();
  $controller->register_routes();
}

add_action( 'rest_api_init', 'register_elasticsearch_rest_routes' );

// Add cdp-rest support for the base post type
add_post_type_support('post', 'cdp-rest');
