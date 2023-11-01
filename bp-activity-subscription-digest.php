<?php

// for testing only
//if you need to add this at the TOP of your wp-config.php file. (Here are the timezones http://us3.php.net/manual/en/timezones.php)
//date_default_timezone_set('Asia/Tokyo');
//date_default_timezone_set('America/New_York');


/* This function was used for debugging the digest scheduling features */
function ass_digest_schedule_print() {
	print "<br />";
	print "<br />";

	$crons = _get_cron_array();
	echo "<div style='background: #fff;'>";
	$sched = wp_next_scheduled( 'ass_digest_event' );
	echo "Scheduled: " . date( 'h:i', $sched );
	$until = ( (int)$sched - time() ) / ( 60 * 60 );
	echo " Until: " . $until . " hours";
	echo "</div>";
}
//add_action( 'wp_head', 'ass_digest_schedule_print' );


/* Digest-specific functions */

/**
 * Trigger the batched sending of digests.
 *
 * @since 3.9.0
 *
 * @param string $type Digest type. 'sum' or 'dig'.
 */
function bpges_trigger_digest( $type ) {
	$timestamp = date( 'Y-m-d H:i:s' );
	bpges_send_queue()->data( array(
		'type'      => $type,
		'timestamp' => $timestamp,
	) )->dispatch();
}

/**
 * Process and generate a digest for a user/type.
 *
 * This function queries for the necessary queued items, and then passes them to
 * bpges_generate_digest() for the actual generation of the digest email. We break
 * the logic out in this way primarily for automated testing.
 *
 * @since 3.9.0
 *
 * @param int    $user_id    ID of the user.
 * @param string $type       Digest type. 'sum' or 'dig'.
 * @param string $timestamp  Timestamp for the current digest run. Used to determine the queued
 *                           items that should be included in the digest.
 * @param bool   $is_preview Whether this is a preview. When preview, email content will be
 *                           echoed and not sent or deleted from the queue.
 */
function bpges_process_digest_for_user( $user_id, $type, $timestamp, $is_preview = false ) {
	$query = new BPGES_Queued_Item_Query( array(
		'user_id'  => $user_id,
		'type'     => $type,
		'before'   => $timestamp,
	) );

	$items = $query->get_results();

	/**
	 * Filters the items to be included in a digest for a user.
	 *
	 * @since 3.9.5
	 *
	 * @param array  $items      Items returned by BPGES_Queued_Item_Query::get_results().
	 * @param int    $user_id    ID of the user.
	 * @param string $type       Digest type. 'sum' or 'dig'.
	 * @param string $timestamp  Timestamp for the current digest run.
	 * @param bool   $is_preview Whether this is a preview.
	 */
	$items = apply_filters( 'bpges_user_digest_items', $items, $user_id, $type, $timestamp, $is_preview );

	// Sort by group.
	$sorted_by_group = array();
	foreach ( $items as $item ) {
		if ( ! isset( $sorted_by_group[ $item->group_id ] ) ) {
			$sorted_by_group[ $item->group_id ] = array();
		}

		$sorted_by_group[ $item->group_id ][] = $item->activity_id;
	}

	// Ensure numerical sort.
	foreach ( $sorted_by_group as $group_id => &$group_activity_ids ) {
		sort( $group_activity_ids );
	}

	$sent_activity_ids = bpges_generate_digest( $user_id, $type, $sorted_by_group, $is_preview );

	if ( ! $is_preview ) {
		// Collate queued-item IDs for bulk deletion.
		$to_delete_ids = array();
		foreach ( $items as $item ) {
			$group_id    = $item->group_id;
			$activity_id = $item->activity_id;

			if ( ! isset( $sent_activity_ids[ $group_id ] ) ) {
				continue;
			}

			if ( ! in_array( $activity_id, $sent_activity_ids[ $group_id ], true ) ) {
				continue;
			}

			$to_delete_ids[] = $item->id;
		}

		if ( $to_delete_ids ) {
			BPGES_Queued_Item::bulk_delete( $to_delete_ids );
		}
	}
}

/**
 * Generate and send a digest email.
 *
 * @param int    $user_id            ID of the user.
 * @param string $type               Digest type. 'sum' or 'dig'.
 * @param array  $group_activity_ids Associative array, where keys are group IDs and subarrays
 *                                   are arrays of activity IDs to be included in that group's
 *                                   section of the digest.
 * @param bool   $is_preview         Whether this is a preview.
 * @return array $sent_activity_ids Associative array of activity items that were included in
 *                                  the digest, formatted as `$group_activity_ids` above.
 */
