<?php
/**
 * GES code meant to be run only on group pages.
 *
 * @since 3.7.0
 */

// show group subscription settings on the notification page.
function ass_group_subscribe_settings () {
	global $bp;

	$group = groups_get_current_group();

	if ( !is_user_logged_in() || !empty( $group->is_banned ) || !$group->is_member )
		return false;

	$group_status = ass_get_group_subscription_status( bp_loggedin_user_id(), $group->id );

	$submit_link = bp_get_groups_action_link( 'notifications' );

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

	<?php if ( ass_get_forum_type() ) : ?>
		<div class="ass-email-type">
		<label><input type="radio" name="ass_group_subscribe" value="sub" <?php if ( $group_status == "sub" ) echo 'checked="checked"'; ?>><?php _e('New Topics Email', 'bp-ass'); ?></label>
		<div class="ass-email-explain"><?php _e('Send new topics as they arrive (but don\'t send replies)', 'bp-ass'); ?></div>
		</div>
	<?php endif; ?>

	<div class="ass-email-type">
	<label><input type="radio" name="ass_group_subscribe" value="supersub" <?php if ( $group_status == "supersub" ) echo 'checked="checked"'; ?>><?php _e('All Email', 'bp-ass'); ?></label>
	<div class="ass-email-explain"><?php _e('Send all group activity as it arrives', 'bp-ass'); ?></div>
	</div>

	<input type="submit" value="<?php _e('Save Settings', 'bp-ass') ?>" id="ass-save" name="ass-save" class="button-primary">

	<?php if ( ass_get_forum_type() == 'buddypress' ) : ?>
		<p class="ass-sub-note"><?php _e('Note: Normally, you receive email notifications for topics you start or comment on. This can be changed at', 'bp-ass'); ?> <a href="<?php echo bp_loggedin_user_domain() . BP_SETTINGS_SLUG . '/notifications/' ?>"><?php _e('email notifications', 'bp-ass'); ?></a>.</p>
	<?php endif; ?>

	</form>
	</div><!-- end ass-email-subscriptions-options-page -->
	<?php
}

// update the users' notification settings
function ass_update_group_subscribe_settings() {
	global $bp;

	if ( bp_is_groups_component() && bp_is_current_action( 'notifications' ) ) {

		// If the edit form has been submitted, save the edited details
		if ( isset( $_POST['ass-save'] ) ) {

			//if ( !wp_verify_nonce( $nonce, 'ass_subscribe' ) ) die( 'A Security check failed' );

			$user_id = bp_loggedin_user_id();
			$group_id = $_POST[ 'ass_group_id' ];
			$action = $_POST[ 'ass_group_subscribe' ];

			if ( !groups_is_user_member( $user_id, $group_id ) )
				return;

			ass_group_subscription( $action, $user_id, $group_id ); // save the settings

			bp_core_add_message( sprintf( __( 'Your email notifications are set to %s for this group.', 'bp-ass' ), ass_subscribe_translate( $action ) ) );
			bp_core_redirect( trailingslashit( bp_get_group_permalink( groups_get_current_group() ) . 'notifications' ) );
		}
	}
}
add_action( 'bp_actions', 'ass_update_group_subscribe_settings' );

