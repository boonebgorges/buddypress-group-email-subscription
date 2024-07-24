<?php
/**
 * Functions related to the older legacy forums component.
 *
 * Most installs will never need to load this code. Ever.
 *
 * @since 3.7.0
 */

/**
 * When a new forum topic or post is posted in bbPress, either:
 *  1) Send emails to all group subscribers
 *  2) Prepares to record it for digest purposes - see {@link ass_group_forum_record_digest()}.
 *
 * Hooks into the bbPress action - 'bb_new_post' - to easily identify new forum posts vs edits.
 */
function ass_group_notification_forum_posts( $post_id ) {
	global $bp, $wpdb;

	$post = bb_get_post( $post_id );

	// Check to see if user has been registered long enough
	if ( ! ass_registered_long_enough( $post->poster_id ) ) {
		return;
	}

	$topic = get_topic( $post->topic_id );

	$group = groups_get_current_group();

	// if the current group isn't available, grab it
	if ( empty( $group ) ) {
		// get the group ID by looking up the forum ID in the groupmeta table
		// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$group_id = $wpdb->get_var(
			$wpdb->prepare(
				"
				SELECT group_id
				FROM {$bp->groups->table_name_groupmeta}
				WHERE meta_key = %s
				AND meta_value = %d
			",
				'forum_id',
				$topic->forum_id
			)
		);
		// phpcs:enable WordPress.DB.PreparedSQL.InterpolatedNotPrepared

		// now get the group
		$group = groups_get_group(
			array(
				'group_id' => $group_id,
			)
		);
	}

	$primary_link = bp_get_group_url(
		$group,
		bp_groups_get_path_chunks( array( 'forum', 'topic', $topic->topic_slug ) )
	);

	$blogname = '[' . get_blog_option( BP_ROOT_BLOG, 'blogname' ) . ']';

	$is_topic = false;

	// initialize faux activity object for backpat filter reasons
	//
	// due to r-a-y being an idiot here:
	// https://github.com/boonebgorges/buddypress-group-email-subscription/commit/526b80c617fe9058a859ac4eb4cfb1d42d333aa0
	//
	// because we moved the email recording process to 'bb_new_post' from the BP activity save hook,
	// we need to ensure that 3rd-party code will continue to work as-is
	//
	// we can't add the 'id' because we're firing the filters before the activity item is created :(
	$activity            = new stdClass();
	$activity->user_id   = $post->poster_id;
	$activity->component = 'groups';
	$activity->item_id   = $group->id;
	$activity->content   = $post->post_text;

	// this is a new topic
	if ( 1 === (int) $post->post_position ) {
		$is_topic = true;

		// more faux activity items!
		$activity->type              = 'new_forum_topic';
		$activity->secondary_item_id = $topic->topic_id;
		$activity->primary_link      = $primary_link;

		// translators: 1. User name, 2. Topic title, 3. Group name..
		$action = sprintf( __( '%1$s started the forum topic "%2$s" in the group "%3$s"', 'buddypress-group-email-subscription' ), bp_core_get_user_displayname( $post->poster_id ), $topic->topic_title, $group->name );

		$activity->action = $action;

		$subject     = apply_filters( 'bp_ass_new_topic_subject', $action . ' ' . $blogname, $action, $blogname );
		$the_content = apply_filters( 'bp_ass_new_topic_content', $post->post_text, $activity, $topic, $group );
	} else {
		// this is a forum reply
		// more faux activity items!
		$activity->type              = 'new_forum_post';
		$activity->secondary_item_id = $post_id;

		// translators: 1. User name, 2. Topic title, 3. Group name.
		$action = sprintf( __( '%1$s replied to the forum topic "%2$s" in the group "%3$s"', 'buddypress-group-email-subscription' ), bp_core_get_user_displayname( $post->poster_id ), $topic->topic_title, $group->name );

		$activity->action = $action;

		// calculate the topic page for pagination purposes
		$pag_num = apply_filters( 'bp_ass_topic_pag_num', 15 );
		$page    = ceil( $topic->topic_posts / $pag_num );

		if ( $page > 1 ) {
			$primary_link .= '?topic_page=' . $page;
		}

		$primary_link .= '#post-' . $post_id;

		$activity->primary_link = $primary_link;

		$subject     = apply_filters( 'bp_ass_forum_reply_subject', $action . ' ' . $blogname, $action, $blogname );
		$the_content = apply_filters( 'bp_ass_forum_reply_content', $post->post_text, $activity, $topic, $group );
	}

	// Convert entities and do other cleanup
	$the_content = ass_clean_content( $the_content );

	// if group is not public, change primary link to login URL to verify
	// authentication and for easier redirection after logging in
	if ( 'public' !== $group->status ) {
		$primary_link = ass_get_login_redirect_url( $primary_link, 'legacy_forums_view' );

		$text_before_primary = __( 'To view or reply to this topic, go to:', 'buddypress-group-email-subscription' );

	} else {
		// if public, show standard text
		$text_before_primary = __( 'To view or reply to this topic, log in and go to:', 'buddypress-group-email-subscription' );
	}

	// setup the email meessage
	$message = sprintf(
		// translators: 1. Action text; 2. Activity content, 3. Prefix text for link, 4. URL.
		__(
			'%1$s

"%2$s"

%3$s
%4$s

---------------------
',
			'buddypress-group-email-subscription'
		),
		$action . ':',
		$the_content,
		$text_before_primary,
		$primary_link
	);

	// get subscribed users
	$subscribed_users = groups_get_groupmeta( $group->id, 'ass_subscribed_users' );

	// do this for forum replies only
	if ( ! $is_topic ) {
		// pre-load these arrays to reduce db calls in the loop
		$ass_replies_to_my_topic    = ass_user_settings_array( 'ass_replies_to_my_topic' );
		$ass_replies_after_me_topic = ass_user_settings_array( 'ass_replies_after_me_topic' );
		$previous_posters           = ass_get_previous_posters( $post->topic_id );

		// make sure manually-subscribed topic users and regular group subscribed users are combined
		$user_topic_status = groups_get_groupmeta( $group->id, 'ass_user_topic_status_' . $topic->topic_id );

		if ( ! empty( $subscribed_users ) && ! empty( $user_topic_status ) ) {
			$subscribed_users = $subscribed_users + $user_topic_status;
		}

		// consolidate the arrays to speed up processing
		foreach ( array_keys( $previous_posters ) as $previous_poster ) {
			if ( empty( $subscribed_users[ $previous_poster ] ) ) {
				$subscribed_users[ $previous_poster ] = 'prev-post';
			}
		}
	}

	// setup our temporary GES object
	$bp->ges        = new stdClass();
	$bp->ges->items = array();

	// digest key iterator
	$d = 0;

	// now let's either send the email or record it for digest purposes
	foreach ( (array) $subscribed_users as $user_id => $group_status ) {
		$self_notify = '';

		// Does the author want updates of their own forum posts?
		if ( (int) $user_id === (int) $post->poster_id ) {
			$self_notify = ass_self_post_notification( $user_id );

			// Author does not want notifications of their own posts
			if ( ! $self_notify ) {
				continue;
			}
		}

		$send_it = false;
		$notice  = false;

		// default settings link
		$settings_link = bp_get_group_url(
			$group,
			bp_groups_get_path_chunks( array( 'notifications' ) )
		);

		$settings_link = ass_get_login_redirect_url( $settings_link, 'legacy_forums_settings' );

		// Self-notification emails
		if ( true === $self_notify ) {
			$send_it      = true;
			$group_status = 'self_notify';

			// notification settings link
			$settings_link = bp_members_get_user_url(
				$user_id,
				bp_members_get_path_chunks( array( bp_get_settings_slug(), 'notifications' ) )
			);

			// set notice
			$notice = __( 'You are currently receiving notifications for your own posts.', 'buddypress-group-email-subscription' );

			// translators: settings link.
			$notice .= "\n\n" . sprintf( __( 'To disable these notifications please log in and go to: %s', 'buddypress-group-email-subscription' ), $settings_link );
			$notice .= "\n" . __( 'Once you are logged in, uncheck "Receive notifications of your own posts?".', 'buddypress-group-email-subscription' );

		} elseif ( $is_topic ) {
			// do the following for new topics
			if ( 'sub' === $group_status || 'supersub' === $group_status ) {
				$send_it = true;

				$notice .= "\n" . __( 'Your email setting for this group is: ', 'buddypress-group-email-subscription' ) . ass_subscribe_translate( $group_status );

				// until we get a real follow link, this will have to do
				if ( 'sub' === $group_status ) {
					$notice .= __( ", therefore you won't receive replies to this topic. To get them, click the link to view this topic on the web then click the 'Follow this topic' button.", 'buddypress-group-email-subscription' );
				} elseif ( 'supersub' === $group_status ) {
					// user's group setting is "All Mail"
					// translators: settings link.
					$notice .= "\n" . sprintf( __( 'To change your email setting for this group, please log in and go to: %s', 'buddypress-group-email-subscription' ), $settings_link );
				}

				$notice .= "\n\n" . ass_group_unsubscribe_links( $user_id );
			}
		} else {
			// do the following for forum replies
			$topic_status = isset( $user_topic_status[ $user_id ] ) ? $user_topic_status[ $user_id ] : '';

			// the topic mute button will override the subscription options below
			if ( 'mute' === $topic_status ) {
				continue;
			}

			// skip if user set to weekly summary and they're not following this topic
			// maybe not neccesary, but good to be cautious
			if ( 'sum' === $group_status && 'sub' !== $topic_status ) {
				continue;
			}

			// User's group setting is "All Mail", so we should send this
			if ( 'supersub' === $group_status ) {
				$send_it = true;

				$notice = __( 'Your email setting for this group is: ', 'buddypress-group-email-subscription' ) . ass_subscribe_translate( $group_status );

				// translators: settings link.
				$notice .= "\n" . sprintf( __( 'To change your email setting for this group, please log in and go to: %s', 'buddypress-group-email-subscription' ), $settings_link );
				$notice .= "\n\n" . ass_group_unsubscribe_links( $user_id );
			} elseif ( 'sub' === $topic_status ) {
				// User is manually subscribed to this topic
				$send_it      = true;
				$group_status = 'manual_topic';

				// change settings link to the forum thread
				// get rid of any query args and anchors from the thread permalink
				$settings_link = trailingslashit( strtok( $primary_link, '?' ) );

				// let's change the notice to accurately reflect that the user is following this topic
				// translators: Settings link.
				$notice  = sprintf( __( 'To disable these notifications please log in and go to: %s', 'buddypress-group-email-subscription' ), $settings_link );
				$notice .= "\n" . __( 'Once you are logged in, click on the "Mute this topic" button to unsubscribe from the forum thread.', 'buddypress-group-email-subscription' );
			} elseif ( (int) $topic->topic_poster === (int) $user_id && isset( $ass_replies_to_my_topic[ $user_id ] ) && 'no' !== $ass_replies_to_my_topic[ $user_id ] ) {
				// User started the topic and wants to receive email replies to his/her topic
				$send_it      = true;
				$group_status = 'replies_to_my_topic';

				// override settings link to user's notifications
				$settings_link = bp_members_get_user_url(
					$user_id,
					bp_members_get_path_chunks( array( bp_get_settings_slug(), 'notifications' ) )
				);

				// let's change the notice to accurately reflect that the user is receiving replies based on their settings
				$notice = __( 'You are currently receiving notifications to topics that you have started.', 'buddypress-group-email-subscription' );

				// translators: %s is the settings link
				$notice .= "\n\n" . sprintf( __( 'To disable these notifications please log in and go to: %s', 'buddypress-group-email-subscription' ), $settings_link );
				$notice .= "\n" . __( 'Once you are logged in, uncheck "A member replies in a forum topic you\'ve started".', 'buddypress-group-email-subscription' );
			} elseif ( isset( $previous_posters[ $user_id ] ) && isset( $ass_replies_after_me_topic[ $user_id ] ) && 'no' !== $ass_replies_after_me_topic[ $user_id ] ) {
				// User posted in this topic and wants to receive all subsequent replies
				$send_it      = true;
				$group_status = 'replies_after_me_topic';

				// override settings link to user's notifications
				$settings_link = bp_members_get_user_url(
					$user_id,
					bp_members_get_path_chunks( array( bp_get_settings_slug(), 'notifications' ) )
				);

				// let's change the notice to accurately reflect that the user is receiving replies based on their settings
				$notice = __( 'You are currently receiving notifications to topics that you have replied in.', 'buddypress-group-email-subscription' );

				// translators: %s is the settings link
				$notice .= "\n\n" . sprintf( __( 'To disable these notifications please log in and go to: %s', 'buddypress-group-email-subscription' ), $settings_link );
				$notice .= "\n" . __( 'Once you are logged in, uncheck "A member replies after you in a forum topic".', 'buddypress-group-email-subscription' );
			}
		}

		// if we're good to send, send the email!
		if ( $send_it ) {
			// One last chance to filter the message content
			$user_message = apply_filters(
				'bp_ass_forum_notification_message',
				$message . $notice,
				array(
					'message'           => $message,
					'notice'            => $notice,
					'user_id'           => $user_id,
					'subscription_type' => $group_status,
					'content'           => $the_content,
					'view_link'         => $primary_link,
					'settings_link'     => $settings_link,
				)
			);

			// Get the details for the user
			$user = bp_core_get_core_userdata( $user_id );

			// Send the email
			if ( $user->user_email ) {
				wp_mail( $user->user_email, $subject, $user_message );
			}
		}

		// otherwise if digest or summary, record it!
		// temporarily save some variables to pass to groups_record_activity()
		// actual digest recording occurs in ass_group_forum_record_digest()
		if ( 'dig' === $group_status || ( $is_topic && 'sum' === $group_status ) ) {
			$bp->ges->items[ $d ]               = new stdClass();
			$bp->ges->items[ $d ]->user_id      = $user_id;
			$bp->ges->items[ $d ]->group_id     = $group->id;
			$bp->ges->items[ $d ]->group_status = $group_status;

			// iterate our key value
			++$d;
		}

		unset( $notice );
	}
}
add_action( 'bb_new_post', 'ass_group_notification_forum_posts' );

