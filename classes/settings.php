<?php


class RS_Entry_Archives_Settings {
	
	private $screen;
	private $page;
	private $view;
	private $form_id;
	private $entry_id;
	private $settings = null;
	
	function __construct() {
	}
	
	/**
	 * Get an array of settings, which are loaded the first time this is called.
	 * 
	 * @param $key
	 *
	 * @return array|mixed|null
	 */
	public function get_settings( $key = null ) {
		if ( $this->settings === null ) {
			$this->settings = array(
				'screen'   => function_exists('get_current_screen') ? get_current_screen() : false,
				'page'     => isset($_GET['page']) ? stripslashes($_GET['page']) : null,
				'view'     => isset($_GET['view']) ? stripslashes($_GET['view']) : null,
				'form_id'  => isset($_GET['id']) ? stripslashes($_GET['id']) : null,
				'entry_id' => isset($_GET['lid']) ? stripslashes($_GET['lid']) : null,
				'filter'   => isset($_GET['filter']) ? stripslashes($_GET['filter']) : null,
			);
		}
		
		if ( $key === null ) {
			// Get all settings as an array
			return $this->settings;
		}else{
			// Get a specific setting
			return $this->settings[ $key ] ?? null;
		}
	}
	
	/**
	 * Get the current screen object, or a property from within it
	 * 
	 * @param null|string $key
	 *
	 * @return WP_Screen|string|false
	 */
	public function get_screen( $key = null ) {
		$screen = $this->get_settings( 'screen' );
		
		// If no key specified, return screen object
		if ( $key === null ) return $screen;
		
		// Return a single property
		if ( $screen instanceof WP_Screen && property_exists( $screen, $key ) ) {
			return $screen->{$key};
		}else{
			return false;
		}
	}
	
	public function get_page() {
		return $this->get_settings( 'page' );
	}
	
	public function get_view() {
		return $this->get_settings( 'view' );
	}
	
	public function get_form_id() {
		return $this->get_settings( 'form_id' );
	}
	
	public function get_entry_id() {
		return $this->get_settings( 'entry_id' );
	}
	
	public function get_filter() {
		return $this->get_settings( 'filter' );
	}
	
	/**
	 * Check if on one of the gravity forms screens on the backend.
	 *
	 * @return bool
	 */
	public function is_gravity_forms_screen() {
		return (
			   $this->is_form_list_screen()
			|| $this->is_form_edit_screen()
			|| $this->is_entry_list_screen()
			|| $this->is_entry_edit_screen()
			|| $this->is_any_gravity_screen()
		);
	}
	
	public function is_any_gravity_screen() {
		return GFForms::is_gravity_page();
	}
	
	/**
	 * Check if on the form list screen on the backend
	 *
	 * Form list screen
	 * admin.php?page=gf_new_form
	 * action = ''
	 * base = 'forms_page_gf_new_form'
	 * id = 'forms_page_gf_new_form'
	 *
	 * @return bool
	 */
	public function is_form_list_screen() {
		return (
	        $this->get_screen('base') === 'toplevel_page_gf_edit_forms'
			&& $this->get_page() === 'gf_edit_forms'
			&& $this->get_form_id() === false
		);
	}
	
	/**
	 * Check if editing a form on the backend
	 *
	 * Form edit screen
	 * admin.php?page=gf_edit_forms&id=8
	 * action = ''
	 * base = 'toplevel_page_gf_edit_forms'
	 * id = 'toplevel_page_gf_edit_forms'
	 *
	 * @return bool
	 */
	public function is_form_edit_screen() {
		return (
		   $this->get_screen('base') === 'toplevel_page_gf_edit_forms'
			&& $this->get_page() === 'gf_edit_forms'
			&& $this->get_form_id() !== false
		);
	}
	
	/**
	 * Check if viewing the entry list screen on the dashboard.
	 *
	 * Entry list screen:
	 * admin.php?page=gf_entries&view=entries&id=1&filter
	 * action = ''
	 * base = 'forms_page_gf_entries'
	 * id = 'forms_page_gf_entries'
	 *
	 * @return bool
	 */
	public function is_entry_list_screen() {
		return (
		   $this->get_screen('base') === 'forms_page_gf_entries'
			&& $this->get_page() === 'gf_entries'
			&& ( ! $this->get_view() || $this->get_view() === 'entries')
		);
	}
	
	/**
	 * Check if editing an entry on the backend
	 *
	 * Entry detail screen:
	 * admin.php?page=gf_entries&view=entry&id=1&lid=24706&order=ASC&filter=&paged=1&pos=1&field_id=&operator=
	 * action = ''
	 * base = 'forms_page_gf_entries'
	 * id = 'forms_page_gf_entries'
	 *
	 * @return bool
	 */
	public function is_entry_edit_screen() {
		return (
		   $this->get_screen('base') === 'forms_page_gf_entries'
			&& $this->get_page() === 'gf_entries'
			&& $this->get_view() === 'entry'
		);
	}
	
}

return new RS_Entry_Archives_Settings();