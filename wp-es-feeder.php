<?php

/**
 * @link              https://github.com/IIP-Design/wp-elasticsearch-feeder
 * @since             1.0.0
 * @package           wp_es_feeder
 * @wordpress-plugin
 * Plugin Name:       WP Elasticsearch Feeder
 * Description:       Creates REST api endpoints for each post type and indexes them into Elasticsearch.
 * Version:           1.0.0
 * Author:            Max Orelus
 * Author URI:        http://github.com/MaxOrelus
 * License:           GPL-2.0+
 * License URI:       http://www.gnu.org/licenses/gpl-2.0.txt
 * Text Domain:       wp-es-feeder
 */

// abort if not called via Wordpress
if ( !defined( 'WPINC' ) ) {
  die;
}

// load elasticsearch REST api/elasticsearch feeder dependencies
require plugin_dir_path( __FILE__ ) . 'includes/class-wp-es-feeder.php';

// run elasticsearch feeder plugin
$feeder = new wp_es_feeder();
$feeder->run();
