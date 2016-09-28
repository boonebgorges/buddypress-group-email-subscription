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