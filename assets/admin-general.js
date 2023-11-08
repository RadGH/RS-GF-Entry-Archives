(function() {

	// Store everything in this "o" object
	const o = {};

	// Make it accessible for other scripts as a global variable
	window.rs_entry_archives = o;

	// Load settings from enqueue.php and clear the original settings from the global scope
	const s = window.rsea_settings || false;
	window.rsea_settings = null;

	// Store settings
	o.settings = {
		ajax_url: s.settings.ajax_url || '/wp-admin/admin-ajax.php',
		nonce:    s.settings.nonce    || false,
	};

	// Store screen info
	o.screen = {
		// action:   s.screen.action || false,
		// base:     s.screen.base || false,
		// id:       s.screen.id || false,
		// page:     s.screen.page || false,
		// view:     s.screen.view || false,

		form_id:  s.screen.form_id || false,
		entry_id: s.screen.entry_id || false,
		filter: s.screen.filter || false,

		is_gravity_forms_screen: s.screen.is_gravity_forms_screen || false,
		is_form_list_screen: s.screen.is_form_list_screen || false,
		is_form_edit_screen: s.screen.is_form_edit_screen || false,
		is_entry_list_screen: s.screen.is_entry_list_screen || false,
		is_entry_edit_screen: s.screen.is_entry_edit_screen || false
	};

	// Code to run after the script is loaded
	o.init = function() {

		if ( o.is_entry_list_screen() ) {
			o.setup_entry_list_screen();
		}
		
	};

	o.is_gravity_forms_screen = function() {
		return o.screen.is_gravity_forms_screen;
	};

	o.is_form_list_screen = function() {
		return o.screen.is_form_list_screen;
	};

	o.is_form_edit_screen = function() {
		return o.screen.is_form_edit_screen;
	};

	o.is_entry_list_screen = function() {
		return o.screen.is_entry_list_screen;
	};

	o.is_entry_edit_screen = function() {
		return o.screen.is_entry_edit_screen;
	};

	o.show_archived_entries = function() {
		return o.is_entry_list_screen() && ( o.screen.filter === 'archived' || o.screen.filter === 'trash' );
	};

	// Set up the entry list screen, where entries are displayed for a single form
	o.setup_entry_list_screen = function() {

		if ( o.show_archived_entries() ) {
			jQuery(document.body).addClass('rsea-show-archived-entries');
		}else{
			jQuery(document.body).addClass('rsea-hide-archived-entries');
		}

		const $list = jQuery('#the-list');

		// Move the archive / un-archive links to be between the "Mark Read/Unread" and "Spam" links
		$list.find('tr.entry_row').each(function() {
			const $row = jQuery(this);
			const $actions = $row.find('.row-actions').first();
			const $archive_link = $actions.find('.rsea-archive-link');
			const $end_link = $actions.find('.spam, .trash').first();

			if ( $end_link.length > 0 && $archive_link.length > 0 ) {
				// Move the archive link before the end link
				$archive_link.insertBefore( $end_link );
			}
		});

		// Clicking on a link to archive or un-archive an entry performs the action with ajax.
		$list.on('click', 'a.rsea-archive-entry, a.rsea-unarchive-entry', function(e) {
			let $link = jQuery(this);
			let $other_link = $link.siblings('a');
			let $row = $link.closest('tr.entry_row');

			let entry_id = $link.attr('data-entry-id');
			let is_archiving = $link.hasClass('rsea-archive-entry');

			// Use a class on the row so that only one query can be performed at a time
			if ( $row.hasClass('loading') ) {
				return false;
			}else{
				$row.addClass('loading');
			}

			// Switch the links around
			$link.css('display', 'none');
			$other_link.css('display', '');

			// Do an ajax request for that URL. If successful, hide the link. If an error occurs, show a popup
			jQuery.ajax({
				url: o.settings.ajax_url,
				method: 'GET',
				dataType: 'json',
				data: {
					action: 'rsea_archive',
					rsea_archive: is_archiving ? 1 : 0,
					rsea_entry_id: entry_id,
				},

				success: function(data) {
					$row.removeClass('loading');

					$row
						.toggleClass('rsea-archived', is_archiving)
						.toggleClass('rsea-not-archived', !is_archiving);
				},

				error: function(jqXHR, textStatus, errorThrown) {
					$row.removeClass('loading');

					// Change the links back
					$link.css('display', '');
					$other_link.css('display', 'none');

					alert('Failed to toggle archive state for entry #' + entry_id + "\n\n" + 'See console for details');
					console.log(errorThrown);
				}
			});

			return false;
		});

	};

	// Run the initial code on document ready
	jQuery(document).ready( o.init );

})();