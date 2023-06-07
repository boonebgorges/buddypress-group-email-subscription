<?php

/**
 * Subscription options template for group member options screen (Group > Email Options)
 */

// Init
$group        = groups_get_current_group();
$group_status = ass_get_group_subscription_status( bp_loggedin_user_id(), $group->id );

$group_status_normalized = 'no' === $group_status || 'un' === $group_status || ! $group_status ? 'no' : $group_status;

$subscription_levels = bpges_subscription_levels();

?>

<?php foreach ( $subscription_levels as $level_slug => $level_data ) : ?>
	<div class="ass-email-type" id="ass-email-type_<?php echo esc_attr( $level_slug ); ?>">
		<label for="ass_group_subscribe_<?php echo esc_attr( $level_slug ); ?>"><input type="radio" name="ass_group_subscribe" id="ass_group_subscribe_<?php echo esc_attr( $level_slug ); ?>" value="<?php echo esc_attr( $level_slug ); ?>" <?php checked( $group_status_normalized, $level_slug ); ?>><?php echo esc_html( $level_data['label'] ); ?></label>
		<div class="ass-email-explain"><?php echo esc_html( $level_data['description_user'] ); ?></div>
	</div>
<?php endforeach; ?>