/**
 * Records group forum digest items in GES after the activity item is posted.
 *
 * {@link ass_group_notification_forum_posts()} handles non-digest sendouts, but
 * for digest items, we have to wait for the corresponding activity item to be posted
 * before we can record it.
 */
function ass_group_forum_record_digest( $activity ) {
	global $bp;

	// see if our temporary GES variable is set via ass_group_notification_forum_posts()
	if ( ! empty( $bp->ges->items ) ) {

		// okay, we're good to go! let's record this digest item!
		foreach ( $bp->ges->items as $item ) {
			ass_digest_record_activity( $activity->id, $item->user_id, $item->group_id, $item->group_status );

		}

		// unset the temporary variable
		unset( $bp->ges );
	}
}
add_action( 'bp_activity_after_save', 'ass_group_forum_record_digest' );

/**
 * Get topic subscription status for legacy forums.
 */
function ass_get_topic_subscription_status( $user_id, $topic_id ) {
	global $bp;

	if ( ! $user_id || ! $topic_id ) {
		return false;
	}

	$user_topic_status = groups_get_groupmeta( bp_get_current_group_id(), 'ass_user_topic_status_' . $topic_id );

	if ( is_array( $user_topic_status ) && isset( $user_topic_status[ $user_id ] ) ) {
		return ( $user_topic_status[ $user_id ] );
	} else {
		return false;
	}
}

