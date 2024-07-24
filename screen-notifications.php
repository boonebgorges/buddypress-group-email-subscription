<?php
/**
 * GES code meant to be run only on a group's "Email Options" page.
 *
 * @since 3.7.0
 */

// show group subscription settings on the notification page.
function ass_group_subscribe_settings() {
	global $bp;

	$group = groups_get_current_group();

	if ( ! is_user_logged_in() || ! empty( $group->is_banned ) || ! $group->is_member ) {
		return false;
	}

	$submit_link = bp_get_groups_action_link( 'notifications' );

	$settings_link = bp_loggedin_user_url( bp_members_get_path_chunks( array( bp_get_settings_slug(), 'notifications' ) ) );

	?>
	<div id="ass-email-subscriptions-options-page">
	<h3 class="activity-subscription-settings-title"><?php esc_html_e( 'Email Subscription Options', 'buddypress-group-email-subscription' ); ?></h3>
	<form action="<?php echo esc_attr( $submit_link ); ?>" method="post">
	<input type="hidden" name="ass_group_id" value="<?php echo esc_attr( $group->id ); ?>"/>

	<?php wp_nonce_field( 'bpges_subscribe', 'bpges-subscribe-nonce' ); ?>

	<b><?php esc_html_e( 'How do you want to read this group?', 'buddypress-group-email-subscription' ); ?></b>

	<?php bp_get_template_part( 'bpges/subscription-options-group' ); ?>

	<input type="submit" value="<?php esc_attr_e( 'Save Settings', 'buddypress-group-email-subscription' ); ?>" id="ass-save" name="ass-save" class="button-primary">

	<?php if ( 'buddypress' === ass_get_forum_type() ) : ?>
		<p class="ass-sub-note"><?php esc_html_e( 'Note: Normally, you receive email notifications for topics you start or comment on. This can be changed at', 'buddypress-group-email-subscription' ); ?> <a href="<?php echo esc_url( $settings_link ); ?>"><?php esc_html_e( 'email notifications', 'buddypress-group-email-subscription' ); ?></a>.</p>
	<?php endif; ?>

	</form>
	</div><!-- end ass-email-subscriptions-options-page -->
	<?php
}

// update the users' notification settings
function ass_update_group_subscribe_settings() {
	global $bp;

	if ( ! bp_is_groups_component() || ! bp_is_current_action( 'notifications' ) ) {
		return;
	}

	if ( empty( $_POST['bpges-subscribe-nonce'] ) ) {
		return;
	}

	check_admin_referer( 'bpges_subscribe', 'bpges-subscribe-nonce' );

	// If the edit form has been submitted, save the edited details
	if ( isset( $_POST['ass-save'] ) ) {

		$user_id  = bp_loggedin_user_id();
		$group_id = isset( $_POST['ass_group_id'] ) ? (int) $_POST['ass_group_id'] : 0;
		$action   = isset( $_POST['ass_group_subscribe'] ) ? sanitize_text_field( wp_unslash( $_POST['ass_group_subscribe'] ) ) : '';

		if ( ! $group_id || ! $action ) {
			return;
		}

		if ( ! groups_is_user_member( $user_id, $group_id ) ) {
			return;
		}

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
add_action( 'bp_actions', 'ass_update_group_subscribe_settings' );
