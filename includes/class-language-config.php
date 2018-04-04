<?php

global $cdp_language_helper;
$cdp_language_helper = new Language_Helper();

class Language_Helper {

  public $languages;

  public $backup_lang;

  public function __construct() {
    $this->backup_lang = (object) array(
      'language_code' => 'en',
      'locale' => 'en-us',
      'text_direction' => false,
      'display_name' => 'English',
      'native_name' => 'English'
    );
  }

  public function get_language_by_locale( $locale ) {
    $locale = strtolower($locale);
    if ( !$this->languages ) $this->load_languages();
    if ( !$this->languages || !count($this->languages)) {
      if ( $locale == 'en' || $locale == 'en-us' )
        return $this->backup_lang;
      return null;
    }
    return $this->languages[strtolower($locale)];
  }

  public function get_language_by_meta_field( $id, $meta_field ) {
    $locale = get_post_meta( $id, $meta_field, true );   //'
    $locale = empty( $locale ) ? 'en' : $locale;
    if ( !$this->languages ) $this->load_languages();
    return $this->languages[strtolower($locale)];
  }

  public function load_languages() {
    global $feeder;
    if ( !$feeder ) return;
    $args = [
      'method' => 'GET',
      'url' => 'language'
    ];
    $data = $feeder->es_request($args);
    if ( $data && count( $data ) && (!array_key_exists( 'error', $data ) || !$data[ 'error' ]) && (!is_object( $data ) || !$data->error) ) {
      $this->languages = [];
      foreach ( $data as $lang ) {
        $this->languages[$lang->locale] = $lang;
      }
    }
  }

  public function get_languages() {
    if ( !$this->languages ) $this->load_languages();
    if ( !$this->languages || !count($this->languages)) return ['en' => $this->backup_lang];
    return $this->languages;
  }
}