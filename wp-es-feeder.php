<?php

/**
 * The plugin bootstrap file
 *
 * This file is read by WordPress to generate the plugin information in the plugin
 * admin area. This file also includes all of the dependencies used by the plugin,
 * registers the activation and deactivation functions, and defines a function
 * that starts the plugin.
 *
 * @link              http://github.com/MaxOrelus
 * @since             1.0.0
 * @package           Wp_Es_Feeder
 *
 * @wordpress-plugin
 * Plugin Name:       WP Elasticsearch Feeder
 * Plugin URI:        http://githhub.com/MaxOrelus/wp-elasticsearch-feeder
 * Description:       Plugin that ingests the Wordpress REST api for post, pages, and custom post-types into Elasticsearch.
 * Version:           1.0.0
 * Author:            Max Orelus
 * Author URI:        http://github.com/MaxOrelus
 * License:           GPL-2.0+
 * License URI:       http://www.gnu.org/licenses/gpl-2.0.txt
 * Text Domain:       wp-es-feeder
 * Domain Path:       /languages
 */

// If this file is called directly, abort.
if ( ! defined( 'WPINC' ) ) {
	die;
}

/**
 * The code that runs during plugin activation.
 * This action is documented in includes/class-wp-es-feeder-activator.php
 */
function activate_wp_es_feeder() {
	require_once plugin_dir_path( __FILE__ ) . 'includes/class-wp-es-feeder-activator.php';
	Wp_Es_Feeder_Activator::activate();
}

/**
 * The code that runs during plugin deactivation.
 * This action is documented in includes/class-wp-es-feeder-deactivator.php
 */
function deactivate_wp_es_feeder() {
	require_once plugin_dir_path( __FILE__ ) . 'includes/class-wp-es-feeder-deactivator.php';
	Wp_Es_Feeder_Deactivator::deactivate();
}

register_activation_hook( __FILE__, 'activate_wp_es_feeder' );
register_deactivation_hook( __FILE__, 'deactivate_wp_es_feeder' );

/**
 * The core plugin class that is used to define internationalization,
 * admin-specific hooks, and public-facing site hooks.
 */
require plugin_dir_path( __FILE__ ) . 'includes/class-wp-es-feeder.php';

/**
 * Begins execution of the plugin.
 *
 * Since everything within the plugin is registered via hooks,
 * then kicking off the plugin from this point in the file does
 * not affect the page life cycle.
 *
 * @since    1.0.0
 */
function run_wp_es_feeder() {

	$plugin = new Wp_Es_Feeder();
	$plugin->run();

}
run_wp_es_feeder();
