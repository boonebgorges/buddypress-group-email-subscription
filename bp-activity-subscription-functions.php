<?php
/*

TODO
- Keep subscription simple. (then time permitting maybe create global settings for what people get for three levels...or not)
- create new functions for new_forum_post and new_forum_topics, and keep the generic one for the rest.
- put the email icon back for subscribed, maybe make it green, and super more green
- add bp_before_topic_posts and bp_after_topic_posts in topic.php request into trac
- create a way for users to GLOBALY set their preference for super/sub/unsub for the misc activity types	

*/

// Loads JS on group pages
function ass_add_js() {
	global $bp;
	
	if ( $bp->current_component == $bp->groups->slug ) {
		wp_register_script('bp-activity-subscription-js', WP_PLUGIN_URL . '/buddypress-group-email-subscription/bp-activity-subscription-js.js');
		wp_enqueue_script( 'bp-activity-subscription-js' );

	}
}
add_action( 'wp_head', 'ass_add_js', 1 );


// DW: Why is this here, it seems like a duplication to the class function in main.php ?
// Loads required stylesheet on forum pages
/*

function ass_add_css() {
	global $bp;
	if ( $bp->current_component == $bp->groups->slug ) {
   		$style_url = WP_PLUGIN_URL . '/buddypress-group-email-subscription/bp-activity-subscription-css.css';
        $style_file = WP_PLUGIN_DIR . '/buddypress-group-email-subscription/bp-activity-subscription-css.css';
        if (file_exists($style_file)) {
            wp_register_style('activity-subscription-style', $style_url);
            wp_enqueue_style('activity-subscription-style');
        }
    }
}
add_action( 'wp_print_styles', 'ass_add_css' );
*/



//
// SEND EMAIL UDPATES FOR FORUM TOPICS AND POSTS
//


