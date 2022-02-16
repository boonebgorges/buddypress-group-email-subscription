<?php

/**
 * Subscription options template for group member options screen (Group > Email Options)
 */

// Init
$group        = groups_get_current_group();
$group_status = ass_get_group_subscription_status( bp_loggedin_user_id(), $group->id );

?>

<div class="ass-email-type" id="ass-email-type_no">
	<label for="ass_group_subscribe_no"><input type="radio" name="ass_group_subscribe" id="ass_group_subscribe_no" value="no" <?php if ( $group_status == "no" || $group_status == "un" || ! $group_status ) { echo 'checked="checked"'; } ?>><?php _e( 'No Email', 'buddypress-group-email-subscription' ); ?></label>
	<div class="ass-email-explain"><?php _e( 'I will read this group on the web', 'buddypress-group-email-subscription' ); ?></div>
</div>

<div class="ass-email-type" id="ass-email-type_sum">
	<label for="ass_group_subscribe_sum"><input type="radio" name="ass_group_subscribe" id="ass_group_subscribe_sum" value="sum" <?php if ( $group_status == "sum" ) { echo 'checked="checked"'; } ?>><?php _e( 'Weekly Summary Email', 'buddypress-group-email-subscription' ); ?></label>
	<div class="ass-email-explain"><?php _e( 'Get a summary of new topics each week', 'buddypress-group-email-subscription' ); ?></div>
</div>

<div class="ass-email-type" id="ass-email-type_dig">
	<label for="ass_group_subscribe_dig"><input type="radio" name="ass_group_subscribe" id="ass_group_subscribe_dig" value="dig" <?php if ( $group_status == "dig" ) { echo 'checked="checked"'; } ?>><?php _e( 'Daily Digest Email', 'buddypress-group-email-subscription' ); ?></label>
	<div class="ass-email-explain"><?php _e( 'Get all the day\'s activity bundled into a single email', 'buddypress-group-email-subscription' ); ?></div>
</div>

<?php if ( ass_get_forum_type() ) : ?>
	<div class="ass-email-type" id="ass-email-type_sub">
		<label for="ass_group_subscribe_sub"><input type="radio" name="ass_group_subscribe" id="ass_group_subscribe_sub" value="sub" <?php if ( $group_status == "sub" ) { echo 'checked="checked"'; } ?>><?php _e( 'New Topics Email', 'buddypress-group-email-subscription' ); ?></label>
		<div class="ass-email-explain"><?php _e( 'Send new topics as they arrive (but don\'t send replies)', 'buddypress-group-email-subscription' ); ?></div>
	</div>
<?php endif; ?>

<div class="ass-email-type" id="ass-email-type_supersub">
	<label for="ass_group_subscribe_supersub"><input type="radio" name="ass_group_subscribe" id="ass_group_subscribe_supersub" value="supersub" <?php if ( $group_status == "supersub" ) { echo 'checked="checked"'; } ?>><?php _e( 'All Email', 'buddypress-group-email-subscription' ); ?></label>
	<div class="ass-email-explain"><?php _e( 'Send all group activity as it arrives', 'buddypress-group-email-subscription' ); ?></div>
</div>
