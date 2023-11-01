<?php
/**
 * Core functions for GES.
 *
 * @since 1.0.0
 */

//
// !SEND EMAIL UPDATES FOR FORUM TOPICS AND POSTS
//

/**
 * Returns an unsubscribe link to disable email notifications for a given group and/or all groups.
 *
 * @param int $user_id  WP user ID
 * @param int $group_id BuddyPress group ID.
 *
 * @return string
 */
function ass_group_unsubscribe_links( $user_id, $group_id = 0 ) {
	global $bp;

	$links = sprintf( __( 'To disable all notifications for this group, click: %s', 'buddypress-group-email-subscription' ), ass_get_group_unsubscribe_link_for_user( $user_id, $group_id ) );

	if ( get_option( 'ass-global-unsubscribe-link' ) == 'yes' ) {
		$links .= "\n\n" . sprintf( __( 'Or to disable notifications for *all* your groups, click: %s', 'buddypress-group-email-subscription' ), ass_get_group_unsubscribe_link_for_user( $user_id, 0, true ) );
	}

	$links .= "\n";

	return $links;
}

/**
 * Get the group unsubscribe link for a user.
 *
 * @since 3.7.0
 *
 * @param  int  $user_id  WP user ID.
 * @param  int  $group_id BuddyPress group ID.
 * @param  bool $global   Should we use the global unsubscribe link? If 'false', we will use the
  *                       single group's unsubscribe link. Default: false.
 * @return string|bool URL for unsubscribe link on success; boolean false on failure.
 */
function ass_get_group_unsubscribe_link_for_user( $user_id = 0, $group_id = 0, $global = false ) {
	if ( empty( $user_id ) ) {
		return false;
	}

	$args = array(
		'bpass-action' => 'unsubscribe',
	);

	// Use global unsubscribe link.
	if ( true === $global ) {
		$access_key = md5( "{$user_id}unsubscribe" . wp_salt() );

	// Single group unsubscribe link.
	} else {
		$group_id = empty( $group_id ) ? bp_get_current_group_id() : (int) $group_id;
		if ( empty( $group_id ) ) {
			return false;
		}

		$access_key = md5( "{$group_id}{$user_id}unsubscribe" . wp_salt() );

		$args['group'] = $group_id;
	}

	$args['access_key'] = $access_key;

	return add_query_arg( $args, bp_members_get_user_url( $user_id ) );
}

/**
 * Temporarily save the full activity content before activity KSES kicks in.
 *
 * At the moment, we only do this for bbPress content since bbPress supports a
 * larger amount of elements than BuddyPress' activity KSES filter.
 *
 * @since 3.7.0
 *
 * @param string               $retval   Current activity content.
 * @param BP_Activity_Activity $activity Activity object.
 * @return string
 */
function ass_group_notification_activity_content_before_save( $retval = '', BP_Activity_Activity $activity = null ) {
	// If not bbPress content, bail.
	if ( 0 !== strpos( $activity->type, 'bbp_' ) ) {
		return $retval;
	}

	// Temporarily save bbPress content.
	$GLOBALS['bp']->ges_content = $retval;

	return $retval;
}
add_filter( 'bp_activity_content_before_save', 'ass_group_notification_activity_content_before_save', -999, 2 );

/**
 * Records group activity items in GES for all activity except:
 *  - group forum posts (handled in ass_group_notification_forum_posts())
 *  - created and joined group entries (irrelevant)
 *
 * You can do more fine-grained activity filtering with the
 * 'ass_block_group_activity_types' filter.
 */
