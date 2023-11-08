<?php

class RS_Entry_Archives_Form {
	
	function __construct() {
		
		// Add a filter to view archived entries to the entries screen
		add_filter( 'gform_filter_links_entry_list', array( $this, 'update_filter_links' ), 30, 3 );
		
		// When filtering by filter=archive, show archived entries only in the entry screen
		// (not needed, see field_filters in the filter link)
		
		// Add a link to archive an entry next to the spam and trash links
		add_action( 'gform_entries_first_column_actions', array( $this, 'add_archive_link' ), 10, 5 );
		
		// Handle the action of archiving or un-archiving an entry
		add_action( 'admin_init', array( $this, 'handle_archive_action' ) );
		add_action( 'wp_ajax_rsea_archive', array( $this, 'handle_archive_action' ) );
		
		// Show a notice on the admin after archiving or un-archiving an entry
		add_action( 'admin_notices', array( $this, 'show_archive_notice' ) );
		
		// Hooks that apply on the entry list screen which effect the queried entries
		$this->add_archived_view_hooks();
		
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
		
		// When performing an export, exclude archived entries by default unless conditional logic overrides it
		add_filter( 'wp_ajax_gf_process_export', array( $this, 'apply_conditional_logic_for_exporting' ), 5 );
		
		// Add a custom column for archive status to the entry list screen
	    add_filter( 'gform_form_post_get_meta', array( $this, 'add_archive_status_column' ), 10, 1 );
		
	}
	
	/**
	 * Count the number of entries for each type of filter, accounting for archived entries based on filter.
	 * If the query takes more than 1 second to generate, results are cached for 10 minutes.
	 *
	 * @param int $form_id
	 *
	 * @return array {
	 *     @type int $total
	 *     @type int $unread
	 *     @type int $starred
	 *     @type int $spam
	 *     @type int $trash
	 *     @type int $archived
	 * }
	 */
	public function get_filter_entry_counts( $form_id ) {
		global $wpdb;
		
		$cache_key = 'rs_unarchived_form_counts_' . $form_id;
		
		$results = get_transient( $cache_key );
		if ( ! empty( $results ) ) return $results;
		
		/**
		 * @see GFFormsModel::get_entry_table_name()
		 * @see GFFormsModel::get_entry_meta_table_name()
		 */
		$entry_table_name = $wpdb->prefix . 'gf_entry';
		$entry_detail_table_name = $wpdb->prefix . 'gf_entry_meta';
		
		$select = "count(DISTINCT(e.id)) FROM $entry_table_name e ";
		$select.= "LEFT JOIN $entry_detail_table_name m ON e.id = m.entry_id AND m.meta_key = 'is_archived'";
		
		$ignore_archived = "e.form_id = $form_id";
		$not_archived = "e.form_id = $form_id AND (m.meta_key IS NULL OR m.meta_value != '1')";
		$is_archived = "e.form_id = $form_id AND m.meta_value = '1'";
		
		$sql = <<<SQL
SELECT
       (SELECT {$select} WHERE {$not_archived}    AND e.status = 'active')                      as total,
       (SELECT {$select} WHERE {$not_archived}    AND e.status = 'active' AND e.is_read = 0 )   as unread,
       (SELECT {$select} WHERE {$not_archived}    AND e.status = 'active' AND e.is_starred = 1) as starred,
       (SELECT {$select} WHERE {$ignore_archived} AND e.status = 'spam')                        as spam,
       (SELECT {$select} WHERE {$ignore_archived} AND e.status = 'trash')                       as trash,
       (SELECT {$select} WHERE {$is_archived}     AND e.status = 'active')                      as archived
SQL;
		
		$wpdb->timer_start();
		
		$results = $wpdb->get_results( $sql, ARRAY_A );
		
		$time_total = $wpdb->timer_stop();
		
		// If the query took longer than 1 second to generate, cache it for 10 minutes
		if ( $time_total > 1 ) {
			set_transient(  $cache_key, $results[0], 10 * MINUTE_IN_SECONDS );
		}
		
		return $results[0];
	}
	
	public function add_archived_view_hooks() {
		// When entries are queried on the entry list page, filter out any archived entries, or view only archived entries
		add_filter( 'gform_gf_query_sql', array( $this, 'modify_entry_search_sql' ), 20, 2 );
	}
	
