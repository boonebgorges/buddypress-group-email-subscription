<?php

//
// !SEND EMAIL UDPATES FOR FORUM TOPICS AND POSTS
//

// these hooks are a bit cludgy, but they work to ensure that only new posts get emailed out and post edits don't
// if the topic is new, set $ass_item_is_new to true and it will get sent, otherwise if an update it won't
function ass_item_is_new( $item ) {
	global $ass_item_is_new;
	$ass_item_is_new = true;
	return $item;
}
add_filter( 'group_forum_topic_forum_id_before_save', 'ass_item_is_new' );
// if the post is an update, set $ass_item_is_update to true and it will not get sent, otherwise it will
function ass_item_is_update( $item ) {
	global $ass_item_is_update;
	$ass_item_is_update = true;
	return $item;
}
add_filter( 'bp_activity_get_activity_id', 'ass_item_is_update' );



// send email notificaitons for new forum topics
function ass_group_notification_new_forum_topic( $content ) {
	global $bp, $ass_item_is_new;

	/* New forum topics only */
	if ( $content->type != 'new_forum_topic' )
		return;	

	/* Check to see if user has been registered long enough */
	if ( !ass_registered_long_enough( $bp->loggedin_user->id ) ) 
		return;
		
	/* Subject & Content */
	$action = ass_clean_subject( $content->action );
	$subject = $action . ' [' . get_blog_option( BP_ROOT_BLOG, 'blogname' ) . ']';
	$the_content = html_entity_decode( strip_tags( stripslashes( $content->content ) ) );
	
	$message = sprintf( __(
'%s

"%s"

To view or reply to this topic, log in and go to:
%s

---------------------
', 'bp-ass' ), $action . ':', $the_content, $content->primary_link );

	/* Content footer */
	$settings_link = $bp->root_domain . '/' . $bp->groups->slug . '/' . $bp->groups->current_group->slug . '/notifications/';
	$message .= sprintf( __( 'To disable these notifications please log in and go to: %s', 'bp-ass' ), $settings_link );
	
	$group_id = $content->item_id;	
	$subscribed_users = groups_get_groupmeta( $group_id , 'ass_subscribed_users' );
	
	// cycle through subscribed members and send an email
	foreach ( (array)$subscribed_users as $user_id => $group_status ) { 		

		if ( $user_id == $bp->loggedin_user->id )  // don't send email to topic author	
			continue;
		
		if ( $group_status == 'sub' || $group_status == 'supersub' )  {
		
			if ( !$ass_item_is_new ) //don't send emails for item edits (but do update the digest)
				continue;			
				
			$notice = "\n" . __('Your email setting for this group is: ', 'bp-ass') . ass_subscribe_translate( $group_status );
			
			if ( $group_status == 'sub' ) // until we get a real follow link, this will have to do
				$notice .= __(", therefore you won't receive replies to this topic. To get them, click the link to view this topic on the web then click the 'Follow this topic' button.", 'bp-ass');
			
			$user = bp_core_get_core_userdata( $user_id );
			wp_mail( $user->user_email, $subject, $message . $notice );  // Send the email
			//echo '<br>Email: ' . $user->user_email;
			
		} elseif ( $group_status == 'dig' || $group_status == 'sum' ) {
		
			ass_digest_record_activity( $content->id, $user_id, $group_id, $group_status );
			
		}
		
	}	
	//echo '<p>Subject: ' . $subject;
	//echo '<pre>'; print_r( $message . $notice ); echo '</pre>';
}

add_action( 'bp_activity_after_save', 'ass_group_notification_new_forum_topic' );




// send email notificaitons for forum replies (or store for digest)
function ass_group_notification_forum_reply( $content ) {
	global $bp, $ass_item_is_update;

	/* New forum posts only */
	if ( $content->type != 'new_forum_post' )
		return;
	
	/* skip item edits */
	if ( $ass_item_is_update )
		return;	
		
	/* Check to see if user has been registered long enough */
	if ( !ass_registered_long_enough( $bp->loggedin_user->id ) )
		return;
	
	/* Subject & Content */
	$action = ass_clean_subject( $content->action );
	$subject = $action . ' [' . get_blog_option( BP_ROOT_BLOG, 'blogname' ) . ']';
	$the_content = html_entity_decode( strip_tags( stripslashes( $content->content ) ) );
	
	$message = sprintf( __(
'%s

"%s"

To view or reply to this topic, log in and go to:
%s

---------------------
', 'bp-ass' ), $action . ':', $the_content, $content->primary_link );

	/* Content footer */
	$settings_link = $bp->root_domain . '/' . $bp->groups->slug . '/' . $bp->groups->current_group->slug . '/notifications/';
	$message .= sprintf( __( 'To disable these notifications please log in and go to: %s', 'bp-ass' ), $settings_link );

	$group_id = $content->item_id;
	//$user_ids = BP_Groups_Member::get_group_member_ids( $group_id );
	$subscribed_users = groups_get_groupmeta( $group_id , 'ass_subscribed_users' );

	$post = bp_forums_get_post( $content->secondary_item_id );	
	$topic = get_topic( $post->topic_id );
	
	// pre-load these arrays to reduce db calls in the loop
	$ass_replies_to_my_topic = ass_user_settings_array( 'ass_replies_to_my_topic' );
	$ass_replies_after_me_topic = ass_user_settings_array( 'ass_replies_after_me_topic' );
	$user_topic_status = groups_get_groupmeta( $bp->groups->current_group->id , 'ass_user_topic_status_' . $topic->topic_id );
	$previous_posters = ass_get_previous_posters( $post->topic_id );

	// consolidate the arrays to speed up processing
	foreach ( array_keys( $previous_posters) as $previous_poster ) {
		if ( !$subscribed_users[ $previous_poster ] )
			$subscribed_users[ $previous_poster ] = 'prev-post';
	}
	
	foreach ( (array)$subscribed_users as $user_id => $group_status ) {
		if ( $user_id == $bp->loggedin_user->id )  // don't send email to topic author	
			continue;
		
		$send_it = NULL;
		//$group_status = $subscribed_users[ $user_id ]; // only need this if we're looping through user_ids
		$topic_status = $user_topic_status[ $user_id ];
	
	 	//echo '<p>uid:' . $user_id .' | gstat:' . $group_status . ' | tstat:'.$topic_status . ' | owner:'.$topic->topic_poster . ' | prev:'.$previous_posters[ $user_id ];
		
		if ( $topic_status == 'mute' )  // the topic mute button will override the subscription options below
			continue;
		
		if ( $group_status == 'sum' ) // skip if user set to weekly summary
			continue;
		
		if ( $group_status == 'supersub' )
			$send_it = true;	
		elseif ( $topic_status == 'sub' )
			$send_it = true;	
		elseif ( $topic->topic_poster == $user_id && $ass_replies_to_my_topic[ $user_id ] != 'no' )
			$send_it = true;
		elseif ( $previous_posters[ $user_id ] && $ass_replies_after_me_topic[ $user_id ] != 'no' )
			$send_it = true; 
		
		if ( $send_it ) {
			$notice = "\n" . __('Your email setting for this group is: ', 'bp-ass') . ass_subscribe_translate( $group_status );
			$user = bp_core_get_core_userdata( $user_id ); // Get the details for the user
			wp_mail( $user->user_email, $subject, $message . $notice );  // Send the email
			//echo '<br>Email: ' . $user->user_email;
		} 
		
		if ( $group_status == 'dig' ) {
			ass_digest_record_activity( $content->id, $user_id, $group_id, $group_status );
			//echo '<br>Digest: ' . $user_id;
		}
		
	}
	
	//echo '<p>Subject: ' . $subject;
	//echo '<pre>'; print_r( $message ); echo '</pre>';
}
add_action( 'bp_activity_after_save', 'ass_group_notification_forum_reply' );





// The email notification function for all other activity
// as of right now activity_comment is not caught because it is missing the proper group_id a track request has be added for this feature at http://trac.buddypress.org/ticket/2388
function ass_group_notification_activity( $content ) {
	global $bp;
	$type = $content->type;	
	
	/* all other activity notifications */	
	if ( $content->component != 'groups' || $type == 'new_forum_topic' || $type == 'new_forum_post' || $type == 'created_group' )
		return;

	if ( !ass_registered_long_enough( $bp->loggedin_user->id ) )
		return;
	
	if ( $type == 'joined_group' )	// TODO: in the future, it would be nice for admins to get this message
		return;
	
	/* Subject & Content */
	$action = ass_clean_subject( $content->action );
	$subject = $action . ' [' . get_blog_option( BP_ROOT_BLOG, 'blogname' ) . ']';
	$the_content = html_entity_decode( strip_tags( stripslashes( $content->content ) ) );
	
	// TODO: if there is no content, perhaps we should not send the email?
		
	/* If it's an activity item, switch the activity permalink to the group homepage rather than the user's homepage */
	$activity_permalink = ( isset( $content->primary_link ) && $content->primary_link != bp_core_get_user_domain( $content->user_id ) ) ? $content->primary_link : bp_get_group_permalink( $bp->groups->current_group );
	
	$message = sprintf( __(
'%s

"%s"

To view or reply, log in and go to:
%s

---------------------
', 'bp-ass' ), $action, $the_content, $activity_permalink );

	/* Content footer */
	$settings_link = $bp->root_domain . '/' . $bp->groups->slug . '/' . $bp->groups->current_group->slug . '/notifications/';
	$message .= sprintf( __( 'To disable these notifications please log in and go to: %s', 'bp-ass' ), $settings_link );

	$group_id = $content->item_id;
	$subscribed_users = groups_get_groupmeta( $group_id , 'ass_subscribed_users' );
	
	//if ( $type == 'joined_group' )  // a failed attempt at restricting group join email to admins and mods
	//	$group_admins_mods = ass_get_group_admins_mods( $group_id );
		
	$this_activity_is_important = apply_filters( 'ass_this_activity_is_important', false, $type );	
	
	// cycle through subscribed users
	foreach ( (array)$subscribed_users as $user_id => $group_status ) { 			
		//echo '<p>uid: ' . $user_id .' | gstat: ' . $group_status ;

		if ( $user_id == $bp->loggedin_user->id )  // don't send email to topic author	
			continue;
		
		//if ( $type == 'joined_group' ) // a failed attempt at restricting group joins to admins and mods
		//	if ( !$group_admins_mods[ $user_id ] )
		//		continue;
			
		// activity update notifications only go to Email and Digest. However plugin authors can make important activity updates get emailed out to Weekly summary and New topics by using the ass_group_notification_activity action hook. 
		
		if ( $group_status == 'supersub' || $group_status == 'sub' && $this_activity_is_important ) {
			$notice = "\n" . __('Your email setting for this group is: ', 'bp-ass') . ass_subscribe_translate( $group_status );
			$user = bp_core_get_core_userdata( $user_id );
			wp_mail( $user->user_email, $subject, $message . $notice );  // Send the email
			//echo '<br>EMAIL: ' . $user->user_email . "<br>";
		} elseif ( $group_status == 'dig' || $group_status == 'sum' && $this_activity_is_important ) {
			ass_digest_record_activity( $content->id, $user_id, $group_id, $group_status );
			//echo '<br>DIGEST: ' . $user_id . "<br>";
		}
	}
	
	//echo '<p>Subject: ' . $subject;
	//echo '<pre>'; print_r( $message ); echo '</pre>';	
}
add_action( 'bp_activity_after_save' , 'ass_group_notification_activity' , 50 );




// this funciton is used to include important activity updates from plugins for Topic only and Weekly Summary emails 
// plugin developers can write a similar one to include important updates such as adding documents, wiki pages, calenar events
// editing of these itmes or comments on them SHOULD NOT be included
function ass_default_important_things( $is_important, $type ) {
	// group documents send out their own email for adding new docs
	if ( $type == 'wiki_group_page_create' || $type == 'new_calendar_event' )
		$is_important = true;
	
	return $is_important;
}
add_filter( 'ass_this_activity_is_important', 'ass_default_important_things', 1, 2 );



//
//	!GROUP SUBSCRIPTION
//


// returns the subscription status of a user in a group
function ass_get_group_subscription_status( $user_id, $group_id ) {
	global $bp;
	
	if ( !$user_id )
		$bp->loggedin_user->id;
		
	if ( !$group_id )
		$bp->groups->current_group->id;
	
	$group_user_subscriptions = groups_get_groupmeta( $group_id, 'ass_subscribed_users' );
	return $group_user_subscriptions[ $user_id ];
}


// updates the group's user subscription list.
function ass_group_subscription( $action, $user_id, $group_id ) {
	if ( !$action || !$user_id || !$group_id )
		return false;
		
	$group_user_subscriptions = groups_get_groupmeta( $group_id , 'ass_subscribed_users' );
	
	// we're being overly careful here	
	if ( $action == 'no' ) {
		$group_user_subscriptions[ $user_id ] = 'no';
	} elseif ( $action == 'sum' ) {
		$group_user_subscriptions[ $user_id ] = 'sum';
	} elseif ( $action == 'dig' ) {
		$group_user_subscriptions[ $user_id ] = 'dig';
	} elseif ( $action == 'sub' ) {
		$group_user_subscriptions[ $user_id ] = 'sub';
	} elseif ( $action == 'supersub' ) {
		$group_user_subscriptions[ $user_id ] = 'supersub';
	} elseif ( $action == 'delete' ) {
		if ( $group_user_subscriptions[ $user_id ] )
			unset( $group_user_subscriptions[ $user_id ] );
	}
	
	groups_update_groupmeta( $group_id , 'ass_subscribed_users', $group_user_subscriptions );
}



// show group subscription settings on the notification page. 
function ass_group_subscribe_settings ( $group = false ) {
	global $bp, $groups_template;

	if ( !$group )
		$group = $bp->groups->current_group;
	
	if ( !is_user_logged_in() || $group->is_banned || !$group->is_member )
		return false;
		
	$group_status = ass_get_group_subscription_status( $bp->loggedin_user->id, $group->id );
	
	$submit_link = $bp->root_domain . '/' . $bp->groups->slug . '/' . $bp->groups->current_group->slug . '/notifications';
	
	?>
	<div id="ass-email-subscriptions-options-page">
	<h3 class="activity-subscription-settings-title"><?php _e('Email Subscription Options', 'bp-ass') ?></h3>
	<form action="<?php echo $submit_link ?>" method="post">
	<input type="hidden" name="ass_group_id" value="<?php echo $group->id; ?>"/>
	<?php wp_nonce_field( 'ass_subscribe' ); ?>
	
	<b><?php _e('How do you want to read this group?', 'bp-ass'); ?></b>
	
	<div class="ass-email-type">
	<label><input type="radio" name="ass_group_subscribe" value="no" <?php if ( $group_status == "no" || $group_status == "un" || !$group_status ) echo 'checked="checked"'; ?>><?php _e('No Email', 'bp-ass'); ?></label>
	<div class="ass-email-explain"><?php _e('I will read this group on the web', 'bp-ass'); ?></div>
	</div>
	
	<div class="ass-email-type">
	<label><input type="radio" name="ass_group_subscribe" value="sum" <?php if ( $group_status == "sum" ) echo 'checked="checked"'; ?>><?php _e('Weekly Summary Email', 'bp-ass'); ?></label>
	<div class="ass-email-explain"><?php _e('Get a summary of new topics each week', 'bp-ass'); ?></div>
	</div>
	
	<div class="ass-email-type">
	<label><input type="radio" name="ass_group_subscribe" value="dig" <?php if ( $group_status == "dig" ) echo 'checked="checked"'; ?>><?php _e('Daily Digest Email', 'bp-ass'); ?></label>
	<div class="ass-email-explain"><?php _e('Get all the day\'s activity bundled into a single email', 'bp-ass'); ?></div>
	</div>
	
	<div class="ass-email-type">
	<label><input type="radio" name="ass_group_subscribe" value="sub" <?php if ( $group_status == "sub" ) echo 'checked="checked"'; ?>><?php _e('New Topics Email', 'bp-ass'); ?></label>
	<div class="ass-email-explain"><?php _e('Send new topics as they arrive (but don\'t send replies)', 'bp-ass'); ?></div>
	</div>
	
	<div class="ass-email-type">
	<label><input type="radio" name="ass_group_subscribe" value="supersub" <?php if ( $group_status == "supersub" ) echo 'checked="checked"'; ?>><?php _e('All Email', 'bp-ass'); ?></label>
	<div class="ass-email-explain"><?php _e('Send all group activity as it arrives', 'bp-ass'); ?></div>
	</div>

	<input type="submit" value="<?php _e('Save Settings', 'bp-ass') ?>" id="ass-save" name="ass-save" class="button-primary">

	<p class="ass-sub-note"><?php _e('Note: Normally, you receive email notifications for topics you start or comment on. This can be changed at', 'bp-ass'); ?> <a href="<?php echo $bp->loggedin_user->domain . 'settings/notifications/' ?>"><?php _e('email notifications', 'bp-ass'); ?></a>.</p>
	
	</form>
	</div><!-- end ass-email-subscriptions-options-page -->
	<?php
}

// update the users' notification settings
function ass_update_group_subscribe_settings() {
	global $bp;
	
	if ( $bp->current_component == 'groups' && $bp->current_action == 'notifications' ) {

		// If the edit form has been submitted, save the edited details
		if ( isset( $_POST['ass-save'] ) ) {
		
			//if ( !wp_verify_nonce( $nonce, 'ass_subscribe' ) ) die( 'A Security check failed' );
			
			$user_id = $bp->loggedin_user->id;
			$group_id = $_POST[ 'ass_group_id' ];
			$action = $_POST[ 'ass_group_subscribe' ];
			
			if ( !groups_is_user_member( $user_id, $group_id ) )
				return;
				
			ass_group_subscription( $action, $user_id, $group_id ); // save the settings
			
			bp_core_add_message( __( $security.'Your email notifications are set to ' . ass_subscribe_translate( $action ) . ' for this group.', 'bp-ass' ) );
			bp_core_redirect( wp_get_referer() );	
		}
	}
}
add_action( 'wp', 'ass_update_group_subscribe_settings', 4 );



// translate the short code subscription status into a nicer version
function ass_subscribe_translate( $status ){
	if ( $status == 'no' || !$status )
		$output = __('No Email', 'bp-ass');
	elseif ( $status == 'sum' )
		$output = __('Weekly Summary', 'bp-ass');
	elseif ( $status == 'dig' )
		$output = __('Daily Digest', 'bp-ass');
	elseif ( $status == 'sub' )
		$output = __('New Topics', 'bp-ass');
	elseif ( $status == 'supersub' )
		$output = __('All Email', 'bp-ass');
	
	return $output;
}


// this adds the ajax-based subscription option in the group header, or group directory
function ass_group_subscribe_button( $group = false ) {
	global $bp, $groups_template;

	if ( !$group )
		$group =& $groups_template->group;

	if ( !is_user_logged_in() || $group->is_banned || !$group->is_member )
		return;
	
	// if we're looking at someone elses list of groups hide the subscription
	if ( $bp->displayed_user->id && ( $bp->loggedin_user->id != $bp->displayed_user->id ) )
		return;
	
	$group_status = ass_get_group_subscription_status( $bp->loggedin_user->id, $group->id );
	
	if ( $group_status == 'no' )
		$group_status = NULL;
		
	$status_desc = __('Your email status is ', 'bp-ass');
	$link_text = __('change', 'bp-ass');
	$gemail_icon_class = ' gemail_icon';
	$sep = '';
	
	if ( !$group_status ) {
		//$status_desc = '';
		$link_text = __('Get email updates', 'bp-ass');
		$gemail_icon_class = '';
		$sep = '';
	}
	
	$status = ass_subscribe_translate( $group_status );
	?>
		
	<div class="group-subscription-div">
		<span class="group-subscription-status-desc"><?php echo $status_desc; ?></span>
		<span class="group-subscription-status<?php echo $gemail_icon_class ?>" id="gsubstat-<?php echo $group->id; ?>"><?php echo $status; ?></span> <?php echo $sep; ?>	
		(<a class="group-subscription-options-link" id="gsublink-<?php echo $group->id; ?>" href="javascript:void(0);" title="<?php _e('Change your email subscription options for this group','bp-ass');?>"><?php echo $link_text; ?></a>)
		<span class="ajax-loader" id="gsubajaxload-<?php echo $group->id; ?>"></span>
	</div>
	<div class="generic-button group-subscription-options" id="gsubopt-<?php echo $group->id; ?>">
		<a class="group-sub" id="no-<?php echo $group->id; ?>"><?php _e('No Email', 'bp-ass') ?></a> <?php _e('I will read this group on the web', 'bp-ass') ?><br>
		<a class="group-sub" id="sum-<?php echo $group->id; ?>"><?php _e('Weekly Summary', 'bp-ass') ?></a> <?php _e('Get a summary of topics each', 'bp-ass') ?> <?php echo ass_weekly_digest_week(); ?><br>
		<a class="group-sub" id="dig-<?php echo $group->id; ?>"><?php _e('Daily Digest', 'bp-ass') ?></a> <?php _e('Get the day\'s activity bundled into one email', 'bp-ass') ?><br>
		<a class="group-sub" id="sub-<?php echo $group->id; ?>"><?php _e('New Topics', 'bp-ass') ?></a> <?php _e('Send new topics as they arrive (but no replies)', 'bp-ass') ?><br>
		<a class="group-sub" id="supersub-<?php echo $group->id; ?>"><?php _e('All Email', 'bp-ass') ?></a> <?php _e('Send all group activity as it arrives', 'bp-ass') ?><br>
		<a class="group-subscription-close" id="gsubclose-<?php echo $group->id; ?>"><?php _e('close', 'bp-ass') ?></a>
	</div>
	
	<?php
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


// if the user leaves the group, delete their subscription status
function ass_unsubscribe_on_leave( $group_id, $user_id ){
	ass_group_subscription( 'delete', $user_id, $group_id );
}
add_action( 'groups_leave_group', 'ass_unsubscribe_on_leave', 100, 2 );



//
//	!Default Group Subscription
//

// when a user joins a group, set their default subscription level
function ass_set_default_subscription( $groups_member ){
	global $bp;
		
	// only set the default if the user has no subscription history for this group
	if ( ass_get_group_subscription_status( $groups_member->user_id, $groups_member->group_id ) )
		return;

	//if the person has requested access to a private group but has not been approved, don't subscribe them
	if ( !$groups_member->is_confirmed )
		return;
	
	$default_gsub = apply_filters( 'ass_default_subscription_level', groups_get_groupmeta( $groups_member->group_id, 'ass_default_subscription' ), $groups_member->group_id );
	
	if ( $default_gsub ) {
		ass_group_subscription( $default_gsub, $groups_member->user_id, $groups_member->group_id );
	}
}
add_action( 'groups_member_after_save', 'ass_set_default_subscription', 20, 1 );


// give the user a notice if they are default subscribed to this group (does not work for invites or requests)
function ass_join_group_message( $group_id, $user_id ) {
	global $bp;
	
	if ( $user_id != $bp->loggedin_user->id  )
		return;
	
	$status = groups_get_groupmeta( $group_id, 'ass_default_subscription' );
	
	if ( !$status )
		$status = 'no';

	bp_core_add_message( __( 'You successfully joined the group. Your group email status is: ', 'bp-ass' ) . ass_subscribe_translate( $status ) );
	
}
add_action( 'groups_join_group', 'ass_join_group_message', 1, 2 );




// create the default subscription settings during group creation and editing
function ass_default_subscription_settings_form() {
	?>
	<h4><?php _e('Email Subscription Defaults', 'bp-ass'); ?></h4>
	<p><?php _e('When new users join this group, their default email notification settings will be:', 'bp-ass'); ?></p>
	<div class="radio">
		<label><input type="radio" name="ass-default-subscription" value="no" <?php ass_default_subscription_settings( 'no' ) ?> /> 
			<?php _e( 'No Email (users will read this group on the web - good for any group - the default)', 'bp-ass' ) ?></label>
		<label><input type="radio" name="ass-default-subscription" value="sum" <?php ass_default_subscription_settings( 'sum' ) ?> /> 
			<?php _e( 'Weekly Summary Email (the week\'s topics - good for large groups)', 'bp-ass' ) ?></label>
		<label><input type="radio" name="ass-default-subscription" value="dig" <?php ass_default_subscription_settings( 'dig' ) ?> /> 
			<?php _e( 'Daily Digest Email (all daily activity bundles in one email - good for medium-size groups)', 'bp-ass' ) ?></label>
		<label><input type="radio" name="ass-default-subscription" value="sub" <?php ass_default_subscription_settings( 'sub' ) ?> /> 
			<?php _e( 'New Topics Email (new topics are sent as they arrive, but not replies - good for small groups)', 'bp-ass' ) ?></label>
		<label><input type="radio" name="ass-default-subscription" value="supersub" <?php ass_default_subscription_settings( 'supersub' ) ?> /> 
			<?php _e( 'All Email (send emails about everything - recommended only for working groups)', 'bp-ass' ) ?></label>
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
	else if ( $setting == 'no' && !$stored_setting )
		echo ' checked="checked"';
}


// Save the default group subscription setting in the group meta, if no, delete it
function ass_save_default_subscription( $group ) { 
	global $bp, $_POST;
	
	if ( $postval = $_POST['ass-default-subscription'] ) {
		if ( $postval && $postval != 'no' )
			groups_update_groupmeta( $group->id, 'ass_default_subscription', $postval );
		elseif ( $postval == 'no' )
			groups_delete_groupmeta( $group->id, 'ass_default_subscription' );
	}
}
add_action( 'groups_group_after_save', 'ass_save_default_subscription' );


// Get the default subscription settings for the group
function ass_get_default_subscription( $group = false ) {
	global $groups_template;
	if ( !$group )
		$group =& $groups_template->group;
	$default_subscription =  groups_get_groupmeta( $group->id, 'ass_default_subscription' );
	return apply_filters( 'ass_get_default_subscription', $default_subscription );
}








//
//	!TOPIC SUBSCRIPTION
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
		$link_text = __('Follow','bp-ass');
		$title = __('You are not following this topic. Click to follow it and get email updates for new posts','bp-ass');
	} else if ( $topic_status == 'sub' || ( $group_status == 'supersub' && !$topic_status ) ) {
		$action = 'mute';
		$link_text = __('Mute','bp-ass');
		$title = __('You are following this topic. Click to stop getting email updates','bp-ass');
	} else {
		echo 'nothing'; // do nothing
	}
	
	if ( $topic_status == 'mute' )
		$title = __('This conversation is muted. Click to follow it','bp-ass');
			
	if ( $action && $bp->action_variables[0] == 'topic' ) { // we're viewing one topic
		echo '<div class="generic-button ass-topic-subscribe"><a title="'.$title.'" 
			id="'.$action.'-'.$topic_id.'">'.$link_text.' '.__('this topic','bp-ass').'</a></div>'; 
	} else if ( $action )  { // we're viewing a list of topics
		echo '<td class="td-email-sub"><div class="generic-button ass-topic-subscribe"><a title="'.$title.'" 
			id="'.$action.'-'.$topic_id.'">'.$link_text.'</a></div></td>'; 
	}
}
add_action( 'bp_directory_forums_extra_cell', 'ass_topic_follow_or_mute_link', 50 );
add_action( 'bp_before_group_forum_topic_posts', 'ass_topic_follow_or_mute_link' );
add_action( 'bp_after_group_forum_topic_posts', 'ass_topic_follow_or_mute_link' );


// add a title to the mute/follow above (in the th tag)
function ass_after_topic_title_head() {
	global $bp;
	
	if ( !$bp->groups->current_group->is_member )
		return;
		
	echo '<th id="th-email-sub">'.__('Email','bp-ass').'</th>';
}
add_filter( 'bp_directory_forums_extra_cell_head', 'ass_after_topic_title_head', 3 ); 



// Handles AJAX request to follow/mute a topic
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
//	!SUPPORT FUNCTIONS
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

// return array of users who match a usermeta value
function ass_user_settings_array( $setting ) {
	global $wpdb;
	$results = $wpdb->get_results( "SELECT user_id, meta_value FROM $wpdb->usermeta WHERE meta_key LIKE '{$setting}'" );
	
	foreach ( $results as $result ) {
		$settings[ $result->user_id ] = $result->meta_value;
	}
	
	return $settings;
}

/*
// here lies a failed attempt ...
// return array of users who are admins or mods in a specific group
function ass_get_group_admins_mods( $group_id ) {
	global $bp;
	$results = $wpdb->get_results( "SELECT user_id, is_admin, is_mod FROM {$bp->groups->table_name_members} WHERE group_id = $group_id AND (is_admin = 1 OR is_mod = 1)", ARRAY_A );
	
	return $results;
}
*/



// cleans up the subject for email, strips trailing colon, add quotes to topic name, strips html
function ass_clean_subject( $subject ) {
	
	// this feature of adding quotes only happens in english installs // and is not that useful in the HTML digest
	$subject_quotes = preg_replace( '/posted on the forum topic /', 'posted on the forum topic "', $subject );
	$subject_quotes = preg_replace( '/started the forum topic /', 'started the forum topic "', $subject_quotes );	
	if ( $subject != $subject_quotes )
		$subject = preg_replace( '/ in the group /', '" in the group ', $subject_quotes );
	
	$subject = preg_replace( '/:$/', '', $subject ); // remove trailing colon
	$subject = html_entity_decode( strip_tags( $subject ) );
		
	return apply_filters( 'ass_clean_subject', $subject );
}

function ass_clean_subject_html( $subject ) {
	$subject = preg_replace( '/:$/', '', $subject ); // remove trailing colon		
	return apply_filters( 'ass_clean_subject_html', $subject );
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


// show group email subscription status on group member pages (for admins and mods only)
function ass_show_subscription_status_in_member_list( $user_id='' ) {
	global $bp, $members_template;
	
	$group_id = $bp->groups->current_group->id;
	
	if ( groups_is_user_admin( $bp->loggedin_user->id , $group_id ) || groups_is_user_mod( $bp->loggedin_user->id , $group_id ) || is_site_admin() ) {
		if ( !$user_id ) 
			$user_id = $members_template->member->user_id;	
		$sub_type = ass_get_group_subscription_status( $user_id, $group_id );
		echo '<div class="ass_members_status">'.__('Email status:','bp-ass'). ' ' . ass_subscribe_translate( $sub_type ) . '</div>';
	}
}
add_action( 'bp_group_members_list_item_action', 'ass_show_subscription_status_in_member_list', 100 );



// add links to the group admin manage members section so admins can change user's email status
function ass_manage_members_email_status(  $user_id='' ) {
	global $members_template, $groups_template, $bp;
	
	if ( get_option('ass-admin-can-edit-email') == 'no' )
		return;
		
	if ( !$user_id ) 
		$user_id = $members_template->member->user_id;
		
	$group = &$groups_template->group;
	$group_url = bp_get_group_permalink( $group ) . 'admin/manage-members/email';
	$sub_type = ass_get_group_subscription_status( $user_id, $group->id );
	echo '<div class="ass_manage_members_links"> '.__('Email status:','bp-ass').' ' . ass_subscribe_translate( $sub_type ) . '.';	
	echo ' &nbsp; '.__('Change to:','bp-ass').' ';
	echo '<a href="' . wp_nonce_url( $group_url.'/no/'.$user_id, 'ass_member_email_status' ) . '">'.__('No Email','bp-ass').'</a> | ';
	echo '<a href="' . wp_nonce_url( $group_url.'/sum/'.$user_id, 'ass_member_email_status' ) . '">'.__('Weekly','bp-ass').'</a> | ';
	echo '<a href="' . wp_nonce_url( $group_url.'/dig/'.$user_id, 'ass_member_email_status' ) . '">'.__('Daily','bp-ass').'</a> | ';
	echo '<a href="' . wp_nonce_url( $group_url.'/sub/'.$user_id, 'ass_member_email_status' ) . '">'.__('New Topics','bp-ass').'</a> | ';
	echo '<a href="' . wp_nonce_url( $group_url.'/supersub/'.$user_id, 'ass_member_email_status' ) . '">'.__('All Email','bp-ass').'</a>';
	echo '</div>';
}
add_action( 'bp_group_manage_members_admin_item', 'ass_manage_members_email_status' );

// make the change to the users' email status based on the function above
function ass_manage_members_email_update() {
	global $bp;

	if ( $bp->current_component == $bp->groups->slug && 'manage-members' == $bp->action_variables[0] ) {

		if ( !$bp->is_item_admin )
			return false;

		if ( 'email' == $bp->action_variables[1] && ( 'no' == $bp->action_variables[2] || 'sum' == $bp->action_variables[2] || 'dig' == $bp->action_variables[2] || 'sub' == $bp->action_variables[2] || 'supersub' == $bp->action_variables[2] ) && is_numeric( $bp->action_variables[3] ) ) {
			$user_id = $bp->action_variables[3];
			$action = $bp->action_variables[2];

			/* Check the nonce first. */
			if ( !check_admin_referer( 'ass_member_email_status' ) )
				return false;

			ass_group_subscription( $action, $user_id, $bp->groups->current_group->id );
			bp_core_add_message( __( 'User email status changed successfully', 'bp-ass' ) );
			bp_core_redirect( bp_get_group_permalink( $bp->groups->current_group ) . 'admin/manage-members/' );
		}
	}
}
add_action( 'wp', 'ass_manage_members_email_update', 4 );



// Site admin can change the email settings for ALL users in a group
function ass_change_all_email_sub() {
	global $groups_template, $bp;
		
	if ( !is_site_admin() )
		return false;
	
	$group = &$groups_template->group;
		
	if (! $default_email_sub = ass_get_default_subscription( $group ) )
		$default_email_sub = 'no';
		
	echo '<p><br>'.__('Site Admin Only: update email subscription settings for ALL members to the default:', 'bp-ass').' <i>' . ass_subscribe_translate( $default_email_sub ) . '</i>.  '.__('Warning: this is not reversible so use with caution.', 'bp-ass').' <a href="' . wp_nonce_url( bp_get_group_permalink( $group ) . 'admin/manage-members/email-all/'. $default_email_sub, 'ass_change_all_email_sub' ) . '">'.__('Make it so!', 'bp-ass').'</a>';
}
add_action( 'bp_after_group_manage_members_admin', 'ass_change_all_email_sub' );

// change all users' email status based on the function above
function ass_manage_all_members_email_update() {
	global $bp;

	if ( $bp->current_component == $bp->groups->slug && 'manage-members' == $bp->action_variables[0] ) {
	
		if ( !is_site_admin() )
			return false;
		
		$action = $bp->action_variables[2];	

		if ( 'email-all' == $bp->action_variables[1] && ( 'no' == $action || 'sum' == $action || 'dig' == $action || 'sub' == $action || 'supersub' == $action ) ) {
					
			if ( !check_admin_referer( 'ass_change_all_email_sub' ) ) 
				return false;
			
			$result = BP_Groups_Member::get_all_for_group( $bp->groups->current_group->id, 0, 0, 0 ); // set the last value to 1 to exclude admins
			$members = $result['members'];
			
			foreach ( $members as $member ) {
				ass_group_subscription( $action, $member->user_id, $bp->groups->current_group->id );
			}	
			
			bp_core_add_message( __( 'All user email status\'s changed successfully', 'bp-ass' ) );
			bp_core_redirect( bp_get_group_permalink( $bp->groups->current_group ) . 'admin/manage-members/' );
		}
	}
}
add_action( 'wp', 'ass_manage_all_members_email_update', 4 );


// Add a notice at end of email notification about how to change group email subscriptions
function ass_add_notice_to_notifications_page() {
	echo '<p><b>'.__('Group Email Settings','bp-ass').'</b></p>';
	echo '<p>' . sprintf( __('To change the email notification settings for your groups go to %s and click change for each group.','bp-ass') . '</p>', '<a href="'. bp_loggedin_user_domain() .'groups/">'.__('My Groups','bp-ass') .'</a>' );
}
add_action( 'bp_notification_settings', 'ass_add_notice_to_notifications_page', 9000 );








//
//	!FRONT END ADMIN AND SETTINGS FUNCTIONS
//


// creata a form that allows admins to email everyone in the group
function ass_admin_notice_form() {	
	global $bp;	

	if ( groups_is_user_admin( $bp->loggedin_user->id , $bp->groups->current_group->id ) || is_site_admin() ) {
		$submit_link = $bp->root_domain . '/' . $bp->groups->slug . '/' . $bp->groups->current_group->slug . '/notifications';
		?> 
		<h3><?php _e('Send an email notice to everyone in the group', 'bp-ass'); ?></h3>
		<p><?php _e('You can use the form below to send an email notice to all group members.', 'bp-ass'); ?> <br>
		<b><?php _e('Everyone in the group will receive the email -- regardless of their email settings -- so use with caution', 'bp-ass'); ?></b>.</p>
		<form action="<?php echo $submit_link ?>" method="post">
		<?php wp_nonce_field( 'ass_admin_notice' ); ?>
		<input type="hidden" name="ass_group_id" value="<?php echo $bp->groups->current_group->id; ?>"/>
		<?php _e('Email Subject:', 'bp-ass') ?><br>
		<input type="text" name="ass_admin_notice_subject" value=""/><br><br>
		<?php _e('Email Content:', 'bp-ass') ?><br>
		<textarea value="" name="ass_admin_notice" id="ass-admin-notice-textarea"></textarea><br>
		<input type="submit" name="ass_admin_notice_send" value="<?php _e('Email this notice to everyone in the group', 'bp-ass') ?>" />
		</form> 
		<?php
	}
}


// This function sends an email out to all group members regardless of subscription status. 
function ass_admin_notice() {
    global $bp;

    if ( $bp->current_component == 'groups' && $bp->current_action == 'admin' && $bp->action_variables[0] == 'notifications' ) {
    
	    // Make sure the user is an admin
		if ( !groups_is_user_admin( $bp->loggedin_user->id , $group_id ) && !is_site_admin() )
			return;
		
		if ( get_option('ass-admin-can-send-email') == 'no' )
			return;
		
		// make sure the correct form variables are here
		if ( isset( $_POST[ 'ass_admin_notice_send' ] ) && isset( $_POST[ 'ass_admin_notice' ] ) ) {
			//echo '<pre>'; print_r( $_POST ); echo '</pre>';
			$group_id = $_POST[ 'ass_group_id' ];
			$group_name = $bp->groups->current_group->name;
			$group_link = $bp->root_domain . '/' . $bp->groups->slug . '/' . $bp->groups->current_group->slug . '/';
			$subject = $_POST[ 'ass_admin_notice_subject' ];
			$subject .= __(' - sent from the group ', 'bp-ass') . $group_name .' [' . get_blog_option( BP_ROOT_BLOG, 'blogname' ) . ']';
			$message = sprintf( __(
'This is a notice from the group \'%s\':

"%s"


To view this group log in and follow the link below:
%s

---------------------
', 'bp-ass' ), $group_name,  $_POST[ 'ass_admin_notice' ], $group_link );
			
			$message .= __( 'Please note: admin notices are sent to everyone in the group and cannot be disabled. 
If you feel this service is being misused please speak to the website administrator.', 'bp-ass' );
			
			$user_ids = BP_Groups_Member::get_group_member_ids( $group_id );
			
			// cycle through all group members
			foreach ( (array)$user_ids as $user_id ) { 		
				$user = bp_core_get_core_userdata( $user_id ); // Get the details for the user
				wp_mail( $user->user_email, $subject, $message );  // Send the email
				//echo '<br>Email: ' . $user->user_email;
			}
			
			bp_core_add_message( __( 'The email notice was sent successfully.', 'bp-ass' ) );
			//echo '<p>Subject: ' . $subject;
			//echo '<pre>'; print_r( $message ); echo '</pre>';
		}
	}
}
add_action('wp', 'ass_admin_notice');  





// adds forum notification options in the users settings->notifications page 
function ass_group_subscription_notification_settings() {
	global $current_user; ?>
	<table class="notification-settings zebra" id="groups-notification-settings">
	<thead>
		<tr>
			<th class="icon"></th>
			<th class="title"><?php _e( 'Group Forum', 'bp-ass' ) ?></th>
			<th class="yes"><?php _e( 'Yes', 'bp-ass' ) ?></th>
			<th class="no"><?php _e( 'No', 'bp-ass' )?></th>
		</tr>
	</thead>
	<tbody>
		<tr>
			<td></td>
			<td><?php _e( 'A member replies in a forum topic you\'ve started', 'bp-ass' ) ?></td>
			<td class="yes"><input type="radio" name="notifications[ass_replies_to_my_topic]" value="yes" <?php if ( !get_usermeta( $current_user->id, 'ass_replies_to_my_topic') || 'yes' == get_usermeta( $current_user->id, 'ass_replies_to_my_topic') ) { ?>checked="checked" <?php } ?>/></td>
			<td class="no"><input type="radio" name="notifications[ass_replies_to_my_topic]" value="no" <?php if ( 'no' == get_usermeta( $current_user->id, 'ass_replies_to_my_topic') ) { ?>checked="checked" <?php } ?>/></td>
		</tr>
		<tr>
			<td></td>
			<td><?php _e( 'A member replies after you in a forum topic', 'bp-ass' ) ?></td>
			<td class="yes"><input type="radio" name="notifications[ass_replies_after_me_topic]" value="yes" <?php if ( !get_usermeta( $current_user->id, 'ass_replies_after_me_topic') || 'yes' == get_usermeta( $current_user->id, 'ass_replies_after_me_topic') ) { ?>checked="checked" <?php } ?>/></td>
			<td class="no"><input type="radio" name="notifications[ass_replies_after_me_topic]" value="no" <?php if ( 'no' == get_usermeta( $current_user->id, 'ass_replies_after_me_topic') ) { ?>checked="checked" <?php } ?>/></td>
		</tr>

		<?php do_action( 'ass_group_subscription_notification_settings' ); ?>
		</tbody>
	</table>
<?php
}
add_action( 'bp_notification_settings', 'ass_group_subscription_notification_settings' );







//
//	!WP BACKEND ADMIN SETTINGS
//


// Functions to add the backend admin menu to control changing default settings
function ass_admin_menu() {
	add_submenu_page( 'bp-general-settings', __("Group Email Options", 'bp-ass'), __("Group Email Options", 'bp-ass'), 'manage_options', 'ass_admin_options', "ass_admin_options" );
}
add_action('admin_menu', 'ass_admin_menu');

// function to create the back end admin form
function ass_admin_options() {
	//print_r($_POST); die();
	
	if ( $_POST )
		ass_update_dashboard_settings();
	
	//set the first time defaults
	if ( !$ass_digest_time = get_option( 'ass_digest_time' ) )
		$ass_digest_time = array( 'hours' => '05', 'minutes' => '00' );
	
	if ( !$ass_weekly_digest = get_option( 'ass_weekly_digest' ) )
//		$ass_weekly_digest = 5; // friday
		$ass_weekly_digest = 0; // sunday
		
	$next = date( "r", wp_next_scheduled( 'ass_digest_event' ) );
	?>
	<div class="wrap">
		<h2><?php _e('Group Email Subscription Settings', 'bp-ass'); ?></h2>

		<form id="ass-admin-settings-form" method="post" action="admin.php?page=ass_admin_options">
		<?php wp_nonce_field( 'ass_admin_settings' ); ?>

		<h3><?php _e( 'Digests & Summaries', 'bp-ass' ) ?></h3>
		
		<p><b><a href="<?php bloginfo('url') ?>?sum=1" target="_blank"><?php _e('View queued digest items</a></b> (in new window)<br>As admin, you can see what is currently in the email queue by adding ?sum=1 to your url. This will not fire the digest, it will just show you what is waiting to be sent.', 'bp-ass') ?><br>
		</p>
		
		<p>
			<label for="ass_digest_time"><?php _e( '<strong>Daily Digests</strong> should be sent at this time:', 'bp-ass' ) ?> </label>
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
			<label for="ass_weekly_digest"><?php _e( '<strong>Weekly Summaries</strong> should be sent on:', 'bp-ass' ) ?> </label>
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
			<!-- (the summary will be sent one hour after the daily digests) -->			
		</p>
		
		<p><i><?php $weekday = array( __("Sunday"), __("Monday"), __("Tuesday"), __("Wednesday"), __("Thursday"), __("Friday"), __("Saturday") ); echo sprintf( __( 'The server timezone is %s (%s); the current server time is %s (%s); and the day is %s.', 'bp-ass' ), date( 'T' ), date( 'e' ), date( 'g:ia' ), date( 'H:i' ), $weekday[date( 'w' )] ) ?></i>
		<br>
		<br>
		

		<h3><?php _e('Group Admin Abilities', 'bp-ass'); ?></h3>
		<p><?php _e('Allow group admins and mods to change members\' email subscription settings: ', 'bp-ass'); ?>
		<?php $admins_can_edit_status = get_option('ass-admin-can-edit-email'); ?>
		<input type="radio" name="ass-admin-can-edit-email" value="yes" <?php if ( $admins_can_edit_status == 'yes' || !$admins_can_edit_status ) echo 'checked="checked"'; ?>> <?php _e('yes', 'bp-ass') ?> &nbsp; 
		<input type="radio" name="ass-admin-can-edit-email" value="no" <?php if ( $admins_can_edit_status == 'no' ) echo 'checked="checked"'; ?>> <?php _e('no', 'bp-ass') ?>
		
		<p><?php _e('Allow group admins to override subscription settings and send an email to everyone in their group: ', 'bp-ass'); ?>
		<?php $admins_can_send_email = get_option('ass-admin-can-send-email'); ?>
		<input type="radio" name="ass-admin-can-send-email" value="yes" <?php if ( $admins_can_send_email == 'yes' || !$admins_can_send_email ) echo 'checked="checked"'; ?>> <?php _e('yes', 'bp-ass') ?> &nbsp; 
		<input type="radio" name="ass-admin-can-send-email" value="no" <?php if ( $admins_can_send_email == 'no' ) echo 'checked="checked"'; ?>> <?php _e('no', 'bp-ass') ?>

		<br>
		<br>
		<h3><?php _e('Spam Prevention', 'bp-ass'); ?></h3>
			<p><?php _e('To help protect against spam, you may wish to require a user to have been a member of the site for a certain amount of days before any group updates are emailed to the other group members. This is disabled by default.', 'bp-ass'); ?> </p>
			<?php _e('Member must be registered for', 'bp-ass'); ?><input type="text" size="1" name="ass_registered_req" value="<?php echo get_option( 'ass_registered_req' ); ?>" style="text-align:center"/><?php _e('days', 'bp-ass'); ?></p>
		
		
			<p class="submit">
				<input type="submit" value="<?php _e('Save Settings', 'bp-ass') ?>" id="bp-admin-ass-submit" name="bp-admin-ass-submit" class="button-primary">
			</p>

		</form>
		
		<hr>
		<form action="https://www.paypal.com/cgi-bin/webscr" method="post" target="_blank">
		<?php echo sprintf( __('If you enjoy using this plugin %s please rate it %s.', 'bp-ass'), '<a href="http://wordpress.org/extend/plugins/buddypress-group-email-subscription/" target="_blank">', '</a>'); ?><br>
		<?php _e('Please make a donation to the team to support ongoing development.', 'bp-ass'); ?><br>
		<input type="hidden" name="cmd" value="_s-xclick">
		<input type="hidden" name="hosted_button_id" value="PXD76LU2VQ5AS">
		<input type="image" src="https://www.paypal.com/en_US/i/btn/btn_donate_SM.gif" border="0" name="submit" alt="PayPal - The safer, easier way to pay online!">
		<img alt="" border="0" src="https://www.paypal.com/en_US/i/scr/pixel.gif" width="1" height="1">
				
	</div>
	<?php
}


// save the back-end admin settings
function ass_update_dashboard_settings() {
	if ( !check_admin_referer( 'ass_admin_settings' ) )
		return;
	
	if ( !is_site_admin() )
		return;
		
	/* The daily digest time has been changed */
	if ( $_POST['ass_digest_time'] != get_option( 'ass_digest_time' ) )		
		ass_set_daily_digest_time( $_POST['ass_digest_time']['hours'], $_POST['ass_digest_time']['minutes'] );
	
	/* The weekly digest day has been changed */
	if ( $_POST['ass_weekly_digest'] != get_option( 'ass_weekly_digest' ) )		
		ass_set_weekly_digest_time( $_POST['ass_weekly_digest'] );

	if ( $_POST['ass-admin-can-edit-email'] != get_option( 'ass-admin-can-edit-email' ) )
		update_option( 'ass-admin-can-edit-email', $_POST['ass-admin-can-edit-email'] );

	if ( $_POST['ass-admin-can-send-email'] != get_option( 'ass-admin-can-send-email' ) )
		update_option( 'ass-admin-can-send-email', $_POST['ass-admin-can-send-email'] );

	if ( $_POST['ass_registered_req'] != get_option( 'ass_registered_req' ) )
		update_option( 'ass_registered_req', $_POST['ass_registered_req'] );
	
	//echo '<pre>'; print_r( $_POST ); echo '</pre>';
}



function ass_weekly_digest_week() {
	$ass_weekly_digest = get_option( 'ass_weekly_digest' );
	if ( $ass_weekly_digest == 1 )
		return __('Monday' );
	elseif ( $ass_weekly_digest == 2 )
		return __('Tuesday' );
	elseif ( $ass_weekly_digest == 3 )
		return __('Wednesday' );
	elseif ( $ass_weekly_digest == 4 )
		return __('Thursday' );
	elseif ( $ass_weekly_digest == 5 )
		return __('Friday' );
	elseif ( $ass_weekly_digest == 6 )
		return __('Saturday' );
	elseif ( $ass_weekly_digest == 0 )
		return __('Sunday' );
}

function ass_testing_func() {
	//echo '<pre>'; print_r( wp_get_schedules() ); echo '</pre>';
}
//add_action('bp_before_container','ass_testing_func');
add_action('bp_after_container','ass_testing_func');


?>