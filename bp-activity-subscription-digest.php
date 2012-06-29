<?php

// for testing only
//if you need to add this at the TOP of your wp-config.php file. (Here are the timezones http://us3.php.net/manual/en/timezones.php)
//date_default_timezone_set('Asia/Tokyo');
//date_default_timezone_set('America/New_York');


/* This function was used for debugging the digest scheduling features */
function ass_digest_schedule_print() {
	print "<br />";
	print "<br />";

//	ass_digest_fire( 'dig' );
	$crons = _get_cron_array();
	echo "<div style='background: #fff;'>";
	$sched = wp_next_scheduled( 'ass_digest_event' );
	echo "Scheduled: " . date( 'h:i', $sched );
	$until = ( (int)$sched - time() ) / ( 60 * 60 );
	echo " Until: " . $until . " hours";
	echo "</div>";
}
//add_action( 'wp_head', 'ass_digest_schedule_print' );


/* Digest-specific functions */

function ass_digest_fire( $type ) {
	global $bp, $wpdb, $groups_template, $ass_email_css, $current_user;

	if ( !is_string($type) )
		$type = 'sum';

	// HTML emails only work with inline CSS styles. Here we setup the styles to be used in various functions below.
	$ass_email_css['wrapper'] = 		'style="color:#333;clear:both;'; // use this to style the body
	$ass_email_css['title'] = 			'style="font-size:130%;"';
	$ass_email_css['summary']	      = '';
	$ass_email_css['summary_ul'] = 		'style="padding:12px 0 5px; list-style-type:circle; list-style-position:inside;"';
	//$ass_email_css['summary'] = 		'style="display:list-item;"';
	$ass_email_css['follow_topic'] = 	'style="padding:15px 0 0; color: #888;clear:both;"';
	$ass_email_css['group_title'] = 	'style="font-size:120%; background-color:#F5F5F5; padding:3px; margin:20px 0 0; border-top: 1px #eee solid;"';
	$ass_email_css['change_email'] = 	'style="font-size:12px; margin-left:10px; color:#888;"';
	$ass_email_css['item_div'] = 		'style="padding: 10px; border-top: 1px #eee solid;"';
	$ass_email_css['item_action'] = 	'style="color:#888;"';
	$ass_email_css['item_date'] = 		'style="font-size:85%; color:#bbb; margin-left:8px;"';
	$ass_email_css['item_content'] = 	'style="color:#333;"';
	$ass_email_css['item_weekly'] = 	'style="color:#888; padding:4px 10px 0"'; // used in weekly in place of other item_ above
	$ass_email_css['footer'] = 			'class="ass-footer" style="margin:25px 0 0; padding-top:5px; border-top:1px #bbb solid;"';

	// Allow plugins to filter the CSS
	$ass_email_css = apply_filters( 'ass_email_css', $ass_email_css );

	if ( $type == 'dig' )
		$title = sprintf( __( 'Your daily digest of group activity', 'bp-ass' ) );
	else
		$title = sprintf( __( 'Your weekly summary of group topics', 'bp-ass' ) );

	$title = apply_filters( 'ass_digest_title', $title, $type );

	$blogname = get_blog_option( BP_ROOT_BLOG, 'blogname' );
	$subject = apply_filters( 'ass_digest_subject', "$title [$blogname]", $blogname, $title, $type );

	$footer = "\n\n<div {$ass_email_css['footer']}>";
	$footer .= sprintf( __( "You have received this message because you are subscribed to receive a digest of activity in some of your groups on %s.", 'bp-ass' ), $blogname );
	$footer = apply_filters( 'ass_digest_footer', $footer, $type );

	// get list of all groups so we can look them up quickly in the foreach loop below
	$all_groups = $wpdb->get_results( $wpdb->prepare( "SELECT id, name, slug FROM {$bp->groups->table_name}" ) );
	foreach ( $all_groups as $group ) {
		$group_name = ass_digest_filter( $group->name );
		$groups_info[ $group->id ] = array( 'name'=>$group_name, 'slug'=>$group->slug );
	}

	$user_subscriptions = $wpdb->get_results( $wpdb->prepare( "SELECT user_id, meta_value FROM $wpdb->usermeta WHERE meta_key = 'ass_digest_items' AND meta_value != ''" ) );

	// Do all activity lookups in one single query, and cache the results
	// Todo: in_array() is slow; isset( array_flip( $array[$id] ) ) is better
	// but it will take a flip every time
	$all_activity_items = array();
	foreach( (array)$user_subscriptions as $us ) {
		$subs = maybe_unserialize( $us->meta_value );
		foreach( (array)$subs as $digest_type => $group_subs ) {
			foreach( (array)$group_subs as $group_id => $sub_ids ) {
				foreach( (array)$sub_ids as $sid ) {
					if ( !in_array( $sid, $all_activity_items ) ) {
						$all_activity_items[] = $sid;
					}
				}
			}
		}
	}

	$items = bp_activity_get_specific( array(
		'sort' 		=> 'ASC',
		'activity_ids' 	=> $all_activity_items,
		'show_hidden' 	=> true
	) );

	foreach( (array)$items['activities'] as $activity ) {
		$key = 'bp_activity_' . $activity->id;
		if ( !wp_cache_get( $key, 'bp' ) ) {
			wp_cache_set( $key, $activity, 'bp', 60*60 );
		}
	}

	foreach ( (array)$user_subscriptions as $user ) {
		$user_id = $user->user_id;

		$group_activity_ids_array = unserialize( $user->meta_value );
		$summary = $activity_message = '';

		// We only want the weekly or daily ones
		if ( empty( $group_activity_ids_array[$type] ) || !$group_activity_ids = (array)$group_activity_ids_array[$type] )
			continue;

		// Get the details for the user
		if ( !$userdata = bp_core_get_core_userdata( $user_id ) )
			continue;

		if ( !$to = $userdata->user_email )
			continue;

		$userdomain = bp_core_get_user_domain( $user_id );

		// filter the list - can be used to sort the groups
		$group_activity_ids = apply_filters( 'ass_digest_group_activity_ids', @$group_activity_ids );

		$header = "<div {$ass_email_css['title']}>$title " . __('at', 'bp-ass')." <a href='{$bp->root_domain}'>$blogname</a></div>\n\n";
		$message = apply_filters( 'ass_digest_header', $header, $title, $ass_email_css['title'] );

		// loop through each group for this user
		foreach ( $group_activity_ids as $group_id => $activity_ids ) {
			$group_name = $groups_info[ $group_id ][ 'name' ];
			$group_slug = $groups_info[ $group_id ][ 'slug' ];
			if ( 'dig' == $type ) // might be nice here to link to anchor tags in the message
				$summary .= apply_filters( 'ass_digest_summary', "<li {$ass_email_css['summary']}><a href='#{$group_slug}'>$group_name</a> " . sprintf( __( '(%s items)', 'bp-ass' ), count( $activity_ids ) ) ."</li>\n", $ass_email_css['summary'], $group_slug, $group_name, $activity_ids );

			$activity_message .= ass_digest_format_item_group( $group_id, $activity_ids, $type, $group_name, $group_slug, $user_id );
			unset( $group_activity_ids[ $group_id ] );
		}

		// reset the user's sub array removing those sent sent
		$group_activity_ids_array[$type] = $group_activity_ids;

		// show group summary for digest, and follow help text for weekly summary
		if ( 'dig' == $type )
			$message .= apply_filters( 'ass_digest_summary_full', "\n<ul {$ass_email_css['summary_ul']}>" . __( 'Group Summary', 'bp-ass') . ":\n" . $summary . "</ul>\n", $ass_email_css['summary_ul'], $summary );

		$message .= $activity_message; // the meat of the message which we generated above goes here

		if ( 'sum' == $type )
			$message .= apply_filters( 'ass_summary_follow_topic', "<div {$ass_email_css['follow_topic']}>" . __( "How to follow a topic: to get email updates for a specific topic, click the topic title - then on the webpage click the <i>Follow this topic</i> button. (If you don't see the button you need to login first.)", 'bp-ass' ) . "</div>\n", $ass_email_css['follow_topic'] );

		$message .= $footer;

		$unsubscribe_message = "\n\n<br><br>" . sprintf( __( "To disable these notifications per group please login and go to: %s where you can change your email settings for each group.", 'bp-ass' ), "<a href=\"{$userdomain}{$bp->groups->slug}/\">" . __( 'My Groups', 'bp-ass' ) . "</a>" );

		if ( get_option( 'ass-global-unsubscribe-link' ) == 'yes' ) {
			$unsubscribe_link = "$userdomain?bpass-action=unsubscribe&access_key=" . md5( $user_id . 'unsubscribe' . wp_salt() );
			$unsubscribe_message .= "\n\n<br><br><a href=\"$unsubscribe_link\">" . __( 'Disable these notifications for all my groups at once.', 'bp_ass' ) . '</a>';
		}

		$message .= apply_filters( 'ass_digest_disable_notifications', $unsubscribe_message, $userdomain . $bp->groups->slug );

		$message .= "</div>";

		$message_plaintext = ass_convert_html_to_plaintext( $message );

		if ( isset( $_GET['sum'] ) ) {
			// test mode run from the browser, dont send the emails, just show them on screen using domain.com?sum=1
			echo '<div style="background-color:white; width:75%;padding:20px 10px;">';
			echo '<p>======================== to: <b>'.$to.'</b> ========================</p>';
			echo $message;
			//echo '<br>PLAIN TEXT PART:<br><pre>'; echo $message_plaintext ; echo '</pre>';
			echo '</div>';
		} else {
			// send out the email
			ass_send_multipart_email( $to, $subject, $message_plaintext, $message );
			// update the subscriber's digest list
			update_user_meta( $user_id, 'ass_digest_items', $group_activity_ids_array );

		}

		unset( $message, $message_plaintext, $message, $to, $userdata, $userdomain, $activity_message, $summary, $group_activity_ids_array, $group_activity_ids );
	}
}


