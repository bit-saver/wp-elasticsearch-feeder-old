<?php
if ( !class_exists( 'ES_API_HELPER' ) ) {
  
  class ES_API_HELPER {

    const PLUGIN_NAME = 'wp-es-feeder';
    const NAME_SPACE = 'elasticsearch/v1';
    const SYNC_TIMEOUT = 10; // minutes

    public static function get_post_type_label($post_type = 'post', $display = 'name') {
      $obj = get_post_type_object($post_type);
      if (is_object($obj)) {
        $labels = $obj->labels;
      }
      return strtolower(isset($labels) ? $labels->$display : $post_type);
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

    public static function get_image_size_array( $id ) {
      $image = wp_prepare_attachment_for_js( $id );
      $sizes = [
        'small' => null,
        'medium' => null,
        'large' => null,
        'full' => null
      ];
      if (!$image) return $sizes;
      foreach ($image['sizes'] as $size) {
        if ($size['width'] < 400) {
          if (!$sizes['small'] || $size['width'] > $sizes['small']['width'])
            $sizes['small'] = $size;
        } else if ($size['width'] >= 400 && $size['width'] <= 900) {
          if (!$sizes['medium'] || $size['width'] > $sizes['medium']['width'])
            $sizes['medium'] = $size;
        } else if ($size['width'] > 900 && $size['width'] < 3000) {
          if (!$sizes['large'] || $size['width'] > $sizes['large']['width'])
            $sizes['large'] = $size;
        }
      }
      if ($image['sizes']['full'])
        $sizes['full'] = $image['sizes']['full'];
      return $sizes;
    }

    public static function get_language( $id ) {
      global $sitepress;
      if ( $sitepress ) {
        $output = apply_filters( 'wpml_post_language_details', null, $id );
        $output['locale'] = str_replace('_', '-', $output['locale']);
        return $output;
      } else {
        return self::get_language_by_locale( 'en' );
      }
    }

    public static function get_index_to_cdp( $id ) {
      $value = get_post_meta($id, '_iip_index_post_to_cdp_option', true);

      return ( $value === 'no' ) ? false : true;
    }

    public static function get_language_by_locale( $locale ) {
      global $cdp_language_helper;
      return $cdp_language_helper->get_language_by_locale( $locale );
    }

    public static function get_language_by_meta_field( $id, $meta_field ) {
      global $cdp_language_helper;
      return $cdp_language_helper->get_language_by_meta_field( $id, $meta_field );
    }

    public static function get_related_translated_posts( $id, $post_type ) {
      global $sitepress;
      if ( $sitepress ) {
        // @todo Move all language related info to Language helper
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
    

    public static function get_custom_taxonomies( $id ) {
      $custom_taxonomies = get_taxonomies( array( 'public' => true, '_builtin' => false) ); 
      $taxonomies = get_post_taxonomies( $id );   

      $output = array();

      if ( !empty($taxonomies) ) {
        foreach ( $taxonomies as $taxonomomy ) {
         if( in_array($taxonomomy, $custom_taxonomies ) ) {
           $terms = wp_get_post_terms( $id,  $taxonomomy, array("fields" => "names", "fields" => 'all') );
           if( count($terms) ) {
            $output[$taxonomomy] = self::remap_terms( $terms );
          }
        }
      } 
      return  $output;
    }
  }

    public static function remap_terms ( $terms ) {
      $arr = array();
      foreach ( $terms as $term ) {
        $arr[] = array(
          'id' => $term->term_id,
          'slug' => $term->slug,
          'name' => $term->name
        );
      }
      return $arr;
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
       // 'name' => get_the_author_meta( 'nicename', $id ),
        'name' => get_the_author_meta( 'display_name', $id )
      );
      return $data;
    }

    /**
     * Renders Visual Composer shortcodes if Visual Composer is turned on
     *
     * @param [type] $object
     * @return void
     */
    public static function render_vs_shortcodes( $object ) {
      if ( !class_exists( 'WPBMap' ) ) { // VC Class
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

if ( !class_exists( 'ES_FEEDER_SYNC' ) ) {
  /**
   * Class ES_FEEDER_SYNC
   *
   * Contains constants used to represent and evaluate various sync states.
   */
  class ES_FEEDER_SYNC {
    const NOT_SYNCED = 0;
    const SYNCING = 1;
    const SYNC_WHILE_SYNCING = 2;
    const SYNCED = 3;
    const RESYNC = 4;
    const ERROR = 5;

    /**
     * Returns English string version of status.
     *
     * @param $status
     * @return string
     */
    public static function display($status) {
      switch ($status) {
        case ES_FEEDER_SYNC::NOT_SYNCED: return 'Not Published';
        case ES_FEEDER_SYNC::SYNCING: return 'Publishing';
        case ES_FEEDER_SYNC::SYNCED: return 'Published';
        case ES_FEEDER_SYNC::SYNC_WHILE_SYNCING: return 'Could not publish while publish in progress';
        case ES_FEEDER_SYNC::RESYNC: return 'Publish Required';
        case ES_FEEDER_SYNC::ERROR: return 'Error';
        default: return 'Never Published';
      }
    }

    /**
     * Returns true if the status is not representative of a syncing state.
     *
     * @param $status
     * @return bool
     */
    public static function sync_allowed($status) {
      switch ($status) {
        case ES_FEEDER_SYNC::SYNC_WHILE_SYNCING:
        case ES_FEEDER_SYNC::SYNCING: return false;
        default: return true;
      }
    }
  }
}
