<?php

class RS_Entry_Archives_Enqueue {
	
	function __construct() {
		
		// Include scripts on the admin page
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_admin_scripts' ) );
		
	}
	
	/**
	 * Enqueue JS and CSS for the admin
	 *
	 * @return void
	 */
	function enqueue_admin_scripts() {
		
		// Get the admin object to more easily access those methods
		$Settings = RS_Entry_Archives()->Settings;
		
		// Must be on a gravity forms screen
		if ( ! RS_Entry_Archives()->Settings->is_gravity_forms_screen() ) return;
		
		// Enqueue the JS and CSS
		wp_enqueue_script( 'rs-entry-archives-admin', RSEA_URL . '/assets/admin-general.js', array( 'jquery' ), RSEA_VERSION );
		wp_enqueue_style( 'rs-entry-archives-admin', RSEA_URL . '/assets/admin-general.css', array(), RSEA_VERSION );
		
		// Include settings
		wp_localize_script( 'rs-entry-archives-admin', 'rsea_settings', array(
			'settings' => array(
				'ajax_url' => admin_url( 'admin-ajax.php' ),
				'nonce' => wp_create_nonce( 'rsea-nonce' ),
			),
			
			/*
			'screen' => array(
				// 'action' => $Settings->get_screen('action'),
				// 'base' => $Settings->get_screen('base'),
				// 'id' => $Settings->get_screen('id'),
				// 'page' => $Settings->get_page(),
				// 'view' => $Settings->get_view(),
				
				'form_id' => $Settings->get_form_id(),
				'entry_id' => $Settings->get_entry_id(),
				'filter' => $Settings->get_filter(),
				
				'is_gravity_forms_screen' => $Settings->is_gravity_forms_screen(),
				'is_form_list_screen' => $Settings->is_form_list_screen(),
				'is_form_edit_screen' => $Settings->is_form_edit_screen(),
				'is_entry_list_screen' => $Settings->is_entry_list_screen(),
				'is_entry_edit_screen' => $Settings->is_entry_edit_screen(),
			),
			*/
		));
			
	}
	
}

return new RS_Entry_Archives_Enqueue();