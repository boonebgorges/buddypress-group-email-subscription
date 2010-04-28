<?php
/* This is for local testing only!! */
date_default_timezone_set('America/New_York');

/* This function was used for debugging the digest scheduling features */
function ass_digest_schedule_print() {	
	//print "<pre>";
	print "<br />";
$crons = _get_cron_array();
	echo "<div style='background: #fff;'>";
	
	$sched = wp_next_scheduled( 'ass_digest_event' );
	
	echo "Scheduled: " . date( 'h:i', $sched );
	
	$until = (int)$sched - time();
	echo " Until: " . $until;
	echo "</div>";
	
}
//add_action( 'wp_head', 'ass_digest_schedule_print' );


/* Digest-specific functions */

function ass_digest_fire() {
	global $bp;
	
	if ( bp_has_groups( 'per_page=100000' ) ) {
		
		$blogname = get_blog_option( BP_ROOT_BLOG, 'blogname' );
		$subject = sprintf( __( 'Your group updates from %s', 'bp-ass' ), $blogname );
		
		$footer = '
		-----------
		';
		$footer .= sprintf( __( "You have received this message because you are subscribed to receive a digest of activity in some of your groups on %s. To change your notification settings for a given group, click on the group\'s link above and visit the Email Options page.", 'bp-ass' ), $blogname );
			
		$member_sent = array();
		
		while ( bp_groups() ) {
			bp_the_group();
			
			$group_id = bp_get_group_id();
			
			// boone: I switched this to the consolidated subscribe/digeset list
			// $subscribers = groups_get_groupmeta( $group_id, 'ass_digest_subscribers' );
			$subscribers = groups_get_groupmeta( $group_id , 'ass_subscribed_users' );
			
			foreach ( (array)$subscribers as $subscriber => $email_status ) {
				if ( $email_status != 'dig' ) // *** boone: somewhere you'll need to put the one for the weekly summary 'sum'
					continue; 
			
				$message = $subject . '
';
				
				if ( !$group_activity_ids = get_usermeta( $subscriber, 'ass_digest_items' ) )
					continue;
		
				foreach ( $group_activity_ids as $group_id => $activity_ids ) {
					$message .= ass_digest_format_item_group( $group_id, $activity_ids );
				}
				
				$message .= $footer;
				
				$message = strip_tags(stripslashes( $message ) );
				
				// Get the details for the user
				$ud = bp_core_get_core_userdata( $subscriber );
		
				// Set up and send the message
				$to = $ud->user_email;
				//print_r($to); die();
				wp_mail( $to, $subject, $message );
		//echo $message; die();
				unset( $message, $to );
				
				delete_usermeta( $subscriber, 'ass_digest_items' );
				$member_sent[] = $subscriber;
			}
		}
	}
}
//add_action( 'bp_init', 'ass_digest_fire' ); // for testing only
add_action( 'ass_digest_event', 'ass_digest_fire' );




function ass_digest_format_item_group( $group_id, $activity_ids ) {
	
	$group_message = '';
	
	$group = new BP_Groups_Group( $group_id, false, true );	
	
	$group_message .= '
-----------
';
	$group_message .= sprintf( __( 'Activity from the group "%s"', 'bp-ass' ), $group->name );
	$group_message .= '
';
	$group_message .= bp_get_group_permalink( $group );
	
	$group_message .= '
-----------
	
';
	
	if ( is_array( $activity_ids ) )
		$activity_ids = implode( ",", $activity_ids );
	$items = bp_activity_get_specific( "sort=ASC&activity_ids=" . $activity_ids );
			
	foreach ( $items['activities'] as $item ) {
		$group_message .= ass_digest_format_item( $item );
	}
	
	return $group_message;
}


function ass_digest_format_item( $item ) {
	//print_r($item);
	
	//$options = get_site_option( 'ass_digest_options' );
	$options = array( 'forum_post_format' => 'full_text' );
	
	$item_message = '';
	
	/* Action text */
	/* This technique will not translate well */
	$action_split = explode( ' in the group', $item->action );
	if ( $action_split[1] )
		$action = $action_split[0] . ':';
	else
		$action = $item->action;
	
	$item_message .= $action . '
';
	
	/* Activity content */
	/* At some point I will get the full text to work. For now it sucks and is hard */
	if ( $options['forum_post_format'] == 'full_text' ) {
/*		if ( bp_has_forum_topic_posts( "topic_id=2" ) ) {
			global $topic_template;
		}
	
		print_r($topic_template);*/
		$content = '"' . $item->content . '"';
	} else {
		$content = '"' . $item->content . '"';
	}
	
	if ( $content )
		$item_message .= '   ' . $content . '
';
	
	/* Activity timestamp */
	$timestamp = strtotime( $item->date_recorded );
	$time_format = get_option( 'time_format' );
	$date_format = get_option( 'date_format' );
	$time_posted = date( "$time_format", $timestamp );
	$date_posted = date( "$date_format", $timestamp );
	$item_message .= sprintf( 'at %s %s', $time_posted, $date_posted );
	
	/* Permalink */
	if ( $item->type == 'new_forum_topic' || $item->type == 'new_forum_post' || $item->type == 'new_blog_post' )
		$item_message .= ' - ' . $item->primary_link;
	
	/* Cleanup */
	$item_message .= '

';

	return $item_message;
}



function ass_digest_record_activity( $activity_id, $user_id, $group_id ) {
	global $bp;
	
	if ( !$group_activity_ids = get_usermeta( $user_id, 'ass_digest_items' ) )
		$group_activity_ids = array();
	
	if ( !$this_group_activity_ids = $group_activity_ids[$group_id] )
		$this_group_activity_ids = array();
		
	$this_group_activity_ids[] = $activity_id;

	$group_activity_ids[$group_id] = $this_group_activity_ids;

	update_usermeta( $user_id, 'ass_digest_items', $group_activity_ids );
	
}

// boone: I changed this so it works now, the filter is not really a true filter, it's more like an action.
// in cron.php this is the code: return array_merge( apply_filters( 'cron_schedules', array() ), $schedules );
function ass_cron_add_weekly( $schedules ) {
	return array( 
		'weekly' => array( 'interval' => 604800, 'display' => __( 'Once Weekly', 'bp-ass' ) )
	);
}
add_filter( 'cron_schedules', 'ass_cron_add_weekly' );

?>