jQuery(document).ready( function($) {
	var groupRow = $( '#groups-list li' );

	if ( groupRow.find( 'div.ges-panel' ).length ) {
		repositionGESPanel();
		$( window ).on('resize', function() {
			repositionGESPanel();
		});
	}

	// If positioned right, ensure panel is aligned right as well.
	function repositionGESPanel() {
		if ( 'right' === groupRow.find('div.action').css('float') || 'right' === groupRow.find('div.action, div.item-actions').css('text-align') ) {
			groupRow.find('.group-subscription-div').addClass( 'ges-panel-right' );
		} else {
			groupRow.find('.group-subscription-div').removeClass( 'ges-panel-right' );
		}
	}

	// topic follow/mute
	$( document ).on("click", '.ass-topic-subscribe > a', function() {
		var it = $(this),
			theid = $(this).attr('id'),
			stheid = theid.split('-'),
			data;

		//$('.pagination .ajax-loader').toggle();

		data = {
			action: 'ass_ajax',
			a: stheid[0],
			topic_id: stheid[1],
			group_id: stheid[2]
			//,_ajax_nonce: stheid[2]
		};

		// TODO: add ajax code to give status feedback that will fade out

		$.post( ajaxurl, data, function( response ) {
			var m, theid;

			if ( response == 'follow' ) {
				m = bp_ass.mute;
				theid = theid.replace( 'follow', 'mute' );
			} else if ( response == 'mute' ) {
				m = bp_ass.follow;
				theid = theid.replace( 'mute', 'follow' );
			} else {
				m = bp_ass.error;
			}

			$(it).html(m);
			$(it).attr('id', theid);
			$(it).attr('title', '');

			//$('.pagination .ajax-loader').toggle();

		});
	});


	// group subscription options
	$( document ).on("click", '.group-sub', function(e) {
		e.preventDefault();

		var it = $(this),
			theid = $(this).attr('id'),
			stheid = theid.split('-'),
			group_id = stheid[1],
			current = $( '#gsubstat-' + group_id ).html(),
			newBtn = $('button.js-tooltip[data-tooltip-content-id="ges-panel-' + group_id + '"]'),
			data;

		$('#gsubajaxload-' + group_id).css('display','inline-block');
		newBtn.hide();

		data = {
			action: 'ass_group_ajax',
			a: stheid[0],
			group_id: stheid[1],
			_ajax_nonce: it.parent().data( 'security' )
		};

		$( '#js-tooltip-close' ).click();

		$.post( ajaxurl, data, function( response ) {
			var status = $(it).html();
			if ( !current || current == 'No Email' ) {
				$( '#gsublink-' + group_id ).html('change');
				//status = status + ' / ';
			}
			$( '#gsubstat-' + group_id ).html( status ); //add .animate({opacity: 1.0}, 2000) to slow things down for testing
			$( '#gsubstat-' + group_id ).addClass( 'gemail_icon' );
			$( '#gsubopt-' + group_id ).slideToggle('fast');
			$( '#gsubajaxload-' + group_id ).hide();
			newBtn.show();
		});

	});

	$( document ).on("click", '.group-subscription-options-link', function() {
		var stheid = $(this).attr('id').split('-'),
			group_id = stheid[1];

		$( '#gsubopt-' + group_id ).slideToggle('fast');
	});

	$( document ).on("click", '.group-subscription-close', function() {
		var stheid = $(this).attr('id').split('-'),
			group_id = stheid[1];

		$( '#gsubopt-' + group_id ).slideToggle('fast');
	});

	//$( document ).on("click", '.ass-settings-advanced-link', function() {
	//	$( '.ass-settings-advanced' ).slideToggle('fast');
	//});

	// Toggle welcome email fields on group email options page
	$( document ).on("change", '#ass-welcome-email-enabled', function() {
		if ( $(this).prop('checked') ) {
			$('.ass-welcome-email-field').show();
		} else {
			$('.ass-welcome-email-field').hide();
		}
	});
});