	public function remove_archived_view_hooks() {
		remove_filter( 'gform_gf_query_sql', array( $this, 'modify_entry_search_sql' ), 20 );
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
	function modify_entry_search_sql( $sql ) {
		if ( ! RS_Entry_Archives()->Settings->is_entry_list_screen() ) return $sql;

		$filter = isset($_GET['filter']) ? stripslashes($_GET['filter']) :'';
		
		// When filtering by "archived", gravity forms already checked for the meta key "is_archived" = 1.
		// For other filters, except trash, hide archived entries.
		if ( $filter == 'archived' || $filter == 'trash' ) return $sql;
		
		global $wpdb;
		
		$table = $wpdb->prefix . 'gf_entry_meta';
		$column = 'rs_archived';
		
		$sql['join'] .= ' LEFT JOIN `'. $table .'` AS `'. $column .'` ON (`'. $column .'`.`entry_id` = `t1`.`id` AND `'. $column .'`.`meta_key` = "is_archived")';
		$sql['where'] .= ' AND `'. $column .'`.meta_value IS NULL';
		
		return $sql;
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
	function update_filter_links( $filter_links, $form, $include_counts ) {
		
		// Add a filter for archived entries
		$filter_links[] = array(
			'id' => 'archived',
			'status' => 'active',
			'field_filters' => array(
				array( 'key' => 'is_archived', 'value' => 1 ),
			),
			'count' => 0, // calculated below. previously: $this->get_archived_entry_count( $form['id'] )
			'label'   => esc_html__( 'Archived', 'rs-entry-archives' ),
		);
		
		if ( $include_counts ) {
			
			// Recalculate entry counts, accounting for archived entries
			$counts = $this->get_filter_entry_counts( $form['id'] );
			
			// Replace the counts for each filter link
			foreach( $filter_links as &$f ) switch( $f['id'] ) {
				case 'all':      $f['count'] = $counts['total']; break;
				case 'unread':   $f['count'] = $counts['unread']; break;
				case 'star':     $f['count'] = $counts['starred']; break;
				case 'spam':     $f['count'] = $counts['spam']; break;
				case 'trash':    $f['count'] = $counts['trash']; break;
				case 'archived': $f['count'] = $counts['archived']; break;
			}
			
		}
		
		return $filter_links;
	}
	
	/**
	 * Count the number of entries that have been archived for a given form ID
	 *
	 * @param $form_id
	 *
	 * @return int
	 */
	/*
	function get_archived_entry_count( $form_id ) {
		$search_criteria = array(
			'field_filters' => array(
				array( 'key' => 'is_archived', 'value' => 1 ),
			),
		);
		
		// Temporarily unhook the filters that exclude archived entries from the query
		$this->remove_archived_view_hooks();
		
		// Count entries that are archived.
		$count = GFAPI::count_entries( $form_id, $search_criteria );
		
		// Re-add hooks
		$this->add_archived_view_hooks();
		
		return $count;
	}
	*/
	
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
			'label' => 'Is Archived',
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
		if ( $field_id !== 'is_archived' ) return $value;
		
		$value = $this->is_archived( $entry['id'] ) ? 'Archived' : '';
		
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
	 * @return void
	 */
	public function apply_conditional_logic_for_exporting( $form = null ) {
		$filter_fields    = rgpost( 'f' ) ?: array();
		$filter_operators = rgpost( 'o' ) ?: array();
		$filter_values    = rgpost( 'v' ) ?: array();
		$filter_t         = rgpost( 't' ) ?: array();
		
		// IDK what the "t" field is for, but it should align with the others.
		
		$index = array_search( 'is_archived', $filter_fields );
		
		if ( $index === false ) {
			// Did not filter by archive status with conditional logic.
			// Default to hide archived entries.
			$filter_fields[] = 'is_archived';
			$filter_operators[] = 'is';
			$filter_values[] = '';
			$filter_t[] = '';
		}else if ( $filter_values[$index] === '-1' ) {
			// Conditional logic selected "Any" for archive status.
			// In this case, remove the filter and do not apply the default value
			unset( $filter_fields[$index] );
			unset( $filter_operators[$index] );
			unset( $filter_values[$index] );
			unset( $filter_t[$index] );
		}
		
		// Gravity Forms does not let us filter these directly.
		// Instead, modify the global variables before they are used.
		$_POST['f'] = $filter_fields;
		$_POST['o'] = $filter_operators;
		$_POST['v'] = $filter_values;
		$_POST['t'] = $filter_t;
		
	}
	
	/**
	 * Add a custom column for archive status to the entry list screen
	 *
	 * @param $form
	 *
	 * @return array
	 */
	public function add_archive_status_column( $form ) {
		
		// Only add this field to the entry list screen, or the column select screen
		// The column select screen pops up in an iframe
		$is_entry_list_screen = RS_Entry_Archives()->Settings->is_entry_list_screen();
		$is_column_select_screen = rgget( 'gf_page') === 'select_columns';
		if ( ! $is_entry_list_screen && ! $is_column_select_screen ) return $form;
		
		// Add a very basic field similar to:
		/** @see GFSelectColumns::select_columns_page() */
		$form['fields'][] = array(
			'id' => 'is_archived',
			'label' => esc_html__( 'Archive Status', 'rs-entry-archives' ),
		);
		
		$form = GFFormsModel::convert_field_objects( $form );
		
		// Display the column value for archive status
		add_filter( 'gform_entries_column_filter', array( $this, 'display_archive_status_column_value' ), 10, 5 );
		
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
	public function display_archive_status_column_value( $value, $form_id, $field_id, $entry, $query_string ) {
		if ( $field_id !== 'is_archived' ) return $value;
		
		if ( $this->is_archived( $entry['id'] ) ) {
			return __( 'Archived', 'rs-entry-archives' );
		}else{
			return __( 'Not Archived', 'rs-entry-archives' );
		}
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
	
	function archive_entry( $entry_id ) {
		gform_update_meta( $entry_id, 'is_archived', 1 );
		gform_update_meta( $entry_id, 'archive_date', date( 'Y-m-d H:i:s' ) );
	}
	
	function unarchive_entry( $entry_id ) {
		gform_delete_meta( $entry_id, 'is_archived' );
		gform_delete_meta( $entry_id, 'archive_date' );
	}
	
	/**
	 * @param int $entry_id
	 * @return bool
	 */
	function is_archived( $entry_id ) {
		return 1 === (int) gform_get_meta( $entry_id, 'is_archived' );
	}
	
	
}

return new RS_Entry_Archives_Form();