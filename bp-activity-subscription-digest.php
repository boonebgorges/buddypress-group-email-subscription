<?php

// for testing only
//if you need to add this at the TOP of your wp-config.php file. (Here are the timezones http://us3.php.net/manual/en/timezones.php)
//date_default_timezone_set('Asia/Tokyo');
//date_default_timezone_set('America/New_York');

/**
 * Offload GES digest sending to another page process.
 *
 * Uses the awesome TechCrunch WP Async Task library.  Licensed under the MIT.
 *
 * @since 3.7.0
 */
class BP_GES_Async_Task_Send_Digests extends WP_Async_Task {

	/**
	 * Hook that WP Async Task should attach itself to.
	 *
	 * @var string
	 */
	protected $action = 'ass_digest_event';

	/**
	 * Which digest are we sending?
	 *
	 * @var string
	 */
	protected $style = 'dig';

	/**
	 * Priority to run the hook.
	 *
	 * @var int
	 */
	protected $priority = 10;

	/**
	 * How many arguments are we passing to the hook?
	 *
	 * @var int
	 */
	protected $argument_count = 1;

	/**
	 * Constructor to wire up the necessary actions
	 *
	 * Which hooks the asynchronous postback happens on can be set by the
	 * $auth_level parameter. There are essentially three options: logged in users
	 * only, logged out users only, or both. Set this when you instantiate an
	 * object by using one of the three class constants to do so:
	 *  - LOGGED_IN
	 *  - LOGGED_OUT
	 *  - BOTH
	 * $auth_level defaults to BOTH
	 *
	 * @throws Exception If the class' $action value hasn't been set
	 *
	 * @param int $auth_level The authentication level to use (see above)
	 * @param string $style   Which digest are we sending?
	 *                        Specify 'dig' for daily, 'sum' for weekly.
	 */
	public function __construct( $auth_level = WP_Async_Task::BOTH, $style = 'dig' ) {
		if ( $style == 'sum' ) {
			$this->action = $this->action . '_weekly';
			$this->style  = 'sum';
		}

		if ( empty( $this->action ) ) {
			throw new Exception( 'Action not defined for class ' . __CLASS__ );
		}
		add_action( $this->action, array( $this, 'launch' ), (int) $this->priority, (int) $this->argument_count );
		if ( $auth_level & self::LOGGED_IN ) {
			add_action( "admin_post_wp_async_$this->action", array( $this, 'handle_postback' ) );
		}
		if ( $auth_level & self::LOGGED_OUT ) {
			add_action( "admin_post_nopriv_wp_async_$this->action", array( $this, 'handle_postback' ) );
		}
	}

	/**
	 * Launch the request on the WordPress shutdown hook
	 *
	 * On VIP we got into data races due to the postback sometimes completing
	 * faster than the data could propogate to the database server cluster.
	 * This made WordPress get empty data sets from the database without
	 * failing. On their advice, we're moving the actual firing of the async
	 * postback to the shutdown hook. Supposedly that will ensure that the
	 * data at least has time to get into the object cache.
	 *
	 * @uses $_COOKIE        To send a cookie header for async postback
	 * @uses apply_filters()
	 * @uses admin_url()
	 * @uses wp_remote_post()
	 */
	public function launch_on_shutdown() {
		if ( ! empty( $this->_body_data ) ) {
			$cookies = array();
			foreach ( $_COOKIE as $name => $value ) {
				$cookies[] = "$name=" . urlencode( is_array( $value ) ? serialize( $value ) : $value );
			}

			$request_args = array(
				'timeout'   => apply_filters( 'ass_async_task_timeout', 0.01 ),
				'blocking'  => false,
				'sslverify' => apply_filters( 'https_local_ssl_verify', true ),
				'body'      => $this->_body_data,
				'headers'   => array(
					'cookie' => implode( '; ', $cookies ),
				),
			);

			$url = admin_url( 'admin-post.php' );

			wp_remote_post( $url, $request_args );
		}
	}

