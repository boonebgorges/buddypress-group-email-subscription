<?php

/**
 * Subscription options template for group admin screen (Group > Manage > Settings)
 */

$subscription_levels = bpges_subscription_levels();

?>

<?php foreach ( $subscription_levels as $label_slug => $label_data ) : ?>
	<label id="ass-email-type_<?php echo esc_attr( $label_slug ); ?>" for="ass-default-subscription_<?php echo esc_attr( $label_slug ); ?>">
		<input type="radio" name="ass-default-subscription" id="ass-default-subscription_<?php echo esc_attr( $label_slug ); ?>" value="<?php echo esc_attr( $label_slug ); ?>" <?php ass_default_subscription_settings( $label_slug ); ?> />
		<?php echo esc_html( $label_data['description_admin'] ); ?>
	</label>
<?php endforeach; ?>
