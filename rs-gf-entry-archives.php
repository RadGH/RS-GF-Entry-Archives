<?php
/*
Plugin Name: RS Entry Archives for Gravity Forms 
Description: Adds the ability to archive Gravity Forms entries.
Version: 1.4.3
Author: Radley Sustaire
Author URI: https://radleysustaire.com/
GitHub Plugin URI: https://github.com/RadGH/RS-GF-Entry-Archives
Primary Branch: main
*/

if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly.

define( 'RSEA_VERSION', '1.4.3' );
define( 'RSEA_URL', untrailingslashit(plugin_dir_url( __FILE__ )) );
define( 'RSEA_PATH', dirname(__FILE__) );

class RS_Entry_Archives_Plugin {
	
	public RS_Entry_Archives_Enqueue  $Enqueue;
	public RS_Entry_Archives_Form     $Form;
	public RS_Entry_Archives_Settings $Settings;
	
	public function __construct() {
		
		// Load the rest of the plugin when other plugins have finished loading.
		add_action( 'plugins_loaded', array( $this, 'load_plugin' ) );
		
		// Register activation and deactivation hooks
		register_activation_hook( __FILE__, array( $this, 'activate_plugin' ) );
		
	}
	
	public function load_plugin() {
		
		// Check if Gravity Forms is loaded
		if ( ! class_exists( 'GFForms' ) ) return;
		
		// Classes
		// - For usage outside of this plugin: RS_Entry_Archives()->Enqueue->something();
		$this->Enqueue  = include( RSEA_PATH . '/classes/enqueue.php' );
		$this->Form     = include( RSEA_PATH . '/classes/form.php' );
		$this->Settings = include( RSEA_PATH . '/classes/settings.php' );
		
	}
	
	public function activate_plugin() {
		
		// Perform upgrades when the plugin is updated
		$install = include( RSEA_PATH . '/classes/advanced/install.php' );
		$install->upgrade();
		
	}
	
}

/**
 * Get the plugin object, creating it the first time.
 *
 * @return RS_Entry_Archives_Plugin
 */
function RS_Entry_Archives() {
	static $instance = null;
	if ( $instance === null ) $instance = new RS_Entry_Archives_Plugin();
	return $instance;
}

// Initialize the plugin
RS_Entry_Archives();