function bpges_generate_digest( $user_id, $type, $group_activity_ids, $is_preview = false ) {
	$ass_email_css = bpges_digest_css();

	$title = ass_digest_get_title( $type );
	$blogname = get_blog_option( BP_ROOT_BLOG, 'blogname' );
	$subject = apply_filters( 'ass_digest_subject', "$title [$blogname]", $blogname, $title, $type );

	$footer = "\n\n<div class=\"digest-footer\" {$ass_email_css['footer']}>";
	$footer .= sprintf( __( "You have received this message because you are subscribed to receive a digest of activity in some of your groups on %s.", 'buddypress-group-email-subscription' ), $blogname );
	$footer = apply_filters( 'ass_digest_footer', $footer, $type );

	// initialize some strings
	$bp = buddypress();
	$summary = $activity_message = $body = '';

	$userdata = new WP_User( $user_id );
	if ( 0 === $userdata->ID ) {
		return $group_activity_ids;
	}

	$to = $userdata->user_email;

	$user_url = bp_members_get_user_url( $user_id );

	$user_groups_url = bp_members_get_user_url(
		$user_id,
		bp_members_get_path_chunks( array( bp_get_groups_slug() ) )
	);

	// Keep an unfiltered copy of the activity IDs to be compared with sent items.
	$group_activity_ids_unfiltered = $group_activity_ids;

	// filter the list - can be used to sort the groups
	$group_activity_ids = apply_filters( 'ass_digest_group_activity_ids', @$group_activity_ids );

	// Keep an unmodified copy of the activity IDs to be passed to filters.
	$group_activity_ids_pristine = $group_activity_ids;

	$header = "<div class=\"digest-header\" {$ass_email_css['title']}>$title " . __('at', 'buddypress-group-email-subscription')." <a href='" . $bp->root_domain . "'>$blogname</a></div>\n\n";
	$message = apply_filters( 'ass_digest_header', $header, $title, $ass_email_css['title'] );

	$sent_activity_ids = array();

	// loop through each group for this user
	$has_group_activity = false;
	foreach ( $group_activity_ids as $group_id => $activity_ids ) {
		// Prime cache and filter out invalid items.
		$activity_get = bp_activity_get( array(
			'in'          => $activity_ids,
			'show_hidden' => true,
		) );

		$activity_ids = wp_list_pluck( $activity_get['activities'], 'id' );

		// Discard activity items that are invalid.
		$activity_ids_raw = $activity_ids;
		$activity_ids = array();
		foreach ( $activity_ids_raw as $activity_id_raw ) {
			if ( bp_ges_activity_is_valid_for_digest( $activity_id_raw, $type, $userdata->user_id ) ) {
				$activity_ids[] = $activity_id_raw;
			}
		}

		// Activities could have been deleted since being recorded for digest emails.
		if ( empty( $activity_ids ) ) {
			continue;
		}

		$has_group_activity = true;

		$group = groups_get_group( array(
			'group_id' => $group_id,
		) );
		$group_name = $group->name;
		$group_slug = $group->slug;
		$group_permalink = ass_get_login_redirect_url( bp_get_group_url( $group ) );

		// Might be nice here to link to anchor tags in the message.
		if ( 'dig' == $type ) {
			$summary .= apply_filters( 'ass_digest_summary', "<li class=\"digest-group-summary\" {$ass_email_css['summary']}><a href='{$group_permalink}'>$group_name</a> " . sprintf( __( '(%s items)', 'buddypress-group-email-subscription' ), count( $activity_ids ) ) ."</li>\n", $ass_email_css['summary'], $group_slug, $group_name, $activity_ids );
		}

		$activity_message .= ass_digest_format_item_group( $group_id, $activity_ids, $type, $group_name, $group_slug, $user_id );

		$sent_activity_ids[ $group_id ] = $activity_ids;
	}

	// If there's nothing to send, skip this use.
	if ( ! $has_group_activity ) {
		return $group_activity_ids;
	}

	// show group summary for digest, and follow help text for weekly summary
	if ( 'dig' == $type ) {
		$message .= apply_filters( 'ass_digest_summary_full', __( 'Group Summary', 'buddypress-group-email-subscription') . ":\n<ul class=\"digest-group-summaries\" {$ass_email_css['summary_ul']}>" .  $summary . "</ul>", $ass_email_css['summary_ul'], $summary );
	}

	// the meat of the message which we generated above goes here
	$message .= $activity_message;
	$body .= $activity_message;

	// user is subscribed to "New Topics"
	// add follow help text only if bundled forums are enabled
	if ( 'sum' == $type && class_exists( 'BP_Forums_Component' ) ) {
		$message .= apply_filters( 'ass_summary_follow_topic', "<div {$ass_email_css['follow_topic']}>" . __( "How to follow a topic: to get email updates for a specific topic, click the topic title - then on the webpage click the <i>Follow this topic</i> button. (If you don't see the button you need to login first.)", 'buddypress-group-email-subscription' ) . "</div>\n", $ass_email_css['follow_topic'] );
	}

	$message .= $footer;

	$unsubscribe_message = "\n\n" . sprintf( __( "To disable these notifications per group please login and go to: %s where you can change your email settings for each group.", 'buddypress-group-email-subscription' ), '<a href="' . esc_url( $user_groups_url ) . '">' . __( 'My Groups', 'buddypress-group-email-subscription' ) . "</a>" );

	if ( bp_get_option( 'ass-global-unsubscribe-link' ) == 'yes' ) {
		$unsubscribe_link = "$user_url?bpass-action=unsubscribe&access_key=" . md5( $user_id . 'unsubscribe' . wp_salt() );
		$unsubscribe_message .= "\n\n<br><br><a class=\"digest-unsubscribe-link\" href=\"$unsubscribe_link\">" . __( 'Disable these notifications for all my groups at once.', 'buddypress-group-email-subscription' ) . '</a>';
	}

	$message .= apply_filters( 'ass_digest_disable_notifications', $unsubscribe_message, $user_groups_url );

	$message .= "</div>";

	if ( $is_preview ) {
		echo $message;
		return;
	}

	/**
	 * Filter to allow plugins to stop the email from being sent.
	 *
	 * @since 3.8.0
	 *
	 * @param bool   true                Whether or not to send the email.
	 * @param int    $user_id            ID of the user whose digest is currently being processed.
	 * @param array  $group_activity_ids Array of activity items in the digest.
	 * @param string $message            Message body.
	 */
	$send = apply_filters( 'bp_ges_send_digest_to_user', true, $user_id, $group_activity_ids, $message );
	if ( ! $send ) {
		return $group_activity_ids;
	}

	// Sending time!
	if ( true === function_exists( 'bp_send_email' ) && true === ! apply_filters( 'bp_email_use_wp_mail', false ) ) {
		// Custom GES email tokens.
		$user_message_args = array();

		// Digest summary only available for daily digests.
		$user_message_args['ges.digest-summary'] = '';
		if ( 'dig' == $type ) {
			$user_message_args['ges.digest-summary'] = apply_filters( 'ass_digest_summary_full', __( 'Group Summary', 'buddypress-group-email-subscription' ) . ":\n<ul {$ass_email_css['summary_ul']}>" .  $summary . "</ul>", $ass_email_css['summary_ul'], $summary );
		}

		$user_message_args['ges.subject']       = $title;
		$user_message_args['ges.settings-link'] = ass_get_login_redirect_url( $user_groups_url );
		$user_message_args['subscription_type'] = $type;
		$user_message_args['recipient.id']      = $user_id;

		// Unused.
		$user_message_args['poster.url']   = $user_url;

		// BP-specific tokens.
		$user_message_args['usermessage'] = $body;
		$user_message_args['poster.name'] = $userdata->user_login; // Unused

		/**
		 * Filters the arguments passed to `ass_send_email()` when a digest is sent.
		 *
		 * @since 3.7.3
		 *
		 * @param array $user_message_args           Arguments passed to ass_send_email 'tokens' param.
		 * @param int   $user_id                     ID of the user whose digest is currently being processed.
		 * @param array $group_activity_ids_pristine Array of activity items in the digest.
		 */
		$user_message_args = apply_filters( 'bp_ges_user_digest_message_args', $user_message_args, $user_id, $group_activity_ids_pristine );

		// Filters.
		add_filter( 'bp_email_get_salutation', 'ass_digest_filter_salutation' );
		add_filter( 'bp_email_get_property', 'ass_digest_strip_plaintext_separators', 1, 3 );

		// Send the email.
		ass_send_email( 'bp-ges-digest', $to, array(
			'tokens'  => $user_message_args
		) );

		// Remove filter.
		remove_filter( 'bp_email_get_salutation', 'ass_digest_filter_salutation' );
		remove_filter( 'bp_email_get_property', 'ass_digest_strip_plaintext_separators', 1, 3 );

	// Old version.
	} else {
		$message_plaintext = ass_convert_html_to_plaintext( $message );

		// Send out the email.
		ass_send_multipart_email( $to, $subject, $message_plaintext, str_replace( '---', '', $message ) );
	}

	return $sent_activity_ids;
}

