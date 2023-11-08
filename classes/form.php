<?php

class RS_Entry_Archives_Form {
	
	function __construct() {
		
		// Add a filter to view archived entries to the entries screen
		add_filter( 'gform_filter_links_entry_list', array( $this, 'add_archived_filter_link' ), 30, 3 );
		
		// When using the archive filter view, only show archived entries
		add_filter( 'gform_gf_query_sql', array( $this, 'pre_get_entries_sql' ), 20, 2 );
		
		// Add a link to archive an entry next to the spam and trash links
		add_action( 'gform_entries_first_column_actions', array( $this, 'add_archive_link' ), 10, 5 );
		
		// Handle the action of archiving or un-archiving an entry
		add_action( 'admin_init', array( $this, 'handle_archive_action' ) );
		add_action( 'wp_ajax_rsea_archive', array( $this, 'handle_archive_action' ) );
		
		// Show a notice on the admin after archiving or un-archiving an entry
		add_action( 'admin_notices', array( $this, 'show_archive_notice' ) );
		
		// Add a bulk action to mark multiple entries as archived / un-archived
		add_filter( 'gform_entry_list_bulk_actions', array( $this, 'add_bulk_actions' ), 10, 2 );
		
		// Handle the bulk action of archiving or un-archiving entries
		add_action( 'gform_entry_list_action', array( $this, 'handle_bulk_action' ), 10, 3 );
		
		// Make "Archived" a field available to export
		add_filter( 'gform_export_fields', array( $this, 'add_export_field' ) );
		
		// Add the "Archived" value to the exports
		add_filter( 'gform_export_field_value', array( $this, 'add_export_value' ), 10, 4 );
		
		// Add a conditional logic field to export screen (and other areas)
		add_filter( 'gform_field_filters', array( $this, 'add_conditional_logic_field' ), 10, 2 );
		
		// When performing an export, allow conditional logic for entry status or archive status
		add_filter( 'gform_search_criteria_export_entries', array( $this, 'apply_conditional_logic_status_for_exporting' ), 10, 2 );
		
		// Gravity Forms doesn't support the "any" status, so we replace it in the MySQL query
		add_filter( 'gform_gf_query_sql', array( $this, 'pre_get_entries_any_status' ), 20, 2 );
		
		// Add a custom column for archive status to the entry list screen
	    add_filter( 'gform_form_post_get_meta', array( $this, 'add_entry_status_column' ), 10, 1 );
		
	}
	
	/**
	 * Add a filter to view archived entries to the entries screen
	 *
	 * @param $filter_links
	 * @param $form
	 * @param $include_counts
	 *
	 * @return mixed
	 */
	function add_archived_filter_link( $filter_links, $form, $include_counts ) {
		
		$archived_count = $include_counts ? GFAPI::count_entries( $form['id'], array( 'status' => 'archived' ) ) : 0;
		
		// Add a filter for archived entries
		$filter_links[] = array(
			'id' => 'archived',
			'count' => $archived_count,
			'label'   => esc_html__( 'Archived', 'rs-entry-archives' ),
		);
		
		// The "All" filter actually excludes spam, trash, and archived entries.
		// Change that label to "Active" instead.
		foreach( $filter_links as &$f ) if ( $f['id'] === 'all' ) {
			$f['label'] = esc_html__( 'Active', 'rs-entry-archives' );
		}
		
		return $filter_links;
	}
	