// this adds the ajax-based subscription option in the group header, or group directory
function ass_group_subscribe_button() {
	global $bp, $groups_template;

	if( ! empty( $groups_template ) ) {
		$group =& $groups_template->group;
	}
	else {
		$group = groups_get_current_group();
	}

	if ( !is_user_logged_in() || !empty( $group->is_banned ) || !$group->is_member )
		return;

	// if we're looking at someone elses list of groups hide the subscription
	if ( bp_displayed_user_id() && ( bp_loggedin_user_id() != bp_displayed_user_id() ) )
		return;

	$group_status = ass_get_group_subscription_status( bp_loggedin_user_id(), $group->id );

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

		<?php if ( ass_get_forum_type() ) : ?>
			<a class="group-sub" id="sub-<?php echo $group->id; ?>"><?php _e('New Topics', 'bp-ass') ?></a> <?php _e('Send new topics as they arrive (but no replies)', 'bp-ass') ?><br>
		<?php endif; ?>

		<a class="group-sub" id="supersub-<?php echo $group->id; ?>"><?php _e('All Email', 'bp-ass') ?></a> <?php _e('Send all group activity as it arrives', 'bp-ass') ?><br>
		<a class="group-subscription-close" id="gsubclose-<?php echo $group->id; ?>"><?php _e('close', 'bp-ass') ?></a>
	</div>

	<?php
}
add_action ( 'bp_group_header_meta', 'ass_group_subscribe_button' );
add_action ( 'bp_directory_groups_actions', 'ass_group_subscribe_button' );
//add_action ( 'bp_directory_groups_item', 'ass_group_subscribe_button' );  //useful to put in different location with css abs pos

// give the user a notice if they are default subscribed to this group (does not work for invites or requests)
function ass_join_group_message( $group_id, $user_id ) {
	global $bp;

	if ( $user_id != bp_loggedin_user_id()  )
		return;

	$status = apply_filters( 'ass_default_subscription_level', groups_get_groupmeta( $group_id, 'ass_default_subscription' ), $group_id );

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
			<?php _e( 'No Email (users will read this group on the web - good for any group)', 'bp-ass' ) ?></label>
		<label><input type="radio" name="ass-default-subscription" value="sum" <?php ass_default_subscription_settings( 'sum' ) ?> />
			<?php _e( 'Weekly Summary Email (the week\'s topics - good for large groups)', 'bp-ass' ) ?></label>
		<label><input type="radio" name="ass-default-subscription" value="dig" <?php ass_default_subscription_settings( 'dig' ) ?> />
			<?php _e( 'Daily Digest Email (all daily activity bundles in one email - good for medium-size groups)', 'bp-ass' ) ?></label>

		<?php if ( ass_get_forum_type() ) : ?>
			<label><input type="radio" name="ass-default-subscription" value="sub" <?php ass_default_subscription_settings( 'sub' ) ?> />
			<?php _e( 'New Topics Email (new topics are sent as they arrive, but not replies - good for small groups)', 'bp-ass' ) ?></label>
		<?php endif; ?>

		<label><input type="radio" name="ass-default-subscription" value="supersub" <?php ass_default_subscription_settings( 'supersub' ) ?> />
			<?php _e( 'All Email (send emails about everything - recommended only for working groups)', 'bp-ass' ) ?></label>
	</div>
	<hr />
	<?php
}
add_action ( 'bp_after_group_settings_admin' ,'ass_default_subscription_settings_form' );
add_action ( 'bp_after_group_settings_creation_step' ,'ass_default_subscription_settings_form' );

// show group email subscription status on group member pages (for admins and mods only)
function ass_show_subscription_status_in_member_list( $user_id='' ) {
	global $bp, $members_template;

	$group_id = bp_get_current_group_id();

	if ( groups_is_user_admin( bp_loggedin_user_id() , $group_id ) || groups_is_user_mod( bp_loggedin_user_id() , $group_id ) || is_super_admin() ) {
		if ( !$user_id )
			$user_id = $members_template->member->user_id;
		$sub_type = ass_get_group_subscription_status( $user_id, $group_id );
		echo '<div class="ass_members_status">'.__('Email status:','bp-ass'). ' ' . ass_subscribe_translate( $sub_type ) . '</div>';
	}
}
add_action( 'bp_group_members_list_item_action', 'ass_show_subscription_status_in_member_list', 100 );