/**
 * Deprecated function for firing a digest run.
 *
 * @deprecated 3.9.0 Use bpges_trigger_digest() to trigger digest runs.
 */
function ass_digest_fire( $type ) {}

/**
 * Get the title for the digest email.
 *
 * @since 3.7.0
 *
 * @param  string $type Type of digest. Either 'dig' for daily digest or 'sum' for weekly summary.
 * @return string
 */
function ass_digest_get_title( $type = '' ) {
	if ( $type == 'dig' ) {
		$title = sprintf( __( 'Your daily digest of group activity', 'buddypress-group-email-subscription' ) );
	} else {
		$title = sprintf( __( 'Your weekly summary of group topics', 'buddypress-group-email-subscription' ) );
	}

	return apply_filters( 'ass_digest_title', $title, $type );
}

/**
 * Filter the "Hi, X" salutation in BP 2.5 emails to use the digest title.
 *
 * @since 3.7.0
 *
 * @param  string $retval Current salutation.
 * @return string
 */
function ass_digest_filter_salutation( $retval = '' ) {
	$tokens = buddypress()->ges_tokens;
	if ( empty( $tokens ) ) {
		return $retval;
	}

	return ass_digest_get_title( buddypress()->ges_tokens['subscription_type'] );
}

/**
 * Strip plain-text only content from BP 2.5 digest HTML emails.
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
function ass_digest_strip_plaintext_separators( $content = '', $prop = '', $transform = '' ) {
	if ( $transform !== 'add-content' ) {
		return $content;
	}

	$find = array(
		"---",
		"<br />",
		"\n&ndash;\n",
	);

	$replace = array(
		'',
		'',
		'<br />&ndash;<br />',
	);

	return str_replace( $find, $replace, $content );
}

// these functions are hooked in via the cron
function ass_daily_digest_fire() {
	bpges_trigger_digest( 'dig' );
}
add_action( 'ass_digest_event', 'ass_daily_digest_fire' );

function ass_weekly_digest_fire() {
	bpges_trigger_digest( 'sum' );
}
add_action( 'ass_digest_event_weekly', 'ass_weekly_digest_fire' );

// for testing the digest firing in real-time, add /?sum=1 to the url
function ass_digest_fire_test() {
	if ( isset( $_GET['sum'] ) && is_super_admin() ){
		echo '<h2>' . __( 'DAILY DIGEST:','buddypress-group-email-subscription' ) . '</h2>';
		bpges_generate_digest_preview_for_type( 'dig' );
		echo '<h2 style="margin-top:150px">' . __( 'WEEKLY DIGEST:','buddypress-group-email-subscription' ) . '</h2>';
		bpges_generate_digest_preview_for_type( 'sum' );
		die();
	}
}
add_action( 'bp_actions', 'ass_digest_fire_test' );

/**
 * Generate preview for digest type.
 *
 * @since 3.9.0
 *
 * @param string $type Digest type. 'sum' or 'dig'.
 */