	/**
	 * When entries are queried on the entry list page, filter out any archived entries, or view only archived entries
	 *
	 * @param array $sql An array with all the SQL fragments: select, from, join, where, order, paginate. {
	 *     @type string $select   "SELECT SQL_CALC_FOUND_ROWS DISTINCT `t1`.`id`"
	 *     @type string $from     "FROM `wp_gf_entry` AS `t1`"
	 *     @type string $join     "LEFT JOIN `wp_gf_entry_meta` AS `m2` ON (`m2`.`entry_id` = `t1`.`id` AND `m2`.`meta_key` = 'is_archived')"
	 *     @type string $where    "WHERE (`t1`.`form_id` IN (1) AND (`t1`.`status` = 'active' AND (`m2`.`meta_key` = 'is_archived' AND `m2`.`meta_value` = '1')))"
	 *     @type string $order    "ORDER BY `t1`.`id` DESC"
	 *     @type string $paginate "LIMIT 20"
	 * }
	 *
	 * @return array
	 */
	function pre_get_entries_sql( $sql ) {
		if ( ! RS_Entry_Archives()->Settings->is_entry_list_screen() ) return $sql;
		
		$filter = isset($_GET['filter']) ? stripslashes($_GET['filter']) : 'all';
		
		// default filters: all, unread, star, spam, trash, archived
		
		if ( $filter === 'archived' ) {
			$sql['where'] = str_replace(
				"`status` = 'active'",
				"`status` = 'archived'",
				$sql['where']
			);
			
		}
		
		return $sql;
	}
	
	/**
	 * Add a link to archive an entry next to the spam and trash links
	 *
	 * @param int $form_id
	 * @param int $field_id
	 * @param mixed $value
	 * @param array $entry
	 * @param string $query_string
	 *
	 * @return void
	 */
	function add_archive_link( $form_id, $field_id, $value, $entry, $query_string ) {
		$is_archived = $this->is_archived( $entry['id'] );
		
		$archive_link = $this->get_archive_entry_url( $entry['id'], true );
		$unarchive_link = $this->get_archive_entry_url( $entry['id'], false );
		
		?>
		<span class="edit rsea-archive-link">
            <a data-entry-id="<?php echo $entry['id']; ?>" id="archive_entry_<?php echo esc_attr( $entry['id'] ); ?>" aria-label="Archive this entry" href="<?php echo esc_attr($archive_link); ?>" class="rsea-archive-entry" <?php if ( $is_archived ) echo 'style="display: none;"'; ?>><?php esc_html_e( 'Archive', 'rs-entry-archives' ); ?></a>
            <a data-entry-id="<?php echo $entry['id']; ?>" id="unarchive_entry_<?php echo esc_attr( $entry['id'] ); ?>" aria-label="Remove entry from archives" href="<?php echo esc_attr($unarchive_link); ?>" class="rsea-unarchive-entry" <?php if ( !$is_archived ) echo 'style="display: none;"'; ?>><?php esc_html_e( 'Unarchive', 'rs-entry-archives' ); ?></a>
			<?php echo GFCommon::current_user_can_any( 'gravityforms_delete_entries' ) || GFCommon::akismet_enabled( $form_id ) ? '|' : '' ?>
        </span>
		<?php
	}
	
	/**
	 * Handle the action of archiving or un-archiving an entry
	 *
	 * @return void
	 */
	function handle_archive_action() {
		$make_archived = isset($_GET['rsea_archive']) ? (int) $_GET['rsea_archive'] : null;
		$entry_id = isset($_GET['rsea_entry_id']) ? (int) $_GET['rsea_entry_id'] : null;
		if ( $make_archived === null || $entry_id === null ) return;
		
		if ( $make_archived ) {
			$this->archive_entry( $entry_id );
		}else{
			$this->unarchive_entry( $entry_id );
		}
		
		if ( defined('DOING_AJAX') && DOING_AJAX ) {
			wp_send_json_success(array(
				'entry_id' => $entry_id,
				'archived' => $make_archived,
			));
		}else{
			$url = remove_query_arg( array( 'rsea_archive', 'rsea_entry_id' ) );
			$url = add_query_arg(array( 'rsea_archived' => $make_archived, 'rsea_archived_id' => $entry_id ), $url);
			wp_redirect( $url );
			exit;
		}
	}
	
