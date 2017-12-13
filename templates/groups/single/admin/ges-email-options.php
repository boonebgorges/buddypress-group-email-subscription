<?php wp_nonce_field( 'ass_email_options' ); ?>

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
	bp_get_template_part( 'groups/single/admin/ges-email-notice' );
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
	bp_get_template_part( 'groups/single/admin/ges-welcome-email' );
}
?>

<br />

<?php

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
