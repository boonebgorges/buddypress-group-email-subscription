jQuery(document).ready( function() {
	var j = jQuery;
	
	// topic follow/mute
	j(".ass-topic-subscribe > a").click( function() {
		it = j(this);
		var theid = j(this).attr('id');
		var stheid = theid.split('-');
		
		//j('.pagination .ajax-loader').toggle();		
		var data = {
			action: 'ass_ajax',
			a: stheid[0],
			topic_id: stheid[1],
			//_ajax_nonce: stheid[2],
		};
				
		// TODO: add ajax code to give status feedback that will fade out
				
		j.post( ajaxurl, data, function( response ) {
			if ( response == 'follow' ) {
				var m = 'Mute';
				theid = theid.replace( 'follow', 'mute' );
			} else if ( response == 'mute' ) {
				var m = 'Follow';
				theid = theid.replace( 'mute', 'follow' );
			} else {
				var m = 'Error';
			}
					
			j(it).html(m);
			j(it).attr('id', theid);
			j(it).attr('title', '');
			
			//j('.pagination .ajax-loader').toggle();
			
		});
	});


	// group subscription options
	j(".group-subscription").click( function() {
		it = j(this);
		var theid = j(this).attr('id');
		var stheid = theid.split('-');
		group_id = stheid[1];
		current = j( '#gsubstat-'+group_id ).html();
		if (current=='Unsubscribed')
			current ='';
		
		var data = {
			action: 'ass_group_ajax',
			a: stheid[0],
			group_id: stheid[1],
			//_ajax_nonce: stheid[2],
		};
		j.post( ajaxurl, data, function( response ) {
		
			if ( response == 'gsubscribe' && !current ) {
				m = 'unsubscribe';
				theid = theid.replace( 'gsubscribe', 'gunsubscribe' );
				stat = 'Subscribed';
				j( '#gsublink-'+group_id ).html('Options &#187;');
			} else if ( response == 'gsubscribe' && current ) {
				m = 'super-subscribe';
				theid = theid.replace( 'gsubscribe', 'gsupersubscribe' );
				stat = 'Subscribed';
			} else if ( response == 'gsupersubscribe' && !current ) {
				m = 'unsubscribe';
				theid = theid.replace( 'gsupersubscribe', 'gunsubscribe' );
				stat = 'Super-subscribed';
				j( '#gsublink-'+group_id ).html('Options &#187;');
			} else if ( response == 'gsupersubscribe' && current ) {
				m = 'subscribe';
				theid = theid.replace( 'gsupersubscribe', 'gsubscribe' );
				stat = 'Super-subscribed';
			} else if ( response == 'gunsubscribe' && current == 'Super-subscribed' ) {
				m = 'super-subscribe';
				theid = theid.replace( 'gunsubscribe', 'gsupersubscribe' );
				stat = 'Unsubscribed';
			} else if ( response == 'gunsubscribe' && current == 'Subscribed' ) {
				m = 'subscribe';
				theid = theid.replace( 'gunsubscribe', 'gsubscribe' );
				stat = 'Unsubscribed';
			} else {
				m = 'error';
			}
					
			j(it).html(m);
			j(it).attr('id', theid);	
			j( '#gsubstat-'+group_id ).html(stat);
			j( '#gsubopt-'+group_id ).slideToggle('fast');
		});		
		
	});
		
	j('.group-subscription-options-link').click( function() {
		stheid = j(this).attr('id').split('-');
		group_id = stheid[1];
		j( '#gsubopt-'+group_id ).slideToggle('fast');
	});
	
	j('.group-subscription-close').click( function() {
		stheid = j(this).attr('id').split('-');
		group_id = stheid[1];
		j( '#gsubopt-'+group_id ).slideToggle('fast');
	});
	
	//j('.ass-settings-advanced-link').click( function() {
	//	j( '.ass-settings-advanced' ).slideToggle('fast');
	//});
	
});