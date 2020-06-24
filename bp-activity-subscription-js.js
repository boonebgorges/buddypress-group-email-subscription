jQuery(document).ready( function($) {
	// topic follow/mute
	$( document ).on("click", '.ass-topic-subscribe > a', function() {
		it = $(this);
		var theid = $(this).attr('id');
		var stheid = theid.split('-');

		//$('.pagination .ajax-loader').toggle();

		var data = {
			action: 'ass_ajax',
			a: stheid[0],
			topic_id: stheid[1],
			group_id: stheid[2]
			//,_ajax_nonce: stheid[2]
		};

		// TODO: add ajax code to give status feedback that will fade out

		$.post( ajaxurl, data, function( response ) {
			if ( response == 'follow' ) {
				var m = bp_ass.mute;
				theid = theid.replace( 'follow', 'mute' );
			} else if ( response == 'mute' ) {
				var m = bp_ass.follow;
				theid = theid.replace( 'mute', 'follow' );
			} else {
				var m = bp_ass.error;
			}

			$(it).html(m);
			$(it).attr('id', theid);
			$(it).attr('title', '');

			//$('.pagination .ajax-loader').toggle();

		});
	});


	// group subscription options
	$( document ).on("click", '.group-sub', function() {
		it = $(this);
		var theid = $(this).attr('id');
		var stheid = theid.split('-');
		group_id = stheid[1];
		current = $( '#gsubstat-'+group_id ).html();
		$('#gsubajaxload-'+group_id).css('display','inline-block');

		newBtn = $('button.js-tooltip[data-tooltip-content-id="ges-panel-' + group_id + '"]');
		newBtn.hide();

		var data = {
			action: 'ass_group_ajax',
			a: stheid[0],
			group_id: stheid[1]
			//,_ajax_nonce: stheid[2]
		};

		$( '#js-tooltip-close' ).click();

		$.post( ajaxurl, data, function( response ) {
			status = $(it).html();
			if ( !current || current == 'No Email' ) {
				$( '#gsublink-'+group_id ).html('change');
				//status = status + ' / ';
			}
			$( '#gsubstat-'+group_id ).html( status ); //add .animate({opacity: 1.0}, 2000) to slow things down for testing
			$( '#gsubstat-'+group_id ).addClass( 'gemail_icon' );
			$( '#gsubopt-'+group_id ).slideToggle('fast');
			$( '#gsubajaxload-'+group_id ).hide();
			newBtn.show();
		});

	});

	$( document ).on("click", '.group-subscription-options-link', function() {
		stheid = $(this).attr('id').split('-');
		group_id = stheid[1];
		$( '#gsubopt-'+group_id ).slideToggle('fast');
	});

	$( document ).on("click", '.group-subscription-close', function() {
		stheid = $(this).attr('id').split('-');
		group_id = stheid[1];
		$( '#gsubopt-'+group_id ).slideToggle('fast');
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