function bpges_generate_digest_preview_for_type( $type ) {
	$timestamp = date( 'Y-m-d H:i:s' );

	if ( isset( $_GET['user_ids'] ) ) {
		$user_ids = wp_parse_id_list( wp_unslash( $_GET['user_ids'] ) );
	} else {
		$count = isset( $_GET['user_count'] ) ? intval( wp_unslash( $_GET['user_count'] ) ) : 25;

		$user_ids = BPGES_Queued_Item_Query::get_users_with_pending_digest( $type, $count, $timestamp );
	}

	foreach ( $user_ids as $user_id ) {
		$user = new WP_User( $user_id );
		echo '<div style="background-color:white; width:75%;padding:20px 10px;">';
		echo '<p>=================== to: <b>' . esc_html( $user->user_email ) . '</b> ===================</p>';
		bpges_process_digest_for_user( $user_id, $type, $timestamp, true );
		//echo '<br>PLAIN TEXT PART:<br><pre>'; echo $message_plaintext ; echo '</pre>';
		echo '</div>';
	}
}




/**
 * Displays the introduction for the group and loops through each item
 *
 * I've chosen to cache on an individual-activity basis, instead of a group-by-group basis. This
 * requires just a touch more overhead (in terms of looping through individual activity_ids), and
 * doesn't really have any added effect at the moment (since an activity item can only be associated
 * with a single group). But it provides the greatest amount of flexibility going forward, both in
 * terms of the possibility that activity items could be associated with more than one group, and
 * the possibility that users within a single group would want more highly-filtered digests.
 */
function ass_digest_format_item_group( $group_id, $activity_ids, $type, $group_name, $group_slug, $user_id ) {
	global $bp;

	$ass_email_css = bpges_digest_css();

	$group_permalink = bp_get_group_url( $group_id );
	$group_name_link = '<a class="item-group-group-link" href="'.$group_permalink.'" name="'.$group_slug.'">'.$group_name.'</a>';

	$userdomain = ass_digest_get_user_domain( $user_id );
	$unsubscribe_link = "$userdomain?bpass-action=unsubscribe&group=$group_id&access_key=" . md5( "{$group_id}{$user_id}unsubscribe" . wp_salt() );
	$gnotifications_link = ass_get_login_redirect_url( $group_permalink . 'notifications/' );

	// add the group title bar
	if ( $type == 'dig' ) {
		$group_message = "\n---\n\n<div class=\"item-group-title\" {$ass_email_css['group_title']}>". sprintf( __( 'Group: %s', 'buddypress-group-email-subscription' ), $group_name_link ) . "</div>\n\n";
	} elseif ( $type == 'sum' ) {
		$group_message = "\n---\n\n<div class=\"item-group-title\" {$ass_email_css['group_title']}>". sprintf( __( 'Group: %s weekly summary', 'buddypress-group-email-subscription' ), $group_name_link ) . "</div>\n";
	}

	// add change email settings link
	$group_message .= "\n<div class=\"item-group-settings-link\" {$ass_email_css['change_email']}>";
	$group_message .= __('To disable these notifications for this group click ', 'buddypress-group-email-subscription'). " <a href=\"$unsubscribe_link\">" . __( 'unsubscribe', 'buddypress-group-email-subscription' ) . '</a> - ';
	$group_message .=  __('change ', 'buddypress-group-email-subscription') . '<a href="' . $gnotifications_link . '">' . __( 'email options', 'buddypress-group-email-subscription' ) . '</a>';
	$group_message .= "</div>\n\n";

	$group_message = apply_filters( 'ass_digest_group_message_title', $group_message, $group_id, $type );

	// Finally, add the markup to the digest
	foreach ( $activity_ids as $activity_id ) {
		$activity_item = new BP_Activity_Activity( $activity_id );

		if ( ! empty( $activity_item ) ) {
			$group_message .= ass_digest_format_item( $activity_item, $type );
		}
		//$group_message .= '<pre>'. $item->id .'</pre>';
	}

	/**
	 * Filters the markup for a group's digest section.
	 *
	 * @since 3.8.0 Introduced $activity_ids and $user_id parameters.
	 *
	 * @param string $group_message Markup.
	 * @param int    $group_id      ID of the group.
	 * @param string $type          Digest type. 'dig' or 'sum'.
	 * @param array  $activity_ids  Array of IDs included in this group's digest.
	 * @param int    $user_id       ID of the user receiving the digest.
	 */
	return apply_filters( 'ass_digest_format_item_group', $group_message, $group_id, $type, $activity_ids, $user_id );
}



