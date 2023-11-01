<?php
/**
 * GES code meant to be run only on frontend group administration pages.
 *
 * @since 3.7.0
 */

// create the default subscription settings during group creation and editing
function ass_default_subscription_settings_form() {
	?>
	<h4><?php _e('Email Subscription Defaults', 'buddypress-group-email-subscription'); ?></h4>
	<p><?php _e('When new users join this group, their default email notification settings will be:', 'buddypress-group-email-subscription'); ?></p>
	<div class="radio ass-email-subscriptions-options">
		<?php bp_get_template_part( 'bpges/subscription-options-admin' ); ?>
	</div>
	<hr />
	<?php
}
add_action ( 'bp_after_group_settings_admin' ,'ass_default_subscription_settings_form' );
add_action ( 'bp_after_group_settings_creation_step' ,'ass_default_subscription_settings_form' );

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
			bp_core_add_message( __( 'User email status changed successfully', 'buddypress-group-email-subscription' ) );

			$redirect_url = bp_get_group_manage_url(
				bp_get_current_group_id(),
				bp_groups_get_path_chunks( array( 'manage-members' ), 'manage' )
			);

			bp_core_redirect( $redirect_url );
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

	$url = bp_get_group_manage_url(
		$group,
		bp_groups_get_path_chunks( array( 'manage-members', 'email-all', $default_email_sub ) )
	);

	echo '<p><br>'.__('Site Admin Only: update email subscription settings for ALL members to the default:', 'buddypress-group-email-subscription').' <i>' . ass_subscribe_translate( $default_email_sub ) . '</i>.  '.__('Warning: this is not reversible so use with caution.', 'buddypress-group-email-subscription').' <a href="' . wp_nonce_url( $url, 'ass_change_all_email_sub' ) . '">'.__('Make it so!', 'buddypress-group-email-subscription').'</a></p>';
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

			$result = groups_get_group_members( array(
				'group_id' => bp_get_current_group_id(),
				'per_page' => 0,
				'exclude_admins_mods' => false
			) );
			$members = $result['members'];

			foreach ( $members as $member ) {
				ass_group_subscription( $action, $member->user_id, bp_get_current_group_id() );
			}

			bp_core_add_message( __( 'All user email status\'s changed successfully', 'buddypress-group-email-subscription' ) );

			$redirect_url = bp_get_group_manage_url(
				bp_get_current_group_id(),
				bp_groups_get_path_chunks( array( 'manage-members' ), 'manage' )
			);

			bp_core_redirect( $redirect_url );
		}
	}
}
add_action( 'bp_actions', 'ass_manage_all_members_email_update' );

/**
 * Options displayed on a group's "Manage > Email Options" page.
 *
 * This page is only visible to group admins and when the "Allow group admins
 * to override subscription settings and send an email to everyone in their
 * group" is enabled in the GES admin settings page.
 *
 * @since 2.1b2
 */
function ass_admin_notice_form() {
	if ( ! groups_is_user_admin( bp_loggedin_user_id(), bp_get_current_group_id() ) && ! bp_current_user_can( 'bp_moderate' ) ) {
		return;
	}

	bp_get_template_part( 'groups/single/admin/ges-email-options' );
}