// these functions are hooked in via the cron
function ass_daily_digest_fire() {
	ass_digest_fire( 'dig' );
}
add_action( 'ass_digest_event', 'ass_daily_digest_fire' );

function ass_weekly_digest_fire() {
	ass_digest_fire( 'sum' );
}
add_action( 'ass_digest_event_weekly', 'ass_weekly_digest_fire' );

// Use these two lines for testing the digest firing in real-time
//add_action( 'bp_after_container', 'ass_daily_digest_fire' ); // for testing only
//add_action( 'bp_after_container', 'ass_weekly_digest_fire' ); // for testing only



// for testing the digest firing in real-time, add /?sum=1 to the url
function ass_digest_fire_test() {
	if ( isset( $_GET['sum'] ) && is_super_admin() ){
		echo "<h2>".__('DAILY DIGEST:','bp-ass')."</h2>";
		ass_digest_fire( 'dig' );
		echo "<h2 style='margin-top:150px'>".__('WEEKLY DIGEST:','bp-ass')."</h2>";
		ass_digest_fire( 'sum' );

		//global $wpdb;
		//echo '<pre>';print_r( $wpdb->queries );
		die();
	}

}
add_action( 'bp_actions', 'ass_digest_fire_test' );




/**
 * Displays the introduction for the group and loops through each item
 *
 * I've chosen to cache on an individual-activity basis, instead of a group-by-group basis. This
 * requires just a touch more overhead (in terms of looping through individual activity_ids), and
 * doesn't really have any added effect at the moment (since an activity item can only be associated
 * with a single group). But it provides the greatest amount of flexibility going forward, both in
 * terms of the possibility that activity items could be associated with more than one group, and
 * the possibility that users within a single group would want more highly-filtered digests.
 */
