<?php
/* This is for local testing only!! */
//date_default_timezone_set('America/New_York');

/* This function was used for debugging the digest scheduling features */
function ass_digest_schedule_print() {	
	//print "<pre>";
	print "<br />";
$crons = _get_cron_array();
	echo "<div style='background: #fff;'>";
	
	$sched = wp_next_scheduled( 'ass_digest_event_weekly' );
	
	echo "Scheduled: " . date( 'h:i', $sched );
	
	$until = (int)$sched - time();
	echo " Until: " . $until;
	echo "</div>";
	
}
//add_action( 'wp_head', 'ass_digest_schedule_print' );


/* Digest-specific functions */

function ass_digest_fire( $type ) {
	global $bp;

	if ( !$type )
		$type = 'dig';
		
	// This is done with bp_has_groups because user subscription status is stored in groupmeta. Therefore you can't simply pull up a list of all members on the site and run through them one by one. The bp_has_groups method will result in far fewer db hits
	
	if ( bp_has_groups( 'per_page=100000' ) ) {
		
		if ( $type == 'dig' )
			$subject = sprintf( __( 'Your daily digest of group activity', 'bp-ass' ) );
		else
			$subject = sprintf( __( 'Your weekly summary of group topics', 'bp-ass' ) );

		$blogname = get_blog_option( BP_ROOT_BLOG, 'blogname' );			
		$subject .= ' [' . $blogname . '] ';
	
		$footer = "\n-----------\n";
		$footer .= sprintf( __( "You have received this message because you are subscribed to receive a digest of activity in some of your groups on %s. To change your notification settings for a given group, click on the group\'s link above and visit the Email Options page.", 'bp-ass' ), $blogname );
			
		$member_sent = array();
		
		while ( bp_groups() ) {
			bp_the_group();
			
			$group_id = bp_get_group_id();
			
			$subscribers = groups_get_groupmeta( $group_id , 'ass_subscribed_users' );
						
			foreach ( (array)$subscribers as $subscriber => $email_status ) {			
				
				// Each user only gets one digest each time around 
				if ( in_array( $subscriber, $member_sent ) )
					continue;
				
				// Get the activity items the user needs to receive. If there are none, move on to the next member
				if ( !$group_activity_ids_array = get_usermeta( $subscriber, 'ass_digest_items' ) )
					continue;
				
				// We only want the weekly or daily ones
				if ( !$group_activity_ids = (array)$group_activity_ids_array[$type] )
					continue;
		
				$message = $subject . "\n\n----------------------\n";
				$summary = __( 'Group Summary', 'bp-ass');
				
				foreach ( $group_activity_ids as $group_id => $activity_ids ) {
					// get group name and add it to this
					$group = new BP_Groups_Group( $group_id );
					$group_name = bp_get_group_name( $group );
					$act_count = count( $activity_ids );
					$summary .= "\n- " . $group_name . ' ' . sprintf( __( '(%s items)', 'bp-ass' ), $act_count );
					
					$activity_message .= ass_digest_format_item_group( $group_id, $activity_ids, $type );
					unset( $group_activity_ids[$group_id] );
				}
				
				$summary .= "\n----------------------\n\n";
				
				if ( $type == 'dig' )
					$message .= $summary;
					
				$message .= $activity_message;
				$message .= $footer;
				
				$message = strip_tags(stripslashes( $message ) );
				
				// Get the details for the user
				$ud = bp_core_get_core_userdata( $subscriber );
		
				// Set up and send the message
				$to = $ud->user_email;
				
				//echo '<br><br>========================================================<br><br>';// For testing only
				//echo '<pre> To: '.$to . '</pre>'; // For testing only
				//echo '<pre>'; print_r( $message ); echo '</pre>'; //die(); // For testing only
				
				wp_mail( $to, $subject, $message );
				unset( $message, $to );
		
				$group_activity_ids_array[$type] = $group_activity_ids;
				update_usermeta( $subscriber, 'ass_digest_items', $group_activity_ids_array );  // comment this out for helpful testing
				$member_sent[] = $subscriber;
			}
		}
	}
}


function ass_daily_digest_fire() {
	ass_digest_fire( 'dig' );
}
add_action( 'ass_digest_event', 'ass_daily_digest_fire' );

function ass_weekly_digest_fire() {
	ass_digest_fire( 'sum' );
}
add_action( 'ass_digest_event_weekly', 'ass_weekly_digest_fire' );

// Use these two lines for testing the digest firing in realtime
//add_action( 'bp_after_container', 'ass_daily_digest_fire' ); // for testing only
//add_action( 'bp_after_container', 'ass_weekly_digest_fire' ); // for testing only






