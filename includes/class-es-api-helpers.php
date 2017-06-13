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
      $image = wp_prepare_attachment_for_js( $id );
      $data = array(
        "id" => $image[ 'id' ],
        "title" => $image[ 'title' ],
        "alt" => $image[ 'alt' ],
        "caption" => $image[ 'caption' ],
        "mime" => $image[ 'mime' ],
        "sizes" => $image[ 'sizes' ],
      );
      return $data;
    }

    public static function get_language( $id ) {
      global $sitepress;
      if ( $sitepress ) {
        $output = apply_filters( 'wpml_post_language_details', null, $id );
        $output['locale'] = str_replace('_', '-', $output['locale']);
        return $output;
      } else {
        return array(
          'language_code' => 'en',
          'locale' => 'en-US',
          'text_direction' => false,
          'display_name' => 'English',
          'native_name' => 'English',
          'different_language' => true
        );
      }
    }

    public static function get_related_translated_posts( $id, $post_type ) {
      global $sitepress;
      if ( $sitepress ) {
        $languages = array('en', 'es', 'fr', 'pt-br', 'ru', 'ar', 'zh-hans', 'fa', 'id', 'pt-pt');
        $translations = array();

        foreach ($languages as $language) {
          $tmp = icl_object_id($id, $post_type, false, $language);
          if ($tmp !== null) {
            $translations[] = array(
              'language' => $language,
              'id' => icl_object_id($id, $post_type, false, $language)
            );
          }
        }
        return $translations;
      }
    }

    public static function get_categories( $id ) {
      $categories = wp_get_post_categories( $id, array(
         'fields' => 'all'
      ));

      $output = array();

      if ( !empty( $categories ) ) {
        foreach ( $categories as $category ) {
          $output[] = array(
            'id' => (int) $category->term_id,
            'slug' => $category->slug,
            'name' => $category->name
          );
        }
      }
      return $output;
    }

    public static function get_categories_searchable($id) {
      $categories = wp_get_post_categories( $id, array(
         'fields' => 'all'
      ));

      $output = array();

      if ( !empty( $categories ) ) {
        foreach ( $categories as $category ) {
          $output[] = $category->slug;
        }
      }
      return $output;
    }

    public static function get_tags($id ) {
      $tags = wp_get_post_tags( $id );

      $output = array();

      if ( !empty( $tags ) ) {
        foreach ( $tags as $tag ) {
          $output[] = array(
            'id' => $tag->term_id,
            'slug' => $tag->slug,
            'name' => $tag->name
          );
        }
      }
      return $output;
    }

    public static function get_tags_searchable($id) {
      $tags = wp_get_post_tags( $id );

      $output = array();

      if ( !empty( $tags ) ) {
        foreach ( $tags as $tag ) {
          $output[] = $tag->slug;
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
