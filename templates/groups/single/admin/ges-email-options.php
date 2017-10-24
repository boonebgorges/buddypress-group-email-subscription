<?php
/**
 * Filter to display the email notice form on the "Manage > Email Options page".
 *
 * @since 3.7.2
 *
 * @param  bool $retval Defaults to true.
 * @return bool
 */
$enable_email_notice = apply_filters( 'bp_group_email_subscription_enable_email_notice', true );
if ( true === $enable_email_notice ) {
?>

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

<?php
}

/**
 * Filter to display the welcome email form on the "Manage > Email Options page".
 *
 * @since 3.7.2
 *
 * @param  bool $retval Defaults to true.
 * @return bool
 */
$enable_welcome = apply_filters( 'bp_group_email_subscription_enable_welcome_email', true );
if ( true === $enable_welcome ) {
?>

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

<?php
}

/**
 * If plugins are adding custom content to this page and we have hidden both
 * the Email Notice and Welcome Email options, make sure BP's Group Extension
 * API doesn't inject another submit button.
 *
 * To fool BP, we add a hidden submit button.
 *
 * @see BP_Group_Extension::maybe_add_submit_button()
 */
if ( ! $enable_email_notice && ! $enable_welcome ) {
	echo '<input type="submit" style="display:none" />';
}