// displays each item in a group
function ass_digest_format_item( $item, $type ) {
	$ass_email_css = bpges_digest_css();

	$replies = '';

	//load from the cache if it exists
	if ( $item_cached = wp_cache_get( 'digest_item_' . $type . '_' . $item->id, 'ass' ) ) {
		//$item_cached .= "GENERATED FROM CACHE";
		return $item_cached;
	}

	/* Action text - This technique will not translate well */
	// bbPress 2 support
	if ( strpos( $item->type, 'bbp_' ) !== false ) {
		$action_split = explode( ' in the forum', ass_clean_subject_html( $item->action ) );

	// regular group activity items
	} else {
		$action_split = explode( ' in the group', ass_clean_subject_html( $item->action ) );
	}

	if ( isset( $action_split[1] ) )
		$action = $action_split[0];
	else
		$action = $item->action;

	$action = ass_digest_filter( $action );

	$action = str_replace( ' started the forum topic', ' started', $action ); // won't translate but it's not essential
	$action = str_replace( ' posted on the forum topic', ' posted on', $action );
	$action = str_replace( ' started the discussion topic', ' started', $action );
	$action = str_replace( ' posted on the discussion topic', ' posted on', $action );

	/* Activity timestamp */
	$timestamp = strtotime( $item->date_recorded );

	$time_posted = get_date_from_gmt( $item->date_recorded, get_option( 'time_format' ) );
	$date_posted = get_date_from_gmt( $item->date_recorded, get_option( 'date_format' ) );

	//$item_message = strip_tags( $action ) . ": \n";
	$item_message =  "<div class=\"digest-item\" {$ass_email_css['item_div']}>";
	$item_message .=  "<span class=\"digest-item-action\" {$ass_email_css['item_action']}>" . $action . ": ";
	$item_message .= "<span class=\"digest-item-timestamp\" {$ass_email_css['item_date']}>" . sprintf( __('at %s, %s', 'buddypress-group-email-subscription'), $time_posted, $date_posted ) ."</span>";
	$item_message .=  "</span>\n";

	// activity content
	if ( ! empty( $item->content ) )
		$item_message .= "<br><span class=\"digest-item-content\" {$ass_email_css['item_content']}>" . apply_filters( 'ass_digest_content', $item->content, $item, $type ) . "</span>";

	// view link
	if ( $item->type == 'activity_update' || $item->type == 'activity_comment' ) {
		$view_link = bp_activity_get_permalink( $item->id, $item );
	} else {
		$view_link = $item->primary_link;
	}

	$item_message .= ' - <a ' . $ass_email_css['view_link'] . ' class="digest-item-view-link" href="' . ass_get_login_redirect_url( $view_link ) .'">' . __( 'View', 'buddypress-group-email-subscription' ) . '</a>';

	$item_message .= "</div>\n\n";

	$item_message = apply_filters( 'ass_digest_format_item', $item_message, $item, $action, $timestamp, $type, $replies );
	$item_message = ass_digest_filter( $item_message );

	// save the cache
	if ( $item->id )
		wp_cache_set( 'digest_item_' . $type . '_' . $item->id, $item_message, 'ass' );

	return $item_message;
}

// standard wp filters to clean up things that might mess up email display - (maybe not necessary?)
function ass_digest_filter( $item ) {
	$item = wptexturize( $item );
	$item = convert_chars( $item );
	$item = stripslashes( $item );
	return $item;
}

// Run activity content in digests through wpautop for proper handling of line breaks.
add_filter( 'ass_digest_content', 'wpautop' );

// convert the email to plain text, and fancy it up a bit. these conversion only work in English, but it's ok.
function ass_convert_html_to_plaintext( $message ) {
	// convert view links to http:// links
	$message = preg_replace( "/<a href=\"(.[^\"]*)\">View<\/a>/i", "\\1", $message );
	// convert group div to two lines encasing the group name
	$message = preg_replace( "/<div.*>Group: <a href=\"(.[^\"]*)\">(.*)<\/a>.*<\/div>/i", "------\n\\2 - \\1\n------", $message );
	// convert footer line to two dashes
	$message = preg_replace( "/\n<div class=\"ass-footer\"/i", "--\n<div", $message );
	// convert My Groups links to http:// links
	$message = preg_replace( "/<a href=\"(.[^\"]*)\">My Groups<\/a>/i", "\\1", $message );

	$message = preg_replace( "/<a href=\"(.[^\"]*)\">(.*)<\/a>/i", "\\2 (\\1)", $message );

	$message = strip_tags( stripslashes( $message ) );
	// remove uneccesary lines
	$message = str_replace( "change email options for this group\n\n", '', $message );
	$message = html_entity_decode( $message , ENT_QUOTES, 'UTF-8' );

	return $message;
}

