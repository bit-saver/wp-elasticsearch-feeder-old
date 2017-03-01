<?php

/**
 * Define the internationalization functionality
 *
 * Loads and defines the internationalization files for this plugin
 * so that it is ready for translation.
 *
 * @link       http://github.com/MaxOrelus
 * @since      1.0.0
 *
 * @package    Wp_Es_Feeder
 * @subpackage Wp_Es_Feeder/includes
 */

/**
 * Define the internationalization functionality.
 *
 * Loads and defines the internationalization files for this plugin
 * so that it is ready for translation.
 *
 * @since      1.0.0
 * @package    Wp_Es_Feeder
 * @subpackage Wp_Es_Feeder/includes
 * @author     Max Orelus <me@maxorelus.com>
 */
class Wp_Es_Feeder_i18n {


	/**
	 * Load the plugin text domain for translation.
	 *
	 * @since    1.0.0
	 */
	public function load_plugin_textdomain() {

		load_plugin_textdomain(
			'wp-es-feeder',
			false,
			dirname( dirname( plugin_basename( __FILE__ ) ) ) . '/languages/'
		);

	}



}
