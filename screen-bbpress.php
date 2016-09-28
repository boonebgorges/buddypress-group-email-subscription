<?php
/**
 * bbPress code meant to be run only on group pages.
 *
 * @since 3.7.0
 */

/**
 * Disables bbPress 2's subscription block if the user's group setting is set
 * to "All Mail".
 *
 * All other group subscription settings (New Topics, Digests) should be able
 * to use bbP's topic subscription functionality.  This emulates GES' old
 * "Follow / Mute" functionality.  However, these emails are managed by bbP,
 * not GES.
 *
 * @since 3.2.2
 */
function ass_bbp_subscriptions( $retval ) {
	// not logged in? stop now!
	if ( ! is_user_logged_in() ) {
		return $retval;
	}

	// only do this check if BP's theme compat has run on the group page
	if ( ! did_action( 'bp_template_content' ) ) {
		return $retval;
	}

	// get group sub status
	$group_status = ass_get_group_subscription_status( bp_loggedin_user_id(), bp_get_current_group_id() );

	// if group sub status is anything but "All Mail", let the member use bbP's
	// native subscriptions - this emulates GES' old "Follow / Mute" functionality
	if ( $group_status != 'supersub' ) {
		return true;

	// the member's setting is "All Mail" so we shouldn't allow them to subscribe
	// to prevent duplicates
	} else {
		return false;
	}
}
add_filter( 'bbp_is_subscriptions_active', 'ass_bbp_subscriptions' );

/**
 * Stuff to do when the bbPress plugin is ready.
 *
 * @since 3.4.1
 * @since 3.7.0 Adds support to remove user from bbP's forum subscriber list if user is
 *              already subscribed to the group's "All Mail" option.
 */
function ass_bbp_ready() {
	/**
	 * bbPress v2.5.4 changed how emails are sent out.
	 *
	 * They now send one BCC email to their subscribers, so we have to filter out
	 * the topic subscribers before their email is sent.
	 */
	if ( version_compare( bbp_get_version(), '2.5.4' ) >= 0 ) {
		add_filter( 'bbp_subscription_mail_title', 'ass_bbp_add_topic_subscribers_filter',    99 );
		add_action( 'bbp_pre_notify_subscribers',  'ass_bbp_remove_topic_subscribers_filter', 0 );

		add_filter( 'bbp_forum_subscription_mail_title', 'ass_bbp_add_topic_subscribers_filter',    99 );
		add_action( 'bbp_pre_notify_forum_subscribers',  'ass_bbp_remove_topic_subscribers_filter', 0 );

	// bbPress <= v2.5.3
	} else {
		add_filter( 'bbp_subscription_mail_message', 'ass_bbp_disable_email', 10, 4 );
	}
}
add_action( 'bbp_ready', 'ass_bbp_ready' );

/**
 * Removes subscriber from bbP's topic subscriber's list.
 *
 * If the recipient is already subscribed to the group's "All Mail" option, we
 * remove the recipient from bbPress' subscription list to prevent duplicate
 * emails.
 *
 * This scenario might happen if a user subscribed to a bunch of bbP group
 * topics and later switched to the group's "All Mail" subscription.
 *
 * Note: Only applicable for bbPress 2.5.4 and above.
 *
 * @since 3.4.1
 *
 * @param array $retval Array of user IDs subscribed to a topic
 * @return array
 */
function ass_bbp_remove_topic_subscribers( $retval = array() ) {
	global $bp;

	// get group sub status
	// we're using the direct, global reference for sanity's sake
	$group_user_subscriptions = groups_get_groupmeta( $bp->groups->current_group->id, 'ass_subscribed_users' );

	// loop through all bbP topic subscribers and check against group sub status
	foreach ( $retval as $index => $user_id ) {
		$user_subscription = isset( $group_user_subscriptions[$user_id] ) ? $group_user_subscriptions[$user_id] : false;

		// if user's group status is "All Mail", remove user ID from topic subscribers
		if ( 'supersub' === $user_subscription ) {
			unset( $retval[$index] );
		}
	}

	return $retval;
}

/**
 * Wrapper function to fire our topic subscriber filter for bbPress.
 *
 * This fires when the subscription email subject is being filtered.  Only
 * applicable for bbPress >= 2.5.4.
 *
 * @since 3.4.1
 *
 * @param string $retval The subscription email subject
 * @return string
 */
function ass_bbp_add_topic_subscribers_filter( $retval ) {
	// Add our filter to check topic subscribers.
	if ( 'bbp_subscription_mail_title' === current_filter() ) {
		add_filter( 'bbp_get_topic_subscribers', 'ass_bbp_remove_topic_subscribers' );

	// For forum subscribers.
	} else {
		add_filter( 'bbp_get_forum_subscribers', 'ass_bbp_remove_topic_subscribers' );
	}

	return $retval;
}

/**
 * Wrapper function to remove our topic subscriber filter for bbPress.
 *
 * This fires before the bbPress subscription email is sent.  Only
 * applicable for bbPress >= 2.5.4.
 *
 * @since 3.4.1
 */
function ass_bbp_remove_topic_subscribers_filter() {
	// Remove our filter to check topic subscribers.
	if ( 'bbp_pre_notify_subscribers' === current_action() ) {
		remove_filter( 'bbp_get_topic_subscribers', 'ass_bbp_remove_topic_subscribers' );

	// For forum subscribers.
	} else {
		remove_filter( 'bbp_get_forum_subscribers', 'ass_bbp_remove_topic_subscribers' );
	}
}

/**
 * Disable bbP's subscription email blast if group user is already subscribed
 * to "All Mail".
 *
 * This scenario might happen if a user subscribed to a bunch of bbP group
 * topics and later switched to the group's "All Mail" subscription.
 *
 * Note: Only applicable for bbPress 2.5.3 and below.
 *
 * @since 3.4
 */
function ass_bbp_disable_email( $message, $reply_id, $topic_id, $user_id ) {
	global $bp;

	// get group sub status
	// we're using the direct, global reference for sanity's sake
	$group_status = ass_get_group_subscription_status( $user_id, $bp->groups->current_group->id );

	// if user's group sub status is "All Mail", stop bbP's email from sending
	// by blanking out the message
	if ( $group_status == 'supersub' ) {
		return false;
	}
	
	return $message;
}