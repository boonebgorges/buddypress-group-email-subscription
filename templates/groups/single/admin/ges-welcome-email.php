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
