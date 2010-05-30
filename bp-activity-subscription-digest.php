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
	global $bp, $ass_email_css;

	if ( !$type )
		$type = 'dig';
	
	// HTML emails only work with inline CSS styles. Here we setup the styles to be used in various functions below.	
	$ass_email_css['wrapper'] = 		'style="color:#333;'; // use this to style the body
	$ass_email_css['title'] = 			'style="font-size:130%;"';
	$ass_email_css['summary_ul'] = 		'style="padding:12px 0 5px; list-style-type:circle; list-style-position:inside;"';
	//$ass_email_css['summary'] = 		'style="display:list-item;"';
	$ass_email_css['follow_topic'] = 	'style="padding:15px 0 0; color: #888;"';
	$ass_email_css['group_title'] = 	'style="font-size:120%; background-color:#F5F5F5; padding:3px; margin:20px 0 0; border-top: 1px #eee solid;"';
	$ass_email_css['item_div'] = 		'style="padding: 10px; border-top: 1px #eee solid;"';
	$ass_email_css['item_action'] = 	'style="color:#888;"';
	$ass_email_css['item_date'] = 		'style="font-size:85%; color:#bbb; margin-left:8px;"';
	$ass_email_css['item_content'] = 	'style="color:#333;"';
	$ass_email_css['item_weekly'] = 	'style="color:#888; padding:4px 10px 0"'; // used in weekly in place of other item_ above
	$ass_email_css['footer'] = 			'class="ass-footer" style="margin:25px 0 0; padding-top:5px; border-top:1px #bbb solid;"';
		
	// This is done with bp_has_groups because user subscription status is stored in groupmeta. Therefore you can't simply pull up a list of all members on the site and run through them one by one. The bp_has_groups method will result in far fewer db hits
	
	if ( bp_has_groups( 'per_page=100000' ) ) {
		
		if ( $type == 'dig' )
			$title = sprintf( __( 'Your daily digest of group activity', 'bp-ass' ) );
		else
			$title = sprintf( __( 'Your weekly summary of group topics', 'bp-ass' ) );

		$blogname = get_blog_option( BP_ROOT_BLOG, 'blogname' );			
		$subject = "$title [$blogname]";
	
		$footer = "\n\n<div {$ass_email_css['footer']}>";
		$footer .= sprintf( __( "You have received this message because you are subscribed to receive a digest of activity in some of your groups on %s. To change your notification settings for a given group, click on the group's link above and visit the Email Options page.", 'bp-ass' ), $blogname );
		$footer .= "</div>";
			
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
		
				$message = "<div {$ass_email_css['title']}>$title ";
				$message .= __('at', 'bp-ass')." <a href='{$bp->root_domain}'>$blogname</a>";
				$message .= "</div>\n\n";
				
				
				foreach ( $group_activity_ids as $group_id => $activity_ids ) {
					// get group name and add it to this
					$group = new BP_Groups_Group( $group_id ); // boone: rather than create the group twice, shouldn't we pass the group as a reference (@$group) in the ass_digest_format_item_group function below?
					$summary .= "<li {$ass_email_css['summary']}> " . bp_get_group_name( $group );
					$summary .= " " . sprintf( __( '(%s items)', 'bp-ass' ), count( $activity_ids ) ) ."</li>\n";
					
					$activity_message .= ass_digest_format_item_group( $group_id, $activity_ids, $type );
					unset( $group_activity_ids[ $group_id ] );
				}

				if ( $type == 'dig' )
					$message .= "\n<ul {$ass_email_css['summary_ul']}>".__( 'Summary', 'bp-ass').":\n".$summary."</ul>\n";
				elseif ( $type = 'sum' )
					$message .= "<div {$ass_email_css['follow_topic']}>". __( "How to follow a topic: to get email updates for a specific topic click the topic title - then on the webpage click the <i>Follow this topic</i> button. (If you don't see the button you need to log in first.)", 'bp-ass' ) . "</div>\n";

				$message .= $activity_message;
				$message .= $footer;
				$message_text = ass_convert_html_to_plaintext( $message );
				//$message_text = strip_tags(stripslashes( $message_text ) );
				$message_html = $message ;
				
				// Get the details for the user
				$ud = bp_core_get_core_userdata( $subscriber );
				$to = $ud->user_email;
				
				// For testing only
				/*
				echo '<div style="background-color:white; width:600px;padding:10px;">'; 
				echo '<br><br>========================================================<br><br>';
				echo '<p><b> To: '.$to . '</b></p>';
				echo 'HTML PART:<br>'.$message_html ; 
				echo '<br>PLAIN TEXT PART:<br><pre>'; echo $message_text ; echo '</pre>'; 
				echo '</div>'; 
				*/
				//die(); // (by using die() you'll only see one digest, and miss weekly summary)
				// For testing only
				
				ass_send_multipart_email( $to, $subject, $message_text, $message_html );
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

// Use these two lines for testing the digest firing in real-time
//add_action( 'bp_after_container', 'ass_daily_digest_fire' ); // for testing only
//add_action( 'bp_after_container', 'ass_weekly_digest_fire' ); // for testing only





// displays the introduction for the group
function ass_digest_format_item_group( $group_id, $activity_ids, $type ) {
	global $ass_email_css;
	
	$group = new BP_Groups_Group( $group_id, false, true );	
	$group_name_link = '<a href="' . bp_get_group_permalink( $group ) . '">' . $group->name . '</a>'; 
	
	if ( $type == 'dig' ) {
		$group_message = "\n<div {$ass_email_css['group_title']}>". sprintf( __( 'Group: %s', 'bp-ass' ), $group_name_link ) . "</div>\n\n";
	} elseif ( $type == 'sum' ) {
		$group_message = "\n<div {$ass_email_css['group_title']}>". sprintf( __( 'Group: %s new topics summary', 'bp-ass' ), $group_name_link ) . "</div>\n";
	}	
	
	if ( is_array( $activity_ids ) )
		$activity_ids = implode( ",", $activity_ids );
	$items = bp_activity_get_specific( "sort=ASC&activity_ids=" . $activity_ids );
				
	foreach ( $items['activities'] as $item ) {
		$group_message .= ass_digest_format_item( $item, $type );
	}
	
	return $group_message;
}

// displays each item in a group
function ass_digest_format_item( $item, $type ) {
	global $ass_email_css;
		
	//$options = get_site_option( 'ass_digest_options' );
	//$options = array( 'forum_post_format' => 'full_text' );
	
	/* Action text - This technique will not translate well */
	$action_split = explode( ' in the group', ass_clean_subject_html( $item->action ) ); 
	
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
		
		//$item_message = strip_tags( $action ) . ": \n";
		$item_message =  "<div {$ass_email_css['item_div']}>";
		$item_message .=  "<span {$ass_email_css['item_action']}>" . $action . ": ";
		$item_message .= "<span {$ass_email_css['item_date']}>" . sprintf( __('at %s, %s', 'bp-ass'), $time_posted, $date_posted ) ."</span>";
		$item_message .=  "</span>\n";

		/* Activity content */
		/* At some point I will get the full text to work. For now it sucks and is hard */
		/*if ( $options['forum_post_format'] == 'full_text' ) {
			if ( bp_has_forum_topic_posts( "topic_id=2" ) ) {
				global $topic_template;
			}
			print_r($topic_template);
			$content = $item->content;
		} else ...
		*/
			
		if ( $content = $item->content )
			$item_message .= "<br><span {$ass_email_css['item_content']}>" . $content . "</span>";
			
		/* Permalink */
		if ( $item->type == 'new_forum_topic' || $item->type == 'new_forum_post' || $item->type == 'new_blog_post' )
			$item_message .= ' - <a href="' . $item->primary_link .'">'.__('View', 'bp-ass').'</a>';
		
		/* Cleanup */
		$item_message .= "</div>\n\n";

	
	// Weekly summary
	} elseif ( $type == 'sum' ) {
		global $topic_template;
	
		$counter = 0;
		if ( $item->type == 'new_forum_topic' ) {
			if ( bp_has_forum_topic_posts( 'per_page=10000&topic_id=' . $item->secondary_item_id ) ) {
				foreach ( $topic_template->posts as $post ) {
					$since = time() - strtotime( $post->post_time );
					if ( $since < 604800 ) //number of seconds in a week
						$counter++;
				}
			}
			$replies = ' ' . sprintf( __( '(%s replies)', 'bp-ass' ), $counter );
		}
		
		$action = str_replace( ' started the forum topic', ' started:', $action ); // won't translate but it's not essential
		
		$item_message = "<div {$ass_email_css['item_weekly']}>" . $action . $replies;
		$item_message .= " <span {$ass_email_css['item_date']}>" . sprintf( __('at %s, %s', 'bp-ass'), $time_posted, $date_posted ) ."</span>";
		$item_message .= "</div>\n";
	}
	
	return $item_message;
}