	/**
	 * Show a notice on the admin after archiving or un-archiving an entry
	 *
	 * @return void
	 */
	function show_archive_notice() {
		$archived = isset($_GET['rsea_archived']) ? (int) $_GET['rsea_archived'] : null;
		$entry_id = isset($_GET['rsea_archived_id']) ? (int) $_GET['rsea_archived_id'] : null;
		if ( $archived === null || $entry_id === null ) return;
		
		$entry = GFAPI::get_entry( $entry_id );
		if ( ! $entry ) return;
		
		$entry_url = $this->get_entry_url( $entry_id );
		
		if ( $archived ) {
			$url = $this->get_archive_entry_url( $entry_id, false );
			$message = 'Entry <a href="'. esc_attr($entry_url) .'">#'. $entry_id .'</a> has been archived. <small><a href="'. esc_attr($url) .'">Undo</a></small>';
		}else{
			$url = $this->get_archive_entry_url( $entry_id, true );
			$message = 'Entry <a href="'. esc_attr($entry_url) .'">#'. $entry_id .'</a> is no longer archived. <small><a href="'. esc_attr($url) .'">Undo</a></small>';
		}
		
		GFCommon::add_message( $message );
	}
	
	/**
	 * Add a bulk action to mark multiple entries as archived / un-archived
	 *
	 * @param array  $actions    Bulk actions.
	 * @param int    $form_id    The ID of the current form.
	 *
	 * @return array
	 */
	public function add_bulk_actions( $actions, $form_id ) {
		
		$new_items = array(
			'archive' => 'Archive entries',
			'unarchive' => 'Unarchive entries',
		);
		
		// Insert actions below "Mark as Unread"
		$i = array_search( 'mark_unread', array_keys( $actions ) );
		
		if ( $i ) {
			$i += 1;
			$actions = array_slice( $actions, 0, $i, true )
				+ $new_items
				+ array_slice( $actions, $i, count( $actions ) - 1, true );
		}
		
		return $actions;
	}
	
	/**
	 * Handle the bulk action of archiving or un-archiving entries
	 *
	 * @param string $action Action being performed.
	 * @param array $entries The entry IDs the action is being applied to.
	 * @param int $form_id   The current form ID.
	 *
	 * @return void
	 */
	public function handle_bulk_action( $action, $entries, $form_id ) {
		// $action = 'archive';
		// $entries = array( "33494" );
		// $form_id = 6;
		if ( $action !== 'archive' && $action !== 'unarchive' ) return;
		
		$count = 0;
		
		foreach ( $entries as $entry_id ) {
			$is_archived = $this->is_archived( $entry_id );
			
			if ( $action === 'archive' ) {
				if ( ! $is_archived ) {
					$count++;
					$this->archive_entry( $entry_id );
				}
			}else{
				if ( $is_archived ) {
					$count++;
					$this->unarchive_entry( $entry_id );
				}
			}
		}
		
		if ( $count < 1 ) {
			$message_class = 'error';
			$message = __( 'No entries were archived.', 'rs-entry-archives' );
		}else if ( $action === 'archive' ) {
			$message_class = 'success';
			$message = sprintf( _n( '%d entry has been archived.', '%d entries have been archived.', $count, 'rs-entry-archives' ), $count );
		}else{
			$message_class = 'success';
			$message = sprintf( _n( '%d entry has been unarchived.', '%d entries have been unarchived.', $count, 'rs-entry-archives' ), $count );
		}
		
		echo '<div id="message" class="alert ' . $message_class . '"><p>' . $message . '</p></div>';
		
	}
	
	/**
	 * Make "Archived" a field available to export
	 *
	 * @param array $form
	 *
	 * @return array
	 */
	public function add_export_field( $form ) {
		$form['fields'][] = array(
			'label' => 'Entry Status',
			'id' => 'entry_status',
		);
		
		$form['fields'][] = array(
			'label' => 'Archive Status',
			'id' => 'is_archived',
		);
		
		return $form;
	}
	