// This function sends an email out to all group members regardless of subscription status.
// TODO: change this function so the separate from is remove from the admin area and make it a checkbox under the 'add new topic' form. that way group admins can simply check off the box and it'll go to everyone. The benefit: notices are stored in the discussion form for later viewing. We should also alert the admin just how many people will get his post.
function ass_admin_notice() {
	if ( ! bp_is_groups_component() || ! bp_is_current_action( 'admin' ) || ! bp_is_action_variable( 'notifications', 0 ) ) {
		return;
	}

	// Make sure the user is an admin
	if ( ! groups_is_user_admin( bp_loggedin_user_id(), bp_get_current_group_id() ) && ! is_super_admin() ) {
		return;
	}

	if ( get_option( 'ass-admin-can-send-email' ) == 'no' ) {
		return;
	}

	// make sure the correct form variables are here
	if ( ! isset( $_POST['ass_admin_notice_send'] ) ) {
		return;
	}

	check_admin_referer( 'bpges_admin_notice', 'bpges-admin-notice-nonce' );

	if ( empty( $_POST[ 'ass_admin_notice' ] ) ) {
		bp_core_add_message( __( 'The email notice was not sent. Please enter email content.', 'buddypress-group-email-subscription' ), 'error' );
	} else {
		$group      = groups_get_current_group();
		$group_id   = $group->id;
		$group_name = bp_get_current_group_name();
		$group_link = bp_get_group_url( $group );

		if ( $group->status != 'public' ) {
			$group_link = ass_get_login_redirect_url( $group_link, 'admin_notice' );
		}

		$blogname   = '[' . get_blog_option( BP_ROOT_BLOG, 'blogname' ) . ']';

		$_subject   = $_POST[ 'ass_admin_notice_subject' ];
		$subject    = $_subject . __(' - sent from the group ', 'buddypress-group-email-subscription') . $group_name . ' ' . $blogname;
		$subject    = apply_filters( 'ass_admin_notice_subject', $subject, $_POST[ 'ass_admin_notice_subject' ], $group_name, $blogname );
		$subject    = ass_clean_subject( $subject, false );
		$notice     = apply_filters( 'ass_admin_notice_message', $_POST['ass_admin_notice'] );

		$message    = sprintf( __(
'This is a notice from the group \'%s\':

"%s"


To view this group log in and follow the link below:
%s

---------------------
', 'buddypress-group-email-subscription' ), $group_name,  $notice, $group_link );

		if ( bpges_force_immediate_admin_notice() ) {
			$message .= __( 'Please note: admin notices are sent to everyone in the group and cannot be disabled.
	If you feel this service is being misused please speak to the website administrator.', 'buddypress-group-email-subscription' );
		}

		$user_ids = BP_Groups_Member::get_group_member_ids( $group_id );

		add_filter( 'bp_ass_send_activity_notification_for_user', '__return_true' );
		add_filter( 'bp_ges_add_to_digest_queue_for_user', '__return_false' );

		// Fake it.
		$_a = new stdClass;
		$_a->item_id = $group_id;
		$_a->user_id = bp_loggedin_user_id();
		$action = bpges_format_activity_action_bpges_notice( '', $_a, $_subject );

		// We must delay the sending of notifications so that we can save the 'subject' meta.
		remove_action( 'bp_activity_after_save' , 'ass_group_notification_activity' , 50 );
		$activity_id = bp_activity_add(
			array(
				'component'     => buddypress()->groups->id,
				'type'          => 'bpges_notice',
				'content'       => $notice,
				'item_id'       => $group_id,
				'action'        => $action,
				'hide_sitewide' => 'public' !== $group->status,
			)
		);
		add_action( 'bp_activity_after_save' , 'ass_group_notification_activity' , 50 );

		remove_filter( 'bp_ass_send_activity_notification_for_user', '__return_true' );
		remove_filter( 'bp_ges_remove_to_digest_queue_for_user', '__return_false' );

		if ( ! $activity_id ) {
			return;
		}

		// Store subject for later use.
		bp_activity_add_meta( $activity_id, 'bpges_notice_subject', $_subject );

		$activity = new BP_Activity_Activity( $activity_id );
		ass_group_notification_activity( $activity );

		do_action( 'ass_admin_notice', $group_id, $_subject, $notice );

		bp_core_add_message( __( 'The email notice was sent successfully.', 'buddypress-group-email-subscription' ) );
	}

	$redirect_url = bp_get_group_manage_url(
		bp_get_current_group_id(),
		bp_groups_get_path_chunks( array( 'notifications' ), 'manage' )
	);

	bp_core_redirect( $redirect_url );
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

		bp_core_add_message( __( 'The welcome email option has been saved.', 'buddypress-group-email-subscription' ) );

		$redirect_url = bp_get_group_manage_url(
			bp_get_current_group_id(),
			bp_groups_get_path_chunks( array( 'notifications' ), 'manage' )
		);

		bp_core_redirect( $redirect_url );
	}
}
add_action( 'bp_actions', 'ass_save_welcome_email', 1 );
