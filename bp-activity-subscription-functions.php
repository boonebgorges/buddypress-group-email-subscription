<?php

//
// !SEND EMAIL UPDATES FOR FORUM TOPICS AND POSTS
//

/**
 * Returns an unsubscribe link to disable email notifications for a given group and/or all groups.
 */
function ass_group_unsubscribe_links( $user_id ) {
	global $bp;

	//$settings_link = "{$bp->root_domain}/{$bp->groups->slug}/{$bp->groups->current_group->slug}/notifications/";
	//$links = sprintf( __( 'To disable these notifications please log in and go to: %s', 'bp-ass' ), $settings_link );

	$userdomain = bp_core_get_user_domain( $user_id );

	$group_id = bp_get_current_group_id();
	$group_link = "$userdomain?bpass-action=unsubscribe&group={$group_id}&access_key=" . md5( "{$group_id}{$user_id}unsubscribe" . wp_salt() );
	$links = sprintf( __( 'To disable all notifications for this group, click: %s', 'bp-ass' ), $group_link );

	if ( get_option( 'ass-global-unsubscribe-link' ) == 'yes' ) {
		$global_link = "$userdomain?bpass-action=unsubscribe&access_key=" . md5( "{$user_id}unsubscribe" . wp_salt() );
		$links .= "\n\n" . sprintf( __( 'Or to disable notifications for *all* your groups, click: %s', 'bp-ass' ), $global_link );
	}

	$links .= "\n";

	return $links;
}

/**
 * Records group activity items in GES for all activity except:
 *  - group forum posts (handled in ass_group_notification_forum_posts())
 *  - created and joined group entries (irrelevant)
 *
 * You can do more fine-grained activity filtering with the
 * 'ass_block_group_activity_types' filter.
 */
function ass_group_notification_activity( BP_Activity_Activity $activity ) {
	global $bp;

	$component = $activity->component;
	$sender_id = $activity->user_id;

	// get group activity update replies to work (there is no group id passed in $content, but we can get it from $bp)
	if ( $activity->type == 'activity_comment' && bp_is_groups_component() && $component == 'activity' ) {
		$component = 'groups';
	}

	// at this point we only want group activity, perhaps later we can make a function and interface for personal activity...
	if ( $component != 'groups' ) {
		return;
	}

	// if you want to conditionally block certain activity types from appearing,
	// use the filter below
	if ( false === apply_filters( 'ass_block_group_activity_types', true, $activity->type, $activity ) )
		return;

	if ( !ass_registered_long_enough( $sender_id ) )
		return;


	if ( 'activity_comment' === $activity->type ) { // if it's an group activity comment, reset to the proper group id and append the group name to the action
		// this will need to be filtered for plugins manually adding group activity comments
		$group_id = bp_get_current_group_id();

		$action   = ass_clean_subject( $activity->action ) . ' ' . __( 'in the group', 'bp-ass' ) . ' ' . bp_get_current_group_name();
	} else {
		$group_id = $activity->item_id;
		$action = ass_clean_subject( $activity->action );
	}

	$action = apply_filters( 'bp_ass_activity_notification_action', $action, $activity );

	$group = groups_get_group( array( 'group_id' => $group_id ) );

	/*
	 * If it's an activity item, switch the activity permalink to the group homepage
	 * rather than the user's homepage.
	 */
	$link = bp_get_group_permalink( $group );
	if ( $activity->primary_link && $activity->primary_link !== bp_core_get_user_domain( $sender_id ) ) {
		$link = $activity->primary_link;
	}

	$send_args = array(
		'group_id'    => $group_id,
		'sender_id'   => $sender_id,
		'activity_id' => $activity->id,
		'action'      => $action,
		'content'     => $activity->content,
		'link'        => $link,
	);

	ass_generate_notification( $send_args );
}
add_action( 'bp_activity_after_save' , 'ass_group_notification_activity' , 50 );

/**
 * Generate and send a group notification.
 *
 * @since 3.6.0
 *
 * @param array $args {
 *     @type int $group_id ID of the group.
 *     @type int $sender_id ID of the user triggering the activity.
 *     @type int $activity_id ID of the activity item being triggered.
 *     @type string $action Activity action. Used to generate the email subject.
 *     @type string $content Activity content. Used to generate the email content.
 *     @type string $link Primary link for the activity item.
 * }
 */
