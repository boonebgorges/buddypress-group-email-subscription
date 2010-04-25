<?php

require_once( WP_PLUGIN_DIR.'/buddypress-group-email-subscription/bp-activity-subscription-digest.php' );

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
/* Refactored by Boone to hook to bp_after_activity_save */
function ass_group_notification_new_forum_topic( $content ) {
	global $bp;
	
	/* New forum topics only */
	if ( $content->type != 'new_forum_topic' )
		return;	

	/* Check to see if user has been registered long enough */
	if ( !ass_registered_long_enough( $bp->loggedin_user->id ) ) 
		return;
	
	/* Subject */
	/* Strip the final comma from the action, if it exists */
	if ( substr( $content->action, -1 ) == ':' )
		$subject = substr( $content->action, 0, -1 );
	else
		$subject = $content->action;
	
	$subject .= ' [' . get_blog_option( BP_ROOT_BLOG, 'blogname' ) . ']';
	
	/* Content */
	$the_content = strip_tags( stripslashes( $content->content ) );
	
	$message = sprintf( __(
'%s

"%s"

To view or reply to this topic, follow the link below:
%s

---------------------
', 'bp-ass' ), $content->action, $the_content, $content->primary_link );

	/* Content footer */
	$settings_link = $bp->root_domain . '/' . $bp->groups->slug . '/' . $bp->groups->current_group->slug . '/notifications/';
	$message .= sprintf( __( 'To disable these notifications please log in and go to: %s', 'buddypress' ), $settings_link );
	
	$group_id = $content->item_id;
	
	if ( !$subscribed_users = groups_get_groupmeta( $group_id , 'ass_subscribed_users' ) )
		$subscribed_users = array();
			
	if ( !$digest_subscribers = groups_get_groupmeta( $group_id, 'ass_digest_subscribers' ) )
		$digest_subscribers = array();
	
	// cycle through subscribed members and send an email
	foreach ( $subscribed_users as $user_id => $value ) { 		

		if ( $user_id == $bp->loggedin_user->id )  // don't send email to topic author	
			continue;

		if ( $value != 'sub' && $value != 'supersub' )  // only send if the user is subscribed
			continue;
		
		if ( in_array( $user_id, $digest_subscribers ) ) {
			ass_digest_record_activity( $content->id, $user_id, $group_id );
		} else {
			$user = bp_core_get_core_userdata( $user_id ); // Get the details for the user
			wp_mail( $user->user_email, $subject, $message );  // Send the email
		}
		//echo '<br>Email: ' . $user->user_email;
	}	
}

add_action( 'bp_activity_after_save', 'ass_group_notification_new_forum_topic' );




// send email notificaitons for new forum posts to members who are supersubscribed to the group or subscribed to this topic
/* Refactored by Boone to hook to bp_after_activity_save */
function ass_group_notification_new_forum_topic_post( $content ) {
	global $bp;

	/* New forum posts only */
	if ( $content->type != 'new_forum_post' )
		return;
		
	/* Check to see if user has been registered long enough */
	if ( !ass_registered_long_enough( $bp->loggedin_user->id ) )
		return;

	/* Subject */
	/* Strip the final comma from the action, if it exists */
	if ( substr( $content->action, -1 ) == ':' )
		$subject = substr( $content->action, 0, -1 );
	else
		$subject = $content->action;
	
	$subject .= ' [' . get_blog_option( BP_ROOT_BLOG, 'blogname' ) . ']';

	/* Content */
	$the_content = strip_tags( stripslashes( $content->content ) );
	
	$message = sprintf( __(
'%s

"%s"

To view or reply to this topic, follow the link below:
%s

---------------------
', 'bp-ass' ), $content->action, $the_content, $content->primary_link );

	/* Content footer */
	$settings_link = $bp->root_domain . '/' . $bp->groups->slug . '/' . $bp->groups->current_group->slug . '/notifications/';
	$message .= sprintf( __( 'To disable these notifications please log in and go to: %s', 'buddypress' ), $settings_link );

	$group_id = $content->item_id;
	
	if ( !$subscribed_users = groups_get_groupmeta( $group_id , 'ass_subscribed_users' ) )
		$subscribed_users = array();
			
	if ( !$digest_subscribers = groups_get_groupmeta( $group_id, 'ass_digest_subscribers' ) )
		$digest_subscribers = array();

	$post = bp_forums_get_post( $content->secondary_item_id );	
	$topic = get_topic( $post->topic_id );
	
	$user_topic_status = groups_get_groupmeta( $bp->groups->current_group->id , 'ass_user_topic_status_' . $topic->topic_id );
	$previous_posters = ass_get_previous_posters( $post->topic_id );	
	
	// Deryk - I changed this so as not to call up the entire group roster
	foreach ( $subscribed_users as $user_id => $value ) { 
		if ( $user_id == $bp->loggedin_user->id )  // don't send email to topic author	
			continue;
		
		$send_it = NULL;
		$group_status = $value; // get group and topic status for each user
		$topic_status = $user_topic_status[ $user_id ];
	
	 	//echo '<p>uid:' . $user_id .' | gstat:' . $group_status . ' | tstat:'.$topic_status . ' | owner:'.$topic->topic_poster . ' | prev:'.$previous_posters[ $user_id ];
		
		if ( $topic_status == 'mute' )  // the topic mute button will override the subscription options below
			continue;
		
		if ( $group_status == 'supersub' || $topic_status == 'sub' ) 
			$send_it = true;	
		else if ( $topic->topic_poster == $user_id && get_usermeta( $user_id, 'ass_replies_to_my_topic' ) != 'no' ) // Deryk - it's probably a good idea to move this information (as well as ass_replies_after_me) into a blog option associative array to cut WAY down on db hits
			$send_it = true;
		else if ( $previous_posters[ $user_id ] && get_usermeta( $user_id, 'ass_replies_after_me_topic')  != 'no' )
			$send_it = true;
		
		if ( $send_it ) {
			if ( in_array( $user_id, $digest_subscribers ) ) {
				ass_digest_record_activity( $content->id, $user_id, $group_id );
			} else {
				$user = bp_core_get_core_userdata( $user_id ); // Get the details for the user
				wp_mail( $user->user_email, $subject, $message );  // Send the email
				//echo '<br>Email: ' . $user->user_email;
			}
		}
		
	}
	
	//echo '<p>Subject: ' . $subject;
	//echo '<pre>'; print_r( $message ); echo '</pre>';
}
add_action( 'bp_activity_after_save', 'ass_group_notification_new_forum_topic_post' );




//
// SEND EMAIL UDPATES FOR ACTIVITY AND GENERIC ACTIVITY
//


// returns assoc array of activity types, used in sending emails
// other plugins can add thier activity types here. 

/* Deryk - I think that this can all be taken out. The language for core components is recorded along with the activity item (advantage of hooking to bp_activity_after_save). Moreover, plugins should be registering their notifications with BP using bp_add_core_notification or whatever it is. Putting the functionality here in the email plugin is redundant.

The only question is whether a plugin's notifications should be sent to non-super-subscribed users. I think it makes sense to default to yes. */

/* function ass_activity_types() {
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
add_filter( 'ass_activity_types', 'my_fun_activity_filter' ); */




// The email notification function for other activities
function ass_notify_group_members( $content ) {
	global $bp;
	$type = $content->type;	
		
	if ( $content->component != 'groups' || $type == 'new_forum_topic' || $type == 'new_forum_post' || $type == 'created_group' )
		return;
	
	//echo '<pre>'; print_r( $params ); echo '</pre>';	
	
	if ( !ass_registered_long_enough( $bp->loggedin_user->id ) )
		return;

	/* Subject */
	/* Strip the final comma from the action, if it exists */
	if ( substr( $content->action, -1 ) == ':' )
		$subject = substr( $content->action, 0, -1 );
	else
		$subject = $content->action;
	
	$subject .= ' [' . get_blog_option( BP_ROOT_BLOG, 'blogname' ) . ']';



	/* Content */

	$group_id = $content->item_id;
	$the_content = strip_tags( stripslashes( $content->content ) );
	
	/* If it's an activity item, switch the activity permalink to the group homepage rather than the user's homepage */
	$activity_permalink = ( isset( $content->primary_link ) && $content->primary_link != bp_core_get_user_domain( $content->user_id ) ) ? $content->primary_link : bp_get_group_permalink( $bp->groups->current_group );
	
	$message = sprintf( __(
'%s

"%s"

To view or reply, follow the link below:
%s

---------------------
', 'bp-ass' ), $content->action, $the_content, $activity_permalink );

	/* Content footer */
	$settings_link = $bp->root_domain . '/' . $bp->groups->slug . '/' . $bp->groups->current_group->slug . '/notifications/';
	$message .= sprintf( __( 'To disable these notifications please log in and go to: %s', 'buddypress' ), $settings_link );


	
	if ( !$subscribed_users = groups_get_groupmeta( $group_id , 'ass_subscribed_users' ) )
		$subscribed_users = array();
			
	if ( !$digest_subscribers = groups_get_groupmeta( $group_id, 'ass_digest_subscribers' ) )
		$digest_subscribers = array();
	
	//$user_ids = BP_Groups_Member::get_group_member_ids( $group_id );
	
	/*
	$activity_user_name = $bp->loggedin_user->fullname;
	$activity_link = $params['primary_link'];
	$activity_content = strip_tags( stripslashes( $params['content'] ) );

	$activity_type_data = ass_activity_types();
	$activity_name_past = $activity_type_data[ $type ][ 'name_past' ];
	$activity_sub_level = $activity_type_data[ $type ][ 'level' ]; */
	
	//$group = new BP_Groups_Group( $group_id, false, true );	
	
	// cycle through all group members and send them an email depending on their individual settings.
	foreach ( $subscribed_users as $user_id => $value ) { 
			
		if ( $user_id == $bp->loggedin_user->id )  // don't send email to topic author	
			continue;
			
		// it would be good to create a way for users to GLOBALY set their preference for super/sub/unsub for the misc activity types			
		$group_sub_status = $subscribed_users[ $user_id ];
		
		//echo '<p>uid: ' . $user_id .' | gstat: ' . $group_sub_status . ' | actlvl: '. $activity_sub_level . ' | glvlsubstr: ' . substr( $group_sub_status, 5, 8 );
		
		/* Removing this $activity_sub_level business for the time being - see note above about redundancy */
		// email if the subscriptions levels are the same OR if the activity level is 'sub' and the users subscription level is 'supersub'
		// if ( $activity_sub_level == $group_sub_status || $activity_sub_level == substr( $group_sub_status, 5, 8 ) ) {
			if ( in_array( $user_id, $digest_subscribers ) ) {
				ass_digest_record_activity( $content->id, $user_id, $group_id );
			} else {
				$user = bp_core_get_core_userdata( $user_id ); // Get the details for the user
				wp_mail( $user->user_email, $subject, $message );  // Send the email
				//echo '<br>Email: ' . $user->user_email . "<br>";
				//print_r($message);
			}
		
			
			//echo '<br>Email: ' . $user->user_email;	
		//}

	}
	
	//echo '<p>Subject: ' . $subject;
	//echo '<pre>'; print_r( $message ); echo '</pre>';	
}
add_action( 'bp_activity_after_save' , 'ass_notify_group_members' , 50 );






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

	$digest_sub_button = __( 'Switch to digests', 'bp-ass' );
	$digest_unsub_button = __( 'Turn digests off', 'bp-ass' );

	if ( !$digest_period = get_option( 'ass_digest_period' ) )
		$digest_period = '24';
	
	$digest_sub_text = sprintf ( __( 'You are currently receiving individual notification emails for each activity item you are subscribed to. Click this button to receive digests instead, which send a summary of all activity once every %s hours.', 'bp-ass' ), $digest_period );
	
	$digest_unsub_text = __( 'You are currently receiving activity digests for this group. Click this button to receive individual emails for each activity item.', 'bp-ass' );

	if ( !$group )
		$group = $bp->groups->current_group;
	
	if ( !is_user_logged_in() || $group->is_banned || !$group->is_member )
		return false;
		
	$gsub_type = ass_get_group_subscription_status( $bp->loggedin_user->id, $group->id );
	
	if ( !$subscribers = groups_get_groupmeta( $group->id, 'ass_digest_subscribers' ) )
		$subscribers = array();
	
	?>
	<form action="<?php echo $bp->root_domain; ?>/?ass_subscribe_form=1" name="group-subscribe-form" method="post">
	<input type="hidden" name="ass_group_id" value="<?php echo $bp->groups->current_group->id; ?>"/>
	<?php wp_nonce_field( 'ass_subscribe' ); ?>
	<table class="ass-group-sub-settings-table"><tr>
	<td>
		<p><b>Get everything</b></p>
		<?php if ( $gsub_type == 'supersub' ) { ?>
			<p><a class="button">Super-subscribe</a><div class="ass-group-sub-settings-status">You are Super-Subscribed</div></p>
		<?php } else { ?>
			<p><input type="submit" class="generic-button" name="ass_group_subscribe" value="Super-subscribe"></p>
		<?php } ?>
		<p>Get email notifications for all new group content including comments and replies.</p>		
		<p>Note: here would be a good place to list the things they will actually get
	</td><td>
		<p><b>Get content, no conversations</b></p>
		<?php if ( $gsub_type == 'sub' ) { ?>
			<p><a class="button">Subscribe</a><br><div class="ass-group-sub-settings-status">You are Subscribed</div></p>
		<?php } else { ?>
			<p><input type="submit" class="generic-button" name="ass_group_subscribe" value="Subscribe">
		<?php } ?>
		<p>Get email notifications for all new group content, but not for comments or replies.</p>
	</td><td>
		<p><b>Get nothing</b></p>
		<?php if ( !$gsub_type || $gsub_type == 'un' ) { ?>
			<p><a class="button">Unsubscribe</a><br><div class="ass-group-sub-settings-status">You are Unsubscribed</div></p>
		<?php } else { ?>
			<p><input type="submit" class="generic-button" name="ass_group_subscribe" value="Unsubscribe">
		<?php } ?>
		<p>You can visit the group website to stay up-to-date.*</p>
	</td>
	</tr></table>
	<p class="ass-sub-note">* By default users receive notifications for topics they start or comment on. This can be changed at your <a href="<?php echo $bp->loggedin_user->domain . 'settings/notifications/' ?>"> email notifications</a> page.</p>

	
	
	
	<h4><?php _e( 'Email frequency', 'bp-ass' ) ?></h4>
	<?php if ( !in_array( $bp->loggedin_user->id, $subscribers ) ) : ?>
		<p><?php echo $digest_sub_text ?> <input type="submit" class="generic-button" name="ass_digest_toggle" value="<?php echo $digest_sub_button ?>" />
	<?php else : ?>
		<p><?php echo $digest_unsub_text ?> <input type="submit" class="generic-button" name="ass_digest_toggle" value="<?php echo $digest_unsub_button ?>" />
	<?php endif; ?>
	
	</form>
	
	
	
	
	
<?php /*	
	
<p><hr></p>
	<form action="<?php echo $bp->root_domain; ?>/?ass_form=1" id="activity-subscription-settings-form" name="activity-subscription-settings-form" method="post">
		<h3>Digest Options</h3>
		<input type="hidden" name="ass_group_id" value="<?php echo $bp->groups->current_group->id; ?>"/>
		<input type="hidden" name="ass_user_id" value="<?php echo $bp->loggedin_user->id; ?>"/>
		<?php wp_nonce_field( 'ass_form' ); ?>
		
		<?php 
		if ( $gsub_type )
			echo ass_digest_options_cron(); 
		else
			echo 'Digest options are available for subscribed users. Click subscribe above to view options. ';
		
		?>
		<br><input type="submit" name="submit" value="Save Digest Settings" />
	</form>
	*/ ?>
	
	
	<?php
}



// if a user updates the subscription settings from the group notification page, this gets called 
function ass_update_group_subscribe_settings() {
    global $bp, $ass_form_vars;
            
	ass_get_form_vars(); 
	$user_id = $bp->loggedin_user->id;
	$group_id = $ass_form_vars[ 'ass_group_id' ];
	$action = $ass_form_vars[ 'ass_group_subscribe' ];
	$digest_toggle = $ass_form_vars['ass_digest_toggle'];
	//print_r($ass_form_vars); die();
	if ( $group_id && $user_id && $digest_toggle ) {
	
		if ( !groups_is_user_member( $user_id , $group_id ) )
			return;
			
		wp_verify_nonce('ass_form');
	
		if ( !$subscribers = groups_get_groupmeta( $group_id, 'ass_digest_subscribers' ) )
			$subscribers = array();
		
		if ( !in_array( $user_id, $subscribers ) ) {
			$subscribers[] = $user_id;
		} else {
			$key = array_search( $user_id, $subscribers );
			unset( $subscribers[$key] );
		}
		
		groups_update_groupmeta( $group_id, 'ass_digest_subscribers', $subscribers );
		
		
//		ass_digest_options_update_cron( $digest_scheduler, $hook ); // save the settings
		
		bp_core_add_message( __( $security.'You are now ' . $action . 'd for this group.', 'buddypress' ) );
		bp_core_redirect( wp_get_referer() );
	} 
	
	
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
	
	if ( $gsub_type == 'un' )
		$gsub_type = NULL;
		
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
		
	$group_user_subscriptions = groups_get_groupmeta( $group_id , 'ass_subscribed_users' );
	
	if ( $action == 'gsubscribe' || $action == 'Subscribe' || $action == 'subscribe' ) {
		$group_user_subscriptions[ $user_id ] = 'sub';
	} elseif ( $action == 'gsupersubscribe' || $action == 'Super-subscribe' || $action == 'supersubscribe' ) {
		$group_user_subscriptions[ $user_id ] = 'supersub';
	} elseif ( $action == 'gunsubscribe' || $action == 'Unsubscribe' || $action == 'unsubscribe' ) {
		$group_user_subscriptions[ $user_id ] = 'un';
	} elseif ( $action == 'delete' || $action == 'leave' ) {
		unset( $group_user_subscriptions[ $user_id ] );
	}
	
	groups_update_groupmeta( $group_id , 'ass_subscribed_users', $group_user_subscriptions );
}


function ass_unsubscribe_on_leave( $group_id, $user_id ){
	ass_group_subscription( 'delete', $user_id, $group_id );
}
add_action( 'groups_leave_group', 'ass_unsubscribe_on_leave', 100, 2 );


// show subscription status on group member pages (for admins and mods only)
function ass_show_subscription_status_in_member_list() {
	global $bp, $members_template;
	
	$group_id = $bp->groups->current_group->id;
	
	if ( !groups_is_user_admin( $bp->loggedin_user->id , $group_id ) && !groups_is_user_mod( $bp->loggedin_user->id , $group_id ) )
		return;
		
	if ( $sub_type = ass_get_group_subscription_status( $members_template->member->user_id, $group_id ) ) {
		echo '<div class="ass_member_list_sub_status">'. ucfirst($sub_type) .'scribed</div>';
	}
}
add_action( 'bp_group_members_list_item_action', 'ass_show_subscription_status_in_member_list', 100 );

//
//	Default Group Subscription
//


// when a user joins a group, set their default subscription level
function ass_set_default_subscription( $groups_member ){
	global $bp;
	
	// only set the default if the user has no subscription history for this group
	if ( ass_get_group_subscription_status( $groups_member->user_id, $groups_member->group_id ) )
		return;
	
	if ( $default_gsub = groups_get_groupmeta( $groups_member->group_id, 'ass_default_subscription' ) ) {
		ass_group_subscription( $default_gsub, $groups_member->user_id, $groups_member->group_id );
	}
}
add_action( 'groups_member_after_save', 'ass_set_default_subscription', 20, 1 );


// give the user a notice if they are default subscribed to this group (does not work for invites or requests)
function ass_join_group_message( $group_id, $user_id ) {
	global $bp;
	if ( groups_get_groupmeta( $group_id, 'ass_default_subscription' ) != 'gunsubscribe' && $user_id == $bp->loggedin_user->id )
		bp_core_add_message( __( 'You successfully joined the group. You are subscribed via email to new group content.', 'buddypress' ) );
}
add_action( 'groups_join_group', 'ass_join_group_message', 100, 2 );




// create the default subscription settings during group creation and editing
function ass_default_subscription_settings_form() {
	?>
	<h4>Email Subscription Defaults</h4>
	<p>When joining this group, the email settings for new members will be (this setting will not affect existing members):</p>
	<div class="radio">
		<label><input type="radio" name="ass-default-subscription" value="gunsubscribe" <?php ass_default_subscription_settings( 'gunsubscribe' ) ?> /> <?php _e( 'Not subscribed (no emails - recommended for large groups)', 'bp_ges' ) ?></label>
		<label><input type="radio" name="ass-default-subscription" value="gsubscribe" <?php ass_default_subscription_settings( 'gsubscribe' ) ?> /> <?php _e( 'Subscribed (Get emails about content, but no conversations)', 'bp_ges' ) ?></label>
		<label><input type="radio" name="ass-default-subscription" value="gsupersubscribe" <?php ass_default_subscription_settings( 'gsupersubscribe' ) ?> /> <?php _e( 'Super-subscribed (get emails about everything - only use for small groups)', 'bp_ges' ) ?></label>
	</div>
	<hr />
	<?php
}
add_action ( 'bp_after_group_settings_admin' ,'ass_default_subscription_settings_form' );
add_action ( 'bp_after_group_settings_creation_step' ,'ass_default_subscription_settings_form' );


// echo subscription default checked setting for the group admin settings - default to 'unsubscribed' in group creation
function ass_default_subscription_settings( $setting ) {
	$stored_setting = ass_get_default_subscription();
	
	if ( $setting == $stored_setting )
		echo ' checked="checked"';
	else if ( $setting == 'gunsubscribe' && !$stored_setting )
		echo ' checked="checked"';
}


// Get the default subscription settings for the group
function ass_get_default_subscription( $group = false ) {
	global $groups_template;
	if ( !$group )
		$group =& $groups_template->group;
	$default_subscription =  groups_get_groupmeta( $group->id, 'ass_default_subscription' );
	return apply_filters( 'ass_get_default_subscription', $default_subscription );
}


// Save the announce group setting in the group meta, if normal, delete it
function ass_save_default_subscription( $group ) { 
	global $bp, $_POST;
	
	if ( $postval = $_POST['ass-default-subscription'] ) {
		if ( $postval == 'gsubscribe' || $postval == 'gsupersubscribe' )  // this is overly safe
			groups_update_groupmeta( $group->id, 'ass_default_subscription', $postval );
		elseif ( $postval == 'gunsubscribe' )
			groups_delete_groupmeta( $group->id, 'ass_default_subscription' );
	}
}
add_action( 'groups_group_after_save', 'ass_save_default_subscription' );









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
add_action( 'bp_before_group_forum_topic_posts', 'ass_topic_follow_or_mute_link' );
add_action( 'bp_after_group_forum_topic_posts', 'ass_topic_follow_or_mute_link' );



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
	$public_query_vars[] = 'ass_digest_toggle';
	
	//foreach ( $ass_activities as $ass_activity ) {
	//	$public_query_vars[] = $ass_activity['type'];
	//}

	return ($public_query_vars);
}
add_filter('query_vars', 'ass_form_vars');


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
	
	if( get_query_var( 'ass_digest_toggle' ) ) {
		$ass_form_vars['ass_digest_toggle'] = mysql_real_escape_string(get_query_var('ass_digest_toggle'));  
	}
	
	if( get_query_var( 'ass_digest_frequency' ) ) {
		$ass_form_vars['ass_digest_frequency'] = mysql_real_escape_string(get_query_var('ass_digest_frequency'));  
	}
	
	if( get_query_var( 'ass_next_digest' ) ) {
		$ass_form_vars['ass_next_digest'] = mysql_real_escape_string(get_query_var('ass_next_digest'));  
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

	//print_r($_POST); die();
	if ( $_POST )
		ass_update_dashboard_settings();
	
	if ( !$ass_digest_time = get_option( 'ass_digest_time' ) )
		$ass_digest_time = array( 'hours' => '05', 'minutes' => '00' );
	
	if ( !$ass_weekly_digest = get_option( 'ass_weekly_digest' ) )
		$ass_weekly_digest = 'Friday';
		
	$next = wp_next_scheduled( 'ass_digest_event' );
	$next = date( "r", $next );
	?>
	<div class="wrap">
		<h2>Group Notification Settings</h2>

		<form id="ass-admin-settings-form" method="post" action="admin.php?page=ass_admin_options">
		
		<h3>General settings</h3>
		
			<?php wp_nonce_field( 'ass_admin_settings' ); ?>
				
			<table class="form-table">
			<?php //echo ass_admin_group_notification_settings_fields(); ?>
			</table>
			<br/>
			<p>To help protect against spam, you may wish to require a user to have been a member of the site for a certain amount of days before any group updates are emailed to the other group members.  By default, this is set to 3 days.  </p>
			Member must be registered for<input type="text" size="1" name="ass_registered_req" value="<?php echo get_site_option( 'ass_activity_frequency_ass_registered_req' ); ?>" style="text-align:center"/>days
		
		<h3><?php _e( 'Digest settings', 'bp-ass' ) ?></h3>
		<p><?php echo sprintf( __( 'The current server time is %s', 'bp-ass' ), date( r ) ) ?></p>
		
		<p>
			<label for="ass_digest_time"><?php _e( 'When should <strong>daily</strong> digests be sent?', 'bp-ass' ) ?> </label>
			<select name="ass_digest_time[hours]" id="ass_digest_time[hours]">
				<?php for( $i = 0; $i <= 23; $i++ ) : ?>
					<?php if ( $i < 10 ) $i = '0' . $i ?>
					<option value="<?php echo $i?>" <?php if ( $i == $ass_digest_time['hours'] ) : ?>selected="selected"<?php endif; ?>><?php echo $i ?></option>
				<?php endfor; ?>	
			</select>
			
			<select name="ass_digest_time[minutes]" id="ass_digest_time[minutes]">
				<?php for( $i = 0; $i <= 55; $i += 5 ) : ?>
					<?php if ( $i < 10 ) $i = '0' . $i ?>
					<option value="<?php echo $i?>" <?php if ( $i == $ass_digest_time['minutes'] ) : ?>selected="selected"<?php endif; ?>><?php echo $i ?></option>
				<?php endfor; ?>	
			</select>
		</p>
		
		<p>
			<label for="ass_weekly_digest"><?php _e( 'When should <strong>weekly</strong> digests be sent?', 'bp-ass' ) ?> </label>
			<select name="ass_weekly_digest" id="ass_weekly_digest">
				<?php /* disabling "no weekly digest" option for now because it will complicate the individual settings pages */ ?>
				<?php /* <option value="No weekly digest" <?php if ( 'No weekly digest' == $ass_weekly_digest ) : ?>selected="selected"<?php endif; ?>><?php _e( 'No weekly digest', 'bp-ass' ) ?></option> */ ?>
				<option value="1" <?php if ( '1' == $ass_weekly_digest ) : ?>selected="selected"<?php endif; ?>><?php _e( 'Monday' ) ?></option>
				<option value="2" <?php if ( '2' == $ass_weekly_digest ) : ?>selected="selected"<?php endif; ?>><?php _e( 'Tuesday' ) ?></option>
				<option value="3" <?php if ( '3' == $ass_weekly_digest ) : ?>selected="selected"<?php endif; ?>><?php _e( 'Wednesday' ) ?></option>
				<option value="4" <?php if ( '4' == $ass_weekly_digest ) : ?>selected="selected"<?php endif; ?>><?php _e( 'Thursday' ) ?></option>
				<option value="5" <?php if ( '5' == $ass_weekly_digest ) : ?>selected="selected"<?php endif; ?>><?php _e( 'Friday' ) ?></option>
				<option value="6" <?php if ( '6' == $ass_weekly_digest ) : ?>selected="selected"<?php endif; ?>><?php _e( 'Saturday' ) ?></option>
				<option value="0" <?php if ( '0' == $ass_weekly_digest ) : ?>selected="selected"<?php endif; ?>><?php _e( 'Sunday' ) ?></option>
			</select>				
		</p>
		
			<p class="submit">
				<input type="submit" value="Save Settings" id="bp-admin-ass-submit" name="bp-admin-ass-submit" class="button-primary">
			</p>

		</form>
		
	</div>
	<?php
}


function ass_update_dashboard_settings() {
	check_admin_referer( 'ass_admin_settings' );

	if ( $_POST['ass_registered_req'] != get_option( 'ass_registered_req' ) )
		update_option( 'ass_registered_req', $_POST['ass_registered_req'] );
	
	/* Todo: Process the new admin settings and turn them into real schedules */
	
	/* The daily digest time has been changed */
	if ( $_POST['ass_digest_time'] != get_option( 'ass_digest_time' ) ) {
		
		/* Concatenate the hours-minutes entered, and turn it into a timestamp today */
		$the_time = date( 'Y-m-d' ) . ' ' . $_POST['ass_digest_time']['hours'] . ':' . $_POST['ass_digest_time']['minutes'];
		$the_timestamp = strtotime( $the_time );
		
		/* If the time has already passed today, the next run will be tomorrow */
		$the_timestamp = ( $the_timestamp > time() ) ? $the_timestamp : (int)$the_timestamp + 86400;
		
		/* Clear the old recurring event and set up a new one */
		wp_clear_scheduled_hook( 'ass_digest_event' );	
		wp_schedule_event( $the_timestamp, 'daily', 'ass_digest_event' );
		
		/* Finally, save the option */
		update_option( 'ass_digest_time', $_POST['ass_digest_time'] );
	}
	
	/* The weekly digest day has been changed */
	if ( $_POST['ass_weekly_digest'] != get_option( 'ass_weekly_digest' ) ) {
		
		if ( !$next_weekly = wp_next_scheduled( 'ass_digest_event_weekly' ) )
			$next_weekly = wp_next_scheduled( 'ass_digest_event' ); 
		
		while ( date( 'w', $next_weekly ) != $_POST['ass_weekly_digest'] ) {
			$next_weekly += 86400;
		}
		
		/* Clear the old recurring event and set up a new one */
		wp_clear_scheduled_hook( 'ass_digest_event_weekly' );	
		wp_schedule_event( $next_weekly, 'weekly', 'ass_digest_event_weekly' );
		
		/* Finally, save the option */
		update_option( 'ass_weekly_digest', $_POST['ass_weekly_digest'] );
	}
	
	
	
//print_r($_POST);
}

function ass_custom_digest_frequency() {
	if ( !$freq = get_option( 'ass_digest_frequency' ) )
		return array();
		
	$freq_name = $freq . '_hrs';
	
	return array(
		$freq_name => array('interval' => $freq * 3600, 'display' => "Every $freq hours" )
	);
}
add_filter( 'cron_schedules', 'ass_custom_digest_frequency' );




// These are all BP functions, which don't work very well in the WP backend. I've written another function ass_update_dashboard_settings() to handle this stuff.
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



// if a user updates the digest settings from the group notification page, this gets called 
// BG: Now handled in ass_update_group_subscribe_settings
/* 
function ass_digest_update_group_settings() {
    global $bp, $ass_form_vars;
            
	ass_get_form_vars(); 
	$group_id = $ass_form_vars[ 'ass_group_id' ];
	$user_id = $ass_form_vars[ 'ass_user_id' ];
	$action = $ass_form_vars[ 'ass_form' ];
	$digest_toggle = $ass_form_vars['ass_digest_toggle'];
	
	if ( $action && $group_id && $user_id && $digest_toggle ) {
	
		if ( !groups_is_user_member( $user_id , $group_id ) )
			return;
			
		wp_verify_nonce('ass_form');
	
		if ( !$subscribers = groups_get_groupmeta( $group_id, 'ass_subscribed_users' ) )
			$subscribers = array();
		
		if ( !in_array( $user_id, $subscribers ) ) {
			$subscribers[] = $user_id;
		} else {
			$key = array_search( $user_id, $subscribers );
			unset( $subscribers[$key] );
		}
		
		groups_update_groupmeta( $group_id, 'ass_subscribed_users', $subscribers );
		
		
//		ass_digest_options_update_cron( $digest_scheduler, $hook ); // save the settings
		
		bp_core_add_message( __( $security.'You are now ' . $action . 'd for this group.', 'buddypress' ) );
		bp_core_redirect( wp_get_referer() );
	} 
}
add_action('template_redirect', 'ass_digest_update_group_settings');  
*/




?>