<?php

/**
 * Subscription options template for group admin screen (Group > Manage > Settings)
 */

?>

<label id="ass-email-type_no" for="ass-default-subscription_no">
	<input type="radio" name="ass-default-subscription" id="ass-default-subscription_no" value="no" <?php ass_default_subscription_settings( 'no' ); ?> />
	<?php _e( 'No Email (users will read this group on the web - good for any group)', 'buddypress-group-email-subscription' ); ?>
</label>
<label id="ass-email-type_sum" for="ass-default-subscription_sum">
	<input type="radio" name="ass-default-subscription" id="ass-default-subscription_sum" value="sum" <?php ass_default_subscription_settings( 'sum' ); ?> />
	<?php _e( 'Weekly Summary Email (the week\'s topics - good for large groups)', 'buddypress-group-email-subscription' ); ?>
</label>
<label id="ass-email-type_dig" for="ass-default-subscription_dig">
	<input type="radio" name="ass-default-subscription" id="ass-default-subscription_dig" value="dig" <?php ass_default_subscription_settings( 'dig' ); ?> />
	<?php _e( 'Daily Digest Email (all daily activity bundles in one email - good for medium-size groups)', 'buddypress-group-email-subscription' ); ?>
</label>

<?php if ( ass_get_forum_type() ) : ?>
	<label id="ass-email-type_sub" for="ass-default-subscription_sub">
		<input type="radio" name="ass-default-subscription" id="ass-default-subscription_sub" value="sub" <?php ass_default_subscription_settings( 'sub' ); ?> />
		<?php _e( 'New Topics Email (new topics are sent as they arrive, but not replies - good for small groups)', 'buddypress-group-email-subscription' ); ?>
	</label>
<?php endif; ?>

<label id="ass-email-type_supersub" for="ass-default-subscription_supersub">
	<input type="radio" name="ass-default-subscription" id="ass-default-subscription_supersub" value="supersub" <?php ass_default_subscription_settings( 'supersub' ); ?> />
	<?php _e( 'All Email (send emails about everything - recommended only for working groups)', 'buddypress-group-email-subscription' ); ?>
</label>