// make the change to the users' email status based on the function above
function ass_manage_members_email_update() {
	global $bp;

	if ( bp_is_groups_component() && bp_is_action_variable( 'manage-members', 0 ) ) {

		if ( !$bp->is_item_admin )
			return false;

		if ( bp_is_action_variable( 'email', 1 ) && ( bp_is_action_variable( 'no', 2 ) || bp_is_action_variable( 'sum', 2 ) || bp_is_action_variable( 'dig', 2 ) || bp_is_action_variable( 'sub', 2 ) || bp_is_action_variable( 'supersub', 2 ) ) && isset( $bp->action_variables[3] ) && is_numeric( $bp->action_variables[3] ) ) {

			$user_id = $bp->action_variables[3];
			$action = $bp->action_variables[2];

			/* Check the nonce first. */
			if ( !check_admin_referer( 'ass_member_email_status' ) )
				return false;

			ass_group_subscription( $action, $user_id, bp_get_current_group_id() );
			bp_core_add_message( __( 'User email status changed successfully', 'bp-ass' ) );
			bp_core_redirect( bp_get_group_permalink( $bp->groups->current_group ) . 'admin/manage-members/' );
		}
	}
}
add_action( 'bp_actions', 'ass_manage_members_email_update' );

// Site admin can change the email settings for ALL users in a group
function ass_change_all_email_sub() {
	global $groups_template, $bp;

	if ( !is_super_admin() )
		return false;

	$group = &$groups_template->group;

	if (! $default_email_sub = ass_get_default_subscription( $group ) )
		$default_email_sub = 'no';

	echo '<p><br>'.__('Site Admin Only: update email subscription settings for ALL members to the default:', 'bp-ass').' <i>' . ass_subscribe_translate( $default_email_sub ) . '</i>.  '.__('Warning: this is not reversible so use with caution.', 'bp-ass').' <a href="' . wp_nonce_url( bp_get_group_permalink( $group ) . 'admin/manage-members/email-all/'. $default_email_sub, 'ass_change_all_email_sub' ) . '">'.__('Make it so!', 'bp-ass').'</a></p>';
}
add_action( 'bp_after_group_manage_members_admin', 'ass_change_all_email_sub' );

// change all users' email status based on the function above
function ass_manage_all_members_email_update() {
	global $bp;

	if ( bp_is_groups_component() && bp_is_action_variable( 'manage-members', 0 ) ) {

		if ( !is_super_admin() )
			return false;

		$action = bp_action_variable( 2 );

		if ( bp_is_action_variable( 'email-all', 1 ) && ( 'no' == $action || 'sum' == $action || 'dig' == $action || 'sub' == $action || 'supersub' == $action ) ) {

			if ( !check_admin_referer( 'ass_change_all_email_sub' ) )
				return false;

			$result = BP_Groups_Member::get_all_for_group( bp_get_current_group_id(), 0, 0, 0 ); // set the last value to 1 to exclude admins
			$members = $result['members'];

			foreach ( $members as $member ) {
				ass_group_subscription( $action, $member->user_id, bp_get_current_group_id() );
			}

			bp_core_add_message( __( 'All user email status\'s changed successfully', 'bp-ass' ) );
			bp_core_redirect( bp_get_group_permalink( groups_get_current_group() ) . 'admin/manage-members/' );
		}
	}
}
add_action( 'bp_actions', 'ass_manage_all_members_email_update' );

