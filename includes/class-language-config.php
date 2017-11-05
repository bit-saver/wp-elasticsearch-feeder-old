<?php 

class Language_Helper {

  // the country codes are different depending on country so just making language_code & locale same for now
  const LANGUAGE_HASH = array (
    'en' => array (
      'language_code' => 'en',
      'locale' => 'en-US',
      'text_direction' => false,
      'display_name' => 'English',
      'native_name' => 'English',
      'different_language' => true
    ),

    'es' => array (
      'language_code' => 'es',
      'locale' => 'es',  
      'text_direction' => false,
      'display_name' => 'Spanish',
      'native_name' => 'Spanish',
      'different_language' => true
    ),

    'fr' => array(
      'language_code' => 'fr',
      'locale' => 'fr',
      'text_direction' => false,
      'display_name' => 'French',
      'native_name' => 'French',
      'different_language' => true
    ),

    'pt' => array(
      'language_code' => 'pt',
      'locale' => 'pt',
      'text_direction' => false,
      'display_name' => 'Portuguese',
      'native_name' => 'Portuguese',
      'different_language' => true
    )
  );

  public static function get_language_by_locale( $locale ) { 
    return self::LANGUAGE_HASH[$locale];
  }

  public static function get_language_by_meta_field( $id, $meta_field ) { 
    $locale = get_post_meta( $id, $meta_field, true );   //'america_courses_language
    $locale = empty( $locale ) ? 'en' : $locale;
    return self::LANGUAGE_HASH[$locale];
  }
}