function ass_digest_format_item_group( $group_id, $activity_ids, $type, $group_name, $group_slug, $user_id ) {
	global $bp, $ass_email_css;

	$group_permalink = bp_get_root_domain() . '/' . bp_get_groups_root_slug() . '/' . $group_slug . '/';
	$group_name_link = '<a href="'.$group_permalink.'" name="'.$group_slug.'">'.$group_name.'</a>';

	$userdomain = bp_core_get_user_domain( $user_id );
	$unsubscribe_link = "$userdomain?bpass-action=unsubscribe&group=$group_id&access_key=" . md5( "{$group_id}{$user_id}unsubscribe" . wp_salt() );

	// add the group title bar
	if ( $type == 'dig' ) {
		$group_message = "\n<div {$ass_email_css['group_title']}>". sprintf( __( 'Group: %s', 'bp-ass' ), $group_name_link ) . "</div>\n\n";
	} elseif ( $type == 'sum' ) {
		$group_message = "\n<div {$ass_email_css['group_title']}>". sprintf( __( 'Group: %s weekly summary', 'bp-ass' ), $group_name_link ) . "</div>\n";
	}

	// add change email settings link
	$group_message .= "\n<div {$ass_email_css['change_email']}>";
	$group_message .= __('To disable these notifications for this group click ', 'bp-ass'). " <a href=\"$unsubscribe_link\">" . __( 'unsubscribe', 'bp-ass' ) . '</a> - ';
	$group_message .=  __('change ', 'bp-ass')."<a href=\"". $group_permalink . "notifications/\">".__( 'email options', 'bp-ass' )."</a> ";
	$group_message .= "</div>\n\n";

	$group_message = apply_filters( 'ass_digest_group_message_title', $group_message, $group_id, $type );

	// Finally, add the markup to the digest
	foreach ( $activity_ids as $activity_id ) {
		// Cache is set earlier in ass_digest_fire()
		$activity_item = wp_cache_get( 'bp_activity_' . $activity_id, 'bp' );

		if ( !$activity_item ) {
			// Try fetching it manually
			$activity_items = bp_activity_get_specific( array(
				'sort' 		=> 'ASC',
				'activity_ids' 	=> array( $activity_item ),
				'show_hidden' 	=> true
			) );

			if ( !empty( $activity_items ) ) {
				$activity_item = $activity_items[0];
			}
		}

		if ( !empty( $activity_item ) ) {
			$group_message .= ass_digest_format_item( $activity_item, $type );
		}
		//$group_message .= '<pre>'. $item->id .'</pre>';
	}

	return apply_filters( 'ass_digest_format_item_group', $group_message, $group_id, $type );
}