/**
 * Properly encode HTML characters in old digest items.
 *
 * This is a regex callback function used in ass_send_multipart_email().
 *
 * @since 3.7.1
 *
 * @param  array $matches Regex matches.
 * @return string
 */
function ass_old_digest_item_html_entities( $matches ) {
	$ass_email_css = bpges_digest_css();
	return '<span ' . $ass_email_css['item_content'] . '>' . htmlentities( strip_tags( $matches[1] ), ENT_COMPAT, 'utf-8' ) . '</span>';
}

/**
 * Formats and sends a MIME multipart email with both HTML and plaintext
 *
 * We have to use some fancy filters from the wp_mail function to configure the $phpmailer object
 * properly
 */
function ass_send_multipart_email( $to, $subject, $message_plaintext, $message ) {
	$ass_email_css = bpges_digest_css();

     // setup HTML body. plugins that wrap emails with HTML templates can filter this
	$message = apply_filters( 'ass_digest_message_html', "<html><body>{$message}</body></html>", $message );

    // get admin email
	$admin_email = get_site_option( 'admin_email' );

	// if no admin email, use a dummy 'from' email address
	if ( $admin_email == '' ) {
		$admin_email = 'support@' . $_SERVER['SERVER_NAME'];
	}

	// get from name
	$from_name = get_site_option( 'site_name' );

	// if no site name, use WordPress as dummy 'from' name
	if ( empty( $from_name ) ) {
		$from_name = 'WordPress';
	}

	// set up anonymous functions to be used to override some WP mail stuff
	//
	// due to a bug with wp_mail(), we should reset some $phpmailer properties
	// (see http://core.trac.wordpress.org/ticket/18493#comment:13).
	//
	// we're doing this during the 'wp_mail_from' filter because this runs before
	// 'phpmailer_init'
	$admin_email = addslashes( $admin_email );
	$admin_email_filter = function( $admin_email ) {
		global $phpmailer;

		if ( $phpmailer && is_object( $phpmailer ) ) {
			$phpmailer->Body    = "";
			$phpmailer->AltBody = "";
		}

		return $admin_email;
	};

	$from_name = addslashes( $from_name );
	$from_name_filter = function( $from_name ) {
		return $from_name;
	};

	// set the WP email overrides
	add_filter( 'wp_mail_from',      $admin_email_filter );
	add_filter( 'wp_mail_from_name', $from_name_filter );

	// setup plain-text body
	$message_plaintext = addslashes( $message_plaintext );
	add_action( 'phpmailer_init', function( $phpmailer ) use ( $message_plaintext ) {
		if ( $phpmailer && is_object( $phpmailer ) ) {
			$phpmailer->AltBody = "'" . $message_plaintext . "'";
		}
	} );

	// set content type as HTML
	$headers = array( 'Content-type: text/html' );

	/*
	 * Eek. Stupid HTML encoding.
	 *
	 * Wish we could do this higher up the chain...
	 */
	$message = preg_replace_callback( '/<span ' . $ass_email_css['item_content'] . '>(.+?)<\/span>/', 'ass_old_digest_item_html_entities', $message );

	// send the email!
	$result = wp_mail( $to, $subject, $message, $headers );

	// remove our custom hooks
	remove_filter( 'wp_mail_from',      $admin_email_filter );
	remove_filter( 'wp_mail_from_name', $from_name_filter );

	// clean up after ourselves
	// reset $phpmailer->AltBody after we set it in the 'phpmailer_init' hook
	// this is so subsequent calls to wp_mail() by other plugins will be clean
	global $phpmailer;

	if ( $phpmailer && is_object( $phpmailer ) ) {
		$phpmailer->AltBody = "";
	}

	return $result;
}


function ass_digest_record_activity( $activity_id, $user_id, $group_id, $type = 'dig' ) {
	global $bp;

	if ( !$activity_id || !$user_id || !$group_id )
		return;

	/**
	 * Prevent the addition of specific activity item IDs for specific users.
	 *
	 * @since 3.6.1
	 *
	 * @param bool   $proceed     Whether to continue adding this activity item to a user's digest.
	 * @param int    $activity_id ID of activity item to be added.
	 * @param int    $user_id     ID of user whose digest record is being modified.
	 * @param int    $group_id    ID of the group where the action took place.
	 * @param string $type        Type of digest.
	 */
	if ( false === apply_filters( 'ass_digest_record_activity_allow', true, $activity_id, $user_id, $group_id, $type ) ) {
		return;
	}

	ass_queue_activity_item( $activity_id, $user_id, $group_id, $type );
}

/**
 * Get digest queue for a given user.
 *
 * @param int    $user_id ID of the user.
 * @param string $type    Digest type. 'dig' or 'sum'.
 * @return array
 */
