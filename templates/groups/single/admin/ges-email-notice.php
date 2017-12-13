<h3><?php _e( 'Send an email notice to everyone in the group', 'bp-ass' ); ?></h3>
<p><?php _e( 'You can use the form below to send an email notice to all group members.', 'bp-ass' ); ?> <br>
<b><?php _e( 'Everyone in the group will receive the email -- regardless of their email settings -- so use with caution', 'bp-ass' ); ?></b>.</p>

<p>
	<label for="ass-admin-notice-subject"><?php _e( 'Email Subject:', 'bp-ass' ) ?></label>
	<input type="text" name="ass_admin_notice_subject" id="ass-admin-notice-subject" value="" />
</p>

<p>
	<label for="ass-admin-notice-textarea"><?php _e( 'Email Content:', 'bp-ass' ) ?></label>
	<textarea value="" name="ass_admin_notice" id="ass-admin-notice-textarea"></textarea>
</p>

<p>
	<input type="submit" name="ass_admin_notice_send" value="<?php _e( 'Email this notice to everyone in the group', 'bp-ass' ) ?>" />
</p>
