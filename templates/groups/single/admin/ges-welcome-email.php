<?php
$welcome_email         = groups_get_groupmeta( bp_get_current_group_id(), 'ass_welcome_email' );
$welcome_email_enabled = isset( $welcome_email['enabled'] ) ? $welcome_email['enabled'] : '';

$hide_if_js_class = 'yes' === $welcome_email_enabled ? 'hide-if-js' : '';
?>

<h3><?php esc_html_e( 'Welcome Email', 'buddypress-group-email-subscription' ); ?></h3>
<p><?php esc_html_e( 'Send an email when a new member join the group.', 'buddypress-group-email-subscription' ); ?></p>

<p>
	<label>
		<input<?php checked( $welcome_email_enabled, 'yes' ); ?> type="checkbox" name="ass_welcome_email[enabled]" id="ass-welcome-email-enabled" value="yes" />
		<?php esc_html_e( 'Enable welcome email', 'buddypress-group-email-subscription' ); ?>
	</label>
</p>

<p class="ass-welcome-email-field <?php echo esc_attr( $hide_if_js_class ); ?>">
	<label for="ass-welcome-email-subject"><?php esc_html_e( 'Email Subject:', 'buddypress-group-email-subscription' ); ?></label>
	<input value="<?php echo isset( $welcome_email['subject'] ) ? esc_attr( $welcome_email['subject'] ) : ''; ?>" type="text" name="ass_welcome_email[subject]" id="ass-welcome-email-subject" />
</p>

<p class="ass-welcome-email-field<?php echo esc_attr( $hide_if_js_class ); ?>">
	<label for="ass-welcome-email-content"><?php esc_html_e( 'Email Content:', 'buddypress-group-email-subscription' ); ?></label>
	<textarea name="ass_welcome_email[content]" id="ass-welcome-email-content"><?php echo isset( $welcome_email['content'] ) ? esc_textarea( $welcome_email['content'] ) : ''; ?></textarea>
</p>

<p>
	<input type="submit" name="ass_welcome_email_submit" value="<?php esc_attr_e( 'Save', 'buddypress-group-email-subscription' ); ?>" />
</p>
