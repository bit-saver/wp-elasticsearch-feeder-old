<?php
// abort if not called via Wordpress
if ( !defined( 'ABSPATH' ) ) {
  exit;
}

if ( !class_exists( 'WP_ES_FEEDER_REST_Controller' ) ) {
  class WP_ES_FEEDER_REST_Controller extends WP_REST_Controller {
    public function __construct( $post_type ) {
      $this->plugin_name = 'wp-es-feeder';
      $this->namespace = 'elasticsearch/v1';
      $this->resource = ES_API_HELPER::get_post_type_label($post_type, 'name');
      $this->type = $post_type;
      $this->index_name = get_option($this->plugin_name)['es_index'];
    }

    public function register_routes() {
      register_rest_route( $this->namespace, '/' . $this->resource, array(
         array(
           'methods' => WP_REST_Server::READABLE,
          'callback' => array(
             $this,
            'get_items'
          ),
          'args' => array(
             'per_page' => array(
               'validate_callback' => function( $param, $request, $key ) {
                return is_numeric( $param );
              }
            ),
            'page' => array(
               'validate_callback' => function( $param, $request, $key ) {
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

      register_rest_route( $this->namespace, '/' . $this->resource . '/(?P<id>[\d]+)', array(
         array(
           'methods' => WP_REST_Server::READABLE,
          'callback' => array(
             $this,
            'get_item'
          ),
          'args' => array(
             'id' => array(
               'validate_callback' => function( $param, $request, $key ) {
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
      $page = (int) $request->get_param( 'page' );
      $per_page = (int) $request->get_param( 'per_page' );

      if ( $per_page ) {
        $args[ 'posts_per_page' ] = $per_page;
      } else {
        $args[ 'posts_per_page' ] = 25;
      }

      if ( is_numeric( $page ) ) {
        if ( $page == 1 ) {
          $args[ 'offset' ] = 0;
        } elseif ( $page > 1 ) {
          $args[ 'offset' ] = ( $page * $args[ 'posts_per_page' ] ) - $args[ 'posts_per_page' ];
        }
      }

      $posts = get_posts( $args );

      if ( empty( $posts ) ) {
        return rest_ensure_response( $data );
      }

      foreach ( $posts as $post ) {
        $response = $this->prepare_item_for_response( $post, $request );
        $data[] = $this->prepare_response_for_collection( $response );
      }

      return rest_ensure_response($data);
    }

    public function get_item( $request ) {
      $id = (int) $request[ 'id' ];

      $post = $this->type === 'post' ? get_post( $id ) : get_page( $id );

      if ( empty( $post ) ) {
        return rest_ensure_response( array ());
      }

      $response = $this->prepare_item_for_response( $post, $request );

      return $response;
    }

    public function prepare_response_for_collection( $response ) {
      if ( !( $response instanceof WP_REST_Response ) ) {
        return $response;
      }

      $data   = (array) $response->get_data();
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
        $post_data['site'] = $this -> index_name;
        return rest_ensure_response( $post_data );
      }

      // We are also renaming the fields to more understandable names.
      if ( isset( $post->ID ) ) {
        $post_data[ 'id' ] = (int) $post->ID;
      }

      $post_data[ 'type' ] = $this->type;


      $post_data['site'] = $this -> index_name;

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
      $opt = get_option( $this->plugin_name );
      $opt_url = $opt['es_wpdomain'];
      $post_data[ 'link' ] = str_replace(site_url(), $opt_url, get_permalink( $post->ID ));

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

      // pre-approved
      $post_data[ 'categories' ] = ES_API_HELPER::get_categories( $post->ID );
      // $post_data[ 'categories.searchable' ] = ES_API_HELPER::get_categories_searchable( $post->ID );
      $post_data[ 'tags' ] = ES_API_HELPER::get_tags( $post->ID );
      // $post_data[ 'tags.searchable' ] = ES_API_HELPER::get_tags_searchable( $post->ID );
      $post_data[ 'language' ] = ES_API_HELPER::get_language( $post->ID );
      $post_data[ 'translations' ] = ES_API_HELPER::get_related_translated_posts($post->ID, $post->post_type);
      
      $custom_taxonomies = ES_API_HELPER::get_custom_taxonomies($post->ID);
      if( count( $custom_taxonomies) ) {
        $post_data[ 'taxonomies' ] = $custom_taxonomies;
      }
      
      $feature_image_exists = has_post_thumbnail( $post->ID );
      if ( $feature_image_exists ) {
        $post_data[ 'featured_image' ] = ES_API_HELPER::get_featured_image( get_post_thumbnail_id( $post->ID ) );
      }

      if ( isset( $post->comment_count ) ) {
        $post_data[ 'comment_count' ] = (int) $post->comment_count;
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
  }
}

/*
* Creates API endpoints for all public post-types. If you have a custom post-type, you must follow
* the class convention "WP_ES_FEEDER_EXT_{TYPE}_Controller" if you want to customize the output
* If no class convention is found, plugin will create default API routes for custom post types
*/
function register_post_types($type) {
  $base_types = array(
    'post' => true,
    'page' => true,
    'attachment' => true
  );

  $is_base_type = array_key_exists( $type, $base_types);
  if ((int) $is_base_type ) {
    $controller = new WP_ES_FEEDER_REST_Controller( $type );
    $controller->register_routes();
    return;
  } else if(!$is_base_type && !class_exists('WP_ES_FEEDER_EXT_'.strtoupper($type).'_Controller')) {
    $controller = new WP_ES_FEEDER_REST_Controller( $type );
    $controller->register_routes();
    return;
  }
}

function register_elasticsearch_rest_routes() {
  $post_types = get_post_types( array(
     'public' => true
  ));

  if ( is_array( $post_types ) && count( $post_types ) > 0 ) {
    foreach ( $post_types as $type ) {
      register_post_types($type);
    }
  }
}

add_action( 'rest_api_init', 'register_elasticsearch_rest_routes' );
