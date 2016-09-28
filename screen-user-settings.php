<?php
/**
 * GES code meant to be run only on a user's "Settings > Email" page.
 *
 * @since 3.7.0
 */

// adds forum notification options in the users settings->notifications page
function ass_group_subscription_notification_settings() {
	// get forum type
	$forums = ass_get_forum_type();

	// no forums installed? stop now!
	if ( ! $forums ) {
		return;
	}

?>
	<table class="notification-settings zebra" id="groups-subscription-notification-settings">
	<thead>
		<tr>
			<th class="icon"></th>
			<th class="title"><?php _e( 'Group Forum', 'bp-ass' ) ?></th>
			<th class="yes"><?php _e( 'Yes', 'bp-ass' ) ?></th>
			<th class="no"><?php _e( 'No', 'bp-ass' )?></th>
		</tr>
	</thead>
	<tbody>

	<?php
		// only add the following options if BP's bundled forums are installed...
		// @todo add back these options for bbPress if possible.
	?>

	<?php if ( $forums == 'buddypress' ) :
		if ( ! $replies_to_topic = bp_get_user_meta( bp_displayed_user_id(), 'ass_replies_to_my_topic', true ) ) {
			$replies_to_topic = 'yes';
		}

		if ( ! $replies_after_me = bp_get_user_meta( bp_displayed_user_id(), 'ass_replies_after_me_topic', true ) ) {
			$replies_after_me = 'yes';
		}
	?>

		<tr>
			<td></td>
			<td><?php _e( 'A member replies in a forum topic you\'ve started', 'bp-ass' ) ?></td>
			<td class="yes"><input type="radio" name="notifications[ass_replies_to_my_topic]" value="yes" <?php checked( $replies_to_topic, 'yes', true ); ?>/></td>
			<td class="no"><input type="radio" name="notifications[ass_replies_to_my_topic]" value="no" <?php checked( $replies_to_topic, 'no', true ); ?>/></td>
		</tr>

		<tr>
			<td></td>
			<td><?php _e( 'A member replies after you in a forum topic', 'bp-ass' ) ?></td>
			<td class="yes"><input type="radio" name="notifications[ass_replies_after_me_topic]" value="yes" <?php checked( $replies_after_me, 'yes', true ); ?>/></td>
			<td class="no"><input type="radio" name="notifications[ass_replies_after_me_topic]" value="no" <?php checked( $replies_after_me, 'no', true ); ?>/></td>
		</tr>

	<?php endif; ?>

		<tr>
			<td></td>
			<td><?php _e( 'Receive notifications of your own posts?', 'bp-ass' ) ?></td>
			<td class="yes"><input type="radio" name="notifications[ass_self_post_notification]" value="yes" <?php if ( ass_self_post_notification( bp_displayed_user_id() ) ) { ?>checked="checked" <?php } ?>/></td>
			<td class="no"><input type="radio" name="notifications[ass_self_post_notification]" value="no" <?php if ( !ass_self_post_notification( bp_displayed_user_id() ) ) { ?>checked="checked" <?php } ?>/></td>
		</tr>

		<?php do_action( 'ass_group_subscription_notification_settings' ); ?>
		</tbody>
	</table>


<?php
}
add_action( 'bp_notification_settings', 'ass_group_subscription_notification_settings' );

// Add a notice at end of email notification about how to change group email subscriptions
function ass_add_notice_to_notifications_page() {
?>
		<div id="group-email-settings">
			<table class="notification-settings zebra">
				<thead>
					<tr>
						<th class="icon">&nbsp;</th>
						<th class="title"><?php _e( 'Individual Group Email Settings', 'bp-ass' ); ?></th>
					</tr>
				</thead>

				<tbody>
					<tr>
						<td>&nbsp;</td>
						<td>
							<p><?php printf( __('To change the email notification settings for your groups, go to %s and click "Change" for each group.', 'bp-ass' ), '<a href="'. bp_loggedin_user_domain() . trailingslashit( BP_GROUPS_SLUG ) . '">' . __( 'My Groups' ,'bp-ass' ) . '</a>' ); ?></p>

							<?php if ( get_option( 'ass-global-unsubscribe-link' ) == 'yes' ) : ?>
								<p><a href="<?php echo wp_nonce_url( add_query_arg( 'ass_unsubscribe', 'all' ), 'ass_unsubscribe_all' ); ?>"><?php _e( "Or set all your group's email options to No Email", 'bp-ass' ); ?></a></p>
							<?php endif; ?>
						</td>
					</tr>
				</tbody>
			</table>
		</div>
<?php
}
add_action( 'bp_notification_settings', 'ass_add_notice_to_notifications_page', 9000 );