function ass_digest_format_item_group( $group_id, $activity_ids, $type ) {
	
	$group = new BP_Groups_Group( $group_id, false, true );	
	
	if ( $type == 'dig' ) {
		$group_message = "\n-----------\n";
		$group_message .= sprintf( __( 'Activity from the group "%s"', 'bp-ass' ), $group->name );
		$group_message .= "\n";
		$group_message .= bp_get_group_permalink( $group );
		$group_message .= "\n-----------\n\n";
	} else {
		$group_message =  "\n" . sprintf( __( 'New topics for "%s"', 'bp-ass' ), $group->name );
		$group_message .= ' - ' . bp_get_group_permalink( $group );
		$group_message .= "\n";
	}	
	
	if ( is_array( $activity_ids ) )
		$activity_ids = implode( ",", $activity_ids );
	$items = bp_activity_get_specific( "sort=ASC&activity_ids=" . $activity_ids );
				
	foreach ( $items['activities'] as $item ) {
		$group_message .= ass_digest_format_item( $item, $type );
	}
	
	return $group_message;
}


function ass_digest_format_item( $item, $type ) {
	//echo '<pre>'; print_r( $item ); echo '</pre>';	
	
	//$options = get_site_option( 'ass_digest_options' );
	$options = array( 'forum_post_format' => 'full_text' );
	
	/* Action text - This technique will not translate well */
	$action_split = explode( ' in the group', $item->action );
	
	if ( $action_split[1] )
		$action = $action_split[0];
	else
		$action = $item->action;

	/* Activity timestamp */
	$timestamp = strtotime( $item->date_recorded );
	$time_posted = date( get_option( 'time_format' ), $timestamp );
	$date_posted = date( get_option( 'date_format' ), $timestamp );
	
	// Daily Digest
	if ( $type == 'dig' ) {
		
		$item_message = strip_tags( $action ) . ": \n";

		/* Activity content */
		/* At some point I will get the full text to work. For now it sucks and is hard */
		if ( $options['forum_post_format'] == 'full_text' ) {
	/*		if ( bp_has_forum_topic_posts( "topic_id=2" ) ) {
				global $topic_template;
			}
		
			print_r($topic_template);*/
			$content = $item->content;
		} else {
			$content = $item->content;
		}
		
		if ( $content )
			$item_message .= '   "' . $content . '"' . "\n";
	
		$item_message .= sprintf( 'at %s %s', $time_posted, $date_posted );
		
		/* Permalink */
		if ( $item->type == 'new_forum_topic' || $item->type == 'new_forum_post' || $item->type == 'new_blog_post' )
			$item_message .= ' - ' . $item->primary_link;
		
		/* Cleanup */
		$item_message .= "\n\n";
	
	// Weekly summary
	} else {
		
		if ( $item->type == 'new_forum_topic' ) {
			if ( bp_has_forum_topic_posts( 'per_page=10000&topic_id=' . $item->secondary_item_id ) ) {
				global $topic_template;
				foreach ( $topic_template->posts as $post ) {
					$since = time() - strtotime( $post->post_time );
					if ( $since < 604800 ) //number of seconds in a week
						$counter++;
				}
			}
			$replies = ' ' . sprintf( __( '(%s replies)', 'bp-ass' ), $counter );
		}
		
		$action = str_replace( ' started the forum topic', ':', $action ); // won't translate but it's not essential
		$item_message = '   ' . $action . $replies;
		// until we can get this working in dual plain text and html, we should keep it simpler. 
		//$item_message .= ' - ' . $item->primary_link; 
		$item_message .= "\n";
	}
	
	return $item_message;
}


function ass_digest_record_activity( $activity_id, $user_id, $group_id, $type = 'dig' ) {
	global $bp;
	
	if ( !$group_activity_ids = get_usermeta( $user_id, 'ass_digest_items' ) )
		$group_activity_ids = array();
	
	if ( !$this_group_activity_ids = $group_activity_ids[$type][$group_id] )
		$this_group_activity_ids = array();
		
	$this_group_activity_ids[] = $activity_id;
	$group_activity_ids[$type][$group_id] = $this_group_activity_ids;
	update_usermeta( $user_id, 'ass_digest_items', $group_activity_ids );
}


function ass_cron_add_weekly( $schedules ) {
	return array( 
		'weekly' => array( 'interval' => 604800, 'display' => __( 'Once Weekly', 'bp-ass' ) )
	);
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

?>