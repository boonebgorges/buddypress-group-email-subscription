<?php
/**
 * GES code meant to be run only on a group's "Email Options" page.
 *
 * @since 3.7.0
 */

// show group subscription settings on the notification page.
function ass_group_subscribe_settings () {
	global $bp;

	$group = groups_get_current_group();

	if ( !is_user_logged_in() || !empty( $group->is_banned ) || !$group->is_member )
		return false;

	$submit_link = bp_get_groups_action_link( 'notifications' );

	$settings_link = bp_loggedin_user_url( bp_members_get_path_chunks( array( bp_get_settings_slug(), 'notifications' ) ) );

	?>
	<div id="ass-email-subscriptions-options-page">
	<h3 class="activity-subscription-settings-title"><?php _e('Email Subscription Options', 'buddypress-group-email-subscription') ?></h3>
	<form action="<?php echo $submit_link ?>" method="post">
	<input type="hidden" name="ass_group_id" value="<?php echo $group->id; ?>"/>
	<?php wp_nonce_field( 'ass_subscribe' ); ?>

	<b><?php _e('How do you want to read this group?', 'buddypress-group-email-subscription'); ?></b>

	<?php bp_get_template_part( 'bpges/subscription-options-group' ); ?>

	<input type="submit" value="<?php _e('Save Settings', 'buddypress-group-email-subscription') ?>" id="ass-save" name="ass-save" class="button-primary">

	<?php if ( ass_get_forum_type() == 'buddypress' ) : ?>
		<p class="ass-sub-note"><?php _e( 'Note: Normally, you receive email notifications for topics you start or comment on. This can be changed at', 'buddypress-group-email-subscription' ); ?> <a href="<?php echo esc_url( $settings_link ); ?>"><?php _e( 'email notifications', 'buddypress-group-email-subscription' ); ?></a>.</p>
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

			// translators: name of the subscription level
			bp_core_add_message( sprintf( __( 'Your email notifications for this group have been changed to: %s.', 'buddypress-group-email-subscription' ), ass_subscribe_translate( $action ) ) );

			$redirect_url = bp_get_group_url(
				groups_get_current_group(),
				bp_groups_get_path_chunks( array( 'notifications' ) )
			);

			bp_core_redirect( $redirect_url );
		}
	}
}
add_action( 'bp_actions', 'ass_update_group_subscribe_settings' );