	/**
	 * Prepare data for the asynchronous request
	 *
	 * @param  array $data Function arguments from our hook as an array.
	 * @return array Data to pass to the next pageload.
	 */
	protected function prepare_data( $data ) {

		// prepare data for offloading
		return array(
			'increment' => isset( $_POST['increment'] ) ? ++$_POST['increment'] : 1,
		);
	}

	/**
	 * Run the async task action.
	 *
	 * Uses the data passed from prepare_data().
	 */
	protected function run_action() {

		do_action( "wp_async_{$this->action}" );

		/*
		 * Run launch() again if there are members left to process,
		 * preparing a new post request to be fired on shutdown.
		 */
		global $wpdb;
		// Find users with usermeta values containing `dig";a:1(or greater)`, but not `dig";a:0`.
		$type_regex = $this->style . '";a:[1-9][0-9]*';
		$unsent_users = $wpdb->get_results( $wpdb->prepare( "SELECT user_id, meta_value FROM {$wpdb->usermeta} WHERE meta_key = 'ass_digest_items' AND meta_value RLIKE %s LIMIT 1", $type_regex ) );
		if ( ! empty( $unsent_users ) ) {
			/*
			 * We need to remove the nopriv_ from the action that may have been
			 * added in WP_Async_Task::handle_postback(), else launch() will
			 * post with the action `wp_async_nopriv_ass_digest_event_weekly`,
			 * causing the handle_postback hook name to be malformed.
			 */
			if ( substr( $this->action, 0, 7 ) === 'nopriv_' ) {
				$this->action = substr( $this->action, 7 );
			}
			$this->launch();
		}
	}

}

function ass_initialize_async_send_digests_classes(){
	$digest_class  = new BP_GES_Async_Task_Send_Digests( 3, 'dig' );
	$summary_class = new BP_GES_Async_Task_Send_Digests( 3, 'sum' );
}
add_action( 'bp_init', 'ass_initialize_async_send_digests_classes' );

