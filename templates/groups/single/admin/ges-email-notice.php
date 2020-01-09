<?php
/**
 * Admin notice template.
 *
 * @since 3.8.0 Broken into a separate template file.
 * @since 3.9.0 Added nonce field.
 */
?>

<h3><?php _e( 'Send an email notice to everyone in the group', 'buddypress-group-email-subscription' ); ?></h3>
<p><?php _e( 'You can use the form below to send an email notice to all group members.', 'buddypress-group-email-subscription' ); ?>

<?php if ( bpges_force_immediate_admin_notice() ) : ?>
	<br>
	<b><?php _e( 'Everyone in the group will receive the email -- regardless of their email settings -- so use with caution', 'buddypress-group-email-subscription' ); ?></b>.</p>
<?php endif; ?>

<p>
	<label for="ass-admin-notice-subject"><?php _e( 'Email Subject:', 'buddypress-group-email-subscription' ) ?></label>
	<input type="text" name="ass_admin_notice_subject" id="ass-admin-notice-subject" value="" />
</p>

<p>
	<label for="ass-admin-notice-textarea"><?php _e( 'Email Content:', 'buddypress-group-email-subscription' ) ?></label>
	<textarea value="" name="ass_admin_notice" id="ass-admin-notice-textarea"></textarea>
</p>

<?php wp_nonce_field( 'bpges_admin_notice', 'bpges-admin-notice-nonce' ); ?>

<p>
	<input type="submit" name="ass_admin_notice_send" value="<?php bpges_force_immediate_admin_notice() ? esc_html_e( 'Email this notice to everyone in the group', 'buddypress-group-email-subscription' ) : esc_html_e( 'Send this notice', 'buddypress-group-email-subscription' ); ?>" />
</p>