	/**
	 * Add the "Archived" value to the exports
	 *
	 * @param string|array $value
	 * @param int          $form_id
	 * @param string       $field_id
	 * @param array        $entry
	 *
	 * @return string|array
	 */
	public function add_export_value( $value, $form_id, $field_id, $entry ) {
		if ( $field_id === 'is_archived' ) {
			$value = $this->is_archived( $entry['id'] ) ? 'Archived' : '';
		}
		
		if ( $field_id === 'entry_status' ) {
			$value = $entry['status'];
		}
		
		return $value;
	}
	
	/**
	 * Add a conditional logic field to export screen (and other areas)
	 *
	 * @param array $field_filters The form field, entry properties, and entry meta filter settings.
	 * @param array $form          The form object the filter settings have been prepared for.
	 *
	 * @return array
	 */
	public function add_conditional_logic_field( $field_filters, $form ) {
		$field_filters[] = array(
			'key'             => 'entry_status',
			'preventMultiple' => false,
			'text'            => esc_html__( 'Entry Status', 'rs-entry-archives' ),
			'operators'       => array( 'is' ),
			'values'          => array(
				array(
					'text' => esc_html__( 'Active (Default)' , 'rs-entry-archives' ),
					'value' => 'active'
				),
				array(
					'text' => esc_html__( 'Any Status' , 'rs-entry-archives' ),
					'value' => 'any'
				),
				array(
					'text' => esc_html__( 'Archived' , 'rs-entry-archives' ),
					'value' => 'archived'
				),
				array(
					'text' => esc_html__( 'Spam' , 'rs-entry-archives' ),
					'value' => 'spam'
				),
				array(
					'text' => esc_html__( 'Trash' , 'rs-entry-archives' ),
					'value' => 'trash'
				),
			),
		);
		
		$field_filters[] = array(
			'key'             => 'is_archived',
			'preventMultiple' => false,
			'text'            => esc_html__( 'Archive Status', 'rs-entry-archives' ),
			'operators'       => array( 'is' ),
			'values'          => array(
				array(
					'text' => esc_html__( 'Not Archived (Default)' , 'rs-entry-archives' ),
					'value' => ''
				),
				array(
					'text' => esc_html__( 'Archived' , 'rs-entry-archives' ),
					'value' => '1'
				),
				array(
					'text' => esc_html__( 'Any' , 'rs-entry-archives' ),
					'value' => '-1'
				),
			),
		);
		
		return $field_filters;
	}
	
	/**
	 * When performing an export, exclude archived entries by default unless conditional logic overrides it
	 *
	 * @see GFCommon::get_field_filters_from_post()
	 *
	 * @param array|null $form
	 *
	 * @return string|array
	 */
	public function get_entry_status_from_conditional_logic_post() {
		$filter_fields    = rgpost( 'f' ) ?: array();
		$filter_operators = rgpost( 'o' ) ?: array();
		$filter_values    = rgpost( 'v' ) ?: array();
		$filter_t         = rgpost( 't' ) ?: array();
		
		// Get the conditional logic for "Entry Status"
		$status_index = array_search( 'entry_status', $filter_fields );
		
		if ( $status_index !== false ) {
			$status = stripslashes( $filter_values[$status_index] );
			
			// Remove the conditional logic from POST because Gravity Forms will think its entry meta
//			unset( $filter_fields[$status_index] );
//			unset( $filter_operators[$status_index] );
//			unset( $filter_values[$status_index] );
//			unset( $filter_t[$status_index] );
			
			// Replace the conditional logic field to something that is always true
			$filter_fields[$status_index] = 'date_created';
			$filter_operators[$status_index] = '>';
			$filter_values[$status_index] = '1970-01-01';
			$filter_t[$status_index] = '';
			
			return $status;
		}
		
		// Get the conditional logic for "Archive Status"
		$filter_index = array_search( 'is_archived', $filter_fields );
		
		if ( $filter_index !== false ) {
			$status = stripslashes( $filter_values[$filter_index] );
			
			// Remove the conditional logic from POST because Gravity Forms will think its entry meta
			unset( $filter_fields[$filter_index] );
			unset( $filter_operators[$filter_index] );
			unset( $filter_values[$filter_index] );
			unset( $filter_t[$filter_index] );
			
			if ( $status === '-1' ) {
				return 'any';
			}else if ( $status === '1' ) {
				return 'archived';
			}
		}
		
		return false;
	}
	