// convert the email to plain text, and fancy it up a bit. these conversion only work in English, but it's ok.
function ass_convert_html_to_plaintext( $message ) {
	$message = preg_replace( "/<a href=\"(.*)\">View<\/a>/i", "\\1", $message );
	$message = preg_replace( "/<div.*>Group: <a href=\"(.*)\">(.*)<\/a>.*<\/div>/i", "------\n\\2 - \\1\n------", $message );
	$message = preg_replace( "/\n<div class=\"ass-footer\"/i", "--\n<div", $message );
	$message = strip_tags( stripslashes( $message ) );
	$message = html_entity_decode( $message , ENT_QUOTES, 'UTF-8' );    
	
	return $message;
}

// formats and sends a MIME multipart email with both HTML and plaintext
function ass_send_multipart_email( $to, $subject, $message_text, $message_html ) {
	$mime_boundary = "BP_GROUP_SUMMARY--".md5(time()); 
	$headers .= "MIME-Version: 1.0\n"; 
	$headers .= "Content-Type: multipart/alternative; boundary=\"$mime_boundary\"\n";
	
	// text email part	
	$message = "--$mime_boundary\n"; 
	$message .= "Content-Type: text/plain; charset=UTF-8\n"; 
	$message .= "Content-Transfer-Encoding: 8bit\n\n"; 
	
	$message .= "$message_text\n";
	
	// HTML email part
	$message .= "--$mime_boundary\n"; 
	$message .= "Content-Type: text/html; charset=UTF-8\n"; 
	$message .= "Content-Transfer-Encoding: 8bit\n\n"; 

	$message .= "<html>\n"; 
	$message .= "<body>\n"; 
	$message .= "<div {$ass_email_css['wrapper']}>\n"; 
	// maybe replace all '=' signs with '=3D'	
	$message .= "$message_html\n";
	$message .= "</div>\n"; 
	$message .= "</body>\n"; 
	$message .= "</html>\n"; 
	
	//final boundary
	$message .= "--$mime_boundary--\n\n"; 

	// send the darn email!	
	wp_mail( $to, $subject, $message, $headers );
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