// send email notificaitons for new forum topics to members who are subscribed to the group
function ass_group_notification_new_forum_topic( $params ) {
	global $bp;
		
	if ( $params['type'] != 'new_forum_topic' )
		return;	

	//echo '<pre>'; print_r( $params ); echo '</pre>';
	
	if ( !ass_registered_long_enough( $bp->loggedin_user->id ) ) // check to see if the user has been registered long enough
		return;

	$group_id = $params['item_id'];
	$subscribed_users = groups_get_groupmeta( $group_id , 'ass_subscribed_users' );
	$group = new BP_Groups_Group( $group_id, false, true );
	$topic = get_topic( $params[ 'secondary_item_id' ] );
	$subject = "{$bp->loggedin_user->fullname} started the topic '{$topic->topic_title}' in {$group->name} [" . get_blog_option( BP_ROOT_BLOG, 'blogname' ) . "]";
	$content = strip_tags( stripslashes( $params[ 'content' ] ) );
	$settings_link = $bp->root_domain . '/' . $bp->groups->slug . '/' . $group->slug . '/notifications/';
	
	$message = sprintf( __(
'%s started the topic "%s" in %s.

"%s"

To view or reply to this message, follow the link below:
%s

---------------------
', 'buddypress' ), $bp->loggedin_user->fullname, $topic->topic_title, $group->name, $content, $params[ 'primary_link' ] );

	$message .= sprintf( __( 'To disable these notifications please log in and go to: %s', 'buddypress' ), $settings_link );
	
	// cycle through subscribed members and send an email
	foreach ( $subscribed_users as $user_id => $value ) { 		
		if ( $user_id == $bp->loggedin_user->id )  // don't send email to topic author	
			continue;

		if ( $value != 'sub' && $value != 'supersub' )  // this is not really necessary, but good for safety
			continue;
				
		$user = bp_core_get_core_userdata( $user_id ); // Get the details for the user
		wp_mail( $user->user_email, $subject, $message );  // Send the email
		//echo '<br>Email: ' . $user->user_email;
	}
	
	//echo '<p>Subject: ' . $subject;
	//echo '<pre>'; print_r( $message ); echo '</pre>';
}
add_action( 'bp_activity_add' , 'ass_group_notification_new_forum_topic' , 50 );





// send email notificaitons for new forum posts to members who are supersubscribed to the group or subscribed to this topic
function ass_group_notification_new_forum_topic_post( $params ) {
	global $bp;
		
	if ( $params['type'] != 'new_forum_post' )
		return;
		
	//echo '<pre>'; print_r( $params ); echo '</pre>';
	
	if ( !ass_registered_long_enough( $bp->loggedin_user->id ) )
		return;

	$group_id = $params['item_id'];
	$user_ids = BP_Groups_Member::get_group_member_ids( $group_id );
	$subscribed_users = groups_get_groupmeta( $group_id , 'ass_subscribed_users' );
	$post = bp_forums_get_post( $params['secondary_item_id'] );	
	$topic = get_topic( $post->topic_id );
	$user_topic_status = groups_get_groupmeta( $bp->groups->current_group->id , 'ass_user_topic_status_' . $topic->topic_id );
	$previous_posters = ass_get_previous_posters( $post->topic_id );	
	$group = new BP_Groups_Group( $group_id, false, true );
	$subject = "{$bp->loggedin_user->fullname} commented on the topic '{$topic->topic_title}' in {$group->name} [" . get_blog_option( BP_ROOT_BLOG, 'blogname' ) . "]";
	$content = strip_tags( stripslashes( $params[ 'content' ] ) );
	$settings_link = $bp->root_domain . '/' . $bp->groups->slug . '/' . $group->slug . '/notifications/';
	
	$message = sprintf( __(
'%s comented on the topic "%s" in %s.

"%s"

To view or reply to this message, follow the link below:
%s

---------------------
', 'buddypress' ), $bp->loggedin_user->fullname, $topic->topic_title, $group->name, $content, $params[ 'primary_link' ] );

	$message .= sprintf( __( 'To disable these notifications please log in and go to: %s', 'buddypress' ), $settings_link );
	
	// cycle through subscribed members and send an email
	foreach ( $user_ids as $user_id ) { 		 		
		if ( $user_id == $bp->loggedin_user->id )  // don't send email to topic author	
			continue;
		
		$send_it = NULL;
		$group_status = $subscribed_users[ $user_id ]; // get group and topic status for each user
		$topic_status = $user_topic_status[ $user_id ];
	
	 	//echo '<p>uid:' . $user_id .' | gstat:' . $group_status . ' | tstat:'.$topic_status . ' | owner:'.$topic->topic_poster . ' | prev:'.$previous_posters[ $user_id ];
		
		if ( $topic_status == 'mute' )  // the topic mute button will override the subscription options below
			continue;
		
		if ( $group_status == 'supersub' || $topic_status == 'sub' ) 
			$send_it = true;	
		else if ( $topic->topic_poster == $user_id && get_usermeta( $user_id, 'ass_replies_to_my_topic' ) != 'no' )
			$send_it = true;
		else if ( $previous_posters[ $user_id ] && get_usermeta( $user_id, 'ass_replies_after_me_topic')  != 'no' )
			$send_it = true;
			
		
		if ( $send_it ) {		
			$user = bp_core_get_core_userdata( $user_id ); // Get the details for the user
			wp_mail( $user->user_email, $subject, $message );  // Send the email
			//echo '<br>Email: ' . $user->user_email;
		}
		
	}
	
	//echo '<p>Subject: ' . $subject;
	//echo '<pre>'; print_r( $message ); echo '</pre>';
}
add_action( 'bp_activity_add' , 'ass_group_notification_new_forum_topic_post' , 50 );




//
// SEND EMAIL UDPATES FOR ACTIVITY AND GENERIC ACTIVITY
//


// returns assoc array of activity types, used in sending emails
// other plugins can add thier activity types here. 
function ass_activity_types() {
	global $ass_activities;

	$ass_activity[ 'activity_update' ] = array(	
					"level"=>"sub", // can be either "sub" or "supersub"
					"name_past"=>"added an activity update", 
					"name_pres"=>"adds an activity update",   //these next two currently not used, but might be :)
					"section"=>"Activity (Wall)"
					);
				
	$ass_activity[ 'joined_group' ] = array(
					"level"=>"supersub",
					"name_past"=>"joined the group", 
					"name_pres"=>"joins the group", 
					"section"=>"General"
					);
		
	// action filter for people to add additional activity types - should be a filter
	return apply_filters( 'ass_activity_types', $ass_activity );
}

// an example of a plugin adding an activity filter to group email notifications
function my_fun_activity_filter( $ass_activity ) {
	$ass_activity[ 'wiki_add' ] = array(
		"level"=>"sub",  // can be either "sub" or "supersub"
		"name_past"=>"added a wiki page", 
		"name_pres"=>"adds a wiki page", 
		"section"=>"Wiki"
		);
	return $ass_activity;
}
add_filter( 'ass_activity_types', 'my_fun_activity_filter' );




// The email notification function for other activities
function ass_notify_group_members( $params ) {
	global $bp;
	$type = $params['type'];	
		
	if ( $params['component'] != 'groups' || $type == 'new_forum_topic' || $type == 'new_forum_post' || $type == 'created_group' )
		return;
	
	//echo '<pre>'; print_r( $params ); echo '</pre>';	
	
	if ( !ass_registered_long_enough( $bp->loggedin_user->id ) )
		return;

	$group_id = $params[ 'item_id' ];
	$user_ids = BP_Groups_Member::get_group_member_ids( $group_id );
	$subscribed_users = groups_get_groupmeta( $group_id , 'ass_subscribed_users' );	
	$activity_user_name = $bp->loggedin_user->fullname;
	$activity_link = $params['primary_link'];
	$activity_content = strip_tags( stripslashes( $params['content'] ) );

	$activity_type_data = ass_activity_types();
	$activity_name_past = $activity_type_data[ $type ][ 'name_past' ];
	$activity_sub_level = $activity_type_data[ $type ][ 'level' ];
	
	$group = new BP_Groups_Group( $group_id, false, true );	
	$subject = $activity_user_name . ' ' . $activity_name_past . ' in ' . $group->name . ' [' . get_blog_option( BP_ROOT_BLOG, 'blogname' ) . ']';
	$group_link = $bp->root_domain . '/' . $bp->groups->slug . '/' . $group->slug;
	$settings_link = $group_link . '/notifications/';	
	if ( !$activity_link )	$activity_link = $group_link;
	
	$message = sprintf( __(
'%s %s in %s.

%s

To view or reply to this message, follow the link below:
%s

---------------------
', 'buddypress' ), $activity_user_name, $activity_name_past, $group->name, $activity_content, $activity_link );

	$message .= sprintf( __( 'To disable these notifications please log in and go to: %s', 'buddypress' ), $settings_link );
	
	// cycle through all group members and send them an email depending on their individual settings.
	foreach ( $user_ids as $user_id ) { 
		if ( $user_id == $bp->loggedin_user->id )  // don't send email to topic author	
			continue;
			
		// it would be good to create a way for users to GLOBALY set their preference for super/sub/unsub for the misc activity types			
		$group_sub_status = $subscribed_users[ $user_id ];
		
		//echo '<p>uid: ' . $user_id .' | gstat: ' . $group_sub_status . ' | actlvl: '. $activity_sub_level . ' | glvlsubstr: ' . substr( $group_sub_status, 5, 8 );
		
		// email if the subscriptions levels are the same OR if the activity level is 'sub' and the users subscription level is 'supersub'
		if ( $activity_sub_level == $group_sub_status || $activity_sub_level == substr( $group_sub_status, 5, 8 ) ) {
			$user = bp_core_get_core_userdata( $user_id ); // Get the details for the user
			wp_mail( $user->user_email, $subject, $message );
			//echo '<br>Email: ' . $user->user_email;	
		}

	}
	
	//echo '<p>Subject: ' . $subject;
	//echo '<pre>'; print_r( $message ); echo '</pre>';	
}
add_action( 'bp_activity_add' , 'ass_notify_group_members' , 50 );






//
//	GROUP SUBSCRIPTION
//


function ass_get_group_subscription_status( $user_id, $group_id ) {	
	if ( !$user_id || !$group_id )
		return false;
	
	$group_user_subscriptions = groups_get_groupmeta( $group_id, 'ass_subscribed_users' );
	
	if ( $group_user_subscriptions[ $user_id ] ) 
		return 	( $group_user_subscriptions[ $user_id ] );
	else
		return false;
}



// show group subscription settings on the notification page. 
function ass_group_subscribe_settings ( $group = false ) {
	global $bp, $groups_template;

	if ( !$group )
		$group = $bp->groups->current_group;
	
	if ( !is_user_logged_in() || $group->is_banned || !$group->is_member )
		return false;
		
	$gsub_type = ass_get_group_subscription_status( $bp->loggedin_user->id, $group->id );
		
	?>
	<form action="<?php echo $bp->root_domain; ?>/?ass_subscribe_form=1" name="group-subscribe-form" method="post">
	<input type="hidden" name="ass_group_id" value="<?php echo $bp->groups->current_group->id; ?>"/>
	<?php wp_nonce_field( 'ass_subscribe' ); ?>
	<table class="ass-group-sub-settings-table"><tr>
	<td>
		<p><b>Get everything</b></p>
		<?php if ( $gsub_type == 'supersub' ) { ?>
			<p><a class="button">Super-subscribe</a><br><b>You are Super-subscribed</b></p>
		<?php } else { ?>
			<p><input type="submit" class="generic-button" name="ass_group_subscribe" value="Super-subscribe"></p>
		<?php } ?>
		<p>Get email notifications for all new group content including comments and replies.</p>		
		<p>Note: here would be a good place to list the things they will actually get
	</td><td>
		<p><b>Get content, no conversations</b></p>
		<?php if ( $gsub_type == 'sub' ) { ?>
			<p><a class="button">Subscribe</a><br><b>You are Subscribed</b></p>
		<?php } else { ?>
			<p><input type="submit" class="generic-button" name="ass_group_subscribe" value="Subscribe">
		<?php } ?>
		<p>Get email notifications for all new group content, but not for comments or replies.</p>
	</td><td>
		<p><b>Get nothing</b></p>
		<?php if ( !$gsub_type ) { ?>
			<p><a class="button">Unsubscribe</a><br><b>You are Unsubscribed</b></p>
		<?php } else { ?>
			<p><input type="submit" class="generic-button" name="ass_group_subscribe" value="Unsubscribe">
		<?php } ?>
		<p>You can visit the group website to stay up-to-date.*</p>
	</td>
	</tr></table>
	<p class="ass-sub-note">* By default users receive notifications for topics they start or comment on. Go to your member notifications page to change these settings  {ADD LINK}</p>
	</form>
	
	<p><p> HERE IS WHERE THE DIGEST FUNCTIONALITY SHOULD BE
	<?php
	
	// only show the detailed options if they are subscribed
	// this has been removed for a simpler approach all around
	if ( $gsub_type ) {
	?>
		<br><b><a class="ass-settings-advanced-link">Show advanced settings &#187;</a></b><br>
		<div class="ass-settings-advanced">
		<form action="<?php echo $bp->root_domain; ?>/?ass_form=1" id="activity-subscription-settings-form" name="activity-subscription-settings-form" method="post">
			<input type="hidden" name="ass_group_id" value="<?php echo $bp->groups->current_group->id; ?>"/>
			<input type="hidden" name="ass_user_id" value="<?php echo $bp->loggedin_user->id; ?>"/>
			<?php 
				
			?>
			<?php wp_nonce_field( 'ass_form' ); ?>
			<?php echo ass_digest_options_cron(); ?>
			<br><input type="submit" name="submit" value="Save Changes" />
		</form>
		</div>
	<?php
	}
}



// if a user updates the subscription settings from the group notification page, this gets called 
function ass_update_group_subscribe_settings() {
    global $bp, $ass_form_vars;
            
	ass_get_form_vars(); 
	$group_id = $ass_form_vars[ 'ass_group_id' ];
	$action = $ass_form_vars[ 'ass_group_subscribe' ];
	
	if ( $action && $group_id ) {
	
		if ( !groups_is_user_member( $bp->loggedin_user->id , $group_id ) )
			return;
			
		//wp_verify_nonce('ass_subscribe');
		
		ass_group_subscription( $action, $bp->loggedin_user->id, $group_id ); // save the settings
		
		bp_core_add_message( __( $security.'You are now ' . $action . 'd for this group.', 'buddypress' ) );
		bp_core_redirect( wp_get_referer() );
	} 
}
add_action('template_redirect', 'ass_update_group_subscribe_settings');  




// this adds the ajax-based subscription option in the group header
function ass_group_subscribe_button( $group = false ) {
	global $bp, $groups_template;

	if ( !$group )
		$group =& $groups_template->group;

	if ( !is_user_logged_in() || $group->is_banned || !$group->is_member )
		return false;
		
	//$gsub = get_usermeta( $bp->loggedin_user->id, 'ass_group_subscription' ); // the old way
	//$gsub_type = $gsub[ $group->id ];
	
	$gsub_type = ass_get_group_subscription_status( $bp->loggedin_user->id, $group->id );	
		
	echo '<div class="group-subscription-div">';
		$link_text = 'Email Options';
		
		if ( $gsub_type == 'sub' ) {
			$status = 'Subscribed';
			$sep = ' / ';
		} else if ( $gsub_type == 'supersub' ) { 
			$status = 'Super-subscribed';
			$sep = ' / ';
		} else if ( !$gsub_type ) { 
			$status = '';
			$link_text = 'Get email updates';
			$email_icon = 'class="gemail_icon" ';
		} else {
			$link_text = 'Error';
		}
			
		echo '<span class="gemail_icon" id="gsubstat-'.$group->id.'">' . $status . '</span>' . $sep . ' ';	
		echo '<a class="group-subscription-options-link" id="gsublink-' . $group->id . '">' . $link_text . '&nbsp;&#187;</a><br>';	
		echo '<div class="generic-button group-subscription-options" id="gsubopt-'.$group->id.'">';
		
			if ( $gsub_type == 'supersub' || !$gsub_type ) 
				echo '<a class="group-subscription" id="gsubscribe-'.$group->id.'">subscribe</a><br>';
			
			if ( $gsub_type == 'sub' || !$gsub_type ) 
				echo ' <a class="group-subscription" id="gsupersubscribe-'.$group->id.'">super-subscribe</a><br>';
				
			if ( $gsub_type ) {
				echo ' <a class="group-subscription" id="gunsubscribe-'.$group->id.'">unsubscribe</a>';
				echo ' <a class="group-subscription-close" id="gsubclose-'.$group->id.'">x</a>';	
			}
		
		echo '</div>';
	echo '</div>';
	//echo ' <span class="ajax-loader" id="gsubajaxloader-'.$group->id.'"></span>';
}
add_action ( 'bp_group_header_meta', 'ass_group_subscribe_button' );
add_action ( 'bp_directory_groups_actions', 'ass_group_subscribe_button' );
//add_action ( 'bp_directory_groups_item', 'ass_group_subscribe_button' );  //useful to put in different location with css abs pos



// Handles AJAX request to subscribe/unsubscribe from group
function ass_group_ajax_callback() {
	global $bp;
	//check_ajax_referer( "ass_group_subscribe" );
	
	$action = $_POST['a'];
	$user_id = $bp->loggedin_user->id;
	$group_id = $_POST['group_id'];
		
	ass_group_subscription( $action, $user_id, $group_id );
	
	echo $action;
	exit();
}
add_action( 'wp_ajax_ass_group_ajax', 'ass_group_ajax_callback' );




// updates the group's user subscription list.
function ass_group_subscription( $action, $user_id, $group_id ) {
	if ( !$action || !$user_id || !$group_id )
		return false;
		
	//$user_group_subscriptions = get_usermeta( $user_id, 'ass_group_subscription' );
	$group_user_subscriptions = groups_get_groupmeta( $group_id , 'ass_subscribed_users' );
	
	if ( $action == 'gsubscribe' || $action == 'Subscribe' ) {
		//$user_group_subscriptions[ $group_id ] = 'sub';
		$group_user_subscriptions[ $user_id ] = 'sub';
	} elseif ( $action == 'gsupersubscribe' || $action == 'Super-subscribe' ) {
		//$user_group_subscriptions[ $group_id ] = 'supersub';
		$group_user_subscriptions[ $user_id ] = 'supersub';
	} elseif ( $action == 'gunsubscribe' || $action == 'Unsubscribe' ) {
		//unset( $user_group_subscriptions[ $group_id ] );
		unset( $group_user_subscriptions[ $user_id ] );
	}
	
	//update_usermeta( $user_id, 'ass_group_subscription', $user_group_subscriptions );
	groups_update_groupmeta( $group_id , 'ass_subscribed_users', $group_user_subscriptions );
}





//
//	TOPIC SUBSCRIPTION
//


function ass_get_topic_subscription_status( $user_id, $topic_id ) {	
	global $bp;
	
	if ( !$user_id || !$topic_id )
		return false;
	
	$user_topic_status = groups_get_groupmeta( $bp->groups->current_group->id, 'ass_user_topic_status_' . $topic_id );
		
	if ( $user_topic_status[ $user_id ] ) 
		return ( $user_topic_status[ $user_id ] );
	else
		return false;
}


// Creates "subscribe/unsubscribe" link on forum directory page and each topic page
function ass_topic_follow_or_mute_link() {
	global $bp;  
	
	//echo '<pre>'; print_r( $bp ); echo '</pre>';
	
	if ( !$bp->groups->current_group->is_member )
		return;
	
	$topic_id = bp_get_the_topic_id();
	$topic_status = ass_get_topic_subscription_status( $bp->loggedin_user->id, $topic_id );
	$group_status = ass_get_group_subscription_status( $bp->loggedin_user->id, $bp->groups->current_group->id );
			
	if ( $topic_status == 'mute' || ( $group_status != 'supersub' && !$topic_status ) ) {
		$action = 'follow';
		$link_text = 'Follow';
		$title = 'You are not following this topic. Click to follow it and get email updates for new posts';
	} else if ( $topic_status == 'sub' || ( $group_status == 'supersub' && !$topic_status ) ) {
		$action = 'mute';
		$link_text = 'Mute';
		$title = 'You are following this topic. Click to stop getting email updates';
	} else {
		echo 'nothing'; // do nothing
	}
	
	if ( $topic_status == 'mute' )
		$title = 'This conversation is muted. Click to follow it';
			
	if ( $action && $bp->action_variables[0] == 'topic' ) { // we're viewing one topic
		echo "<div class=\"generic-button ass-topic-subscribe\"><a title=\"{$title}\" 
			id=\"{$action}-{$topic_id}\">{$link_text} this topic</a></div>"; 
	} else if ( $action )  { // we're viewing a list of topics
		echo "<td><div class=\"generic-button ass-topic-subscribe\"><a title=\"{$title}\" 
			id=\"{$action}-{$topic_id}\">{$link_text}</a></div></td>"; 
	}
}
add_action( 'bp_directory_forums_extra_cell', 'ass_topic_follow_or_mute_link', 50 );
add_action( 'bp_before_topic_posts', 'ass_topic_follow_or_mute_link' );
add_action( 'bp_after_topic_posts', 'ass_topic_follow_or_mute_link' );



// Handles AJAX request to subscribe/unsubscribe from topic
function ass_ajax_callback() {
	global $bp;
	//check_ajax_referer( "ass_subscribe" );
	
	$action = $_POST['a'];  // action is used by ajax, so we use a here
	$user_id = $bp->loggedin_user->id;
	$topic_id = $_POST['topic_id'];
		
	ass_topic_subscribe_or_mute( $action, $user_id, $topic_id );
	
	echo $action;
	die();
}
add_action( 'wp_ajax_ass_ajax', 'ass_ajax_callback' );


// Adds/removes a $topic_id from the $user_id's mute list.
function ass_topic_subscribe_or_mute( $action, $user_id, $topic_id ) {
	global $bp;
	
	if ( !$action || !$user_id || !$topic_id )
		return false;
		
	//$mute_list = get_usermeta( $user_id, 'ass_topic_mute' );
	$user_topic_status = groups_get_groupmeta( $bp->groups->current_group->id, 'ass_user_topic_status_' . $topic_id );
	
	if ( $action == 'unsubscribe' ||  $action == 'mute' ) {
		//$mute_list[ $topic_id ] = 'mute';
		$user_topic_status[ $user_id ] = 'mute'; 
	} elseif ( $action == 'subscribe' ||  $action == 'follow'  ) {
		//$mute_list[ $topic_id ] = 'subscribe';
		$user_topic_status[ $user_id ] = 'sub'; 
	}
	
	//update_usermeta( $user_id, 'ass_topic_mute', $mute_list );
	groups_update_groupmeta( $bp->groups->current_group->id , 'ass_user_topic_status_' . $topic_id, $user_topic_status );
	//bb_update_topicmeta( $topic_id, 'ass_mute_users', $user_id );
}





//
//	SUPPORT FUNCTIONS
//


// return array of previous posters' ids
function ass_get_previous_posters( $topic_id ) {
	do_action( 'bbpress_init' );
	global $bbdb, $wpdb;

	$posters = $bbdb->get_results( "SELECT poster_id FROM $bbdb->posts WHERE topic_id = {$topic_id}" );
	
	foreach( $posters as $poster ) {
		$user_ids[ $poster->poster_id ] = true;
	}
	
	return $user_ids;
}



// Check how long the user has been registered and return false if not long enough. Return true if setting not active off ( ie. 'n/a')
function ass_registered_long_enough( $activity_user_id ) {
	$ass_reg_age_setting = get_site_option( 'ass_activity_frequency_ass_registered_req' );
	
	if ( is_numeric( $ass_reg_age_setting ) ) {
		$current_user_info = get_userdata( $activity_user_id );
	
		if ( strtotime(current_time("mysql", 0)) - strtotime($current_user_info->user_registered) < ( $ass_reg_age_setting*24*60*60 ) )
			return false;
			
	}
	
	return true;
}




//
//	FORM SUBMITTING AND UPDATE FUNCTIONS
//




// hook into the query_vars parsing so we can get our own form vars	 - DW: isn't there a better way to do this?
function ass_form_vars($public_query_vars) {
	//global $ass_activities;
		
	$public_query_vars[] = 'ass_form';
	$public_query_vars[] = 'ass_admin_notify';
	$public_query_vars[] = 'ass_group_id';
	$public_query_vars[] = 'ass_admin_notice';
	$public_query_vars[] = 'ass_admin_settings';
	$public_query_vars[] = 'ass_registered_req';
	$public_query_vars[] = 'ass_group_subscribe';
	$public_query_vars[] = 'ass_subscribe_form';
	
	//Digest
	$public_query_vars[] = 'ass_user_id';
	$public_query_vars[] = 'ass_digest_scheduler';
	
	//foreach ( $ass_activities as $ass_activity ) {
	//	$public_query_vars[] = $ass_activity['type'];
	//}

	return ($public_query_vars);
}
add_filter('query_vars', 'ass_form_vars');



// these are notes - ignore for now
function ass_send_digest( $interval ) {
	global $bp;
	
	if ( !$interval )
		$interval = '1week';
	
	switch ( $interval ) {
		case '1week':
			$secs = 604800;
		break;
	
		case '1day':
			$secs = 86400;
		break;
	
		case '12hour':
			$secs = 43200;
		break;
	
		case '3hour':
			$secs = 10800;
		break;		
		
	}
	
	if ( bp_has_groups() ) {
		while ( bp_groups() ) : bp_the_group();
		$group_id = bp_get_group_id();
		
		if ( bp_has_activities( 'display_comments=stream' ) ) {
					global $activities_template;
				
				$time = time();
				
				foreach ( $activities_template->activities as $key=>$activity ) {
					$recorded_time = strtotime( $activity->date_recorded );
					
					if ( $time - $recorded_time < $secs ) {
						$action = str_replace( ': <span class="time-since">%s</span>', ' at ' . date('h:ia \o\n l, F j, Y', $recorded_time) , $activity->action);
						print_r($action);
						print_r( $activity->content );
						echo "<br >";
					}
				}
				
				
				print "<pre>";
				//print_r($activities_template);
				print "</pre>";
		}
		
		endwhile;
	}
}
//add_action('plugins_loaded', 'ass_send_digest');

/* Pseudo-code for cron job */
/*
	$this_interval is the interval for this cron job, ie 1hour
	foreach (groups as group)
		$pref_array = associative groupmeta which has member $interval preference
		
		if ( !in_array( $this_interval$pref_array ) ) // if no one in the group has this interval
			continue; // no need to bother with the rest - go to the next group			
		
		grab and format the activities
		build email content for each $interval
		get group members
		$pref_array = associative groupmeta which has member $interval preference
		foreach (members as member)
			get digest prefs from $pref_array ($user_id as $key)
			if ($pref_array[$user_id] = this $interval ) {			
				get email address (currently how plugin does it - can it be moved out to a single call? at least do it at the end so you only get the email address of the user in question)
				send
			}
		end foreach member
	end foreach group
*/



//  Call this at the start of our form processing function to get the variables for use in our script			*
function ass_get_form_vars() {  
    global $ass_form_vars, $ass_activities;  
	
    if(get_query_var('ass_form')) {  
        $ass_form_vars['ass_form'] = mysql_real_escape_string(get_query_var('ass_form'));  
    }
	
    if(get_query_var('ass_group_id')) {  
        $ass_form_vars['ass_group_id'] = mysql_real_escape_string(get_query_var('ass_group_id'));  
    }
		
    if(get_query_var('ass_admin_notify')) {  
        $ass_form_vars['ass_admin_notify'] = mysql_real_escape_string(get_query_var('ass_admin_notify'));  
	}
	
    if(get_query_var('ass_admin_notice')) {  
        $ass_form_vars['ass_admin_notice'] = mysql_real_escape_string(get_query_var('ass_admin_notice'));  
	}
	
    if(get_query_var('ass_admin_settings')) {  
        $ass_form_vars['ass_admin_settings'] = mysql_real_escape_string(get_query_var('ass_admin_settings'));  
	}
	
    if(get_query_var('ass_registered_req')) {  
        $ass_form_vars['ass_registered_req'] = mysql_real_escape_string(get_query_var('ass_registered_req'));  
	}
	
    if(get_query_var('ass_group_subscribe')) {  
        $ass_form_vars['ass_group_subscribe'] = mysql_real_escape_string(get_query_var('ass_group_subscribe'));  
	}
	
	/*foreach ( $ass_activities as $ass_activity ) {
		if(get_query_var($ass_activity['type'])) {  
			$ass_form_vars[$ass_activity['type']] = mysql_real_escape_string(get_query_var($ass_activity['type']));  
		}
	}*/
	
    return $ass_form_vars;  
} 



// This function sends an email out to all group members regardless of subscription status. 
// It's called before template redirect to give feedback.
function ass_admin_notice() {
    global $bp, $wpdb, $ass_form_vars, $ass_activities;
    
    // Make sure the user is an admin or mod of this group
	if ( !groups_is_user_admin( $bp->loggedin_user->id , $group_id ) && !groups_is_user_mod( $bp->loggedin_user->id , $group_id ) )
		return;
	
	ass_get_form_vars();  
	
	if ( $ass_form_vars['ass_admin_notice'] && $ass_form_vars['ass_admin_notify'] ) {
		wp_verify_nonce('ass_admin_notice');  // dw: this does nothing!
		$group_id = $ass_form_vars['ass_group_id'];
		
		// Post an update to the group and force sending of an email to all group members
		$activity_user_id = $bp->loggedin_user->id;
		$activity_user_name = $bp->loggedin_user->fullname;
		$activity_link = '';
		$activity_content = strip_tags( stripslashes( $ass_form_vars[ 'ass_admin_notice' ] ) );
		$activity_type = 'admin_notice';		
		// Generate a nice description for the update based on the activity type.
		// First set a description based on the default
		$activity_name_past = 'posted an important group update';
		
		$group = new BP_Groups_Group( $group_id, false, true );
		$subject = '[' . get_blog_option( BP_ROOT_BLOG, 'blogname' ) . ' | ' . $group->name . '] ' . $activity_user_name . ' ' . $activity_name_past . '.' ;

		$user_ids = BP_Groups_Member::get_group_member_ids( $group->id ); 
		foreach ( $user_ids as $user_id ) { 

			// Get the details for the user
			$ud = bp_core_get_core_userdata( $user_id );

			// Set up and send the message
			$to = $ud->user_email;
			
			//why is this in the foreach loop? dw
			$group_link = $bp->root_domain . '/' . $bp->groups->slug . '/' . $group->slug;
			if ( $activity_link == '' ) {
				$activity_link = $group_link;
			}
			$settings_link = $group_link . '/notifications/';

			$message = sprintf( __(
'%s %s.

%s

Please respond to this notice by visiting your group homepage:
%s

---------------------
', 'buddypress' ), $activity_user_name, $activity_name_past, $activity_content, $activity_link );

			// Send it
			wp_mail( $to, $subject, $message );

			unset( $message, $to );
		}
		bp_core_add_message( __( 'Message sent successfully', 'buddypress' ) );
		bp_core_redirect( wp_get_referer() );
	} 
	// If we get to this point the page request isn't our submitted form.  The rest of WP will load normally now
}
add_action('template_redirect', 'ass_admin_notice');  



/*
// with new system this is not needed, i believe. 
function ass_update_settings() {
    global $bp, $wpdb, $ass_form_vars, $ass_activities;
    
	ass_get_form_vars(); 
	$group_id = $ass_form_vars['ass_group_id'];
	$user_id = $bp->loggedin_user->id;
	
	if ( $group_id && $ass_form_vars['ass_form'] ) {
		 wp_verify_nonce('ass_settings');  // dw: this does nothing!
	
		if ( !groups_is_user_member( $user_id , $group_id ) )
			return;
			
		$user_settings = array();
		// could potentialy add user_id and group_id here for quicker processing when sending emails
		//$user_settings[ 'group_id' ] = $group_id;
		//$user_settings[ 'user_id' ] = $user_id;
		
		// Process each of the user setting changes
		foreach ( $ass_form_vars as $ass_activity => $value ) {
			if ( $ass_activity == 'ass_group_id' || $ass_activity == 'ass_form' ) 
				continue;  // skip over group_id and form submit vars
			
			$user_settings[ $ass_activity ] = $value;
		}
		
		update_usermeta( $user_id, 'ass_activity_' . $group_id, $user_settings ); 
		// DW: maybe we should store this in group meta if we're going to send digests by group
		// groups_update_groupmeta( $group_id , 'ass_activity_' . $user_id, $user_settings );
		
		// maybe in group meta, keep track of each user's preference together for this group
		
		bp_core_add_message( __( 'Settings saved successfully', 'buddypress' ) );
		bp_core_redirect( wp_get_referer() );
	} 
	// If we get to this point the page request isn't our submitted form.  The rest of WP will load normally now
}
add_action('template_redirect', 'ass_update_settings');  
//-------------------------------------------------------------------------------------------


// not needed any more
function ass_group_notification_settings_fields() {
	global $bp, $ass_activities;
		
	$output = '<p>Here you can specify how often and for which types of content you will receive email notifications.</p>';
	$output .= '<p>Send me an email notification when someone...';
	$output .= '<br><div class="ass-email gemail_icon">&nbsp;</div>';

	$user_settings = get_usermeta( $bp->loggedin_user->id , 'ass_activity_' . $bp->groups->current_group->id );
	//$user_settings = groups_get_groupmeta( $bp->groups->current_group->id , 'ass_activity_' . $bp->loggedin_user->id ); //alternative
	
	foreach ( $ass_activities as $ass_activity ) {
		
		// create section headings
		if ( $ass_activity['section'] != $section ) {
			$output .= '<div class="activity-subscription-settings-section">'.$ass_activity['section'].'</div>';
			$section = $ass_activity['section'];
		}
		
		$output .= '<div class="activity-subscription-settings-field">';
		$output .= ucfirst( $ass_activity[ 'name_pres' ] ); 
		$output .= '<input type="checkbox" name="' . $ass_activity['type'] . '" value="1"';
		if ( $user_settings[ $ass_activity[ 'type' ] ] ) 
			$output .= 'checked="checked"';
		$output .= '/>';
		$output .= '<span class="ass-sub-settings-level">' . $ass_activity['level'] . '</span>';
		$output .= '</div>';
	}	
	
	return $output;
}
*/


// Function to allow other plugins to add extra activity types.  Plugins should hook in with a higher priority than ass_update_activities
// ...as this will ensure that the default frequency settings are updated by the main functions
// Also updates the $ass_activities global with any new default frequency settings
/*
function ass_update_activities() {
	global $ass_activities;
	// Update the default frequency settings for each activity
	foreach ( $ass_activities as &$ass_activity ) {
		$ass_activity['default_frequency'] = get_site_option( 'ass_activity_frequency_' . $ass_activity['type'] );
	}
}
*/





// adds forum notification options in the users settings->notifications page 
// TODO: implement this functionality
function ass_group_subscription_notification_settings() {
	global $current_user; ?>
	<table class="notification-settings" id="groups-notification-settings">
		<tr>
			<th class="icon"></th>
			<th class="title"><?php _e( 'Group Forum', 'buddypress' ) ?></th>
			<th class="yes"><?php _e( 'Yes', 'buddypress' ) ?></th>
			<th class="no"><?php _e( 'No', 'buddypress' )?></th>
		</tr>
		<tr>
			<td></td>
			<td><?php _e( 'A member replies in a forum topic you\'ve started', 'buddypress' ) ?></td>
			<td class="yes"><input type="radio" name="notifications[ass_replies_to_my_topic]" value="yes" <?php if ( !get_usermeta( $current_user->id, 'ass_replies_to_my_topic') || 'yes' == get_usermeta( $current_user->id, 'ass_replies_to_my_topic') ) { ?>checked="checked" <?php } ?>/></td>
			<td class="no"><input type="radio" name="notifications[ass_replies_to_my_topic]" value="no" <?php if ( 'no' == get_usermeta( $current_user->id, 'ass_replies_to_my_topic') ) { ?>checked="checked" <?php } ?>/></td>
		</tr>
		<tr>
			<td></td>
			<td><?php _e( 'A member replies after you in a forum topic', 'buddypress' ) ?></td>
			<td class="yes"><input type="radio" name="notifications[ass_replies_after_me_topic]" value="yes" <?php if ( !get_usermeta( $current_user->id, 'ass_replies_after_me_topic') || 'yes' == get_usermeta( $current_user->id, 'ass_replies_after_me_topic') ) { ?>checked="checked" <?php } ?>/></td>
			<td class="no"><input type="radio" name="notifications[ass_replies_after_me_topic]" value="no" <?php if ( 'no' == get_usermeta( $current_user->id, 'ass_replies_after_me_topic') ) { ?>checked="checked" <?php } ?>/></td>
		</tr>

		<?php do_action( 'ass_group_subscription_notification_settings' ); ?>
	</table>
<?php
}
add_action( 'bp_notification_settings', 'ass_group_subscription_notification_settings' );







//
//	WP BACKEND ADMIN SETTINGS
//


// TODO: rework this totally, move to an admin.php file
// Functions to add the backend admin menu to control changing default settings
function ass_admin_menu() {
	add_submenu_page( 'bp-general-settings', "Group Notifications", "Group Notifications", 'manage_options', 'ass_admin_options', "ass_admin_options" );
}
add_action('admin_menu', 'ass_admin_menu');

function ass_admin_options() {
	?>
	<div class="wrap">
		<h2>Group Notification Settings</h2>

		<form id="ass-admin-settings-form" method="post" action="<?php echo $bp->root_domain; ?>/?ass_admin_settings=1">
		
			<?php wp_nonce_field( 'ass_admin_settings' ); ?>
				
			<table class="form-table">
			<?php //echo ass_admin_group_notification_settings_fields(); ?>
			</table>
			<br/>
			<p>To help protect against spam, you may wish to require a user to have been a member of the site for a certain amount of days before any group updates are emailed to the other group members.  By default, this is set to 3 days.  </p>
			Member must be registered for<input type="text" size="1" name="ass_registered_req" value="<?php echo get_site_option( 'ass_activity_frequency_ass_registered_req' ); ?>" style="text-align:center"/>days
			
			<p class="submit">
				<input type="submit" value="Save Settings" id="bp-admin-ass-submit" name="bp-admin-ass-submit" class="button-primary">
			</p>

		</form>
		
	</div>
	<?php
}
/*

// not used any more
function ass_admin_group_notification_settings_fields( ) {
	global $bp, $ass_activities;
	
	$output .= '<th><h3>Activity Type</h3></th><th><h3>Default Frequency of Email Notifications</h3></th>';
	
	foreach ( $ass_activities as $ass_activity ) {
		// Get the user meta for this activity type
		$site_setting = get_site_option( 'ass_activity_frequency_' . $ass_activity['type'] );
		if ( $site_setting == '' ) {
			$site_setting = $ass_activity['default_frequency'];
			update_site_option( 'ass_activity_frequency_' . $ass_activity['type'] , $site_setting );
		}
		$output .= '<tr>';
		$output .= '<td>';
		$output .= ucfirst($ass_activity['name_past']); 
		$output .= '</td>';
		$output .= '<td>';
		$output .= '<input type="radio" name="' . $ass_activity['type'] . '" value="' . 1*60 . '"';
		if ($site_setting == 1*60) $output .= 'checked="checked"';
		$output .= '/>1 min';
		$output .= '<input type="radio" name="' . $ass_activity['type'] . '" value="' . 15*60 . '"';
		if ($site_setting == 15*60) $output .= 'checked="checked"';
		$output .= '/>15 mins';
		$output .= '<input type="radio" name="' . $ass_activity['type'] . '" value="' . 60*60 . '"';
		if ($site_setting == 60*60) $output .= 'checked="checked"';
		$output .= '/>60 mins';
		$output .= '<input type="radio" name="' . $ass_activity['type'] . '" value="' . 12*60*60 . '"'; 
		if ($site_setting == 12*60*60) $output .= 'checked="checked"';
		$output .= '/>12 hours';
		$output .= '<input type="radio" name="' . $ass_activity['type'] . '" value="' . 24*60*60 . '"'; 
		if ($site_setting == 24*60*60) $output .= 'checked="checked"';
		$output .= '/>24 hours';
		$output .= '<input type="radio" name="' . $ass_activity['type'] . '" value="no"';
		if ($site_setting == 'no') $output .= 'checked="checked"';
		$output .= '/>Never';
		$output .= '</td></tr>';
	}	
	
	return $output;
}
*/

function ass_update_admin_settings() {
    global $bp, $wpdb, $ass_form_vars, $ass_activities;
	
	// Get the form vars
	ass_get_form_vars();  
	
	if ( $ass_form_vars['ass_admin_settings'] ) {
		// Check the nonce
		wp_verify_nonce('ass_admin_settings');
				
		// Make sure the user is site admin
		if ( !is_site_admin() ) {
			// If they're not, tell them so and then 'get the fudge out'
			bp_core_add_message( __( 'You are not allowed to do that.', 'buddypress' ), 'error' );
			bp_core_redirect( $bp->root_domain );
		}
		
		// Process each of the user setting changes
		foreach ( $ass_form_vars as $key=>$ass_form_var ) {
			// Check to get rid of the ass_admin_settings from fields.  Oops.
			if ( $key == 'ass_admin_settings' ) continue;
			
			// Input field for member age of registration is dealt with slightly differently
			if ( $key == 'ass_registered_req' ) {
				if ( $ass_form_var > 0 ) {
					update_site_option( 'ass_activity_frequency_' . $key , $ass_form_var );
				} else {
					update_site_option( 'ass_activity_frequency_' . $key , 'n/a' );
				}
				continue;
			}
			
			if ( $ass_form_var > 0 && $ass_form_var < 24*60*60+1 ) {
				update_site_option( 'ass_activity_frequency_' . $key , $ass_form_var );
			} else {
				update_site_option( 'ass_activity_frequency_' . $key , 'no' );
			}
		}
		// End of user settings change process
		
		bp_core_add_message( __( 'Settings saved successfully', 'buddypress' ) );
		bp_core_redirect( wp_get_referer() );
	} 
	// If we get to this point the page request isn't our submitted form.  The rest of WP will load normally now
}
add_action('template_redirect', 'ass_update_admin_settings');  

// Digest functions
function ass_digest_options_cron($hook = '') {
	
	if($hook != '')
		$schedule = wp_get_schedule( $hook );
	else
		$schedule = '';
	$output .= '<table><th><h3>'.__('Digest Subscription','buddypress').'</h3></th>';
	$output .= '<tr><td><input name="ass_digest_scheduler" type="radio" value="daily"  /><span>' . __( 'Daily', 'bp-ass' ) . '</span>';
	$output .= '<input name="ass_digest_scheduler" type="radio" value="twicedaily"  /><span>' . __( 'Twicedaily', 'bp-ass' ) . '</span>';
	$output .= '<input name="ass_digest_scheduler" type="radio" value="hourly"  /><span>' . __( 'Hourly', 'bp-ass' ) . '</span></td></tr></table>';
	
	return $output;
}

// if a user updates the digest settings from the group notification page, this gets called 
function ass_digest_update_group_settings() {
    global $bp, $ass_form_vars;
            
	ass_get_form_vars(); 
	$group_id = $ass_form_vars[ 'ass_group_id' ];
	$user_id = $ass_form_vars[ 'ass_user_id' ];
	$action = $ass_form_vars[ 'ass_form' ];
	$digest_scheduler = $ass_form_vars[ 'ass_digest_scheduler' ];
	
	if ( $action && $group_id && $user_id && $digest_scheduler) {
	
		if ( !groups_is_user_member( $user_id , $group_id ) )
			return;
			
		wp_verify_nonce('ass_form');
		
		ass_digest_options_update_cron( $digest_scheduler, $hook ); // save the settings
		
		bp_core_add_message( __( $security.'You are now ' . $action . 'd for this group.', 'buddypress' ) );
		bp_core_redirect( wp_get_referer() );
	} 
}
add_action('template_redirect', 'ass_digest_update_group_settings');  


function ass_digest_options_update_cron( $new_schedule = 'daily', $hook = '') {

	if ( in_array( $new_schedule, array( 'daily', 'twicedaily', 'hourly' ) ) ) {
		$old_schedule = wp_get_schedule( $hook );

		if ( $new_schedule != $old_schedule ) {
			wp_unschedule_event( wp_next_scheduled( $hook ), $hook );

			wp_schedule_event( time(), $new_schedule, $hook );

		}

	}

}

function my_activation ( ) {
wp_schedule_event ( time ( ) , 'hourly', 'my_hourly_event' ) ;
wp_schedule_event(time(), 'daily', 'my_daily_event');
wp_schedule_event(time(), 'weekly', 'my_weekly_event');
}

function do_this() {
	// will check the groupmeta table looking for the meta_key=ass_digest_users and the users of the array hourly, daily or weekly, depends of $new_schedule
}

?>