	/**
	 * Apply conditional logic to the search criteria for exporting entries
	 *
	 * @param array $search_criteria The search criteria array being filtered.
	 * @param int $form_id           The current form ID.
	 *
	 * @return array
	 */
	public function apply_conditional_logic_status_for_exporting( $search_criteria, $form_id ) {
		if ( empty($search_criteria['field_filters']) ) return $search_criteria;
		
		// Our conditional logic fields are added as "field_filters" (meta fields)
		// We need to change them to filter by status instead.
		foreach( $search_criteria['field_filters'] as $i => $f ) {
			if ( $i === 'mode' ) continue;
			
			$key = $f['key'];
			$value = $f['value'];
			
			if ( $key === 'entry_status' ) {
				// Change the status being searched for
				$search_criteria['status'] = $value;
				
				// Remove the filter because this is not a meta field
				unset( $search_criteria['field_filters'][$i] );
			}
			
			if ( $key === 'is_archived' ) {
				// Change the status being searched for
				if ( $value === '1' ) {
					$search_criteria['status'] = 'archived';
				}else if ( $value === '-1' ) {
					$search_criteria['status'] = 'any';
				}else{
					$search_criteria['status'] = 'active';
				}
				
				// Remove the filter because this is not a meta field
				unset( $search_criteria['field_filters'][$i] );
			}
		}
		
		return $search_criteria;
	}
	
	
	/**
	 * Allow gravity forms to query the entry status "any", which is not supported by default
	 *
	 * @param array $sql An array with all the SQL fragments: select, from, join, where, order, paginate. {
	 *     @type string $select   "SELECT SQL_CALC_FOUND_ROWS DISTINCT `t1`.`id`"
	 *     @type string $from     "FROM `wp_gf_entry` AS `t1`"
	 *     @type string $join     "LEFT JOIN `wp_gf_entry_meta` AS `m2` ON (`m2`.`entry_id` = `t1`.`id` AND `m2`.`meta_key` = 'is_archived')"
	 *     @type string $where    "WHERE (`t1`.`form_id` IN (1) AND (`t1`.`status` = 'active' AND (`m2`.`meta_key` = 'is_archived' AND `m2`.`meta_value` = '1')))"
	 *     @type string $order    "ORDER BY `t1`.`id` DESC"
	 *     @type string $paginate "LIMIT 20"
	 * }
	 *
	 * @return array
	 */
	function pre_get_entries_any_status( $sql ) {
		$sql['where'] = str_replace(
			"`status` = 'any'",
			"`status` IN ( 'active', 'spam', 'trash', 'archived' )",
			$sql['where']
		);
		
		return $sql;
	}
	
	/**
	 * Add a custom column for archive status to the entry list screen
	 *
	 * @param $form
	 *
	 * @return array
	 */
	public function add_entry_status_column( $form ) {
		
		// Only add this field to the entry list screen, or the column select screen
		// The column select screen pops up in an iframe
		$is_entry_list_screen = RS_Entry_Archives()->Settings->is_entry_list_screen();
		$is_column_select_screen = rgget( 'gf_page') === 'select_columns';
		if ( ! $is_entry_list_screen && ! $is_column_select_screen ) return $form;
		
		// Add a very basic field similar to:
		/** @see GFSelectColumns::select_columns_page() */
		$form['fields'][] = array(
			'id' => 'entry_status',
			'label' => esc_html__( 'Entry Status', 'rs-entry-archives' ),
		);
		
		$form['fields'][] = array(
			'id' => 'is_archived',
			'label' => esc_html__( 'Archive Status', 'rs-entry-archives' ),
		);
		
		$form = GFFormsModel::convert_field_objects( $form );
		
		// Display the column value for archive status
		add_filter( 'gform_entries_column_filter', array( $this, 'display_entry_status_column_value' ), 10, 5 );
		
		return $form;
	}
	