/**
 * Creates "subscribe/unsubscribe" link on forum directory page and each topic page.
 */
function ass_topic_follow_or_mute_link() {
	global $bp;

	if ( empty( $bp->groups->current_group->is_member ) ) {
		return;
	}

	$topic_id     = bp_get_the_topic_id();
	$topic_status = ass_get_topic_subscription_status( bp_loggedin_user_id(), $topic_id );
	$group_status = ass_get_group_subscription_status( bp_loggedin_user_id(), bp_get_current_group_id() );

	if ( 'mute' === $topic_status || ( 'supersub' !== $group_status && ! $topic_status ) ) {
		$action    = 'follow';
		$link_text = __( 'Follow', 'buddypress-group-email-subscription' );
		$title     = __( 'You are not following this topic. Click to follow it and get email updates for new posts', 'buddypress-group-email-subscription' );
	} elseif ( 'sub' === $topic_status || ( 'supersub' === $group_status && ! $topic_status ) ) {
		$action    = 'mute';
		$link_text = __( 'Mute', 'buddypress-group-email-subscription' );
		$title     = __( 'You are following this topic. Click to stop getting email updates', 'buddypress-group-email-subscription' );
	} else {
		echo 'nothing'; // do nothing
	}

	if ( 'mute' === $topic_status ) {
		$title = __( 'This conversation is muted. Click to follow it', 'buddypress-group-email-subscription' );
	}

	if ( $action && bp_is_action_variable( 'topic', 0 ) ) { // we're viewing one topic
		echo '<div class="generic-button ass-topic-subscribe"><a title="' . esc_attr( $title ) . '" id="' . esc_attr( $action ) . '-' . esc_attr( $topic_id ) . '-' . esc_attr( bp_get_current_group_id() ) . '">' . esc_html( $link_text ) . ' ' . esc_html__( 'this topic', 'buddypress-group-email-subscription' ) . '</a></div>';
	} elseif ( $action ) { // we're viewing a list of topics
		echo '<td class="td-email-sub"><div class="generic-button ass-topic-subscribe"><a title="' . esc_attr( $title ) . '" id="' . esc_attr( $action ) . '-' . esc_attr( $topic_id ) . '-' . esc_attr( bp_get_current_group_id() ) . '">' . esc_html( $link_text ) . '</a></div></td>';
	}
}
add_action( 'bp_directory_forums_extra_cell', 'ass_topic_follow_or_mute_link', 50 );
add_action( 'bp_before_group_forum_topic_posts', 'ass_topic_follow_or_mute_link' );
add_action( 'bp_after_group_forum_topic_posts', 'ass_topic_follow_or_mute_link' );

