<?php

class RS_Entry_Archives_Install {
	
	public function __construct() {}
	
	public function upgrade() {
		
		// Get current version to compare
		$installed_version = get_option( 'rs_gf_entry_archives_version', '0.0.0' );
		
		// 1.4.0
		if ( version_compare( $installed_version, '1.4.0', '<' ) ) {
			$this->upgrade_v_1_4_0();
		}
		
		// Update the installed version
		update_option( 'rs_gf_entry_archives_version', RSEA_VERSION, false );
		
	}
	
	/**
	 * Upgrade to version 1.4.0
	 *
	 * Previous versions archived entries using a custom meta field named "is_archived" = 1.
	 * This version changed to use the literal entry status rather than a custom field.
	 *
	 * @return void
	 */
	public function upgrade_v_1_4_0() {
		
		// Get all form IDs
		$form_ids = GFFormsModel::get_form_ids( null, null );
		
		// Search for all entries with the "is_archived" meta key
		$search = array(
			'status' => array( 'active', 'trash', 'spam', 'archived' ),
			'field_filters' => array(
				array(
					'key' => 'is_archived',
					'value' => '1',
				),
			),
		);
		
		$entry_ids = GFAPI::get_entry_ids( $form_ids, $search );
		
		if ( $entry_ids ) foreach( $entry_ids as $entry_id ) {
			
			// Update the entry status to 'archived'
			GFAPI::update_entry_property( $entry_id, 'status', 'archived' );
			
			// Delete the previous meta key
			gform_delete_meta( $entry_id, 'is_archived' );
			
		}
		
	}
	
}

return new RS_Entry_Archives_Install();