function ass_generate_notification( $args = array() ) {
	$r = wp_parse_args( $args, array(
		'group_id' => null,
		'sender_id' => null,
		'activity_id' => null,
		'action' => null,
		'content' => null,
		'link' => null,
	) );

	$group = groups_get_group( array( 'group_id' => $r['group_id'] ) );
	if ( ! $group->id ) {
		return;
	}

	$activity_obj = new BP_Activity_Activity( $r['activity_id'] );

	/* Subject & Content */
	$blogname    = '[' . get_blog_option( BP_ROOT_BLOG, 'blogname' ) . ']';
	$subject     = apply_filters( 'bp_ass_activity_notification_subject', $r['action'] . ' ' . $blogname, $r['action'], $blogname );
	$the_content = apply_filters( 'bp_ass_activity_notification_content', $r['content'], $activity_obj, $r['action'], $group );
	$the_content = ass_clean_content( $the_content );

	// If message has no content (as in the case of group joins, etc), we'll use a different
	// $message template
	if ( empty( $the_content ) ) {
		$message = sprintf( __(
'%1$s

To view or reply, log in and go to:
%2$s

---------------------
', 'bp-ass' ), $r['action'], $r['link'] );
	} else {
		$message = sprintf( __(
'%1$s

"%2$s"

To view or reply, log in and go to:
%3$s

---------------------
', 'bp-ass' ), $r['action'], $the_content, $r['link'] );
	}

	// get subscribed users for the group
	$subscribed_users = groups_get_groupmeta( $r['group_id'], 'ass_subscribed_users' );

	// this is used if a user is subscribed to the "Weekly Summary" option.
	// the weekly summary shouldn't record everything, so we have a filter:
	//
	// 'ass_this_activity_is_important'
	//
	// this hook can be used by plugin authors to record important activity items
	// into the weekly summary
	// @see ass_default_weekly_summary_activity_types()
	$this_activity_is_important = apply_filters( 'ass_this_activity_is_important', false, $activity_obj->type );

	// cycle through subscribed users
	foreach ( (array) $subscribed_users as $user_id => $group_status ) {
		$self_notify = false;

		// If user is banned from group, do not send mail.
		if ( groups_is_user_banned( $user_id, $group->id ) ) {
			continue;
		}

		// Does the author want updates of their own forum posts?
		if ( $activity_obj->type == 'bbp_topic_create' || $activity_obj->type == 'bbp_reply_create' ) {

			if ( $user_id == $r['sender_id'] ) {
				$self_notify = ass_self_post_notification( $user_id );

				// Author does not want notifications of their own posts
				if ( ! $self_notify ) {
					continue;
				}
			}

		/*
		 * If this is an activity comment, and the $user_id is the user who is being replied
		 * to, check to make sure that the user is not subscribed to BP's native activity reply notifications.
		 */
		} elseif ( 'activity_comment' == $activity_obj->type ) {
			// First, look at the immediate parent
			$immediate_parent = new BP_Activity_Activity( $activity_obj->secondary_item_id );

			// Don't send the bp-ass notification if the user is subscribed through BP
			if ( $user_id == $immediate_parent->user_id && 'no' != bp_get_user_meta( $user_id, 'notification_activity_new_reply', true ) ) {
				continue;
			}

			// We only need to check the root parent if it's different from the immediate parent.
			if ( $activity_obj->secondary_item_id != $activity_obj->item_id ) {
				$root_parent = new BP_Activity_Activity( $activity_obj->item_id );

				// Don't send the bp-ass notification if the user is subscribed through BP
				if ( $user_id == $root_parent->user_id && 'no' != bp_get_user_meta( $user_id, 'notification_activity_new_reply', true ) ) {
					continue;
				}
			}
		}

		$send_it = false;

		// Self-notification email for bbPress posts
		if ( $self_notify === true ) {
			$send_it      = true;
			$group_status = 'self_notify';

			// notification settings link
			$settings_link = trailingslashit( bp_core_get_user_domain( $user_id ) . bp_get_settings_slug() ) . 'notifications/';

			// set notice
			$notice  = __( 'You are currently receiving notifications for your own posts.', 'bp-ass' );
			$notice .= "\n\n" . sprintf( __( 'To disable these notifications please log in and go to: %s', 'bp-ass' ), $settings_link );
			$notice .= "\n" . __( 'Once you are logged in, uncheck "Receive notifications of your own posts?".', 'bp-ass' );

		// User is subscribed to "All Mail"
		// OR user is subscribed to "New Topics" (bbPress 2)
		} elseif ( $group_status == 'supersub' || ( $group_status == 'sub' && $activity_obj->type == 'bbp_topic_create' ) ) {

			/*
			 * If someone is signed up for all email and they post a group update,
			 * they should not receive an email.
			 */
			if ( 'activity_update' == $activity_obj->type && $r['sender_id'] === $user_id ) {
				continue;
			}

			$send_it = true;

			$settings_link = ass_get_login_redirect_url( trailingslashit( bp_get_group_permalink( $group ) . 'notifications' ), $group_status );

			$notice  = __( 'Your email setting for this group is: ', 'bp-ass' ) . ass_subscribe_translate( $group_status );
			$notice .= "\n" . sprintf( __( 'To change your email setting for this group, please log in and go to: %s', 'bp-ass' ), $settings_link );
			$notice .= "\n\n" . ass_group_unsubscribe_links( $user_id );

		}

		/**
		 * Filter whether a given user should receive immediate notification of the current activity.
		 *
		 * @since 3.6.0
		 *
		 * @param bool   $send_it True to send an immediate email notification, false otherwise.
		 * @param object $content Activity object.
		 * @param int    $user_id ID of the user.
		 */
		$send_it = apply_filters( 'bp_ass_send_activity_notification_for_user', $send_it, $activity_obj, $user_id );

		// if we're good to send, send the email!
		if ( $send_it ) {
			// One last chance to filter the message content
			$user_message = apply_filters( 'bp_ass_activity_notification_message', $message . $notice, array(
				'message'           => $message,
				'notice'            => $notice,
				'user_id'           => $user_id,
				'subscription_type' => $group_status,
				'content'           => $the_content,
				'settings_link'     => ! empty( $settings_link ) ? $settings_link : '',
			) );

			// Get the details for the user
			$user = bp_core_get_core_userdata( $user_id );

			// Send the email
			if ( $user->user_email ) {
				wp_mail( $user->user_email, $subject, $user_message );
			}

		}

		// otherwise, user is subscribed to "Daily Digest" so record item in digest!
		// OR user is subscribed to "Weekly Summary" and activity item is important
		// enough to be recorded
		if ( $group_status == 'dig' || ( $group_status == 'sum' && $this_activity_is_important ) ) {
			ass_digest_record_activity( $r['activity_id'], $user_id, $r['group_id'], $group_status );
		}
	}
}

/**
 * Activity edit checker.
 *
 * Catch attempts to save activity entries to see if they already exist.
 * If they do exist, stop GES from doing its thang.
 *
 * @since 3.2.2
 */
function ass_group_activity_edits( $activity ) {
	// hack to avoid duplicate action firing during activity saving
	// @see https://buddypress.trac.wordpress.org/ticket/3980
	static $run_once = false;

	if ( ! empty( $run_once ) )
		return;

	// if the activity doesn't match the groups component, stop now
	if ( $activity->component != 'groups' )
		return;

	// if the activity ID already exists, this means this is an edit
	// we don't want GES to send emails for edits!
	if ( ! empty( $activity->id ) ) {
		// Make sure GES doesn't fire
		remove_action( 'bp_activity_after_save', 'ass_group_notification_activity', 50 );
	}

	$run_once = true;
}
add_action( 'bp_activity_before_save', 'ass_group_activity_edits' );

/**
 * Block some activity types from being sent / recorded in groups.
 *
 * @since 3.2.2
 */
function ass_default_block_group_activity_types( $retval, $type, $activity ) {

	switch( $type ) {
		/** ACTIVITY TYPES TO BLOCK **************************************/

		// we handle these in ass_group_notification_forum_posts()
		case 'new_forum_topic' :
		case 'new_forum_post' :

		// @todo in the future, it might be nice for admins to optionally get this message
		case 'joined_group' :

		case 'created_group' :
			return false;

			break;

		/** bbPress 2 ****************************************************/

		// groan! bbPress 2 hacks!
		//
		// when bbPress first records an item into the group activity stream, it is
		// incomplete as it is first recorded on the 'wp_insert_post' action
		//
		// it is later updated on the 'bbp_new_reply' / 'bbp_new_topic' action
		//
		// we want to block the first instance, so GES doesn't record or send this
		// incomplete activity item

		// reply
		case 'bbp_reply_create' :

			// to determine if the reply activity item is incomplete, the primary link
			// will be missing the scheme (HTTP) and host (example.com), so our hack does
			// a search for '://' because the site could be using HTTPS.
			if ( strpos( $activity->primary_link, '://' ) === false ) {
				return false;

			// we're okay again!
			} else {
				return $retval;
			}

			break;

		// topic
		case 'bbp_topic_create' :

			// to determine if the topic activity item is incomplete, the primary link
			// will be missing the groups root slug
			if ( strpos( $activity->primary_link, '/' . bp_get_groups_root_slug() . '/' ) === false ) {
				return false;

			// we're okay again!
			} else {
				return $retval;
			}

			break;

		/** ALL OTHER TYPES **********************************************/

		default :
			return $retval;

			break;
	}
}
add_filter( 'ass_block_group_activity_types', 'ass_default_block_group_activity_types', 5, 3 );

/**
 * Allow certain activity types to be recorded for users subscribed to the
 * "Weekly Summary" option.
 *
 * The rationale behind this is the weekly summary shouldn't record every
 * single activity item because the summary could get rather long.
 *
 * @since 3.2.4
 */
function ass_default_weekly_summary_activity_types( $retval, $type ) {

	switch( $type ) {
		/** ACTIVITY TYPES TO RECORD FOR WEEKLY SUMMARY ******************/

		// backpat items
		case 'wiki_group_page_create' :
		case 'new_calendar_event' :

		// bbPress 2 forum topic
		case 'bbp_topic_create' :

		// activity update
		case 'activity_update' :

			return true;

			break;

		/** ALL OTHER TYPES **********************************************/

		default :
			return $retval;

			break;
	}

}
add_filter( 'ass_this_activity_is_important', 'ass_default_weekly_summary_activity_types', 1, 2 );

/**
 * Login redirector.
 *
 * If group is not public, the group link in the email will use {@link wp_login_url()}.
 *
 * If a user clicks on this link and is already logged in, we should attempt
 * to redirect the user to the authorized content instead of forcing the user
 * to re-authenticate.
 *
 * @since 3.2.4
 *
 * @uses bp_loggedin_user_id() To see if a user is logged in
 */
function ass_login_redirector() {
	// see if a redirect link was passed
	if ( empty( $_GET['redirect_to'] ) )
		return;

	// see if our special 'auth' variable was passed
	if( empty( $_GET['auth'] ) )
		return;

	// if user is *not* logged in, stop now!
	if ( ! bp_loggedin_user_id() )
		return;

	// user is logged in, so let's redirect them to the content
	wp_safe_redirect( esc_url_raw( $_GET['redirect_to'] ) );
	exit;
}
add_action( 'login_init', 'ass_login_redirector', 1 );

/**
 * Returns the login URL with a redirect link.
 *
 * Pass the link you want the user to redirect to when authenticated.
 *
 * Redirection occurs in {@link ass_login_redirector()}.
 *
 * @since 3.4
 *
 * @param string $url The URL you want to redirect to.
 * @param string $context The context of the redirect.
 * @return mixed String of the login URL with the passed redirect link. Boolean false on failure.
 */
function ass_get_login_redirect_url( $url = '', $context = '' ) {
	$url = esc_url_raw( $url );

	if ( empty( $url ) ) {
		return false;
	}

	// setup query args
	$query_args = array(
		'action'      => 'bpnoaccess',
		'auth'        => 1,
		'redirect_to' => apply_filters( 'ass_login_redirect_to', urlencode( $url ), $context )
	);

	return add_query_arg(
		$query_args,
		apply_filters( 'ass_login_url', wp_login_url() )
	);
}


//
//	!GROUP SUBSCRIPTION
//


// returns the subscription status of a user in a group
function ass_get_group_subscription_status( $user_id, $group_id ) {
	global $bp;

	if ( !$user_id )
		$user_id = bp_loggedin_user_id();

	if ( !$group_id )
		$group_id = bp_get_current_group_id();

	$group_user_subscriptions = groups_get_groupmeta( $group_id, 'ass_subscribed_users' );

	$user_subscription = isset( $group_user_subscriptions[$user_id] ) ? $group_user_subscriptions[$user_id] : false;

	return $user_subscription;
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
		if ( isset( $group_user_subscriptions[ $user_id ] ) )
			unset( $group_user_subscriptions[ $user_id ] );
	}

	groups_update_groupmeta( $group_id , 'ass_subscribed_users', $group_user_subscriptions );

	// add a hook for 3rd-party plugin devs
	do_action( 'ass_group_subscription', $user_id, $group_id, $action );
}



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

// Handles AJAX request to subscribe/unsubscribe from group
function ass_group_ajax_callback() {
	global $bp;
	//check_ajax_referer( "ass_group_subscribe" );

	$action = $_POST['a'];
	$user_id = bp_loggedin_user_id();
	$group_id = $_POST['group_id'];

	ass_group_subscription( $action, $user_id, $group_id );

	echo $action;
	exit();
}
add_action( 'wp_ajax_ass_group_ajax', 'ass_group_ajax_callback' );


// if the user leaves the group or if they are removed by an admin, delete their subscription status
function ass_unsubscribe_on_leave( $group_id, $user_id ){
	ass_group_subscription( 'delete', $user_id, $group_id );
}
add_action( 'groups_leave_group', 'ass_unsubscribe_on_leave', 100, 2 );
add_action( 'groups_remove_member', 'ass_unsubscribe_on_leave', 100, 2 );



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
	if ( isset( $_POST['ass-default-subscription'] ) && $postval = $_POST['ass-default-subscription'] ) {
		if ( $postval ) {
			groups_update_groupmeta( $group->id, 'ass_default_subscription', $postval );

			// during group creation, also save the sub level for the group creator
			if ( 'group-settings' == bp_get_groups_current_create_step() ) {
				ass_group_subscription( $postval, $group->creator_id, $group->id );
			}
		}
	}
}
add_action( 'groups_group_after_save', 'ass_save_default_subscription' );


// Get the default subscription settings for the group
function ass_get_default_subscription( $group = false ) {
	global $bp, $groups_template;
	if ( !$group )
		$group =& $groups_template->group;

	if ( isset( $group->id ) )
		$group_id = $group->id;
	else if ( isset( $bp->groups->new_group_id ) )
		$group_id = $bp->groups->new_group_id;

	$default_subscription =  groups_get_groupmeta( $group_id, 'ass_default_subscription' );
	return apply_filters( 'ass_get_default_subscription', $default_subscription );
}


//
//	!SUPPORT FUNCTIONS
//

// return array of users who match a usermeta value
function ass_user_settings_array( $setting ) {
	global $wpdb;
	$results = $wpdb->get_results( "SELECT user_id, meta_value FROM $wpdb->usermeta WHERE meta_key LIKE '{$setting}'" );

	$settings = array();

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

/**
 * Cleans up the email content
 *
 * By default we do the following to outgoing email content:
 *   - strip slashes
 *   - convert anchor tags to "Link Text <URL>" format, then strip other HTML tags
 *   - convert HTML entities
 *
 * @uses apply_filters() Filter 'ass_clean_content' to modify our cleaning routine
 * @param string $content The email content
 * @return string $clean_content The email content, cleaned up for plaintext email
 */
function ass_clean_content( $content ) {
	return apply_filters( 'ass_clean_content', $content );
}

// By default, we run content through these filters, which can be individually removed
add_filter( 'ass_clean_content', 'stripslashes', 2 );
add_filter( 'ass_clean_content', 'strip_tags', 4 );
add_filter( 'ass_clean_content', 'ass_convert_links', 6 );
add_filter( 'ass_clean_content', 'ass_html_entity_decode', 8 );

/**
 * Wrapper for html_entity_decode() that can be used as an apply_filters() callback
 *
 * @param string
 * @return string
 */
function ass_html_entity_decode( $content ) {
	return html_entity_decode( $content, ENT_QUOTES );
}

/**
 * Convert <a> tags to a plain-text version.
 *
 * Links like <a href="http://foo.com">Foo</a> become Foo <http://foo.com>
 *
 * @param string $content
 * @return string
 */
function ass_convert_links( $content ) {
	$pattern = '|<a .*?href=["\']([a-zA-Z0-9\-_\./:]+?)["\'].*?>([^<]+)</a>|';
	$content = preg_replace( $pattern, '\2 <\1>', $content );
	return $content;
}

/**
 * Cleans up the subject for email.
 *
 * This function does a few things:
 *  - Add quotes to topic name
 *  - Strips trailing colon
 *  - Strips slashes, HTML
 *  - Convert HTML entities
 *
 * @param string $subject The email subject line to clean
 * @param bool $add_quotes Should we try to add quotes to forum topics?
 * @return string
 */
function ass_clean_subject( $subject, $add_quotes = true ) {

	// this feature of adding quotes only happens in english installs
	// and is not that useful in the HTML digest
	if ( $add_quotes === true ) {
		$subject_quotes = preg_replace( '/posted on the forum topic /', 'posted on the forum topic "', $subject );
		$subject_quotes = preg_replace( '/started the forum topic /', 'started the forum topic "', $subject_quotes );
		if ( $subject != $subject_quotes )
			$subject = preg_replace( '/ in the group /', '" in the group ', $subject_quotes );

		$subject = preg_replace( '/:$/', '', $subject ); // remove trailing colon
	}

	return apply_filters( 'ass_clean_subject', $subject );
}

// By default, we run content through these filters, which can be individually removed
add_filter( 'ass_clean_subject', 'stripslashes', 2 );
add_filter( 'ass_clean_subject', 'strip_tags', 4 );
add_filter( 'ass_clean_subject', 'ass_html_entity_decode', 8 );

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


/**
 * Allow group admins and mods to manage each group member's email
 * subscription settings.
 *
 * This is only enabled if this option is enabled under the main "Group Email
 * Options" settings page.
 *
 * This is hooked to:
 *  - The frontend group's "Admin > Members" page
 *  - The backend group's "Manage Members" metabox (only in BP 1.8+)
 *
 * @param int $user_id The user ID of the group member
 * @param obj $group The BP Group object
 */
function ass_manage_members_email_status(  $user_id = '', $group = '' ) {
	global $members_template, $groups_template;

	// if group admins / mods cannot manage email subscription settings, stop now!
	if ( get_option('ass-admin-can-edit-email') == 'no' ) {
		return;
	}

	// no user ID? fallback on members loop user ID if it exists
	if ( ! $user_id ) {
		$user_id = ! empty( $members_template->member->user_id ) ? $members_template->member->user_id : false;
	}

	// no user ID? fallback on group loop if it exists
	if( ! $group ) {
		$group = ! empty( $groups_template->group ) ? $groups_template->group : false;
	}

	// no user or group? stop now!
	if ( ! $user_id || ! is_object( $group ) ) {
		return;
	}

	$user_id = (int) $user_id;

	$group_url = bp_get_group_permalink( $group ) . 'admin/manage-members/email';
	$sub_type = ass_get_group_subscription_status( $user_id, $group->id );
	echo '<span class="ass_manage_members_links"> '.__('Email status:','bp-ass').' ' . ass_subscribe_translate( $sub_type ) . '.';
	echo ' &nbsp; '.__('Change to:','bp-ass').' ';
	echo '<a href="' . wp_nonce_url( $group_url.'/no/'.$user_id, 'ass_member_email_status' ) . '">'.__('No Email','bp-ass').'</a> | ';
	echo '<a href="' . wp_nonce_url( $group_url.'/sum/'.$user_id, 'ass_member_email_status' ) . '">'.__('Weekly','bp-ass').'</a> | ';
	echo '<a href="' . wp_nonce_url( $group_url.'/dig/'.$user_id, 'ass_member_email_status' ) . '">'.__('Daily','bp-ass').'</a> | ';

	if ( ass_get_forum_type() ) {
		echo '<a href="' . wp_nonce_url( $group_url.'/sub/'.$user_id, 'ass_member_email_status' ) . '">'.__('New Topics','bp-ass').'</a> | ';
	}

	echo '<a href="' . wp_nonce_url( $group_url.'/supersub/'.$user_id, 'ass_member_email_status' ) . '">'.__('All Email','bp-ass').'</a>';
	echo '</span>';
}
add_action( 'bp_group_manage_members_admin_item', 'ass_manage_members_email_status' );





/**
 * Output the group default status
 *
 * First tries to get it out of groupmeta. If not found, falls back on supersub. Filter the supersub
 * default with 'ass_default_subscription_level'
 *
 * @param int $group_id ID of the group. Defaults to current group, if present
 * @return str $status
 */
function ass_group_default_status( $group_id = false ) {
	global $bp;

	if ( !$group_id )
		$group_id = bp_is_group() ? bp_get_current_group_id() : false;

	if ( !$group_id )
		return '';

	$status = groups_get_groupmeta( $group_id, 'ass_default_subscription' );

	if ( !$status ) {
		$status = apply_filters( 'ass_default_subscription_level', 'supersub', $group_id );
	}

	return apply_filters( 'ass_group_default_status', $status, $group_id );
}




// Add a notice at end of email notification about how to change group email subscriptions
function ass_add_notice_to_notifications_page() {
?>
		<div id="group-email-settings">
			<table class="notification-settings zebra">
				<thead>
					<tr>
						<th class="icon">&nbsp;</th>
						<th class="title"><?php _e( 'Individual Group Email Settings', 'bp-ass' ); ?></th>
					</tr>
				</thead>

				<tbody>
					<tr>
						<td>&nbsp;</td>
						<td>
							<p><?php printf( __('To change the email notification settings for your groups, go to %s and click "Change" for each group.', 'bp-ass' ), '<a href="'. bp_loggedin_user_domain() . trailingslashit( BP_GROUPS_SLUG ) . '">' . __( 'My Groups' ,'bp-ass' ) . '</a>' ); ?></p>

							<?php if ( get_option( 'ass-global-unsubscribe-link' ) == 'yes' ) : ?>
								<p><a href="<?php echo wp_nonce_url( add_query_arg( 'ass_unsubscribe', 'all' ), 'ass_unsubscribe_all' ); ?>"><?php _e( "Or set all your group's email options to No Email", 'bp-ass' ); ?></a></p>
							<?php endif; ?>
						</td>
					</tr>
				</tbody>
			</table>
		</div>
<?php
}
add_action( 'bp_notification_settings', 'ass_add_notice_to_notifications_page', 9000 );

// Unsubscribe a user from all or a subset of their groups
function ass_unsubscribe_user( $user_id = 0, $groups = array() ) {
	if ( empty( $user_id ) )
		$user_id = bp_displayed_user_id();

	if ( empty( $groups ) ) {
		$groups = groups_get_user_groups( $user_id );
		$groups = $groups['groups'];
	}

	foreach ( $groups as $group_id ) {
		ass_group_subscription( 'no', $user_id, $group_id );
	}
}

// Process request for logged in user unsubscribing via link in notifications settings
function ass_user_unsubscribe_action() {
	if ( get_option( 'ass-global-unsubscribe-link' ) != 'yes' || ! bp_is_settings_component() || ! isset( $_GET['ass_unsubscribe'] ) )
		return;

	check_admin_referer( 'ass_unsubscribe_all' );

	ass_unsubscribe_user();

	if ( bp_is_my_profile() )
		bp_core_add_message( __( 'You have been unsubscribed from all groups notifications.', 'bp-ass' ), 'success' );
	else
		bp_core_add_message( __( "This user's has been unsubscribed from all groups notifications.", 'bp-ass' ), 'success' );

	bp_core_redirect( bp_displayed_user_domain() . bp_get_settings_slug() . '/notifications/' );
}
add_action( 'bp_actions', 'ass_user_unsubscribe_action' );

// Form to confirm unsubscription from all groups
function ass_user_unsubscribe_form() {
	$action = isset( $_GET['bpass-action'] ) ? $_GET['bpass-action'] : '';

	if ( 'unsubscribe' != $action )
		return;

	if ( empty( $_GET['group'] ) && get_option( 'ass-global-unsubscribe-link' ) != 'yes' )
		return;

	$user_id = bp_displayed_user_id();
	$access_key = $_GET['access_key'];

	// unsubscribing from one group only
	if ( isset( $_GET['group'] ) ) {
		$group = groups_get_group( array( 'group_id' => $_GET['group'] ) );

		if ( $access_key != md5( "{$group->id}{$user_id}unsubscribe" . wp_salt() ) )
			return;

		ass_unsubscribe_user( $user_id, (array) $group->id );

		$message = sprintf( __( 'Your unsubscription was successful. You will no longer receive email notifications from the group %s.', 'bp-ass' ), '<a href="' . bp_get_group_permalink( $group ) . '">' . $group->name . '</a>' );

		$continue_link = sprintf( __( '<a href="%1$s">Continue to %2$s</a>', 'bp-ass' ), bp_get_group_permalink( $group ), esc_html( $group->name ) );

		$unsubscribed = true;
	} else {
		// unsubscribe from all groups
		if ( $access_key != md5( $user_id . 'unsubscribe' . wp_salt() ) )
			return;

		if ( isset( $_GET['submit'] ) ) {
			ass_unsubscribe_user( $user_id );

			$message = __( 'Your unsubscription was successful. You will no longer receive email notifications from any of your groups.', 'bp-ass' );

			$continue_link = sprintf( __( '<a href="%1$s">Continue to %2$s</a>', 'bp-ass' ), bp_get_root_domain(), get_option( 'blogname' ) );

			$unsubscribed = true;
		}
	}
?>
<html>
<head>
	<meta http-equiv="Content-Type" content="text/html; charset=utf-8" />
	<meta name = "viewport" content="width=640" />
	<title><?php echo bloginfo( 'name' ); ?> - <?php _e( 'Unsubscribe from all groups notifications' ); ?></title>
	<style type="text/css">
		.container {
			background-color:#fff;
			width:400px;
			border:1px solid #999;
			padding: 20px;
			margin: 0 auto;
		}
	</style>
	<?php wp_head(); ?>
</head>
<body>
	<div class="container">
		<h1><?php echo bloginfo( 'name' ); ?> - <?php _e( 'Unsubscribe' ); ?></h1>
		<?php if ( isset( $unsubscribed ) ) : ?>
			<p><?php echo $message ?></p>
			<p><?php echo $continue_link ?></p>
		<?php else : ?>
			<p><?php _e( 'Do you really want to unsubscribe from all groups notifications?' ); ?></p>

			<form id="ass-unsubscribe-form" action="" method="get">
				<input type="hidden" name="bpass-action" value="<?php echo $action; ?>" />
				<input type="hidden" name="access_key" value="<?php echo $access_key; ?>" />
				<input type="submit" name="submit" value="<?php _e( 'Yes, unsubscribe from all my groups' ); ?>" />
				<a href="<?php echo esc_attr( site_url() ); ?>"><?php _e( 'No, close' ); ?></a>
			</form>
		<?php endif; ?>
	</div>
</body>
</html>
<?php
	die;
}
add_action( 'bp_init', 'ass_user_unsubscribe_form' );








//
//	!FRONT END ADMIN AND SETTINGS FUNCTIONS
//



/**
 * Send welcome email to new group members
 *
 * @uses apply_filters() Filter 'ass_welcome_email' to change the content/subject of the email
 */
function ass_send_welcome_email( $group_id, $user_id ) {
	$user = bp_core_get_core_userdata( $user_id );

	$welcome_email = groups_get_groupmeta( $group_id, 'ass_welcome_email' );

	/**
	 * Filters the parameters of the welcome email.
	 *
	 * @since 3.1.1
	 * @since 3.7.0 Added $user parameter.
	 *
	 * @param array $welcome_email Message details {
	 *              $enabled Whether the group has a welcome email enabled or not.
	 *              $subject The saved subject of the welcome email.
	 *              $content The saved content of the welcome email.
	 * }
 	 * @param int   $group_id ID of the group the email is sent by.
	 * @param array $user     Details of the user who just joined the group.
	 */
	$welcome_email = apply_filters( 'ass_welcome_email', $welcome_email, $group_id, $user ); // for multilingual filtering
	$welcome_email_enabled = isset( $welcome_email['enabled'] ) ? $welcome_email['enabled'] : 'no';

	if ( 'no' == $welcome_email_enabled ) {
		return;
	}

	$subject = ass_clean_subject( $welcome_email['subject'], false );
	$message = ass_clean_content( $welcome_email['content'] );

	if ( ! $user->user_email || 'yes' != $welcome_email_enabled || empty( $message ) )
		return;

	if ( get_option( 'ass-global-unsubscribe-link' ) == 'yes' ) {
		$global_link = bp_core_get_user_domain( $user_id ) . '?bpass-action=unsubscribe&access_key=' . md5( "{$user_id}unsubscribe" . wp_salt() );
		$message .= "\n\n---------------------\n";
		$message .= sprintf( __( 'To disable emails from all your groups at once click: %s', 'bp-ass' ), $global_link );
	}

	$group_admin_ids = groups_get_group_admins( $group_id );
	$group_admin = bp_core_get_core_userdata( $group_admin_ids[0]->user_id );
	$headers = array(
		"From: \"{$group_admin->display_name}\" <{$group_admin->user_email}>"
	);

	wp_mail( $user->user_email, $subject, $message, $headers );
}
add_action( 'groups_join_group', 'ass_send_welcome_email', 10, 2 );

/**
 * Send welcome email to new group members when they join via accepting an invitation
 * or having their membership request is approved.
 *
 * @param int $user_id  ID of the user who joined the group.
 * @param int $group_id ID of the group the member has joined.
 */
function ass_send_welcome_email_on_accept_invite_or_request( $user_id, $group_id ) {
	ass_send_welcome_email( $group_id, $user_id );
}
add_action( 'groups_accept_invite', 'ass_send_welcome_email_on_accept_invite_or_request', 10, 2 );
add_action( 'groups_membership_accepted', 'ass_send_welcome_email_on_accept_invite_or_request', 10, 2 );

/**
 * Determine what type of forums are running on this BP install.
 *
 * Returns either 'bbpress' or 'buddypress' on success.
 * Boolean false if neither forums are enabled.
 *
 * @since 3.4
 *
 * @return mixed String of forum type on success; boolean false if forums aren't installed.
 */
function ass_get_forum_type() {
	// sanity check
	if ( ! bp_is_active( 'groups' ) ) {
		return false;
	}

	$type = false;

	// check if bbP is installed
	if ( class_exists( 'bbpress' ) AND function_exists( 'bbp_is_group_forums_active' ) ) {
		// check if bbP group forum support is active
		if ( ! bbp_is_group_forums_active() ) {
			return false;
		}

		$type = 'bbpress';

	// check for BP's bundled forums
	} else {
		// BP's bundled forums aren't installed correctly, so stop!
		if ( ! bp_is_active( 'forums' ) || ! bp_forums_is_installed_correctly() ) {
			return false;
		}

		$type = 'buddypress';
	}

	return $type;
}

// adds forum notification options in the users settings->notifications page
function ass_group_subscription_notification_settings() {
	// get forum type
	$forums = ass_get_forum_type();

	// no forums installed? stop now!
	if ( ! $forums ) {
		return;
	}

?>
	<table class="notification-settings zebra" id="groups-subscription-notification-settings">
	<thead>
		<tr>
			<th class="icon"></th>
			<th class="title"><?php _e( 'Group Forum', 'bp-ass' ) ?></th>
			<th class="yes"><?php _e( 'Yes', 'bp-ass' ) ?></th>
			<th class="no"><?php _e( 'No', 'bp-ass' )?></th>
		</tr>
	</thead>
	<tbody>

	<?php
		// only add the following options if BP's bundled forums are installed...
		// @todo add back these options for bbPress if possible.
	?>

	<?php if ( $forums == 'buddypress' ) :
		if ( ! $replies_to_topic = bp_get_user_meta( bp_displayed_user_id(), 'ass_replies_to_my_topic', true ) ) {
			$replies_to_topic = 'yes';
		}

		if ( ! $replies_after_me = bp_get_user_meta( bp_displayed_user_id(), 'ass_replies_after_me_topic', true ) ) {
			$replies_after_me = 'yes';
		}
	?>

		<tr>
			<td></td>
			<td><?php _e( 'A member replies in a forum topic you\'ve started', 'bp-ass' ) ?></td>
			<td class="yes"><input type="radio" name="notifications[ass_replies_to_my_topic]" value="yes" <?php checked( $replies_to_topic, 'yes', true ); ?>/></td>
			<td class="no"><input type="radio" name="notifications[ass_replies_to_my_topic]" value="no" <?php checked( $replies_to_topic, 'no', true ); ?>/></td>
		</tr>

		<tr>
			<td></td>
			<td><?php _e( 'A member replies after you in a forum topic', 'bp-ass' ) ?></td>
			<td class="yes"><input type="radio" name="notifications[ass_replies_after_me_topic]" value="yes" <?php checked( $replies_after_me, 'yes', true ); ?>/></td>
			<td class="no"><input type="radio" name="notifications[ass_replies_after_me_topic]" value="no" <?php checked( $replies_after_me, 'no', true ); ?>/></td>
		</tr>

	<?php endif; ?>

		<tr>
			<td></td>
			<td><?php _e( 'Receive notifications of your own posts?', 'bp-ass' ) ?></td>
			<td class="yes"><input type="radio" name="notifications[ass_self_post_notification]" value="yes" <?php if ( ass_self_post_notification( bp_displayed_user_id() ) ) { ?>checked="checked" <?php } ?>/></td>
			<td class="no"><input type="radio" name="notifications[ass_self_post_notification]" value="no" <?php if ( !ass_self_post_notification( bp_displayed_user_id() ) ) { ?>checked="checked" <?php } ?>/></td>
		</tr>

		<?php do_action( 'ass_group_subscription_notification_settings' ); ?>
		</tbody>
	</table>


<?php
}
add_action( 'bp_notification_settings', 'ass_group_subscription_notification_settings' );

/**
 * Determine whether user should receive a notification of their own posts
 *
 * The main purpose of the filter is so that admins can override the setting, especially
 * in cases where the user has not specified a setting (ie you can set the default to true)
 *
 * @param int $user_id Optional
 * @return string|array Single metadata value, or array of values
 */
function ass_self_post_notification( $user_id = false ) {
	global $bp;

	if ( empty( $user_id ) )
		$user_id = bp_loggedin_user_id();

	$meta = bp_get_user_meta( $user_id, 'ass_self_post_notification', true );

	$self_notify = $meta == 'yes' ? true : false;

	//if ( $user_id == 4  ) { if ( $self_notify) print_r( $bp ); print_r( $meta ); die(); }
	return apply_filters( 'ass_self_post_notification', $self_notify, $meta, $user_id );
}