function bpges_get_digest_queue_for_user( $user_id, $type ) {
	if ( ! is_numeric( $user_id ) ) {
		return array();
	}

	$user_id = (int) $user_id;

	$query = new BPGES_Queued_Item_Query( array(
		'user_id' => $user_id,
		'type'    => $type,
	) );

	$queue = array();
	foreach ( $query->get_results() as $item ) {
		$queue[ $item->group_id ][] = $item->activity_id;
	}

	return $queue;
}

function ass_cron_add_weekly( $schedules ) {
	if ( !isset( $schedules[ 'weekly' ] ) ) {
		$schedules['weekly'] = array( 'interval' => 604800, 'display' => __( 'Once Weekly', 'buddypress-group-email-subscription' ) );
	}
	return $schedules;
}
add_filter( 'cron_schedules', 'ass_cron_add_weekly' );



function ass_set_daily_digest_time( $hours, $minutes ) {
	$the_time = date( 'Y-m-d' ) . ' ' . $hours . ':' . $minutes;
	$the_timestamp = strtotime( $the_time );

	/* If the time has already passed today, the next run will be tomorrow */
	$the_timestamp = ( $the_timestamp > time() ) ? $the_timestamp : (int)$the_timestamp + 86400;

	/* Clear the old recurring event and set up a new one */
	wp_clear_scheduled_hook( 'ass_digest_event' );

	/*
	 * Not using bp_get_root_blog_id() since it might not be available during
	 * activation time.
	 */
	if ( defined( 'BP_ROOT_BLOG' ) ) {
		/** This filter is documented in /wp-content/plugins/buddypress/bp-core/bp-core-functions.php */
		$blog_id = (int) apply_filters( 'bp_get_root_blog_id', constant( 'BP_ROOT_BLOG' ) );
	} else {
		$blog_id = 1;
	}

	// Custom BP root blog, so set up cron on BP sub-site.
	$switched = false;
	if ( 1 !== $blog_id ) {
		switch_to_blog( $blog_id );
		wp_clear_scheduled_hook( 'ass_digest_event' );
		$switched = true;
	}

	wp_schedule_event( $the_timestamp, 'daily', 'ass_digest_event' );

	update_option( 'ass_digest_time', array( 'hours' => $hours, 'minutes' => $minutes ) );

	// Restore current blog.
	if ( $switched ) {
		restore_current_blog();
	}
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

	/*
	 * Not using bp_get_root_blog_id() since it might not be available during
	 * activation time.
	 */
	if ( defined( 'BP_ROOT_BLOG' ) ) {
		/** This filter is documented in /wp-content/plugins/buddypress/bp-core/bp-core-functions.php */
		$blog_id = (int) apply_filters( 'bp_get_root_blog_id', constant( 'BP_ROOT_BLOG' ) );
	} else {
		$blog_id = 1;
	}

	// Custom BP root blog, so set up cron on BP sub-site.
	$switched = false;
	if ( 1 !== $blog_id ) {
		switch_to_blog( $blog_id );
		wp_clear_scheduled_hook( 'ass_digest_event_weekly' );
		$switched = true;
	}

	wp_schedule_event( $next_weekly, 'weekly', 'ass_digest_event_weekly' );

	update_option( 'ass_weekly_digest', $day );

	// Restore current blog.
	if ( $switched ) {
		restore_current_blog();
	}
}

/*
// if in the future we want to do flexible schedules. this is how we could add the custom cron. Then we need to change the digest or summary to use this custom schedule.
function ass_custom_digest_frequency( $schedules ) {
    if( ( $freq = get_option(  'ass_digest_frequency' ) ) ) {
        if( !isset( $schedules[$freq.'_hrs'] ) ) {
            $schedules[$freq.'_hrs'] = array( 'interval' => $freq * 3600, 'display' => "Every $freq hours" );
        }
    }
    return $schedules;
}
add_filter( 'cron_schedules', 'ass_custom_digest_frequency' );
*/

/**
 * Gets the user_login, user_nicename and email address for an array of user IDs
 * all in one fell swoop!
 *
 * This is designed to avoid pinging the DB over and over in a foreach loop.
 */
