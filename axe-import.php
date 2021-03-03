<?php
/**
 * Plugin Name: Axe Import
 * Version: 1.0.1
 * Plugin URI: https://github.com/dmitri-ko/axe-import
 * Description: DB Models and Import Plugin for Exhibitions.
 * Author: Dmitry Kokorin
 * Author URI: https://github.com/dmitri-ko
 * Requires at least: 4.0
 * Tested up to: 4.0
 *
 * Text Domain: axe-import
 * Domain Path: /lang/
 *
 * @package WordPress
 * @author Dmitry Kokorin
 * @since 1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Load plugin class files.
require_once 'includes/class-axe-import.php';
require_once 'includes/class-axe-import-settings.php';

// Load plugin libraries.
require_once 'includes/lib/class-axe-import-admin-api.php';
require_once 'includes/lib/class-axe-import-post-type.php';
require_once 'includes/lib/class-axe-import-simplexmlelement.php';
require_once 'includes/lib/class-axe-import-taxonomy.php';
require_once 'includes/lib/class-axe-import-metabox.php';
require_once 'includes/lib/rapid-addon.php';

/**
 * Returns the main instance of Axe_Import to prevent the need to use globals.
 *
 * @since  1.0.0
 * @return object Axe_Import
 */
function axe_import() {
	$instance = Axe_Import::instance( __FILE__, '1.0.0' );

	if ( is_null( $instance->settings ) ) {
		$instance->settings = Axe_Import_Settings::instance( $instance );
	}

	return $instance;
}

$import = axe_import();

$import->register_all_post_types();