/* This function was used for debugging the digest scheduling features */
function ass_digest_schedule_print() {
	print "<br />";
	print "<br />";

//	ass_digest_fire( 'dig' );
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

function ass_digest_fire( $type ) {
	global $bp, $wpdb, $groups_template, $ass_email_css;

	// If safe mode isn't on, then let's set the execution time to unlimited
	if ( ! ini_get( 'safe_mode' ) ) {
		set_time_limit(0);
	}

	if ( 'dig' != $type ) {
		$type = 'sum';
	}

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
	$ass_email_css['item_weekly']  = 'style="color:#888; padding:4px 10px 0"'; // used in weekly in place of other item_ above
	$ass_email_css['footer']       = 'class="ass-footer" style="margin:25px 0 0; padding-top:5px; border-top:1px #bbb solid;"';

	// BP 2.5+ overrides.
	if ( true === function_exists( 'bp_send_email' ) && true === ! apply_filters( 'bp_email_use_wp_mail', false ) ) {
		$ass_email_css['summary_ul']  = 'style="margin:0; padding:0 0 25px 15px; list-style-type:circle; list-style-position:inside;"';
		$ass_email_css['item_action'] = $ass_email_css['item_content'] = '';
		$ass_email_css['item_date']   = 'style="font-size:85%;"';
	}

	// Allow plugins to filter the CSS
	$ass_email_css = apply_filters( 'ass_email_css', $ass_email_css );

	$title = ass_digest_get_title( $type );
	$blogname = get_blog_option( BP_ROOT_BLOG, 'blogname' );
	$subject = apply_filters( 'ass_digest_subject', "$title [$blogname]", $blogname, $title, $type );

	$footer = "\n\n<div class=\"digest-footer\" {$ass_email_css['footer']}>";
	$footer .= sprintf( __( "You have received this message because you are subscribed to receive a digest of activity in some of your groups on %s.", 'bp-ass' ), $blogname );
	$footer = apply_filters( 'ass_digest_footer', $footer, $type );

	// get user subscription data for this type
	// If we're incrementing, fetch a subset, otherwise we fetch everything.
	if ( isset( $_POST['increment'] ) ) {
		/**
		 * Filters the number of users to send digests to in one iteration.
		 *
		 * @since 3.7.0
		 *
		 * @param int $number  How many users to send digests to.
		 * @param string $type Which type of digest we're currently sending.
		 */
		$user_limit = apply_filters( 'ass_set_users_per_iteration_digest_send', 50, $type );
		$type_regex = $type . '";a:[1-9][0-9]*';
		$user_subscriptions = $wpdb->get_results( $wpdb->prepare( "SELECT user_id, meta_value FROM {$wpdb->usermeta} WHERE meta_key = 'ass_digest_items' AND meta_value RLIKE %s LIMIT %d", $type_regex, $user_limit ) );
	} else {
		$user_subscriptions = $wpdb->get_results( "SELECT user_id, meta_value FROM {$wpdb->usermeta} WHERE meta_key = 'ass_digest_items' AND meta_value != ''" );
	}

	// no subscription data? stop now!
	if ( empty( $user_subscriptions ) ) {
		return;
	}

	// get all activity IDs for everyone subscribed to a digest
	$all_activity_items = array();

	foreach( (array) $user_subscriptions as $us ) {
		$subs = maybe_unserialize( $us->meta_value );
		foreach( (array) $subs as $digest_type => $group_subs ) {
			foreach( (array) $group_subs as $group_id => $sub_ids ) {
				foreach( (array) $sub_ids as $sid ) {
					// note: activity ID is added as the key for performance reasons
					if ( ! isset( $all_activity_items[$sid] ) && ! empty( $sid ) ) {
						$all_activity_items[$sid] = 1;
					}
				}
			}
		}
	}

	// no activity IDs? stop now!
	if ( empty( $all_activity_items ) ) {
		return;
	}

	// setup the IN query
	$in = implode( ",", array_keys( $all_activity_items ) );

	// get only the activity data we need
	//
	// we're not using bp_activity_get_specific() to avoid an extra query with the
	// xprofile table
	//
	// @todo MySQL IN query doesn't scale well when querying a ton of IDs
	$items = $wpdb->get_results( "
		SELECT id, type, action, content, primary_link, item_id, secondary_item_id, date_recorded
			FROM {$bp->activity->table_name}
			WHERE id IN ({$in})
	" );

	// cache some stuff
	$bp->ass = new stdClass;
	$bp->ass->massdata     = ass_get_mass_userdata( wp_list_pluck( $user_subscriptions, 'user_id' ) );
	$bp->ass->activity_ids = array_flip( (array) wp_list_pluck( $items, 'id' ) );
	$bp->ass->items = array();

	// cache activity items
	foreach ( $items as $item ) {
		$bp->ass->items[$item->id] = $item;
	}

	// get list of all groups so we can look them up quickly in the foreach loop below
	$all_groups = $wpdb->get_results( "SELECT id, name, slug FROM {$bp->groups->table_name}" );

	// setup group info array that we'll reference later
	$groups_info = array();
	foreach ( $all_groups as $group ) {
		$groups_info[ $group->id ] = array(
			'name' => ass_digest_filter( $group->name ),
			'slug' => $group->slug
		);
	}

	// start the digest loop for each user
	foreach ( (array) $user_subscriptions as $user ) {
		$user_id = $user->user_id;

		// get the group subscriptions for the user
		$group_activity_ids_array = unserialize( $user->meta_value );

		// initialize some strings
		$summary = $activity_message = $body = '';

		// We only want the weekly or daily ones
		if ( empty( $group_activity_ids_array[$type] ) || !$group_activity_ids = (array)$group_activity_ids_array[$type] )
			continue;

		// get userdata
		// @see ass_get_mass_userdata()
		$userdata = $bp->ass->massdata[$user_id];

		// sanity check!
		if ( empty( $userdata ) )
			continue;

		// email address of user
		$to = $userdata['email'];

		$userdomain = ass_digest_get_user_domain( $user_id );

		// filter the list - can be used to sort the groups
		$group_activity_ids = apply_filters( 'ass_digest_group_activity_ids', @$group_activity_ids );

		// Keep an unmodified copy of the activity IDs to be passed to filters.
		$group_activity_ids_pristine = $group_activity_ids;

		$header = "<div class=\"digest-header\" {$ass_email_css['title']}>$title " . __('at', 'bp-ass')." <a href='" . $bp->root_domain . "'>$blogname</a></div>\n\n";
		$message = apply_filters( 'ass_digest_header', $header, $title, $ass_email_css['title'] );

		// loop through each group for this user
		$has_group_activity = false;
		foreach ( $group_activity_ids as $group_id => $activity_ids ) {
			// check to see if our activity IDs exist
			// intersect against our master activity IDs array
			$activity_ids = array_intersect_key( array_flip( $activity_ids ), $bp->ass->activity_ids );
			$activity_ids = array_keys( $activity_ids );

			// Activities could have been deleted since being recorded for digest emails.
			if ( empty( $activity_ids ) ) {
				continue;
			}

			$has_group_activity = true;

			$group_name = $groups_info[ $group_id ][ 'name' ];
			$group_slug = $groups_info[ $group_id ][ 'slug' ];
			$group_permalink = bp_get_group_permalink( groups_get_group( array( 'group_id' => $group_id ) ) );

			// Might be nice here to link to anchor tags in the message.
			if ( 'dig' == $type ) {
				$summary .= apply_filters( 'ass_digest_summary', "<li class=\"digest-group-summary\" {$ass_email_css['summary']}><a href='{$group_permalink}'>$group_name</a> " . sprintf( __( '(%s items)', 'bp-ass' ), count( $activity_ids ) ) ."</li>\n", $ass_email_css['summary'], $group_slug, $group_name, $activity_ids );
			}

			$activity_message .= ass_digest_format_item_group( $group_id, $activity_ids, $type, $group_name, $group_slug, $user_id );
			unset( $group_activity_ids[ $group_id ] );
		}

		// If there's nothing to send, skip this use.
		if ( ! $has_group_activity ) {
			continue;
		}


		// reset the user's sub array removing those sent
		$group_activity_ids_array[$type] = $group_activity_ids;

		// show group summary for digest, and follow help text for weekly summary
		if ( 'dig' == $type ) {
			$message .= apply_filters( 'ass_digest_summary_full', __( 'Group Summary', 'bp-ass') . ":\n<ul class=\"digest-group-summaries\" {$ass_email_css['summary_ul']}>" .  $summary . "</ul>", $ass_email_css['summary_ul'], $summary );
		}

		// the meat of the message which we generated above goes here
		$message .= $activity_message;
		$body .= $activity_message;

		// user is subscribed to "New Topics"
		// add follow help text only if bundled forums are enabled
		if ( 'sum' == $type && class_exists( 'BP_Forums_Component' ) ) {
			$message .= apply_filters( 'ass_summary_follow_topic', "<div {$ass_email_css['follow_topic']}>" . __( "How to follow a topic: to get email updates for a specific topic, click the topic title - then on the webpage click the <i>Follow this topic</i> button. (If you don't see the button you need to login first.)", 'bp-ass' ) . "</div>\n", $ass_email_css['follow_topic'] );
		}

		$message .= $footer;

		$unsubscribe_message = "\n\n" . sprintf( __( "To disable these notifications per group please login and go to: %s where you can change your email settings for each group.", 'bp-ass' ), "<a href=\"{$userdomain}{$bp->groups->slug}/\">" . __( 'My Groups', 'bp-ass' ) . "</a>" );

		if ( bp_get_option( 'ass-global-unsubscribe-link' ) == 'yes' ) {
			$unsubscribe_link = "$userdomain?bpass-action=unsubscribe&access_key=" . md5( $user_id . 'unsubscribe' . wp_salt() );
			$unsubscribe_message .= "\n\n<br><br><a class=\"digest-unsubscribe-link\" href=\"$unsubscribe_link\">" . __( 'Disable these notifications for all my groups at once.', 'bp-ass' ) . '</a>';
		}

		$message .= apply_filters( 'ass_digest_disable_notifications', $unsubscribe_message, $userdomain . $bp->groups->slug );

		$message .= "</div>";

		if ( isset( $_GET['sum'] ) ) {
			// test mode run from the browser, dont send the emails, just show them on screen using domain.com?sum=1
			echo '<div style="background-color:white; width:75%;padding:20px 10px;">';
			echo '<p>======================== to: <b>'.$to.'</b> ========================</p>';
			echo $message;
			//echo '<br>PLAIN TEXT PART:<br><pre>'; echo $message_plaintext ; echo '</pre>';
			echo '</div>';
		} else {

			// Sending time!
			if ( true === function_exists( 'bp_send_email' ) && true === ! apply_filters( 'bp_email_use_wp_mail', false ) ) {
				// Custom GES email tokens.
				$user_message_args = array();

				// Digest summary only available for daily digests.
				$user_message_args['ges.digest-summary'] = '';
				if ( 'dig' == $type ) {
					$user_message_args['ges.digest-summary'] = apply_filters( 'ass_digest_summary_full', __( 'Group Summary', 'bp-ass' ) . ":\n<ul {$ass_email_css['summary_ul']}>" .  $summary . "</ul>", $ass_email_css['summary_ul'], $summary );
				}

				$user_message_args['ges.subject']       = $title;
				$user_message_args['ges.settings-link'] = ass_get_login_redirect_url( "{$userdomain}{$bp->groups->slug}" );
				$user_message_args['subscription_type'] = $type;
				$user_message_args['recipient.id']      = $user_id;

				// Unused.
				$user_message_args['poster.url']   = $userdomain;

				// BP-specific tokens.
				$user_message_args['usermessage'] = $body;
				$user_message_args['poster.name'] = $userdata['user_login']; // Unused

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

			// update the subscriber's digest list
			bp_update_user_meta( $user_id, 'ass_digest_items', $group_activity_ids_array );

		}

		unset( $message, $message_plaintext, $message, $to, $userdata, $userdomain, $activity_message, $summary, $group_activity_ids_array, $group_activity_ids );
	}
}

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
		$title = sprintf( __( 'Your daily digest of group activity', 'bp-ass' ) );
	} else {
		$title = sprintf( __( 'Your weekly summary of group topics', 'bp-ass' ) );
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
	if ( true === empty( buddypress()->ges_tokens ) ) {
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
	ass_digest_fire( 'dig' );
}
add_action( 'wp_async_nopriv_ass_digest_event', 'ass_daily_digest_fire' );
add_action( 'wp_async_ass_digest_event',        'ass_daily_digest_fire' );

function ass_weekly_digest_fire() {
	ass_digest_fire( 'sum' );
}
add_action( 'wp_async_nopriv_ass_digest_event_weekly', 'ass_weekly_digest_fire' );
add_action( 'wp_async_ass_digest_event_weekly',        'ass_weekly_digest_fire' );

// Use these two lines for testing the digest firing in real-time
//add_action( 'bp_actions', 'ass_daily_digest_fire' ); // for testing only
//add_action( 'bp_actions', 'ass_weekly_digest_fire' ); // for testing only



// for testing the digest firing in real-time, add /?sum=1 to the url
function ass_digest_fire_test() {
	if ( isset( $_GET['sum'] ) && is_super_admin() ){
		echo "<h2>".__('DAILY DIGEST:','bp-ass')."</h2>";
		ass_digest_fire( 'dig' );
		echo "<h2 style='margin-top:150px'>".__('WEEKLY DIGEST:','bp-ass')."</h2>";
		ass_digest_fire( 'sum' );

		//global $wpdb;
		//echo '<pre>';print_r( $wpdb->queries );
		die();
	}

}
add_action( 'bp_actions', 'ass_digest_fire_test' );




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
	global $bp, $ass_email_css;

	$group_permalink = bp_get_group_permalink( groups_get_group( array( 'group_id' => $group_id ) ) );
	$group_name_link = '<a class="item-group-group-link" href="'.$group_permalink.'" name="'.$group_slug.'">'.$group_name.'</a>';

	$userdomain = ass_digest_get_user_domain( $user_id );
	$unsubscribe_link = "$userdomain?bpass-action=unsubscribe&group=$group_id&access_key=" . md5( "{$group_id}{$user_id}unsubscribe" . wp_salt() );
	$gnotifications_link = ass_get_login_redirect_url( $group_permalink . 'notifications/' );

	// add the group title bar
	if ( $type == 'dig' ) {
		$group_message = "\n---\n\n<div class=\"item-group-title\" {$ass_email_css['group_title']}>". sprintf( __( 'Group: %s', 'bp-ass' ), $group_name_link ) . "</div>\n\n";
	} elseif ( $type == 'sum' ) {
		$group_message = "\n---\n\n<div class=\"item-group-title\" {$ass_email_css['group_title']}>". sprintf( __( 'Group: %s weekly summary', 'bp-ass' ), $group_name_link ) . "</div>\n";
	}

	// add change email settings link
	$group_message .= "\n<div class=\"item-group-settings-link\" {$ass_email_css['change_email']}>";
	$group_message .= __('To disable these notifications for this group click ', 'bp-ass'). " <a href=\"$unsubscribe_link\">" . __( 'unsubscribe', 'bp-ass' ) . '</a> - ';
	$group_message .=  __('change ', 'bp-ass') . '<a href="' . $gnotifications_link . '">' . __( 'email options', 'bp-ass' ) . '</a>';
	$group_message .= "</div>\n\n";

	$group_message = apply_filters( 'ass_digest_group_message_title', $group_message, $group_id, $type );

	// Finally, add the markup to the digest
	foreach ( $activity_ids as $activity_id ) {
		// Cache is set earlier in ass_digest_fire()
		$activity_item = ! empty( $bp->ass->items[$activity_id] ) ? $bp->ass->items[$activity_id] : false;

		if ( ! empty( $activity_item ) ) {
			$group_message .= ass_digest_format_item( $activity_item, $type );
		}
		//$group_message .= '<pre>'. $item->id .'</pre>';
	}

	return apply_filters( 'ass_digest_format_item_group', $group_message, $group_id, $type );
}



// displays each item in a group
function ass_digest_format_item( $item, $type ) {
	global $ass_email_css;

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

	if ( $action_split[1] )
		$action = $action_split[0];
	else
		$action = $item->action;

	$action = ass_digest_filter( $action );

	$action = str_replace( ' started the forum topic', ' started', $action ); // won't translate but it's not essential
	$action = str_replace( ' posted on the forum topic', ' posted on', $action );
	$action = str_replace( ' started the discussion topic', ' started', $action );
	$action = str_replace( ' posted on the discussion topic', ' posted on', $action );

	/* Activity timestamp */
//	$timestamp = strtotime( $item->date_recorded );

	/* Because BuddyPress core set gmt = true, timezone must be added */
	$timestamp = strtotime( $item->date_recorded ) +date('Z');

	$time_posted = date( get_option( 'time_format' ), $timestamp );
	$date_posted = date( get_option( 'date_format' ), $timestamp );

	//$item_message = strip_tags( $action ) . ": \n";
	$item_message =  "<div class=\"digest-item\" {$ass_email_css['item_div']}>";
	$item_message .=  "<span class=\"digest-item-action\" {$ass_email_css['item_action']}>" . $action . ": ";
	$item_message .= "<span class=\"digest-item-timestamp\" {$ass_email_css['item_date']}>" . sprintf( __('at %s, %s', 'bp-ass'), $time_posted, $date_posted ) ."</span>";
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

	$item_message .= ' - <a class="digest-item-view-link" href="' . ass_get_login_redirect_url( $view_link ) .'">' . __( 'View', 'bp-ass' ) . '</a>';

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
	global $ass_email_css;
	return '<span ' . $ass_email_css['item_content'] . '>' . htmlentities( strip_tags( $matches[1] ), ENT_COMPAT, 'utf-8' ) . '</span>';
}

/**
 * Formats and sends a MIME multipart email with both HTML and plaintext
 *
 * We have to use some fancy filters from the wp_mail function to configure the $phpmailer object
 * properly
 */
function ass_send_multipart_email( $to, $subject, $message_plaintext, $message ) {
	global $ass_email_css;

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
	$admin_email_filter = create_function( '$admin_email', '
		global $phpmailer;

		$phpmailer->Body    = "";
		$phpmailer->AltBody = "";

		return $admin_email;
	' );

	$from_name = addslashes( $from_name );
	$from_name_filter = create_function( '$from_name', 'return $from_name;' );

	// set the WP email overrides
	add_filter( 'wp_mail_from',      $admin_email_filter );
	add_filter( 'wp_mail_from_name', $from_name_filter );

	// setup plain-text body
	$message_plaintext = addslashes( $message_plaintext );
	add_action( 'phpmailer_init', create_function( '$phpmailer', '
		$phpmailer->AltBody = "' . $message_plaintext . '";
	' ) );

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

	$phpmailer->AltBody = "";

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

	// get the digest/summary items for all groups for this user
	$group_activity_ids = bp_get_user_meta( $user_id, 'ass_digest_items', true );
	if ( ! is_array( $group_activity_ids ) ) {
		$group_activity_ids = array();
	}

	// update multi-dimensional array with the current activity_id
	$group_activity_ids[$type][$group_id][] = $activity_id;

	// re-save it
	bp_update_user_meta( $user_id, 'ass_digest_items', $group_activity_ids );
}


function ass_cron_add_weekly( $schedules ) {
	if ( !isset( $schedules[ 'weekly' ] ) ) {
		$schedules['weekly'] = array( 'interval' => 604800, 'display' => __( 'Once Weekly', 'bp-ass' ) );
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
	if ( 1 !== $blog_id ) {
		switch_to_blog( $blog_id );
		wp_clear_scheduled_hook( 'ass_digest_event' );
	}

	wp_schedule_event( $the_timestamp, 'daily', 'ass_digest_event' );

	// Restore current blog.
	restore_current_blog();

	/* Finally, save the option */
	bp_update_option( 'ass_digest_time', array( 'hours' => $hours, 'minutes' => $minutes ) );
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

	// Custom BP root blog, so set up cron on BP sub-site.
	if ( ! bp_is_root_blog() ) {
		switch_to_blog( bp_get_root_blog_id() );
		wp_clear_scheduled_hook( 'ass_digest_event_weekly' );
	}

	wp_schedule_event( $next_weekly, 'weekly', 'ass_digest_event_weekly' );

	// Restore current blog.
	restore_current_blog();

	/* Finally, save the option */
	bp_update_option( 'ass_weekly_digest', $day );
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
 * Do not use this outside of digests!
 *
 * This is almost a duplicate of bp_core_get_user_domain(), but references
 * our already-fetched mass-userdata array to avoid pinging the DB over and
 * over again in a foreach loop.
 */
function ass_digest_get_user_domain( $user_id ) {
	global $bp;

	if ( empty( $bp->ass->massdata ) )
		return false;

	$mass_userdata = $bp->ass->massdata;

	$username = bp_is_username_compatibility_mode() ? $mass_userdata[ $user_id ]['user_login'] : $mass_userdata[ $user_id ]['user_nicename'];

	if ( bp_core_enable_root_profiles() ) {
		$after_domain = $username;
	} else {
		$after_domain =  bp_get_members_root_slug() . '/';
		$after_domain .= bp_is_username_compatibility_mode() ? rawurlencode( $username ) : $username;
	}

	$domain = trailingslashit( bp_get_root_domain() . '/' . $after_domain );

	$domain = apply_filters( 'bp_core_get_user_domain_pre_cache', $domain, $user_id, $mass_userdata[ $user_id ]['user_nicename'], $mass_userdata[ $user_id ]['user_login'] );

	return apply_filters( 'bp_core_get_user_domain', $domain, $user_id, $mass_userdata[ $user_id ]['user_nicename'], $mass_userdata[ $user_id ]['user_login'] );
}

// if the WP_Better_Emails plugin is installed, don't wrap the message with <html><body>$message</body></html>
function ass_digest_support_wp_better_emails( $message, $message_pre_html_wrap ) {
    if ( class_exists( 'WP_Better_Emails' ) ) {
        $message = $message_pre_html_wrap;
    }

    return $message;
}
add_filter( 'ass_digest_message_html', 'ass_digest_support_wp_better_emails', 10, 2 );