// displays each item in a group
function ass_digest_format_item( $item, $type ) {
	global $ass_email_css;

	$replies = '';

	//load from the cache if it exists
	if ( $item_cached = wp_cache_get( 'digest_item_' . $type . '_' . $item->id, 'ass' ) ) {
		//$item_cached .= "GENERATED FROM CACHE";
		return $item_cached;
	}

	/* Action text - This technique will not translate well */
	$action_split = explode( ' in the group', ass_clean_subject_html( $item->action ) );

	if ( $action_split[1] )
		$action = $action_split[0];
	else
		$action = $item->action;

	$action = ass_digest_filter( $action );

	$action = str_replace( ' started the forum topic', ' started', $action ); // won't translate but it's not essential
	$action = str_replace( ' posted on the forum topic', ' posted on', $action );
	$action = str_replace( ' started the discussion topic', ' started', $action );
	$action = str_replace( ' posted on the discussion topic', ' posted on', $action );

	/* Activity timestamp */
//	$timestamp = strtotime( $item->date_recorded );

	/* Because BuddyPress core set gmt = true, timezone must be added */
	$timestamp = strtotime( $item->date_recorded ) +date('Z');

	$time_posted = date( get_option( 'time_format' ), $timestamp );
	$date_posted = date( get_option( 'date_format' ), $timestamp );

	// Daily Digest
	if ( $type == 'dig' ) {

		//$item_message = strip_tags( $action ) . ": \n";
		$item_message =  "<div {$ass_email_css['item_div']}>";
		$item_message .=  "<span {$ass_email_css['item_action']}>" . $action . ": ";
		$item_message .= "<span {$ass_email_css['item_date']}>" . sprintf( __('at %s, %s', 'bp-ass'), $time_posted, $date_posted ) ."</span>";
		$item_message .=  "</span>\n";

		if ( $item->content )
			$item_message .= "<br><span {$ass_email_css['item_content']}>" . ass_digest_filter( $item->content ) . "</span>";

		/* Permalink */
		if ( $item->type == 'new_forum_topic' || $item->type == 'new_forum_post' || $item->type == 'new_blog_post' )
			$item_message .= ' - <a href="' . $item->primary_link .'">'.__('View', 'bp-ass').'</a>';

		if ( $item->type == 'activity_update' || $item->type == 'activity_comment' )
			$item_message .= ' - <a href="' . bp_activity_get_permalink( $item->id, $item ).'">'.__('View', 'bp-ass').'</a>';

		/* Cleanup */
		$item_message .= "</div>\n\n";


	// Weekly summary
	} elseif ( $type == 'sum' ) {

		// count the number of replies
		if ( $item->type == 'new_forum_topic' ) {
			if ( $posts = bp_forums_get_topic_posts( 'per_page=10000&topic_id='. $item->secondary_item_id ) ) {
				foreach ( $posts as $post ) {
					$since = time() - strtotime( $post->post_time );
					if ( $since < 604800 ) //number of seconds in a week
						$counter++;
				}
			}
			$replies = ' ' . sprintf( __( '(%s replies)', 'bp-ass' ), $counter );
		}

		$item_message = "<div {$ass_email_css['item_weekly']}>" . $action . $replies;
		$item_message .= " <span {$ass_email_css['item_date']}>" . sprintf( __('at %s, %s', 'bp-ass'), $time_posted, $date_posted ) ."</span>";
		$item_message .= "</div>\n";
	}

	$item_message = apply_filters( 'ass_digest_format_item', $item_message, $item, $action, $timestamp, $type, $replies );
	$item_message = ass_digest_filter( $item_message );

	// save the cache
	if ( $item->id )
		wp_cache_set( 'digest_item_' . $type . '_' . $item->id, $item_message, 'ass' );

	return $item_message;
}

