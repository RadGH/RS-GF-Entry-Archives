(function() {

	// Store everything in this "o" object
	const o = {};

	// Load settings from enqueue.php and clear the original settings from the global scope
	const s = window.rsea_settings || false;
	window.rsea_settings = null;

	// Store settings as individual variables
	const ajax_url = s.settings.ajax_url || '/wp-admin/admin-ajax.php';
	const nonce = s.settings.nonce || false;

	// Code to run after the script is loaded
	o.init = function() {

		// Move the archive links to be after "Mark read" link on entry list page
		o.reorder_archive_links();

		// Handle links to archive or un-archive entries
		o.setup_ajax_archive_links();

	};

	// Move the archive links to be after "Mark read" link on entry list page
	o.reorder_archive_links = function() {

		// Get the entry list table
		const $list = jQuery('#the-list');
		if ( $list.length < 1 ) return;

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

		// Get filter mode from url ?filter=archived
		const url = new URL(window.location.href);
		const filter = url.searchParams.get('filter');

		// Add a body class to indicate if we're showing archived entries or not
		if ( filter === 'archived' ) {
			jQuery(document.body).addClass('rsea-show-archived-entries');
		}else{
			jQuery(document.body).addClass('rsea-hide-archived-entries');
		}

	};

	// Handle links to archive or un-archive entries
	o.setup_ajax_archive_links = function() {

		// Clicking on a link to archive or un-archive an entry performs the action with ajax.
		jQuery(document.body).on('click', '.rsea-archive-entry, .rsea-unarchive-entry', function(e) {
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
				url: ajax_url,
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

	}

	// Run the initial code on document ready
	jQuery(document).ready( o.init );

})();