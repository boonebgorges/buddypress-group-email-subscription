<?php
/**
 * Admin code used by GES.
 *
 * @since 3.7.0
 */

/**
 * Adds "Group Email Options" panel under "Settings" in the admin/network admin.
 *
 * @since 2.1b
 */
function ass_admin_menu() {
	// BP 1.6+ deprecated the "BuddyPress" top-level menu item.
	if ( function_exists( 'bp_version' ) ) {
		// GES is network-activated, so show under Network Settings.
		if ( is_multisite() && is_plugin_active_for_network( plugin_basename( dirname( __FILE__ ) ) . '/bp-activity-subscription.php' ) ) {
			$settings_page = 'settings.php';

		// Everything else.
		} else {
			$settings_page = 'options-general.php';
		}

		$title = __( 'BP Group Email Options', 'bp-ass' );

	// BP 1.5 - Keep using the top-level "BuddyPress" menu item.
	} else {
		$settings_page = 'bp-general-settings';
		$title = __( 'Group Email Options', 'bp-ass' );
	}

	add_submenu_page(
		$settings_page,
		$title,
		$title,
		'manage_options',
		'ass_admin_options',
		'ass_admin_options'
	);
}
add_action( 'admin_menu', 'ass_admin_menu' );
add_action( 'network_admin_menu', 'ass_admin_menu' );

/**
 * Function to create the back end admin form.
 */
function ass_admin_options() {
	//print_r($_POST); die();

	if ( !empty( $_POST ) ) {
		if ( ass_update_dashboard_settings() ) {
			?>

			<div id="message" class="updated">
				<p><?php _e( 'Settings saved.', 'bp-ass' ) ?></p>
			</div>

			<?php
		}
	}

	//set the first time defaults
	if ( !$ass_digest_time = bp_get_option( 'ass_digest_time' ) )
		$ass_digest_time = array( 'hours' => '05', 'minutes' => '00' );

	if ( !$ass_weekly_digest = bp_get_option( 'ass_weekly_digest' ) )
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

		<h3><?php _e( 'Global Unsubscribe Link', 'bp-ass' ); ?></h3>
		<p><?php _e( 'Add a link in the emails and on the notifications settings page allowing users to unsubscribe from all their groups at once:', 'bp-ass' ); ?>
		<?php $global_unsubscribe_link = bp_get_option( 'ass-global-unsubscribe-link' ); ?>
		<input<?php checked( $global_unsubscribe_link, 'yes' ); ?> type="radio" name="ass-global-unsubscribe-link" value="yes"> <?php _e( 'yes', 'bp-ass' ); ?> &nbsp;
		<input<?php checked( $global_unsubscribe_link, '' ); ?> type="radio" name="ass-global-unsubscribe-link" value=""> <?php _e( 'no', 'bp-ass' ); ?>
		<br />
		<br />


		<h3><?php _e('Group Admin Abilities', 'bp-ass'); ?></h3>
		<p><?php _e('Allow group admins and mods to change members\' email subscription settings: ', 'bp-ass'); ?>
		<?php $admins_can_edit_status = bp_get_option('ass-admin-can-edit-email'); ?>
		<input type="radio" name="ass-admin-can-edit-email" value="yes" <?php if ( $admins_can_edit_status == 'yes' || !$admins_can_edit_status ) echo 'checked="checked"'; ?>> <?php _e('yes', 'bp-ass') ?> &nbsp;
		<input type="radio" name="ass-admin-can-edit-email" value="no" <?php if ( $admins_can_edit_status == 'no' ) echo 'checked="checked"'; ?>> <?php _e('no', 'bp-ass') ?>

		<p><?php _e('Allow group admins to override subscription settings and send an email to everyone in their group: ', 'bp-ass'); ?>
		<?php $admins_can_send_email = bp_get_option('ass-admin-can-send-email'); ?>
		<input type="radio" name="ass-admin-can-send-email" value="yes" <?php if ( $admins_can_send_email == 'yes' || !$admins_can_send_email ) echo 'checked="checked"'; ?>> <?php _e('yes', 'bp-ass') ?> &nbsp;
		<input type="radio" name="ass-admin-can-send-email" value="no" <?php if ( $admins_can_send_email == 'no' ) echo 'checked="checked"'; ?>> <?php _e('no', 'bp-ass') ?>

		<br>
		<br>
		<h3><?php _e('Spam Prevention', 'bp-ass'); ?></h3>
			<p><?php _e('To help protect against spam, you may wish to require a user to have been a member of the site for a certain amount of days before any group updates are emailed to the other group members. This is disabled by default.', 'bp-ass'); ?> </p>
			<?php _e('Member must be registered for', 'bp-ass'); ?><input type="text" size="1" name="ass_registered_req" value="<?php echo bp_get_option( 'ass_registered_req' ); ?>" style="text-align:center"/><?php _e('days', 'bp-ass'); ?></p>


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
		<img alt="" border="0" src="https://www.paypal.com/en_US/i/scr/pixel.gif" width="1" height="1" />

	</div>
	<?php
}

/**
 * Save the back-end admin settings.
 */