	/**
	 * Display the column value for archive status
	 *
	 * @param $value
	 * @param $form_id
	 * @param $field_id
	 * @param $entry
	 * @param $query_string
	 *
	 * @return mixed|string|null
	 */
	public function display_entry_status_column_value( $value, $form_id, $field_id, $entry, $query_string ) {
		if ( $field_id == 'is_archived' ) {
			if ( $this->is_archived( $entry['id'] ) ) {
				return __( 'Archived', 'rs-entry-archives' );
			}else{
				return __( 'Not Archived', 'rs-entry-archives' );
			}
		}
		
		if ( $field_id == 'entry_status' ) {
			return $this->get_status_label( $entry['status'] );
		}
		
		return $value;
	}
	
	/**
	 * Convert a status key to a formatted label, e.g. "active" to "Active"
	 *
	 * @param $status
	 *
	 * @return string
	 */
	public function get_status_label( $status ) {
		switch( $status ) {
			case 'active':   return __( 'Active', 'rs-entry-archives' );
			case 'archived': return __( 'Archived', 'rs-entry-archives' );
			case 'spam':     return __( 'Spam', 'rs-entry-archives' );
			case 'trash':    return __( 'Trash', 'rs-entry-archives' );
		}
		
		// Convert "some-status" to "Some Status"
		$status = str_replace(array('-','_'), ' ', $status);
		$status = ucwords( $status );
		
		return $status;
	}
	
	/**
	 * Get the link to view/edit an entry
	 *
	 * @param int $entry_id
	 *
	 * @return false|string
	 */
	function get_entry_url( $entry_id ) {
		$entry = GFAPI::get_entry( $entry_id );
		if ( ! $entry ) return false;
		
		$form = GFAPI::get_form( $entry['form_id'] );
		if ( ! $form ) return false;
		
		// from GFCommon::replace_variables()
		// 1. changed to admin_url() instead of get_bloginfo( 'wpurl' )
		// 2. changed $lead to $entry
		$entry_url = admin_url( '/wp-admin/admin.php?page=gf_entries&view=entry&id=' . rgar( $form, 'id' ) . '&lid=' . rgar( $entry, 'id' ) );
		$entry_url = esc_url( apply_filters( 'gform_entry_detail_url', $entry_url, $form, $entry ) );
		
		return $entry_url;
	}
	
	/**
	 * Get the URL to archive or un-archive an entry
	 *
	 * @param int $entry_id
	 * @param bool $make_archived
	 * @param string|null $base_url
	 *
	 * @return string
	 */
	function get_archive_entry_url( $entry_id, $make_archived, $base_url = null ) {
		if ( ! $base_url ) {
			$base_url = remove_query_arg( array( 'rsea_archive', 'rsea_entry_id', 'rsea_archived', 'rsea_entry_id' ) );
		}
		
		$base_url = add_query_arg(array(
			'rsea_archive' => $make_archived ? 1 : 0,
			'rsea_entry_id' => $entry_id
		), $base_url);
		
		return $base_url;
	}
	
	/**
	 * Archive an entry
	 *
	 * @param $entry_id
	 *
	 * @return void
	 */
	function archive_entry( $entry_id ) {
		GFAPI::update_entry_property( $entry_id, 'status', 'archived' );
	}
	
	/**
	 * Un-archive an entry, making it active again
	 *
	 * @param $entry_id
	 * @param $status
	 *
	 * @return void
	 */
	function unarchive_entry( $entry_id, $status = 'active' ) {
		GFAPI::update_entry_property( $entry_id, 'status', $status );
	}
	
	/**
	 * @param int $entry_id
	 * @return bool
	 */
	function is_archived( $entry_id ) {
		$entry = GFAPI::get_entry( $entry_id );
		return rgar($entry, 'status') === 'archived';
	}
	
	
}

return new RS_Entry_Archives_Form();