/**
 * Add a title to the mute/follow above (in the th tag).
 */
function ass_after_topic_title_head() {
	global $bp;

	if ( empty( $bp->groups->current_group->is_member ) ) {
		return;
	}

	echo '<th id="th-email-sub">' . esc_html__( 'Email', 'buddypress-group-email-subscription' ) . '</th>';
}
add_filter( 'bp_directory_forums_extra_cell_head', 'ass_after_topic_title_head', 3 );

/**
 * Handles AJAX request to follow/mute a topic.
 */
function ass_ajax_callback() {
	global $bp;

	check_ajax_referer( 'ass_subscribe' );

	$action   = sanitize_text_field( wp_unslash( $_POST['a'] ) );  // action is used by ajax, so we use a here
	$user_id  = bp_loggedin_user_id();
	$topic_id = (int) $_POST['topic_id'];
	$group_id = (int) $_POST['group_id'];

	ass_topic_subscribe_or_mute( $action, $user_id, $topic_id, $group_id );

	echo esc_html( $action );
	die();
}
add_action( 'wp_ajax_ass_ajax', 'ass_ajax_callback' );

/**
 * Adds/removes a $topic_id from the $user_id's mute list.
 */
function ass_topic_subscribe_or_mute( $action, $user_id, $topic_id, $group_id ) {
	global $bp;

	if ( ! $action || ! $user_id || ! $topic_id || ! $group_id ) {
		return false;
	}

	$user_topic_status = groups_get_groupmeta( $group_id, 'ass_user_topic_status_' . $topic_id );

	if ( 'unsubscribe' === $action || 'mute' === $action ) {
		$user_topic_status[ $user_id ] = 'mute';
	} elseif ( 'subscribe' === $action || 'follow' === $action ) {
		$user_topic_status[ $user_id ] = 'sub';
	}

	groups_update_groupmeta( $group_id, 'ass_user_topic_status_' . $topic_id, $user_topic_status );

	// add a hook for 3rd-party plugin devs
	do_action( 'ass_topic_subscribe_or_mute', $user_id, $group_id, $topic_id, $action );
}

/**
 * Return array of previous posters' ids.
 */
function ass_get_previous_posters( $topic_id ) {
	do_action( 'bbpress_init' );
	global $bbdb, $wpdb;

	$posters = $bbdb->get_results( "SELECT poster_id FROM $bbdb->posts WHERE topic_id = {$topic_id}" );

	foreach ( $posters as $poster ) {
		$user_ids[ $poster->poster_id ] = true;
	}

	return $user_ids;
}