function ass_group_notification_activity( BP_Activity_Activity $activity ) {
	$is_group_activity_item = buddypress()->groups->id === $activity->component;

	// Special handling for 'activity_comment' items.
	if ( ! $is_group_activity_item && 'activity_comment' === $activity->type ) {
		$root_activity          = new BP_Activity_Activity( $activity->item_id );
		$is_group_activity_item = buddypress()->groups->id === $root_activity->component;
	}

	if ( ! $is_group_activity_item ) {
		return;
	}

	/**
	 * Allows activity item queuing to be prevented.
	 *
	 * @param bool                 $send     Defaults to true. Return false to stop this item from being queued.
	 * @param string               $type     Activity type.
	 * @param BP_Activity_Activity $activity Activity item.
	 */
	if ( false === apply_filters( 'ass_block_group_activity_types', true, $activity->type, $activity ) )
		return;

	if ( ! ass_registered_long_enough( $activity->user_id  ) ) {
		return;
	}

	// activity_comment must have its group ID inferred.
	// @todo This can be made less terrible.
	$group_id = $activity->item_id;
	if ( 'activity_comment' === $activity->type ) {
		$group_id = bp_get_current_group_id();
	}

	// No group, nothing to do.
	$group = groups_get_group( array( 'group_id' => $group_id ) );
	if ( ! $group->id ) {
		return;
	}

	/**
	 * Forces an item to be "important", ie not excluded from weekly summaries.
	 *
	 * @param bool   $is_important Defaults to false.
	 * @param string $type         Activity type.
	 */
	$this_activity_is_important = apply_filters( 'ass_this_activity_is_important', false, $activity->type );

	// If we've gotten this far, record the activity item for each subscribed group member.
	$to_queue         = array();
	$has_immediate    = false;
	$subscribed_users = ass_get_subscriptions_for_group( $group_id );

	foreach ( $subscribed_users as $user_id => $subscription_type ) {
		$self_notify = false;

		// Does the author want updates of their own forum posts?
		if ( $activity->type == 'bbp_topic_create' || $activity->type == 'bbp_reply_create' ) {
			if ( $user_id === $activity->user_id ) {
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
		} elseif ( 'activity_comment' == $activity->type ) {
			// First, look at the immediate parent
			$immediate_parent = new BP_Activity_Activity( $activity->secondary_item_id );

			// Don't send the bp-ass notification if the user is subscribed through BP
			if ( $user_id == $immediate_parent->user_id && 'no' !== bp_get_user_meta( $user_id, 'notification_activity_new_reply', true ) ) {
				continue;
			}

			// We only need to check the root parent if it's different from the immediate parent.
			if ( $activity->secondary_item_id !== $activity->item_id ) {
				$root_parent = new BP_Activity_Activity( $activity->item_id );

				// Don't send the bp-ass notification if the user is subscribed through BP
				if ( $user_id == $root_parent->user_id && 'no' != bp_get_user_meta( $user_id, 'notification_activity_new_reply', true ) ) {
					continue;
				}
			}
		}

		$send_immediately = $add_to_digest_queue = false;

		if (
			( true === $self_notify ) ||
			( 'supersub' === $subscription_type ) ||
			( 'sub' === $subscription_type && 'bbp_topic_create' === $activity->type )
		) {
			$send_immediately = true;
		}

		// Special case: users should not get immediate notifications of their own group activity updates.
		if ( 'activity_update' === $activity->type && $activity->user_id === $user_id ) {
			$send_immediately = false;
		}

		if (
			( 'dig' === $subscription_type ) ||
			( 'sum' === $subscription_type && $this_activity_is_important )
		) {
			$add_to_digest_queue = true;
		}

		// Check whether user preferences should be overridden for admin notices.
		if ( 'bpges_notice' === $activity->type && bpges_force_immediate_admin_notice( $user_id, $activity, $group_id ) ) {
			$send_immediately    = true;
			$add_to_digest_queue = false;
		}

		/**
		 * Filters whether a given user should receive immediate notification of the current activity.
		 *
		 * @since 3.6.0
		 * @since 3.8.0 Added `$subscription_type` parameter.
		 *
		 * @param bool   $send_immediately True to send an immediate email notification, false otherwise.
		 * @param object $activity          Activity object.
		 * @param int    $user_id           ID of the user.
		 * @param string $subscription_type Group subscription status for the current user.
		 */
		$send_immediately = apply_filters( 'bp_ass_send_activity_notification_for_user', $send_immediately, $activity, $user_id, $subscription_type );

		/**
		 * Filters whether to add the current activity item to the digest queue for the current user.
		 *
		 * @since 3.8.0
		 *
		 * @param bool   $add_to_digest_queue True to send an immediate email notification, false otherwise.
		 * @param object $activity_obj        Activity object.
		 * @param int    $user_id             ID of the user.
		 * @param string $subscription_type   Group subscription status for the current user.
		 */
		$add_to_digest_queue = apply_filters( 'bp_ges_add_to_digest_queue_for_user', $add_to_digest_queue, $activity, $user_id, $subscription_type );

		if ( $send_immediately ) {
			$has_immediate = true;

			$to_queue[] = array(
				'user_id'       => $user_id,
				'group_id'      => $group_id,
				'activity_id'   => $activity->id,
				'type'          => 'immediate',
				'date_recorded' => date( 'Y-m-d H:i:s' ),
			);
		}

		if ( $add_to_digest_queue ) {
			$to_queue[] = array(
				'user_id'       => $user_id,
				'group_id'      => $group_id,
				'activity_id'   => $activity->id,
				'type'          => $subscription_type,
				'date_recorded' => date( 'Y-m-d H:i:s' ),
			);
		}
	}

	// Bulk insert.
	if ( $to_queue ) {
		BPGES_Queued_Item::bulk_insert( $to_queue );

		// Trigger the batch process.
		if ( $has_immediate ) {
			bpges_send_queue()->data( array(
				'type'        => 'immediate',
				'activity_id' => $activity->id,
			) )->dispatch();
		}
	}
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
	$the_content = ass_clean_content( $the_content, $activity_obj );

	// If message has no content (as in the case of group joins, etc), we'll use a different
	// $message template
	if ( empty( $the_content ) ) {
		$message = sprintf( __(
'%1$s

To view or reply, log in and go to:
%2$s

---------------------
', 'buddypress-group-email-subscription' ), $r['action'], $r['link'] );
	} else {
		$message = sprintf( __(
'%1$s

"%2$s"

To view or reply, log in and go to:
%3$s

---------------------
', 'buddypress-group-email-subscription' ), $r['action'], $the_content, $r['link'] );
	}

	// Use bbPress filtered post content and reapply GES filter... sigh.
	if ( 0 === strpos( $activity_obj->type, 'bbp_' ) || 'new_groupblog_post' === $activity_obj->type ) {
		// Not in global cache? Query for post content.
		if ( empty( $GLOBALS['bp']->ges_content ) ) {
			$switched = false;
			if ( 'new_groupblog_post' === $activity_obj->type && function_exists( 'get_groupblog_blog_id' ) ) {
				$switched = true;
				switch_to_blog( get_groupblog_blog_id( $group->id ) );
			}

			$the_content = get_post_field( 'post_content', $activity_obj->secondary_item_id, 'raw' );

			if ( true === $switched ) {
				restore_current_blog();
			}
		} else {
			$the_content = $GLOBALS['bp']->ges_content;
			unset( $GLOBALS['bp']->ges_content );
		}

		// Apply bbPress KSES filter if it exists (sanity check!)
		$the_content = ( true === function_exists( 'bbp_filter_kses' ) ) ? wp_unslash( bbp_filter_kses( $the_content ) ) : $the_content;

		$the_content = apply_filters( 'bp_ass_activity_notification_content', $the_content, $activity_obj, $r['action'], $group );
	}

	// get subscribed users for the group
	$subscribed_users = ass_get_subscriptions_for_group( $r['group_id'] );

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

		$send_immediately = $add_to_digest_queue = false;

		if (
			( true === $self_notify ) ||
			( 'supersub' === $group_status ) ||
			( 'sub' === $group_status && 'bbp_topic_create' === $activity_obj->type )
		) {
			$send_immediately = true;
		}

		// Special case: users should not get immediate notifications of their own group activity updates.
		if ( 'activity_update' === $activity_obj->type && $r['sender_id'] === $user_id ) {
			$send_immediately = false;
		}

		if (
			( 'dig' === $group_status ) ||
			( 'sum' === $group_status && $this_activity_is_important )
		) {
			$add_to_digest_queue = true;
		}

		/**
		 * Filters whether a given user should receive immediate notification of the current activity.
		 *
		 * @since 3.6.0
		 * @since 3.8.0 Added `$group_status` parameter.
		 *
		 * @param bool   $send_immediately True to send an immediate email notification, false otherwise.
		 * @param object $activity_obj     Activity object.
		 * @param int    $user_id          ID of the user.
		 * @param string $group_status     Group subscription status for the current user.
		 */
		$send_immediately = apply_filters( 'bp_ass_send_activity_notification_for_user', $send_immediately, $activity_obj, $user_id, $group_status );

		/**
		 * Filters whether to add the current activity item to the digest queue for the current user.
		 *
		 * @since 3.8.0
		 *
		 * @param bool   $add_to_digest_queue True to send an immediate email notification, false otherwise.
		 * @param object $activity_obj        Activity object.
		 * @param int    $user_id             ID of the user.
		 * @param string $group_status        Group subscription status for the current user.
		 */
		$add_to_digest_queue = apply_filters( 'bp_ges_add_to_digest_queue_for_user', $add_to_digest_queue, $activity_obj, $user_id, $group_status );

		$raw_group_status = $group_status;

		// Assemble variables for use in building immediate notification, if necessary.
		if ( $send_immediately ) {
			// Self-notification email for bbPress posts
			if ( true === $self_notify ) {
				$group_status = 'self_notify';

				// notification settings link
				$settings_link = bp_members_get_user_url(
					$user_id,
					bp_members_get_path_chunks( array( bp_get_settings_slug(), 'notifications' ) )
				);

				$settings_link .= '#groups-subscription-notification-settings';

				// set notice
				$notice = $email_setting_desc = __( 'You are currently receiving notifications for your own posts.', 'buddypress-group-email-subscription' );

				$email_setting_links = sprintf( __( 'To disable these notifications please log in and go to: %s', 'buddypress-group-email-subscription' ), $settings_link );
				$email_setting_links .= "\n\n" . __( 'Once you are logged in, uncheck "Receive notifications of your own posts?".', 'buddypress-group-email-subscription' );

				$notice .= "\n\n" . $email_setting_links;

			} else {

				$settings_link = bp_get_group_url(
					$group,
					bp_groups_get_path_chunks( array( 'notifications' ) )
				);
				$settings_link = ass_get_login_redirect_url( $settings_link, $group_status );

				$email_setting_string = __( 'Your email setting for this group is: %s', 'buddypress-group-email-subscription' );
				$group_status_string  = ass_subscribe_translate( $group_status );

				$notice             = sprintf( $email_setting_string, $group_status_string );
				$email_setting_desc = sprintf( $email_setting_string, '<strong> ' . $group_status_string . '</strong>' );

				$email_setting_links = sprintf( __( 'To change your email setting for this group, please log in and go to: %s', 'buddypress-group-email-subscription' ), $settings_link );
				$email_setting_links .= "\n\n" . ass_group_unsubscribe_links( $user_id, $r[ 'group_id' ] );

				$notice .= "\n" . $email_setting_links;
			}
		}

		// if we're good to send, send the email!
		if ( $send_immediately ) {
			$user_message_args = array(
				'message'           => $message,
				'notice'            => $notice,
				'user_id'           => $user_id,
				'subscription_type' => $group_status,
				'content'           => $the_content,
				'settings_link'     => ! empty( $settings_link ) ? $settings_link : '',
			);

			// One last chance to filter the message content
			$user_message = apply_filters( 'bp_ass_activity_notification_message', $message . $notice, $user_message_args );

			// Get the details for the user
			$user = bp_core_get_core_userdata( $user_id );

			// Send the email
			if ( $user->user_email ) {
				// Custom GES email tokens.
				$user_message_args['ges.action']  = stripslashes( $activity_obj->action ); // Unfiltered.
				$user_message_args['ges.subject'] = strip_tags( stripslashes( $r['action'] ) ); // Unfiltered.
				$user_message_args['ges.email-setting-description'] = $email_setting_desc;
				$user_message_args['ges.email-setting-links']       = $email_setting_links;
				$user_message_args['ges.unsubscribe-global']        = ass_get_group_unsubscribe_link_for_user( $user->ID, $r['group_id'], true );
				$user_message_args['ges.unsubscribe']   = ass_get_group_unsubscribe_link_for_user( $user->ID, $r['group_id'] );
				$user_message_args['ges.settings-link'] = $user_message_args['settings_link'];
				$user_message_args['poster.url']        = bp_members_get_user_url( $r['sender_id'] );
				$user_message_args['recipient.id']      = $user->ID;

				// BP-specific tokens.
				$user_message_args['usermessage'] = $the_content;
				$user_message_args['poster.name'] = bp_core_get_user_displayname( $r['sender_id'] );
				$user_message_args['thread.url']  = $r['link'];
				$user_message_args['group.id']    = $r['group_id'];

				// Remove tokens that we're not using.
				unset( $user_message_args['content'], $user_message_args['notice'], $user_message_args['message'], $user_message_args['settings_link'] );

				// Add activity KSES filter if not bbPress or groupblog item.
				if ( false === strpos( $activity->type, 'bbp_' ) && 'new_groupblog_post' !== $activity->type ) {
					add_filter( 'bp_email_set_content_html', 'bp_activity_filter_kses', 6 );
				}

				// Sending time!
				ass_send_email( 'bp-ges-single', $user->user_email, array(
					'tokens'   => $user_message_args,
					'subject'  => $subject,
					'content'  => $user_message,
					'activity' => $activity_obj
				) );

				// Revert!
				if ( false === strpos( $activity->type, 'bbp_' ) && 'new_groupblog_post' !== $activity->type ) {
					remove_filter( 'bp_email_set_content_html', 'bp_activity_filter_kses', 6 );
				}
			}

		}

		// Record in digest queue, if necessary.
		if ( $add_to_digest_queue ) {
			ass_digest_record_activity( $r['activity_id'], $user_id, $r['group_id'], $raw_group_status );
		}
	}
}

/**
 * Generate an immediate email notification for an activity + user combo.
 *
 * @since 3.9.0
 *
 * @param BPGES_Queued_Item $queued_item Queued item.
 */
function bpges_generate_notification( BPGES_Queued_Item $queued_item ) {
	$activity_id = $queued_item->activity_id;
	$user_id     = $queued_item->user_id;
	$group_id    = $queued_item->group_id;

	// Fetch group subscription.
	$sub = new BPGES_Subscription_Query( array(
		'user_id'  => $user_id,
		'group_id' => $group_id,
		'per_page' => 1
	) );
	$sub = $sub->get_results();
	$sub = end( $sub );

	// Set group status to subscription type.
	$group_status = $sub->type;

	$activity = new BP_Activity_Activity( $activity_id );
	$group    = groups_get_group(
		array(
			'group_id' => $group_id,
		)
	);

	// @todo We can add nonpersistent caching for static content.

	if ( 'activity_comment' === $activity->type ) { // if it's an group activity comment, reset to the proper group id and append the group name to the action
		// this will need to be filtered for plugins manually adding group activity comments

		$action_for_subject_line = ass_clean_subject( $activity->action ) . ' ' . __( 'in the group', 'buddypress-group-email-subscription' ) . ' ' . $group->name;
	} else {
		$action_for_subject_line = ass_clean_subject( $activity->action );
	}

	$action_for_email_content = $activity->action;

	if ( has_action( 'bpges_activity_action' ) ) {
		/**
		 * Filters the activity action used when generating notifications.
		 *
		 * @deprecated 4.1.0 Don't use this anymore. Instead use the separate filters for
		 *                   subject lines versus email content.
		 *
		 * @param string $action
		 * @param object $activity
		 */
		$action_for_subject_line  = apply_filters( 'bpges_activity_action', $action_for_subject_line, $activity );
		$action_for_email_content = apply_filters( 'bpges_activity_action', $action_for_email_content, $activity );
	}

	/* Subject & Content */
	$blogname    = '[' . get_blog_option( BP_ROOT_BLOG, 'blogname' ) . ']';
	$subject     = apply_filters( 'bp_ass_activity_notification_subject', $action_for_subject_line . ' ' . $blogname, $action_for_subject_line, $blogname );
	$the_content = apply_filters( 'bp_ass_activity_notification_content', $activity->content, $activity, $action_for_email_content, $group );
	$the_content = ass_clean_content( $the_content, $activity );

	/*
	 * If it's an activity item, switch the activity permalink to the group homepage
	 * rather than the user's homepage.
	 */
	$link = bp_get_group_url( $group );
	if ( $activity->primary_link && $activity->primary_link !== bp_members_get_user_url( $activity->user_id ) ) {
		$link = $activity->primary_link;
	}

	/**
	 * Filters the activity action string used to build the notification email.
	 *
	 * @since 4.1.0
	 *
	 * @param string $action_for_email_content
	 * @return string
	 */
	$subject = apply_filters( 'bpges_notification_activity_action', $action_for_email_content, $activity );

	/**
	 * Filters the activity link used to build the notification email.
	 *
	 * @since 4.0.2
	 *
	 * @param string               $link
	 * @param BP_Activity_Activity $activity
	 */
	$link = apply_filters( 'bpges_notification_link', $link, $activity );

	// If message has no content (as in the case of group joins, etc), we'll use a different
	// $message template
	if ( empty( $the_content ) ) {
		$message = sprintf( __(
'%1$s

To view or reply, log in and go to:
%2$s

---------------------
', 'buddypress-group-email-subscription' ), $action_for_email_content, $link );
	} else {
		$message = sprintf( __(
'%1$s

"%2$s"

To view or reply, log in and go to:
%3$s

---------------------
', 'buddypress-group-email-subscription' ), $action_for_email_content, $the_content, $link );
	}

	// Use bbPress filtered post content and reapply GES filter... sigh.
	$self_notify = false;
	if ( 0 === strpos( $activity->type, 'bbp_' ) || 'new_groupblog_post' === $activity->type ) {
		// Not in global cache? Query for post content.
		if ( empty( $GLOBALS['bp']->ges_content ) ) {
			$switched = false;
			if ( 'new_groupblog_post' === $activity->type && function_exists( 'get_groupblog_blog_id' ) ) {
				$switched = true;
				switch_to_blog( get_groupblog_blog_id( $group_id ) );
			}

			$the_content = get_post_field( 'post_content', $activity->secondary_item_id, 'raw' );

			if ( true === $switched ) {
				restore_current_blog();
			}
		} else {
			$the_content = $GLOBALS['bp']->ges_content;
			unset( $GLOBALS['bp']->ges_content );
		}

		// Apply bbPress KSES filter if it exists (sanity check!)
		$the_content = ( true === function_exists( 'bbp_filter_kses' ) ) ? wp_unslash( bbp_filter_kses( $the_content ) ) : $the_content;

		$the_content = apply_filters( 'bp_ass_activity_notification_content', $the_content, $activity, $action_for_email_content, $group );

		// Check for $self_notify status.
		$self_notify = ass_self_post_notification( $user_id );
		if ( ! empty( $self_notify ) && (int) $activity->user_id === (int) $user_id ) {
			$group_status = 'self_notify';
		}
	}

	if ( $self_notify ) {
		// notification settings link
		$settings_link = bp_members_get_user_url(
			$user_id,
			bp_members_get_path_chunks( array( bp_get_settings_slug(), 'notifications' ) )
		);

		$settings_link .= '#groups-subscription-notification-settings';

		// set notice
		$notice = $email_setting_desc = __( 'You are currently receiving notifications for your own posts.', 'buddypress-group-email-subscription' );

		$email_setting_links = sprintf( __( 'To disable these notifications please log in and go to: %s', 'buddypress-group-email-subscription' ), $settings_link );
		$email_setting_links .= "\n\n" . __( 'Once you are logged in, uncheck "Receive notifications of your own posts?".', 'buddypress-group-email-subscription' );

		$notice .= "\n\n" . $email_setting_links;
	} else {
		$settings_link = bp_get_group_url(
			$group,
			bp_groups_get_path_chunks( array( 'notifications' ) )
		);

		$settings_link = ass_get_login_redirect_url( $settings_link, $group_status );

		$email_setting_string = __( 'Your email setting for this group is: %s', 'buddypress-group-email-subscription' );
		$group_status_string  = ass_subscribe_translate( $group_status );

		$notice             = sprintf( $email_setting_string, $group_status_string );
		$email_setting_desc = sprintf( $email_setting_string, '<strong> ' . $group_status_string . '</strong>' );

		$email_setting_links = sprintf( __( 'To change your email setting for this group, please log in and go to: %s', 'buddypress-group-email-subscription' ), $settings_link );
		$email_setting_links .= "\n\n" . ass_group_unsubscribe_links( $user_id, $group_id );

		$notice .= "\n" . $email_setting_links;
	}

	$email_type = 'bp-ges-single';
	$group_name = bp_get_group_name( $group );
	$group_link = bp_get_group_url( $group );

	$subject = strip_tags( stripslashes( $action_for_subject_line ) );

	/**
	 * Filters the subject line of immediate notifications.
	 *
	 * @since 4.1.0
	 *
	 * @param string $action_for_subject_line
	 * @return string
	 */
	$subject = apply_filters( 'bpges_notification_subject', $subject, $activity );

	// bpges_notice is a special activity type and gets some overrides.
	if ( 'bpges_notice' === $activity->type ) {
		$email_type = 'bp-ges-notice';

		$subject = bp_activity_get_meta( $activity_id, 'bpges_notice_subject', true );

		$message = sprintf( __(
'This is a notice from the group \'%s\':

"%s"


To view this group log in and follow the link below:
%s

---------------------
', 'buddypress-group-email-subscription' ), $group_name, $the_content, bp_get_group_url( $group ) );

		$message .= __( 'Please note: admin notices are sent to everyone in the group and cannot be disabled.
If you feel this service is being misused please speak to the website administrator.', 'buddypress-group-email-subscription' );
	}

	$user_message_args = array(
		'message'           => $message,
		'notice'            => $notice,
		'user_id'           => $user_id,
		'subscription_type' => $group_status,
		'content'           => $the_content,
		'settings_link'     => ! empty( $settings_link ) ? $settings_link : '',
	);

	// One last chance to filter the message content
	$user_message = apply_filters( 'bp_ass_activity_notification_message', $message . $notice, $user_message_args );

	// Get the details for the user
	$user = bp_core_get_core_userdata( $user_id );

	// Send the email
	if ( $user->user_email ) {
		// Custom GES email tokens.
		$user_message_args['ges.action']  = stripslashes( $action_for_email_content ); // Unfiltered.
		$user_message_args['ges.subject'] = $subject; // Unfiltered.
		$user_message_args['ges.email-setting-description'] = $email_setting_desc;
		$user_message_args['ges.email-setting-links']       = $email_setting_links;
		$user_message_args['ges.unsubscribe-global']        = ass_get_group_unsubscribe_link_for_user( $user->ID, $group_id, true );
		$user_message_args['ges.unsubscribe']   = ass_get_group_unsubscribe_link_for_user( $user->ID, $group_id );
		$user_message_args['ges.settings-link'] = $user_message_args['settings_link'];
		$user_message_args['poster.url']        = bp_members_get_user_url( $activity->user_id );
		$user_message_args['recipient.id']      = $user->ID;

		// BP-specific tokens.
		$user_message_args['usermessage'] = $the_content;
		$user_message_args['poster.name'] = bp_core_get_user_displayname( $activity->user_id );
		$user_message_args['thread.url']  = $link;
		$user_message_args['group.id']    = $group_id;
		$user_message_args['group.link']  = sprintf( '<a href="%1$s">%2$s</a>', esc_url( $group_link ), $group_name );
		$user_message_args['group.name']  = $group_name;
		$user_message_args['group.url']   = esc_url( $group_link );
		$user_message_args['group.admin'] = bp_core_get_user_displayname( $user_id );

		// Remove tokens that we're not using.
		unset( $user_message_args['content'], $user_message_args['notice'], $user_message_args['message'], $user_message_args['settings_link'] );

		// Add activity KSES filter if not bbPress or groupblog item.
		if ( false === strpos( $activity->type, 'bbp_' ) && 'new_groupblog_post' !== $activity->type ) {
			add_filter( 'bp_email_set_content_html', 'bp_activity_filter_kses', 6 );
		}

		// Sending time!
		ass_send_email( $email_type, $user->user_email, array(
			'tokens'   => $user_message_args,
			'subject'  => $subject,
			'content'  => $user_message,
			'activity' => $activity,
		) );

		// Revert!
		if ( false === strpos( $activity->type, 'bbp_' ) && 'new_groupblog_post' !== $activity->type ) {
			remove_filter( 'bp_email_set_content_html', 'bp_activity_filter_kses', 6 );
		}
	}
}

/**
 * Email wrapper function for BP-GES.
 *
 * Created to be backward compatible when used on < BP 2.5.
 *
 * @since 3.7.0
 *
 * @param string                   $email_type Type of email being sent.
 * @param string|array|int|WP_User $to         Either a email address, user ID, WP_User object,
 *                                             or an array containg the address and name.
 * @param array                    $args {
 *     Optional. Array of extra parameters.
 *
 *     @type array $tokens Optional. Assocative arrays of string replacements for the email.
 * }
 * @return bool|WP_Error True if the email was sent successfully. Otherwise, a WP_Error object
 *                       describing why the email failed to send. The contents will vary based
 *                       on the email delivery class you are using.
 */
function ass_send_email( $email_type, $to, $args ) {
	/**
	 * Filters the 'from' name on outgoing emails.
	 *
	 * @since 4.0.2
	 *
	 * @param string $from_name  Default is null, which will use WP defaults.
	 * @param string $email_type
	 * @param string $to
	 * @param array  $args
	 */
	$from_name  = apply_filters( 'bpges_email_from_name', null, $email_type, $to, $args );

	/**
	 * Filters the 'from' email address on outgoing emails.
	 *
	 * @since 4.0.2
	 *
	 * @param string $from_email  Default is null, which will use WP defaults.
	 * @param string $email_type
	 * @param string $to
	 * @param array  $args
	 */
	$from_email = apply_filters( 'bpges_email_from_email', null, $email_type, $to, $args );

	if ( ! empty( $from_name ) || ! empty( $from_email ) ) {
		if ( ! isset( $args[ 'from' ] ) ) {
			$args['from'] = [];
		}

		if ( ! empty( $from_name ) ) {
			$args['from' ]['name'] = $from_name;
		}

		if ( ! empty( $from_email ) ) {
			$args['from']['email'] = $from_email;
		}
	}

	// BP 2.5+
	if ( true === function_exists( 'bp_send_email' ) && true === ! apply_filters( 'bp_email_use_wp_mail', false ) ) {
		// Unset array keys used for older BP installs.
		unset( $args['subject'], $args['content'] );

		// Temporary save tokens.
		buddypress()->ges_tokens = $args['tokens'];

		// Remove BP's restrictive HTML filtering.
		remove_filter( 'bp_email_set_content_html', 'wp_filter_post_kses', 6 );

		// Remove BP's plain-text filter and convert the HTML email content to Markdown.
		remove_filter( 'bp_email_set_content_plaintext', 'wp_strip_all_tags', 6 );
		add_filter( 'bp_email_get_property', 'ass_email_strip_trailing_breaklines', 1, 3 );
		add_filter( 'bp_email_get_property', 'ass_email_convert_html_to_plaintext', 20, 3 );

		// Remove default BP email footer.
		add_action( 'bp_before_email_footer', 'ob_start', 999, 0 );
		add_action( 'bp_after_email_footer',  'ob_get_clean', -999, 0 );

		// Add our custom BP email footer.
		add_action( 'bp_after_email_footer', 'ass_bp_email_footer_text' );
		add_action( 'bp_after_email_footer', 'ass_bp_email_footer_html_unsubscribe_links' );

		if ( isset( $args['from'] ) ) {
			buddypress()->ges_from = $args['from'];
			add_action( 'bp_email_set_tokens', 'ass_email_set_from_during_token_addition', 10, 3 );
		}

		/**
		 * Hook to do something before GES sends a BP email.
		 *
		 * @since 3.7.0
		 *
		 * @param string $email_type The GES email type.
		 */
		do_action( 'bp_ges_before_bp_send_email', $email_type );

		/**
		 * Filters the email type that GES uses to send a BuddyPress email.
		 *
		 * @since 4.0.0
		 *
		 * @param string $email_type Current BP email post type.
		 * @param array  $args       See bp_send_email()'s third argument for full documentation.
		 * @param string $to		 Email recipient.
		 */
		$email_type = apply_filters( 'ass_send_email_email_type', $email_type, $args, $to );

		/**
		 * Filter the arguments before GES sends a BuddyPress email.
		 *
		 * @since 3.7.0
		 *
		 * @param array  $args       See bp_send_email()'s third argument for full documentation.
		 * @param string $email_type Current BP email post type.
		 */
		$args = apply_filters( 'ass_send_email_args', $args, $email_type );

		// Email time!
		$send = bp_send_email( $email_type, (int) $args['tokens']['recipient.id'], $args );

		// Clean up after ourselves!
		add_filter( 'bp_email_set_content_html', 'wp_filter_post_kses', 6 );
		add_filter( 'bp_email_set_content_plaintext', 'wp_strip_all_tags', 6 );
		remove_filter( 'bp_email_get_property', 'ass_email_strip_trailing_breaklines', 1, 3 );
		remove_filter( 'bp_email_get_property', 'ass_email_convert_html_to_plaintext', 20, 3 );

		/**
		 * Hook to do something after GES sends a BP email.
		 *
		 * @since 3.7.0
		 *
		 * @param string $email_type The GES email type.
		 */
		do_action( 'bp_ges_after_bp_send_email', $email_type );

		return $send;

	// Older BP versions use wp_mail().
	} else {
		$headers = array();

		if ( isset( $args['from'] ) ) {
			$headers[] = "From: \"{$args['from']['name']}\" <{$args['from']['email']}>";
		}

		$plaintext_content = ass_email_convert_html_to_plaintext( $args['content'] );

		return wp_mail( $to, $args['subject'], $plaintext_content, $headers );
	}
}

/**
 * Sets the email situation type for use in GES.
 *
 * Only applicable for BuddyPress 2.5+.
 *
 * @since 3.7.0
 *
 * @param string $email_type The email type to fetch.
 * @param bool   $term_check Check if our GES email term exists before creating our specific email
 *                           situation. Default: true.
 */
function ass_set_email_type( $email_type, $term_check = true ) {
	$switched = false;

	if ( false === bp_is_root_blog() ) {
		$switched = true;
		switch_to_blog( bp_get_root_blog_id() );
	}

	if ( true === $term_check ) {
		$term = term_exists( $email_type, bp_get_email_tax_type() );
	} else {
		$term = 0;
	}

	// Term already exists so don't do anything.
	if ( true === $term_check && $term !== 0 && $term !== null ) {
		if ( true === $switched ) {
			restore_current_blog();
		}

		return;

	// Create our email situation.
	} else {
		// Set up default email content depending on the email type.
		switch ( $email_type ) {
			// Group activity single items.
			case 'bp-ges-single' :
				/* translators: do not remove {} brackets or translate its contents. */
				$post_title = __( '[{{{site.name}}}] {{{ges.subject}}}', 'buddypress-group-email-subscription' );

				/* translators: do not remove {} brackets or translate its contents. */
				$html_content = __( "{{{ges.action}}}\n\n<blockquote>{{{usermessage}}}</blockquote>\n&ndash;\n<a href=\"{{{thread.url}}}\">Go to the discussion</a> to reply or catch up on the conversation.\n{{{ges.email-setting-description}}}", 'buddypress-group-email-subscription' );

				/* translators: do not remove {} brackets or translate its contents. */
				$plaintext_content = __( "{{{ges.action}}}\n\n\"{{{usermessage}}}\"\n\nGo to the discussion to reply or catch up on the conversation:\n{{{thread.url}}}\n\n----\n\n{{{ges.email-setting-description}}}\n\n{{{ges.email-setting-links}}}", 'buddypress-group-email-subscription' );

				$situation_desc = __( 'A member created a group activity entry. Used by the Group Email Subscription plugin during immediate sendouts.', 'buddypress-group-email-subscription' );

				break;

			// Digests.
			case 'bp-ges-digest' :
				/* translators: do not remove {} brackets or translate its contents. */
				$post_title = __( '[{{{site.name}}}] {{{ges.subject}}}', 'buddypress-group-email-subscription' );

				/* translators: do not remove {} brackets or translate its contents. */
				$html_content = __( "{{{ges.digest-summary}}}{{{usermessage}}}\n&ndash;\nYou have received this message because you are subscribed to receive a digest of activity in some of your groups on {{site.name}}.", 'buddypress-group-email-subscription' );

				/* translators: do not remove {} brackets or translate its contents. */
				$plaintext_content = __( "{{{ges.digest-summary}}}\n\n{{{usermessage}}}\n\n----\n\nYou have received this message because you are subscribed to receive a digest of activity in some of your groups on {{{site.name}}}.\n\nTo disable these notifications per group, please login and [visit your groups page]({{{ges.settings-link}}}) where you can manage your email settings for each group.", 'buddypress-group-email-subscription' );

				$situation_desc = __( 'An email digest is sent to a member. Used by the Group Email Subscription plugin during daily or weekly digest sendouts.', 'buddypress-group-email-subscription' );

				break;

			// Admin notice.
			case 'bp-ges-notice' :
				/* translators: do not remove {} brackets or translate its contents. */
				$post_title = __( '[{{{site.name}}}] {{{ges.subject}}} - from the group "{{{group.name}}}"', 'buddypress-group-email-subscription' );

				/* translators: do not remove {} brackets or translate its contents. */
				$html_content = __( "This is a notice from the group {{{group.link}}}:\n\n{{{usermessage}}}\n\n&ndash;\n<strong>Please note:</strong> admin notices are sent to everyone in the group and cannot be disabled.\nIf you feel this service is being misused please speak to the website administrator.", 'buddypress-group-email-subscription' );

				/* translators: do not remove {} brackets or translate its contents. */
				$plaintext_content = __( "This is a notice from the group \"{{{group.name}}}\":\n\n\"{{{usermessage}}}\"\n\n----\n\nPlease note: admin notices are sent to everyone in the group and cannot be disabled.\n\nIf you feel this service is being misused please speak to the website administrator.\n\nTo visit the group homepage, click on the link below:\n{{{group.url}}}", 'buddypress-group-email-subscription' );

				$situation_desc = __( 'An email notice is sent by a group administrator to all members of the group. Used by the Group Email Subscription plugin.', 'buddypress-group-email-subscription' );

				break;

			// Welcome email.
			case 'bp-ges-welcome' :
				/* translators: do not remove {} brackets or translate its contents. */
				$post_title = __( '[{{{site.name}}}] {{{ges.subject}}}', 'buddypress-group-email-subscription' );

				$html_content = $plaintext_content = "{{{usermessage}}}";

				$situation_desc = __( 'A welcome email is sent to new members of a group. Used by the Group Email Subscription plugin.', 'buddypress-group-email-subscription' );

				break;
		}

		// Sanity check!
		if ( false === isset( $post_title ) ) {
			if ( true === $switched ) {
				restore_current_blog();
			}

			return;
		}

		$id = $email_type;

		$defaults = array(
			'post_status' => 'publish',
			'post_type'   => bp_get_email_post_type(),
		);

		$email = array(
			'post_title'   => $post_title,
			'post_content' => $html_content,
			'post_excerpt' => $plaintext_content,
		);

		// Email post content.
		$post_id = wp_insert_post( bp_parse_args( $email, $defaults, 'install_email_' . $id ) );

		// Save the situation.
		if ( ! is_wp_error( $post_id ) ) {
			$tt_ids = wp_set_object_terms( $post_id, $id, bp_get_email_tax_type() );

			// Situation description.
			if ( ! is_wp_error( $tt_ids ) ) {
				$term = get_term_by( 'term_taxonomy_id', (int) $tt_ids[0], bp_get_email_tax_type() );
				wp_update_term( (int) $term->term_id, bp_get_email_tax_type(), array(
					'description' => $situation_desc,
				) );
			}
		}
	}

	if ( true === $switched ) {
		restore_current_blog();
	}
}

/**
 * Sets 'From' email header for BuddyPress 2.5 emails during token addition.
 *
 * @since  3.7.0
 * @access private
 *
 * @param  array    $retval Formatted tokens.
 * @param  array    $tokens Unformatted tokens.
 * @param  BP_Email $email  BP Email object.
 * @return array    Token array.
 */
function ass_email_set_from_during_token_addition( $retval, $tokens, BP_Email $email ) {
	if ( isset( buddypress()->ges_from ) ) {
		$email->set_from( buddypress()->ges_from['email'], buddypress()->ges_from['name'] );
		unset( buddypress()->ges_from );
	}

	return $retval;
}

/**
 * Strip trailing breaklines created by BuddyPress during token additions.
 *
 * @since 3.7.0
 *
 * @see BP_Email::get() and the nl2br() call.
 *
 * @param string $content   Content to check.
 * @param string $prop      Property to check.
 * @param string $transform Transform type to check.
 * @return string
 */
function ass_email_strip_trailing_breaklines( $content = '', $prop = '', $transform = '' ) {
	if ( $transform !== 'add-content' ) {
		return $content;
	}

	$find = array(
		'ul><br />',
		'ol><br />',
		'li><br />',
		'</p><br />',
		'</blockquote><br />'
	);

	$replace = array(
		'ul>',
		'ol>',
		'li>',
		'</p>',
		'</blockquote>'
	);

	return str_replace( $find, $replace, $content );
}

/**
 * Convert HTML over to a form of Markdown plaintext.
 *
 * Do not confuse with {@link ass_convert_html_to_text()}. That function
 * strips tags.
 *
 * @since 3.7.0
 *
 * @uses html2text() by Jevon Wright. Licensed under the EPL v1.0 and LGPL v3.0.
 *       We use a fork of 0.1.1 to maintain PHP 5.2 compatibility.
 * @link https://github.com/r-a-y/html2text/tree/0.1.x
 * @link https://github.com/soundasleep/html2text/
 *
 * @param string $content The HTML content to convert to plaintext.
 * @param string $prop    Unused. This is only used by the 'bp_email_get_property' filter.
 * @param string $prop    Unused. This is only used by the 'bp_email_get_property' filter.
 * @return string
 */
function ass_email_convert_html_to_plaintext( $content = '', $prop = 'content_plaintext', $transform = 'replace-tokens' ) {
	if ( empty( $content ) || 'content_plaintext' !== $prop || 'replace-tokens' !== $transform ) {
		return $content;
	}

	if ( false === function_exists( 'convert_html_to_text' ) ) {
		require dirname( __FILE__ ) . '/html2text.php';
	}

	// Suppress warnings when using DOMDocument.
	// This addresses issues when failing to parse certain HTML.
	if ( function_exists( 'libxml_use_internal_errors' ) ) {
		libxml_use_internal_errors( true );
	}

	// Convert newlines to breaklines before using our HTML to text function.
	return convert_html_to_text( nl2br( $content ) );
}

/**
 * Output footer text from the BP Emails Customizer.
 *
 * For BuddyPress 2.5+.
 *
 * @since 3.7.0
 */
function ass_bp_email_footer_text() {
	if ( false === function_exists( 'bp_email_get_appearance_settings' ) ) {
		return;
	}

	$settings = bp_email_get_appearance_settings();

	$footer_text = stripslashes( $settings['footer_text'] );
	if ( $footer_text ) :
?>

	<span class="footer_text"><?php echo nl2br( $footer_text ); ?></span>
	<br><br>

<?php
	endif;
}

/**
 * Add custom BP email footer for HTML emails.
 *
 * We want to override the default {{unsubscribe}} token with something else.
 *
 * @since 3.7.0
 */
function ass_bp_email_footer_html_unsubscribe_links() {
	$tokens = buddypress()->ges_tokens;

	if ( ! isset( $tokens['subscription_type'] ) ) {
		return;
	}

	$link_format = '<a href="%1$s" title="%2$s" style="text-decoration: underline;">%3$s</a>';
	$footer_links = array();

	switch( $tokens['subscription_type'] ) {
		// Self-notifications.
		case 'self_notify' :
			$footer_links[] = sprintf( $link_format,
				$tokens['ges.settings-link'],
				esc_attr__( 'Once you are logged in, uncheck "Receive notifications of your own posts?".', 'buddypress-group-email-subscription' ),
				esc_html__( 'Change email settings', 'buddypress-group-email-subscription' )
			);

			break;

		// 'New Topics' or 'All Mail'.
		case 'sub':
		case 'supersub':
			$footer_links[] = sprintf( $link_format,
				$tokens['ges.settings-link'],
				esc_attr__( 'To change your email settings for this group only, click on this link', 'buddypress-group-email-subscription' ),
				esc_html__( 'Change group email Settings', 'buddypress-group-email-subscription' )
			);

			$footer_links[] = sprintf( '<a href="%1$s" title="%2$s">%3$s</a>',
				$tokens['ges.unsubscribe'],
				esc_attr__( 'To disable all notifications for this group, click on this link', 'buddypress-group-email-subscription' ),
				esc_html__( 'Unsubscribe from this group', 'buddypress-group-email-subscription' )
			);

			if ( 'yes' == get_option( 'ass-global-unsubscribe-link' ) ) {
				$footer_links[] = sprintf( $link_format,
					$tokens['ges.unsubscribe-global'],
					esc_attr__( 'To disable notifications from all your groups, click on this link', 'buddypress-group-email-subscription' ),
					esc_html__( 'Unsubscribe from all groups', 'buddypress-group-email-subscription' )
				);
			}

			break;

		// Digests.
		case 'dig' :
		case 'sum' :
			$footer_links[] = sprintf( $link_format,
				$tokens['ges.settings-link'],
				esc_attr__( 'Once you are logged in, change your email settings for each group.', 'buddypress-group-email-subscription' ),
				esc_html__( 'Change email settings', 'buddypress-group-email-subscription' )
			);

			break;
	}

	if ( ! empty( $footer_links ) ) {
		echo implode( ' &middot; ', $footer_links );
	}

	unset( buddypress()->ges_tokens );
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
 * Deletes queued items for the specified activity IDs.
 *
 * @since 3.9.0
 */
function bpges_delete_queued_items_for_activity_ids( $activity_ids ) {
	foreach ( $activity_ids as $activity_id ) {
		$query = new BPGES_Queued_Item_Query( array(
			'activity_id' => $activity_id,
		) );

		$queued_ids = array_keys( $query->get_results() );
		if ( empty( $queued_ids ) ) {
			return;
		}
		BPGES_Queued_Item::bulk_delete( $queued_ids );
	}
}
add_action( 'bp_activity_deleted_activities', 'bpges_delete_queued_items_for_activity_ids' );

/**
 * Queue an activity item for sending.
 *
 * @since 3.9.0
 *
 * @param int    $activity_id ID of the activity item.
 * @param int    $user_id     ID of the user.
 * @param int    $group_id    ID of the group.
 * @param string $type        Notification type. Accepts 'immediate', 'dig', 'sum'.
 */
function ass_queue_activity_item( $activity_id, $user_id, $group_id, $type ) {
	$queued_item = new BPGES_Queued_Item();

	$queued_item->activity_id   = $activity_id;
	$queued_item->user_id       = $user_id;
	$queued_item->group_id      = $group_id;
	$queued_item->type          = $type;
	$queued_item->date_recorded = date( 'Y-m-d H:i:s' );

	return $queued_item->save();
}

/**
 * Registers activity actions.
 *
 * @since 3.9.0
 */
function bpges_register_activity_actions() {
	bp_activity_set_action(
		buddypress()->groups->id,
		'bpges_notice',
		__( 'Posted a Group Notice', 'buddypress-group-email-subscription' ),
		'bpges_format_activity_action_bpges_notice'
	);
}
add_action( 'bp_register_activity_actions', 'bpges_register_activity_actions' );

/**
 * Formats activity actions of type 'bpges_notice'.
 *
 * @since 3.9.0
 *
 * @param string $action   Activity action.
 * @param object $activity Activity object.
 * @return string
 */
function bpges_format_activity_action_bpges_notice( $action, $activity, $subject = '' ) {
	$group      = groups_get_group( $activity->item_id );
	$group_link = bp_get_group_url( $group );

	$user_link = bp_core_get_userlink( $activity->user_id );

	if ( isset( $activity->id ) ) {
		$subject = bp_activity_get_meta( $activity->id, 'bpges_notice_subject', true );
	}

	/* translators: 1. Group admin link, 2. Group link, 3. Notice subject */
	return sprintf(
		'%1$s posted a notice in the group %2$s: "%3$s"',
		$user_link,
		sprintf( '<a href="%s">%s</a>', esc_attr( $group_link ), esc_html( bp_get_group_name( $group ) ) ),
		$subject
	);
}

/**
 * Block some activity types from being sent / recorded in groups.
 *
 * @since 3.2.2
 */
function ass_default_block_group_activity_types( $retval, $type, $activity ) {

	/*
	 * Do not resend a previous bbPress forum item to the group.
	 *
	 * This can occur when unapproving a forum post and later, reapproving it.
	 * We do this by checking the forum post's '_bbp_activity_id' meta entry.
	 */
	if ( 'bbp_topic_create' === $type || 'bbp_reply_create' === $type ) {
		$prev_activity_id = get_post_meta( $activity->secondary_item_id, '_bbp_activity_id', true );
		if ( ! empty( $prev_activity_id ) ) {
			return false;
		}
	}

	switch( $type ) {
		/** ACTIVITY TYPES TO BLOCK **************************************/

		// we handle these in ass_group_notification_forum_posts()
		case 'new_forum_topic' :
		case 'new_forum_post' :

		// @todo in the future, it might be nice for admins to optionally get this message
		case 'joined_group' :

		// BP handles these notifications on its own.
		case 'group_details_updated' :

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

	$login_redirect_url = add_query_arg(
		$query_args,
		apply_filters( 'ass_login_url', wp_login_url() )
	);

	return apply_filters( 'bp_ges_login_redirect_url', $login_redirect_url );
}


//
//	!GROUP SUBSCRIPTION
//

/**
 * Get a list of user subscriptions for a group.
 *
 * @since 3.8.0
 *
 * @param int $group_id
 * @return array
 */
function ass_get_subscriptions_for_group( $group_id ) {
	$query = new BPGES_Subscription_Query( array(
		'group_id' => $group_id,
	) );
	$subs = $query->get_results();

	$group_user_subscriptions = array();
	foreach ( $subs as $sub ) {
		$group_user_subscriptions[ $sub->user_id ] = $sub->type;
	}

	/**
	 * Filter's the group's user subscriptions.
	 *
	 * @since 3.8.0
	 *
	 * @param array $group_user_subscriptions Keys are user IDs, values are subscription levels.
	 * @param int   $group_id                 ID of the group.
	 */
	return apply_filters( 'bp_ges_group_user_subscriptions', $group_user_subscriptions, $group_id );
}


// returns the subscription status of a user in a group
function ass_get_group_subscription_status( $user_id, $group_id ) {
	global $bp;

	if ( !$user_id )
		$user_id = bp_loggedin_user_id();

	if ( !$group_id )
		$group_id = bp_get_current_group_id();

	$group_user_subscriptions = ass_get_subscriptions_for_group( $group_id );

	$user_subscription = isset( $group_user_subscriptions[$user_id] ) ? $group_user_subscriptions[$user_id] : false;

	return $user_subscription;
}


// updates the group's user subscription list.
function ass_group_subscription( $action, $user_id, $group_id ) {
	if ( !$action || !$user_id || !$group_id )
		return false;

	$query = new BPGES_Subscription_Query( array(
		'user_id'  => $user_id,
		'group_id' => $group_id,
	) );

	$existing = $query->get_results();
	if ( $existing ) {
		$subscription = reset( $existing );
	} else {
		$subscription = new BPGES_Subscription();
		$subscription->user_id = $user_id;
		$subscription->group_id = $group_id;
	}

	if ( 'delete' === $action ) {
		$subscription->delete();
	} else {
		$subscription->type = $action;
		$subscription->save();
	}

	// add a hook for 3rd-party plugin devs
	do_action( 'ass_group_subscription', $user_id, $group_id, $action );
}

/**
 * Returns a list of subscription levels, with labels and information about each level.
 *
 * @since 4.1.0
 *
 * @return array
 */
function bpges_subscription_levels() {
	$levels = [
		'no'       => [
			'label'             => __( 'No Email', 'buddypress-group-email-subscription' ),
			'label_short'       => __( 'No Email', 'buddypress-group-email-subscription' ),
			'description_admin' => __( 'No Email (users will read this group on the web - good for any group)', 'buddypress-group-email-subscription' ),
			'description_user'  => __( 'I will read this group on the web', 'buddypress-group-email-subscription' ),
		],
		'sum'      => [
			'label'             => __( 'Weekly Summary', 'buddypress-group-email-subscription' ),
			'label_short'       => __( 'Weekly', 'buddypress-group-email-subscription' ),
			'description_admin' => __( 'Weekly Summary Email (the week\'s topics - good for large groups)', 'buddypress-group-email-subscription' ),
			'description_user'  => __( 'Get a summary of topics each week', 'buddypress-group-email-subscription' ),
		],
		'dig'      => [
			'label'             => __( 'Daily Digest', 'buddypress-group-email-subscription' ),
			'label_short'       => __( 'Daily', 'buddypress-group-email-subscription' ),
			'description_admin' => __( 'Daily Digest Email (all daily activity bundles in one email - good for medium-size groups)', 'buddypress-group-email-subscription' ),
			'description_user'  => __( 'Get the day\'s activity bundled into one email', 'buddypress-group-email-subscription' ),
		],
		'sub'      => [
			'label'             => __( 'New Topics', 'buddypress-group-email-subscription' ),
			'label_short'       => __( 'New Topics', 'buddypress-group-email-subscription' ),
			'description_admin' => __( 'New Topics Email (new topics are sent as they arrive, but not replies - good for small groups)', 'buddypress-group-email-subscription' ),
			'description_user'  => __( 'Send new topics as they arrive (but no replies)', 'buddypress-group-email-subscription' ),
		],
		'supersub' => [
			'label'             => __( 'All Email', 'buddypress-group-email-subscription' ),
			'label_short'       => __( 'All Email', 'buddypress-group-email-subscription' ),
			'description_admin' => __( 'All Email (send emails about everything - recommended only for working groups)', 'buddypress-group-email-subscription' ),
			'description_user'  => __( 'Send all group activity as it arrives', 'buddypress-group-email-subscription' ),
		],
	];

	if ( ! ass_get_forum_type() ) {
		unset( $levels['sub'] );
	}

	/**
	 * Filters the available subscription levels.
	 *
	 * @since 4.1.0
	 *
	 * @param array Array of subscription levels.
	 */
	return apply_filters( 'bpges_subscription_levels', $levels );
}

/**
 * Gets the label for a subscription level.
 *
 * @param string $status Level slug.
 * @return string
 */
function ass_subscribe_translate( $status ) {
	if ( ! $status ) {
		$status = 'no';
	}

	$subscription_levels = bpges_subscription_levels();

	if ( ! isset( $subscription_levels[ $status ]['label_short'] ) ) {
		return '';
	}

	return $subscription_levels[ $status ]['label_short'];
}

// Handles AJAX request to subscribe/unsubscribe from group
function ass_group_ajax_callback() {
	$action = $_POST['a'];
	$user_id = bp_loggedin_user_id();
	$group_id = $_POST['group_id'];

	check_ajax_referer( "bpges-sub-{$group_id}" );

	ass_group_subscription( $action, $user_id, $group_id );

	echo $action;
	exit();
}
add_action( 'wp_ajax_ass_group_ajax', 'ass_group_ajax_callback' );

/** GROUP LEAVE/REMOVAL EVENTS ***********************************************/

/**
 * No longer used.
 *
 * @param int $group_id ID of the group.
 * @param int $user_id  ID of the user.
 */
function ass_unsubscribe_on_leave( $group_id, $user_id ){
	ass_group_subscription( 'delete', $user_id, $group_id );
}

/**
 * Remove a user's subscription level after a 'remove' action.
 *
 * @since 3.8.0
 *
 * @param BP_Groups_Member $membership
 */
function bpges_unsubscribe_on_membership_remove( BP_Groups_Member $membership ) {
	ass_group_subscription( 'delete', $membership->user_id, $membership->group_id );
	bpges_delete_queued_items_for_user_group( $membership->user_id, $membership->group_id );
}
add_action( 'groups_member_before_remove', 'bpges_unsubscribe_on_membership_remove' );

/**
 * Remove a user's subscription level after a 'delete' action.
 *
 * @since 3.8.0
 *
 * @param int $user_id  ID of the user.
 * @param int $group_id ID of the group.
 */
function bpges_unsubscribe_on_membership_delete( $user_id, $group_id ) {
	ass_group_subscription( 'delete', $user_id, $group_id );
	bpges_delete_queued_items_for_user_group( $user_id, $group_id );
}
add_action( 'bp_groups_member_before_delete', 'bpges_unsubscribe_on_membership_delete', 10, 2 );

/**
 * Remove a user's subscription level after a 'ban' action.
 *
 * @since 3.8.0
 *
 * @param BP_Groups_Member $membership
 */
function bpges_unsubscribe_on_membership_ban( BP_Groups_Member $membership ) {
	if ( ! $membership->is_banned ) {
		return;
	}

	ass_group_subscription( 'delete', $membership->user_id, $membership->group_id );
	bpges_delete_queued_items_for_user_group( $membership->user_id, $membership->group_id );
}
add_action( 'groups_member_before_save', 'bpges_unsubscribe_on_membership_ban' );

/**
 * Remove queued items for deleted user.
 *
 * @param int $user_id ID of the deleted user.
 *
 * @since 4.0.0
 */
function bpges_delete_queued_items_for_deleted_user( $user_id ) {
	$query = new BPGES_Queued_Item_Query(
		array(
			'user_id' => $user_id,
		)
	);

	$queued_items_to_delete = array_map(
		function( $item ) {
			return $item->id;
		},
		$query->get_results()
	);

	if ( ! empty( $queued_items_to_delete ) ) {
		BPGES_Queued_Item::bulk_delete( $queued_items_to_delete );
	}
}
add_action( 'delete_user', 'bpges_delete_queued_items_for_deleted_user' );

/**
 * Deletes all queued items for a user + group combo.
 *
 * @since 3.9.3
 *
 * @param int $user_id  ID of the user.
 * @param int $group_id ID of the group.
 */
function bpges_delete_queued_items_for_user_group( $user_id, $group_id ) {
	// Sanity check.
	if ( ! $user_id || ! $group_id ) {
		return;
	}

	$query = new BPGES_Queued_Item_Query( array(
		'user_id'  => $user_id,
		'group_id' => $group_id,
	) );

	$queued_ids = array_keys( $query->get_results() );
	if ( empty( $queued_ids ) ) {
		return;
	}
	BPGES_Queued_Item::bulk_delete( $queued_ids );
}

//
//	!Default Group Subscription
//

// when a user joins a group, set their default subscription level
function ass_set_default_subscription( $groups_member ){
	global $bp;

	$user_id  = (int) $groups_member->user_id;
	$group_id = (int) $groups_member->group_id;

	// only set the default if the user has no subscription history for this group
	if ( ass_get_group_subscription_status( $user_id, $group_id ) )
		return;

	//if the person has requested access to a private group but has not been approved, don't subscribe them
	if ( !$groups_member->is_confirmed )
		return;

	// If the member is banned, don't add.
	if ( $groups_member->is_banned ) {
		return;
	}

	$default_gsub = ass_get_default_subscription( $group_id );

	if ( $default_gsub ) {
		ass_group_subscription( $default_gsub, $user_id, $group_id );
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

/**
 * Gets the global default subscription level for the site.
 *
 * @since 4.1.0
 *
 * @return string
 */
function bpges_get_global_default_subscription() {
	$default = bp_get_option( 'bpges_global_default_subscription', 'supersub' );

	// Verify that the default exists.
	$all_levels = bpges_subscription_levels();
	if ( ! isset( $all_levels[ $default ] ) ) {
		$default = array_keys( $all_levels )[0];
	}

	/**
	 * Filters the global default subscription level for the site.
	 *
	 * @since 4.1.0
	 *
	 * @param string $default Default subscription level.
	 */
	return apply_filters( 'bpges_global_default_subscription', $default );
}

/**
 * Gets the default subscription settings for the group.
 *
 * @param BP_Groups_Group|int $group Group object or group ID. Defaults to the current group.
 * @return string
 */
function ass_get_default_subscription( $group = false ) {
	global $bp, $groups_template;
	if ( ! $group && isset( $groups_template->group ) ) {
		$group =& $groups_template->group;
	}

	if ( is_int( $group ) ) {
		$group_id = $group;
	} elseif ( isset( $group->id ) ) {
		$group_id = $group->id;
	} elseif ( bp_is_group_create() ) {
		$group_id = bp_get_new_group_id();
	}

	$default_subscription = groups_get_groupmeta( $group_id, 'ass_default_subscription' );

	if ( empty( $default_subscription ) ) {
		/**
		 * Filters the fallback value for a group's default subscription level.
		 *
		 * @param string $status   'supersub' by default.
		 * @param int    $group_id ID of the group.
		 */
		$default_subscription = apply_filters( 'ass_default_subscription_level', bpges_get_global_default_subscription(), $group_id );
	}

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
 * @param string               $content  The email content
 * @param BP_Activity_Activity $activity The activity object.
 * @return string $clean_content The email content, cleaned up for plaintext email
 */
function ass_clean_content( $content, $activity = null ) {
	/**
	 * Filter for "cleaning" BPGES email content.
	 *
	 * @param string               $content  Email content.
	 * @param BP_Activity_Activity $activity Activity object.
	 */
	return apply_filters( 'ass_clean_content', $content, $activity );
}

// By default, we run content through these filters, which can be individually removed
add_filter( 'ass_clean_content', 'stripslashes', 2 );
add_filter( 'ass_clean_content', 'strip_tags', 4 );
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
	if ( ! $user_id || ! is_numeric( $user_id ) ) {
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

	$group_url_parts = array( 'manage-members', 'email' );

	$sub_type = ass_get_group_subscription_status( $user_id, $group->id );
	echo '<span class="ass_manage_members_links"> '.__('Email status:','buddypress-group-email-subscription').' ' . ass_subscribe_translate( $sub_type ) . '.';
	echo ' &nbsp; '.__('Change to:','buddypress-group-email-subscription').' ';

	$subscription_levels = bpges_subscription_levels();

	$level_count = count( $subscription_levels );
	$level_i     = 0;
	foreach ( $subscription_levels as $level_slug => $level_data ) {
		$link_url_parts = array_merge(
			$group_url_parts,
			array(
				$level_slug,
				$user_id
			)
		);

		$url = bp_get_group_manage_url( $group->id, bp_groups_get_path_chunks( $link_url_parts, 'manage' ) );
		echo '<a href="' . esc_url( wp_nonce_url( $url, 'ass_member_email_status' ) ) . '">' . esc_html( $level_data['label_short'] ) . '</a>';

		++$level_i;
		if ( $level_i < $level_count ) {
			echo ' | ';
		}
	}

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
		$status = apply_filters( 'ass_default_subscription_level', bpges_get_global_default_subscription(), $group_id );
	}

	return apply_filters( 'ass_group_default_status', $status, $group_id );
}

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
		bp_core_add_message( __( 'You have been unsubscribed from all groups notifications.', 'buddypress-group-email-subscription' ), 'success' );
	else
		bp_core_add_message( __( "This user's has been unsubscribed from all groups notifications.", 'buddypress-group-email-subscription' ), 'success' );

	$settings_link = bp_members_get_user_url(
		$user_id,
		bp_members_get_path_chunks( array( bp_get_settings_slug(), 'notifications' ) )
	);

	bp_core_redirect( $settings_link );
}
add_action( 'bp_actions', 'ass_user_unsubscribe_action' );

// Form to confirm unsubscription from all groups
function ass_user_unsubscribe_form() {
	$action = isset( $_GET['bpass-action'] ) ? $_GET['bpass-action'] : '';

	if ( 'unsubscribe' !== $action ) {
		return;
	}

	if ( empty( $_GET['group'] ) && get_option( 'ass-global-unsubscribe-link' ) != 'yes' ) {
		return;
	}

	$user_id       = bp_displayed_user_id();
	$access_key    = $_GET['access_key'];
	$message       = '';

	/* translators: Continue to "SITE NAME or GROUP NAME" */
	$link_label = esc_html__( 'Continue to %s', 'buddypress-group-email-subscription' );
	$link_href  = bp_get_root_domain();
	$link_text  = get_option( 'blogname' );

	// Unsubscribe time.
	if ( isset( $_POST['submit'] ) ) {
		$group = groups_get_group( array( 'group_id' => $_POST['group_id'] ) );

		// Single group.
		if ( ! empty( $_POST['group_id'] ) && ! empty( $group->id ) ) {
			check_admin_referer( 'bp_ges_unsubscribe_group_' . $group->id );

			$link_href = bp_get_group_url( $group );
			$link_text = bp_get_group_name( $group );

			if ( $access_key != md5( "{$group->id}{$user_id}unsubscribe" . wp_salt() ) ) {
				$message = esc_html__( 'There was a problem unsubscribing you from the group.', 'buddypress-group-email-subscription' );

			} else {
				ass_unsubscribe_user( $user_id, (array) $group->id );

				$message = sprintf( __( 'Your unsubscription was successful. You will no longer receive email notifications from the group %s.', 'buddypress-group-email-subscription' ),
					sprintf( '<a href="%1$s">%2$s</a>', esc_url( $link_href ), $link_text )
				);
			}

		// All groups.
		} elseif ( 0 === $_POST['group_id'] ) {
			check_admin_referer( 'bp_ges_unsubscribe_group_all' );

			if ( $access_key != md5( $user_id . 'unsubscribe' . wp_salt() ) ) {
				$message = esc_html__( 'There was a problem unsubscribing you from all of your groups.', 'buddypress-group-email-subscription' );

			} else {
				ass_unsubscribe_user( $user_id );

				$message = __( 'Your unsubscription was successful. You will no longer receive email notifications from any of your groups.', 'buddypress-group-email-subscription' );
			}
		}
	}

	$continue_link = sprintf(
		'<a href="%1$s">%2$s</a>',
		esc_url( $link_href ),
		sprintf( $link_label, $link_text )
	);

?>
<html>
<head>
	<meta http-equiv="Content-Type" content="text/html; charset=utf-8" />
	<meta name = "viewport" content="width=640" />
	<title><?php echo bloginfo( 'name' ); ?> - <?php _e( 'Unsubscribe from group notifications', 'buddypress-group-email-subscription' ); ?></title>
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
		<h1><?php echo bloginfo( 'name' ); ?> - <?php _e( 'Unsubscribe', 'buddypress-group-email-subscription' ); ?></h1>
		<?php if ( ! empty( $message ) ) : ?>

			<p><?php echo $message ?></p>
			<p><?php echo $continue_link ?></p>

		<?php elseif ( isset( $_GET['group'] ) ) : $group = groups_get_group( array( 'group_id' => $_GET['group'] ) ); ?>

			<p><?php printf( __( 'Do you really want to unsubscribe from all notifications for the group, %s?', 'buddypress-group-email-subscription' ), '<a href="' . bp_get_group_url( $group ) . '">' . bp_get_group_name( $group ) . '</a>' ); ?></p>

			<form id="ass-unsubscribe-form" action="" method="POST">
				<input type="hidden" name="group_id" value="<?php echo (int) $_GET['group']; ?>" />
				<input type="submit" name="submit" value="<?php _e( 'Yes, unsubscribe from this group', 'buddypress-group-email-subscription' ); ?>" />
				<a href="<?php echo esc_attr( site_url() ); ?>"><?php _e( 'No, close', 'buddypress-group-email-subscription' ); ?></a>
				<?php wp_nonce_field( 'bp_ges_unsubscribe_group_' . (int) $_GET['group'] ); ?>
			</form>

		<?php else : ?>

			<p><?php _e( 'Do you really want to unsubscribe from all groups notifications?', 'buddypress-group-email-subscription' ); ?></p>

			<form id="ass-unsubscribe-form" action="" method="POST">
				<input type="hidden" name="group_id" value="0" />
				<input type="submit" name="submit" value="<?php _e( 'Yes, unsubscribe from all my groups', 'buddypress-group-email-subscription' ); ?>" />
				<a href="<?php echo esc_attr( site_url() ); ?>"><?php _e( 'No, close' ); ?></a>
				<?php wp_nonce_field( 'bp_ges_unsubscribe_group_all' ); ?>
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
		$global_link = bp_members_get_url( $user_id ) . '?bpass-action=unsubscribe&access_key=' . md5( "{$user_id}unsubscribe" . wp_salt() );
		$message .= "\n\n---------------------\n";
		$message .= sprintf( __( 'To disable emails from all your groups at once click: %s', 'buddypress-group-email-subscription' ), $global_link );
	}

	$group_admin_ids = groups_get_group_admins( $group_id );
	$group_admin = bp_core_get_core_userdata( $group_admin_ids[0]->user_id );
	$headers = array(
		"From: \"{$group_admin->display_name}\" <{$group_admin->user_email}>"
	);

	$group      = groups_get_group( array( 'group_id' => $group_id ) );
	$group_name = bp_get_group_name( $group );
	$group_link = bp_get_group_url( $group );

	$group_settings_link = bp_get_group_manage_url(
		$group,
		bp_groups_get_path_chunks( array( 'notifications' ) )
	);

	// Sending time!
	ass_send_email( 'bp-ges-welcome', $user->user_email, array(
		'tokens'  => array(
			'ges.subject'  => stripslashes( strip_tags( $welcome_email['subject'] ) ),
			'usermessage'  => stripslashes( $welcome_email['content'] ),
			'group.link'   => sprintf( '<a href="%1$s">%2$s</a>', esc_url( $group_link ), $group_name ),
			'group.name'   => $group_name,
			'group.url'    => esc_url( $group_link ),
			'group.id'     => $group_id,
			'recipient.id' => $user->ID,
			'subscription_type' => 'sub',
			'ges.settings-link' => ass_get_login_redirect_url( $group_settings_link, 'welcome' ),
			'ges.unsubscribe'   => ass_get_group_unsubscribe_link_for_user( $user->ID, $group_id ),
			'ges.unsubscribe-global' => ass_get_group_unsubscribe_link_for_user( $user->ID, $group_id, true ),
		),
		'subject' => $subject,
		'content' => $message,
		'from' => array(
			'name'   => $group_name,
			'email'  => $group_admin->user_email
		)
	) );
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

	// check for BP's bundled legacy forums.
	} else {
		if ( ! bp_is_active( 'forums' ) ) {
			return false;
		}

		$type = 'buddypress';
	}

	return $type;
}

/**
 * Add attachment links by the GD bbPress Attachments plugin to group emails.
 *
 * @since 3.7.3
 *
 * @param  string               $content  Current email content.
 * @param  BP_Activity_Activity $activity Current activity item.
 * @return string
 */
function ass_add_gd_bbpress_attachments_to_email_content( $content, $activity ) {
	// No GD bbPress Attachments or not a bbPress item? Stop now!
	if ( ! function_exists( 'd4p_get_post_attachments' ) || ! in_array( $activity->type, array( 'bbp_reply_create', 'bbp_topic_create' ) ) ) {
		return $content;
	}

	$atts = d4p_get_post_attachments( $activity->secondary_item_id );
	if ( empty( $atts ) ) {
		return $content;
	}

	$attachment_message = "\n\n" . __( 'This post has attachments:', 'buddypress-group-email-subscription' );

	foreach ( $atts as $attachment ) {
		$file_url = wp_get_attachment_url( $attachment->ID );
		$file_name = basename( get_attached_file( $attachment->ID ) );
		$attachment_message .= sprintf( "\n<a href='%s'>%s</a>", $file_url, $file_name );
	}

	return $content . $attachment_message;
}
add_filter( 'bp_ass_activity_notification_content', 'ass_add_gd_bbpress_attachments_to_email_content', 100, 2 );

/**
 * Determines whether admin notices should be forced to 'immediate', overriding user preferences.
 *
 * If false, user notification preferences for the group will be respected.
 *
 * @since 4.0.0
 *
 * @param int                  $user_id  Optional. ID of the user.
 * @param BP_Activity_Activity $activity Optional .Activity item.
 * @param int                  $group_id Optional. ID of the group.
 * @return bool
 */
function bpges_force_immediate_admin_notice( $user_id = null, $activity = null, $group_id = null ) {
	$force = false;

	/**
	 * Filters whether admin notices should be forced to 'immediate', overriding user preferences.
	 *
	 * Please consider the implications of overriding user notification preferences before changing this toggle.
	 *
	 * @since 4.0.0
	 *
	 * @param bool                 $force    Whether to force 'immediate' email for the admin notice.
	 * @param int                  $user_id  ID of the user.
	 * @param BP_Activity_Activity $activity Activity item.
	 * @param int                  $group_id ID of the group.
	 */
	return apply_filters( 'bpges_force_immediate_admin_notice', $force, $user_id, $activity, $group_id );
}

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

/**
 * Register our theme template directory with BuddyPress.
 *
 * @since 3.8.0
 */
function bpges_register_template_stack() {
	bp_register_template_stack( function() {
		return plugin_dir_path( __FILE__ ) . '/templates/';
	}, 20 );
}
add_action( 'bp_actions', 'bpges_register_template_stack' );

/*
 * Logs BPGES actions to a debug log.
 *
 * @since 3.9.0
 */
function bpges_log( $message ) {
	if ( ! defined( 'BPGES_DEBUG' ) || ! BPGES_DEBUG ) {
		return;
	}

	if ( empty( $message ) ) {
		return;
	}

	error_log( '[' . gmdate( 'd-M-Y H:i:s' ) . '] ' . $message . "\n", 3, BPGES_DEBUG_LOG_PATH );
}

/**
 * Gets the send queue process.
 *
 * @since 3.9.0
 *
 * @return BPGES_Async_Request_Send_Queue
 */
function bpges_send_queue() {
	static $queue;

	if ( null === $queue ) {
		$queue = new BPGES_Async_Request_Send_Queue();
	}

	return $queue;
}