function ass_update_dashboard_settings() {
	if ( !check_admin_referer( 'ass_admin_settings' ) )
		return;

	if ( !is_super_admin() )
		return;

	/* The daily digest time has been changed */
	if ( $_POST['ass_digest_time'] )
		ass_set_daily_digest_time( $_POST['ass_digest_time']['hours'], $_POST['ass_digest_time']['minutes'] );

	/* The weekly digest day has been changed */
	if ( $_POST['ass_weekly_digest'] )
		ass_set_weekly_digest_time( $_POST['ass_weekly_digest'] );

	if ( $_POST['ass-global-unsubscribe-link'] != bp_get_option( 'ass-global-unsubscribe-link' ) )
		bp_update_option( 'ass-global-unsubscribe-link', $_POST['ass-global-unsubscribe-link'] );

	if ( $_POST['ass-admin-can-edit-email'] != bp_get_option( 'ass-admin-can-edit-email' ) )
		bp_update_option( 'ass-admin-can-edit-email', $_POST['ass-admin-can-edit-email'] );

	if ( $_POST['ass-admin-can-send-email'] != bp_get_option( 'ass-admin-can-send-email' ) )
		bp_update_option( 'ass-admin-can-send-email', $_POST['ass-admin-can-send-email'] );

	if ( $_POST['ass_registered_req'] != bp_get_option( 'ass_registered_req' ) )
		bp_update_option( 'ass_registered_req', $_POST['ass_registered_req'] );

	return true;
	//echo '<pre>'; print_r( $_POST ); echo '</pre>';
}

/**
 * Manage each group member's email subscription settings from the "Groups"
 * dashboard page.
 *
 * Only works in BP 1.8+.
 *
 * @uses ass_manage_members_email_status()
 */
function ass_groups_admin_manage_member_row( $user_id, $group ) {
	ass_manage_members_email_status( $user_id, $group );
}
add_action( 'bp_groups_admin_manage_member_row', 'ass_groups_admin_manage_member_row', 10, 2 );

/**
 * Install GES emails during email installation routine for BuddyPress.
 *
 * @since 3.7.0
 *
 * @param bool $post_exists_check Should we check to see if our email post types exist before installing?
 *                                Default: false.
 */
function ass_install_emails( $post_exists_check = false ) {
	// No need to check if our post types exist.
	if ( ! $post_exists_check ) {
		ass_set_email_type( 'bp-ges-single', false );
		ass_set_email_type( 'bp-ges-digest', false );
		ass_set_email_type( 'bp-ges-notice', false );
		ass_set_email_type( 'bp-ges-welcome', false );

	// Only create email post types if they do not exist.
	} else {
		switch_to_blog( bp_get_root_blog_id() );

		$ges_types = array( 'bp-ges-single', 'bp-ges-digest', 'bp-ges-notice', 'bp-ges-welcome' );

		// Try to fetch email posts with our taxonomy.
		$emails = get_posts( array(
			'fields'           => 'ids',
			'post_status'      => 'publish',
			'post_type'        => bp_get_email_post_type(),
			'posts_per_page'   => 4,
			'suppress_filters' => false,
			'tax_query' => array(
				'relation' => 'OR',
				array(
					'taxonomy' => bp_get_email_tax_type(),
					'field'    => 'slug',
					'terms'    => $ges_types,
				),
			),
		) );

		// See if our taxonomies are attached to our email posts.
		$found = array();
		foreach ( $emails as $post_id ) {
			$tax   = wp_get_object_terms( $post_id, bp_get_email_tax_type(), array( 'fields' => 'slugs' ) );
			$found = array_merge( $found, (array) $tax );
		}

		restore_current_blog();

		// Find out if we need to create any posts.
		$to_create = array_diff( $ges_types, $found );
		if ( empty( $to_create ) ) {
			return;
		}

		// Create posts with our email types.
		if ( ! function_exists( 'ass_set_email_type' ) ) {
			require_once( dirname( __FILE__ ) . '/bp-activity-subscription-functions.php' );
		}
		foreach ( $to_create as $email_type ) {
			ass_set_email_type( $email_type, false );
		}
	}
}
add_action( 'bp_core_install_emails', 'ass_install_emails' );

/**
 * Show admin notice when editing a BP email in the admin dashboard.
 *
 * @since 3.7.0
 */
function ass_bp_email_admin_notice() {
	// Bail if not using BP 2.5 or if not editing a BP email.
	if ( ! function_exists( 'bp_get_email_post_type' ) ) {
		return;
	}
	if ( get_current_screen()->post_type !== bp_get_email_post_type() ) {
		return;
	}

	// Output notice; hidden by default.
	echo '<div id="bp-ges-notice" class="updated" style="display:none;">';
	printf( '<p>%s</p>',
		sprintf(
			__( 'This email is handled by the Group Email Subscription plugin and uses customized tokens.  <a target="_blank" href="%s">Learn more about GES tokens on our wiki</a>.', 'bp-ass' ),
			esc_url( 'https://github.com/boonebgorges/buddypress-group-email-subscription/wiki/Email-Tokens#tokens' )
		)
	);
	echo '</div>';

	// Inline JS.
	$inline_js = <<<EOD

jQuery( function( $ ) {
	$( '#bp-email-typechecklist input:checked' ).each( function() {
		// If current email is used by GES, show our notice.
		if ( $(this).val().lastIndexOf( 'bp-ges-', 0 ) === 0 ) {
			$( '#bp-ges-notice' ).show();
			return false;
		}
	} );
} );

EOD;

	echo "<script type=\"text/javascript\">{$inline_js}</script>";
}
add_action( 'admin_head-post.php', 'ass_bp_email_admin_notice' );