// create a form that allows admins to email everyone in the group
function ass_admin_notice_form() {
	global $bp;

	if ( groups_is_user_admin( bp_loggedin_user_id() , bp_get_current_group_id() ) || is_super_admin() ) {
		$submit_link = bp_get_groups_action_link( 'notifications' );
		?>
		<form action="<?php echo $submit_link ?>" method="post">
			<?php wp_nonce_field( 'ass_email_options' ); ?>

			<h3><?php _e('Send an email notice to everyone in the group', 'bp-ass'); ?></h3>
			<p><?php _e('You can use the form below to send an email notice to all group members.', 'bp-ass'); ?> <br>
			<b><?php _e('Everyone in the group will receive the email -- regardless of their email settings -- so use with caution', 'bp-ass'); ?></b>.</p>

			<p>
				<label for="ass-admin-notice-subject"><?php _e('Email Subject:', 'bp-ass') ?></label>
				<input type="text" name="ass_admin_notice_subject" id="ass-admin-notice-subject" value="" />
			</p>

			<p>
				<label for="ass-admin-notice-textarea"><?php _e('Email Content:', 'bp-ass') ?></label>
				<textarea value="" name="ass_admin_notice" id="ass-admin-notice-textarea"></textarea>
			</p>

			<p>
				<input type="submit" name="ass_admin_notice_send" value="<?php _e('Email this notice to everyone in the group', 'bp-ass') ?>" />
			</p>

			<br />

			<?php $welcome_email = groups_get_groupmeta( bp_get_current_group_id(), 'ass_welcome_email' ); ?>
			<?php $welcome_email_enabled = isset( $welcome_email['enabled'] ) ? $welcome_email['enabled'] : ''; ?>

			<h3><?php _e( 'Welcome Email', 'bp-ass' ); ?></h3>
			<p><?php _e( 'Send an email when a new member join the group.', 'bp-ass' ); ?></p>

			<p>
				<label>
					<input<?php checked( $welcome_email_enabled, 'yes' ); ?> type="checkbox" name="ass_welcome_email[enabled]" id="ass-welcome-email-enabled" value="yes" />
					<?php _e( 'Enable welcome email', 'bp-ass' ); ?>
				</label>
			</p>

			<p class="ass-welcome-email-field<?php if ( $welcome_email_enabled != 'yes' ) echo ' hide-if-js'; ?>">
				<label for="ass-welcome-email-subject"><?php _e( 'Email Subject:', 'bp-ass' ); ?></label>
				<input value="<?php echo isset( $welcome_email['subject'] ) ? $welcome_email['subject'] : ''; ?>" type="text" name="ass_welcome_email[subject]" id="ass-welcome-email-subject" />
			</p>

			<p class="ass-welcome-email-field<?php if ( $welcome_email_enabled != 'yes' ) echo ' hide-if-js'; ?>">
				<label for="ass-welcome-email-content"><?php _e( 'Email Content:', 'bp-ass'); ?></label>
				<textarea name="ass_welcome_email[content]" id="ass-welcome-email-content"><?php echo isset( $welcome_email['content'] ) ? $welcome_email['content'] : ''; ?></textarea>
			</p>

			<p>
				<input type="submit" name="ass_welcome_email_submit" value="<?php _e( 'Save', 'bp-ass' ); ?>" />
			</p>
		</form>
		<?php
	}
}

