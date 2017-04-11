<?php

if ( !class_exists( 'ES_API_HELPER' ) ) {
  class ES_API_HELPER {
    public static function get_post_type_label($post_type = 'post', $display = 'name') {
      $obj = get_post_type_object($post_type);

      if (is_object($obj)) {
        $labels = $obj -> labels;
      }

      return strtolower($labels -> $display);
    }

    public static function get_featured_image( $id ) {
      $image     = wp_prepare_attachment_for_js( $id );
      $sizes     = $image[ 'sizes' ];
      $sizeArray = array();
      $srcArray  = array();
      if ( !empty( $sizes ) ) {
        foreach ( $sizes as $size ) {
          if ( $size[ 'width' ] <= 770 ) {
            if ( empty( $srcArray ) || $srcArray[ 'width' ] < $size[ 'width' ] ) {
              $srcArray = array(
                 "width" => $size[ 'width' ],
                "height" => $size[ 'height' ],
                "src" => $size[ 'url' ]
              );
            }
          }
          $sizeArray[] = array(
            "width" => $size[ 'width' ],
            "height" => $size[ 'height' ],
            "src" => $size[ 'url' ]
          );
        }
      }
      $data = array(
        "id" => $image[ 'id' ],
        "src" => $srcArray[ 'src' ],
        "width" => $srcArray[ 'width' ],
        "height" => $srcArray[ 'height' ],
        "title" => $image[ 'title' ],
        "alt" => $image[ 'alt' ],
        "caption" => $image[ 'caption' ],
        "srcset" => $sizeArray
      );
      return $data;
    }

    public static function get_language( $id ) {
      global $sitepress;
      if ( $sitepress ) {
        return apply_filters( 'wpml_post_language_details', null, $id );
      } else {
        return array(
           'locale' => get_bloginfo( 'language' )
        );
      }
    }

    public static function get_categories( $id ) {
      $categories = wp_get_post_categories( $id, array(
         'fields' => 'all'
      ) );
      $output = array(
         'id' => array(),
        'slug' => array(),
        'name' => array()
      );

      if ( !empty( $categories ) ) {
        foreach ( $categories as $category ) {
          $output[ 'id' ][] = (int) $category->term_id;
          $output[ 'slug' ][] = $category->slug;
          $output[ 'name' ][] = $category->name;
        }
      }
      return $output;
    }

    public static function get_tags($id ) {
      $tags = wp_get_post_tags( $id );
      $output = array(
         'id' => array(),
        'slug' => array(),
        'name' => array()
      );
      if ( !empty( $tags ) ) {
        foreach ( $tags as $tag ) {
          $output[ 'id' ][]   = $tag->term_id;
          $output[ 'slug' ][] = $tag->slug;
          $output[ 'name' ][] = $tag->name;
        }
      }
      return $output;
    }

    public static function get_author( $id ) {
      $data = array(
         'id' => (int) $id,
        'name' => get_the_author_meta( 'nicename', $id )
      );
      return $data;
    }

    public static function render_vs_shortcodes( $object ) {
      if ( !class_exists( 'WPBMap' ) ) {
        return apply_filters( 'the_content', $object->post_content );
      }

      WPBMap::addAllMappedShortcodes();

      global $post;
      $post   = get_post( $object->ID );
      $output = apply_filters( 'the_content', $post->post_content );

      return $output;
    }
  }
}