function ass_get_mass_userdata( $user_ids = array() ) {
	if ( empty( $user_ids ) )
		return false;

	global $wpdb;

	// implode user IDs
	$in = implode( ',', wp_parse_id_list( $user_ids ) );

	// get our results
	$results = $wpdb->get_results( "
		SELECT ID, user_login, user_nicename, user_email
			FROM {$wpdb->users}
			WHERE ID IN ({$in})
	", ARRAY_A );

	if ( empty( $results ) )
		return false;

	$users = array();

	// setup associative array
	foreach( (array) $results as $result ) {
		$users[ $result['ID'] ]['user_login']    = $result['user_login'];
		$users[ $result['ID'] ]['user_nicename'] = $result['user_nicename'];
		$users[ $result['ID'] ]['email']         = $result['user_email'];
	}

	return $users;
}

/**
 * Get user domain.
 *
 * Previously, this was a cached version of the bp_core_get_user_domain() check.
 *
 * @since 3.9.0 Function is now a wrapper for the BP function.
 * @since 4.1.2 Function is now a wrapper for the BP 12.0+ function bp_members_get_user_url().
 *
 * @param int $user_id
 */
function ass_digest_get_user_domain( $user_id ) {
	return bp_members_get_user_url( $user_id );
}

// if the WP_Better_Emails plugin is installed, don't wrap the message with <html><body>$message</body></html>
function ass_digest_support_wp_better_emails( $message, $message_pre_html_wrap ) {
    if ( class_exists( 'WP_Better_Emails' ) ) {
        $message = $message_pre_html_wrap;
    }

    return $message;
}
add_filter( 'ass_digest_message_html', 'ass_digest_support_wp_better_emails', 10, 2 );

/**
 * Get the CSS for digest emails.
 *
 * @since 3.9.0
 *
 * @return array
 */
function bpges_digest_css() {
	$ass_email_css = array();

	// HTML emails only work with inline CSS styles. Here we setup the styles to be used in various functions below.
	$ass_email_css['wrapper']      = 'style="color:#333;clear:both;'; // use this to style the body
	$ass_email_css['title']        = 'style="font-size:130%; margin:0 0 25px 0;"';
	$ass_email_css['summary']      = '';
	$ass_email_css['summary_ul']   = 'style="margin:0; padding:0 0 5px; list-style-type:circle; list-style-position:inside;"';
	//$ass_email_css['summary']    = 'style="display:list-item;"';
	$ass_email_css['follow_topic'] = 'style="padding:15px 0 0; color: #888;clear:both;"';
	$ass_email_css['group_title']  = 'style="font-size:120%; background-color:#F5F5F5; padding:3px; margin:20px 0 0; border-top: 1px #eee solid;"';
	$ass_email_css['change_email'] = 'style="font-size:12px; margin-left:10px; color:#888;"';
	$ass_email_css['item_div']     = 'style="padding: 10px; border-top: 1px #eee solid;"';
	$ass_email_css['item_action']  = 'style="color:#888;"';
	$ass_email_css['item_date']    = 'style="font-size:85%; color:#bbb; margin-left:8px;"';
	$ass_email_css['item_content'] = 'style="color:#333;"';
	$ass_email_css['view_link']    = 'style="";';
	$ass_email_css['item_weekly']  = 'style="color:#888; padding:4px 10px 0"'; // used in weekly in place of other item_ above
	$ass_email_css['footer']       = 'class="ass-footer" style="margin:25px 0 0; padding-top:5px; border-top:1px #bbb solid;"';

	// BP 2.5+ overrides.
	if ( true === function_exists( 'bp_send_email' ) && true === ! apply_filters( 'bp_email_use_wp_mail', false ) ) {
		$ass_email_css['summary_ul']  = 'style="margin:0; padding:0 0 25px 15px; list-style-type:circle; list-style-position:inside;"';
		$ass_email_css['item_action'] = $ass_email_css['item_content'] = '';
		$ass_email_css['item_date']   = 'style="font-size:85%;"';
	}

	/**
	 * Filters the CSS used when generating digests.
	 *
	 * @since 2.1
	 *
	 * @return array
	 */
	return apply_filters( 'ass_email_css', $ass_email_css );
}

/**
 * Checks whether an item is valid to send in a digest for a user.
 *
 * @since 3.8.0
 *
 * @param
 */
function bp_ges_activity_is_valid_for_digest( $activity_id, $digest_type, $user_id = null ) {
	/*
	 * By default, an activity item is "stale" if it should have sent more than
	 * three digest-periods ago.
	 */
	$is_stale = false;
	$default_stale_activity_period = 'dig' === $digest_type ? ( 3 * DAY_IN_SECONDS ) : ( 3 * WEEK_IN_SECONDS );

	/**
	 * Filters the "staleness" period for an activity item, after which it is discarded and not included in digests.
	 *
	 * @since 3.8.0
	 *
	 * @param int    $stale_activity_period Time period, in seconds.
	 * @param int    $activity_id           Activity ID.
	 * @param string $digest_type           Digest type. 'dig' or 'sum'.
	 * @param int    $user_id               User ID.
	 */
	$stale_activity_period = apply_filters( 'bp_ges_stale_activity_period', $default_stale_activity_period, $activity_id, $digest_type, $user_id );

	if ( isset( buddypress()->ass->items[ $activity_id ] ) ) {
		$activity_item = buddypress()->ass->items[ $activity_id ];
	} else {
		$activity_item = new BP_Activity_Activity( $activity_id );
	}

	if ( ( time() - strtotime( $activity_item->date_recorded ) ) > $stale_activity_period ) {
		$is_stale = true;
	}

	$is_valid = ! $is_stale;

	/**
	 * Filters whether an activity item should be considered valid for a digest.
	 *
	 * @since 3.8.0
	 *
	 * @param bool   $is_valid    Whether the activity item is valid.
	 * @param int    $activity_id Activity ID.
	 * @param string $digest_type Digest type. 'dig' or 'sum'.
	 * @param int    $user_id     User ID.
	 */
	return apply_filters( 'bp_ges_activity_is_valid_for_digest', $is_valid, $activity_id, $digest_type, $user_id );
}