// This function sends an email out to all group members regardless of subscription status.
// TODO: change this function so the separate from is remove from the admin area and make it a checkbox under the 'add new topic' form. that way group admins can simply check off the box and it'll go to everyone. The benefit: notices are stored in the discussion form for later viewing. We should also alert the admin just how many people will get his post.
function ass_admin_notice() {
    if ( bp_is_groups_component() && bp_is_current_action( 'admin' ) && bp_is_action_variable( 'notifications', 0 ) ) {

	    	// Make sure the user is an admin
		if ( !groups_is_user_admin( bp_loggedin_user_id(), bp_get_current_group_id() ) && ! is_super_admin() )
			return;

		if ( get_option('ass-admin-can-send-email') == 'no' )
			return;

		// make sure the correct form variables are here
		if ( ! isset( $_POST[ 'ass_admin_notice_send' ] ) )
			return;

		if ( empty( $_POST[ 'ass_admin_notice' ] ) ) {
			bp_core_add_message( __( 'The email notice was sent not sent. Please enter email content.', 'bp-ass' ), 'error' );
		} else {
			$group      = groups_get_current_group();
			$group_id   = $group->id;
			$group_name = bp_get_current_group_name();
			$group_link = bp_get_group_permalink( $group );

			if ( $group->status != 'public' ) {
				$group_link = ass_get_login_redirect_url( $group_link, 'admin_notice' );
			}

			$blogname   = '[' . get_blog_option( BP_ROOT_BLOG, 'blogname' ) . ']';
			$subject    = $_POST[ 'ass_admin_notice_subject' ];
			$subject   .= __(' - sent from the group ', 'bp-ass') . $group_name . ' ' . $blogname;
			$subject    = apply_filters( 'ass_admin_notice_subject', $subject, $_POST[ 'ass_admin_notice_subject' ], $group_name, $blogname );
			$subject    = ass_clean_subject( $subject, false );
			$notice     = apply_filters( 'ass_admin_notice_message', $_POST['ass_admin_notice'] );
			$notice     = ass_clean_content( $notice );

			$message    = sprintf( __(
'This is a notice from the group \'%s\':

"%s"


To view this group log in and follow the link below:
%s

---------------------
', 'bp-ass' ), $group_name,  $notice, $group_link );

			$message .= __( 'Please note: admin notices are sent to everyone in the group and cannot be disabled.
If you feel this service is being misused please speak to the website administrator.', 'bp-ass' );

			$user_ids = BP_Groups_Member::get_group_member_ids( $group_id );

			// allow others to perform an action when this type of email is sent, like adding to the activity feed
			do_action( 'ass_admin_notice', $group_id, $subject, $notice );

			// cycle through all group members
			foreach ( (array)$user_ids as $user_id ) {
				$user = bp_core_get_core_userdata( $user_id ); // Get the details for the user

				if ( $user->user_email )
					wp_mail( $user->user_email, $subject, $message );  // Send the email

				//echo '<br>Email: ' . $user->user_email;
			}

			bp_core_add_message( __( 'The email notice was sent successfully.', 'bp-ass' ) );
			//echo '<p>Subject: ' . $subject;
			//echo '<pre>'; print_r( $message ); echo '</pre>';
		}

		bp_core_redirect( bp_get_group_permalink( groups_get_current_group() ) . 'admin/notifications/' );
	}
}
add_action( 'bp_actions', 'ass_admin_notice', 1 );

// save welcome email option
function ass_save_welcome_email() {
	if ( bp_is_groups_component() && bp_is_current_action( 'admin' ) && bp_is_action_variable( 'notifications', 0 ) ) {

		if ( ! isset( $_POST['ass_welcome_email_submit'] ) )
			return;

		if ( ! groups_is_user_admin( bp_loggedin_user_id(), bp_get_current_group_id() ) && ! is_super_admin() )
			return;

		check_admin_referer( 'ass_email_options' );

		$values = stripslashes_deep( $_POST['ass_welcome_email'] );
		groups_update_groupmeta( bp_get_current_group_id(), 'ass_welcome_email', $values );

		bp_core_add_message( __( 'The welcome email option has been saved.', 'bp-ass' ) );
		bp_core_redirect( bp_get_group_permalink( groups_get_current_group() ) . 'admin/notifications/' );
	}
}
add_action( 'bp_actions', 'ass_save_welcome_email', 1 );

/** BBPRESS *************************************************************/

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
	// make sure we're on a BP group page
	if ( bp_is_group() ) {
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

	return $retval;
}
add_filter( 'bbp_is_subscriptions_active', 'ass_bbp_subscriptions' );

/**
 * Stuff to do when the bbPress plugin is ready.
 *
 * @since 3.4.1
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
	// make sure we're on a BP group page
	if ( bp_is_group() ) {
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
	// add our filter to check topic subscribers
	add_filter( 'bbp_get_topic_subscribers', 'ass_bbp_remove_topic_subscribers' );

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
	remove_filter( 'bbp_get_topic_subscribers', 'ass_bbp_remove_topic_subscribers' );
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
	// make sure we're on a BP group page
	if ( bp_is_group() ) {
		global $bp;

		// get group sub status
		// we're using the direct, global reference for sanity's sake
		$group_status = ass_get_group_subscription_status( $user_id, $bp->groups->current_group->id );

		// if user's group sub status is "All Mail", stop bbP's email from sending
		// by blanking out the message
		if ( $group_status == 'supersub' ) {
			return false;
		}
	}

	return $message;
}