// standard wp filters to clean up things that might mess up email display - (maybe not necessary?)
function ass_digest_filter( $item ) {
	$item = wptexturize( $item );
	$item = convert_chars( $item );
	return $item;
}

// convert the email to plain text, and fancy it up a bit. these conversion only work in English, but it's ok.
function ass_convert_html_to_plaintext( $message ) {
	// convert view links to http:// links
	$message = preg_replace( "/<a href=\"(.[^\"]*)\">View<\/a>/i", "\\1", $message );
	// convert group div to two lines encasing the group name
	$message = preg_replace( "/<div.*>Group: <a href=\"(.[^\"]*)\">(.*)<\/a>.*<\/div>/i", "------\n\\2 - \\1\n------", $message );
	// convert footer line to two dashes
	$message = preg_replace( "/\n<div class=\"ass-footer\"/i", "--\n<div", $message );
	// convert My Groups links to http:// links
	$message = preg_replace( "/<a href=\"(.[^\"]*)\">My Groups<\/a>/i", "\\1", $message );

	$message = preg_replace( "/<a href=\"(.[^\"]*)\">(.*)<\/a>/i", "\\2 (\\1)", $message );

	$message = strip_tags( stripslashes( $message ) );
	// remove uneccesary lines
	$message = str_replace( "change email options for this group\n\n", '', $message );
	$message = html_entity_decode( $message , ENT_QUOTES, 'UTF-8' );

	return $message;
}

/**
 * Formats and sends a MIME multipart email with both HTML and plaintext
 *
 * We have to use some fancy filters from the wp_mail function to configure the $phpmailer object
 * properly
 */
function ass_send_multipart_email( $to, $subject, $message_plaintext, $message ) {

	$admin_email = get_site_option( 'admin_email' );
	if ( $admin_email == '' )
		$admin_email = 'support@' . $_SERVER['SERVER_NAME'];
	$from_name = get_site_option( 'site_name' ) == '' ? 'WordPress' : esc_html( get_site_option( 'site_name' ) );

	add_filter( 'wp_mail_from',      create_function( '$admin_email', 'return $admin_email;' ) );
	add_filter( 'wp_mail_from_name', create_function( '$from_name', 'return $from_name;' ) );

	add_action( 'phpmailer_init', create_function( '$phpmailer', '
		$phpmailer->Body = "<html><body>' . addslashes( $message ) . '</body></html>";
		$phpmailer->AltBody = "' . $message_plaintext . '";
	' ) );

	$headers = array(
		'content_type' => 'text/html'
	);

	$result = wp_mail( $to, $subject, $message_plaintext, $headers );

	return $result;
}


function ass_digest_record_activity( $activity_id, $user_id, $group_id, $type = 'dig' ) {
	global $bp;

	if ( !$activity_id || !$user_id || !$group_id )
		return;

	// get the digest/summary items for all groups for this user
	$group_activity_ids = get_user_meta( $user_id, 'ass_digest_items', true );

	// update multi-dimensional array with the current activity_id
	$group_activity_ids[$type][$group_id][] = $activity_id;

	// re-save it
	update_user_meta( $user_id, 'ass_digest_items', $group_activity_ids );
}


function ass_cron_add_weekly( $schedules ) {
	if ( !isset( $schedules[ 'weekly' ] ) ) {
		$schedules['weekly'] = array( 'interval' => 604800, 'display' => __( 'Once Weekly', 'bp-ass' ) );
	}
	return $schedules;
}
add_filter( 'cron_schedules', 'ass_cron_add_weekly' );



function ass_set_daily_digest_time( $hours, $minutes ) {
	$the_time = date( 'Y-m-d' ) . ' ' . $hours . ':' . $minutes;
	$the_timestamp = strtotime( $the_time );

	/* If the time has already passed today, the next run will be tomorrow */
	$the_timestamp = ( $the_timestamp > time() ) ? $the_timestamp : (int)$the_timestamp + 86400;

	/* Clear the old recurring event and set up a new one */
	wp_clear_scheduled_hook( 'ass_digest_event' );
	wp_schedule_event( $the_timestamp, 'daily', 'ass_digest_event' );

	/* Finally, save the option */
	update_option( 'ass_digest_time', array( 'hours' => $hours, 'minutes' => $minutes ) );
}

// Takes the numeral equivalent of a $day: 0 for Sunday, 1 for Monday, etc
function ass_set_weekly_digest_time( $day ) {
	if ( !$next_weekly = wp_next_scheduled( 'ass_digest_event' ) )
		$next_weekly = time() + 60;

	while ( date( 'w', $next_weekly ) != $day ) {
		$next_weekly += 86400;
	}

	/* Clear the old recurring event and set up a new one */
	wp_clear_scheduled_hook( 'ass_digest_event_weekly' );
	wp_schedule_event( $next_weekly, 'weekly', 'ass_digest_event_weekly' );

	/* Finally, save the option */
	update_option( 'ass_weekly_digest', $day );
}

/*
// if in the future we want to do flexible schedules. this is how we could add the custom cron. Then we need to change the digest or summary to use this custom schedule.
function ass_custom_digest_frequency( $schedules ) {
    if( ( $freq = get_option(  'ass_digest_frequency' ) ) ) {
        if( !isset( $schedules[$freq.'_hrs'] ) ) {
            $schedules[$freq.'_hrs'] = array( 'interval' => $freq * 3600, 'display' => "Every $freq hours" );
        }
    }
    return $schedules;
}
add_filter( 'cron_schedules', 'ass_custom_digest_frequency' );
*/

?>