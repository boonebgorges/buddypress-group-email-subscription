<?php
/**
 * GES code meant to be run only on group pages.
 *
 * @since 3.7.0
 */

/**
 * Should we use the new options panel?
 *
 * @since 4.0.0
 *
 * @return bool
 */
function bpges_use_new_options_panel() {

	/**
	 * Whether to enable the new options panel.
	 *
	 * Those using the BP Nouveau template pack or the older bp-default theme
	 * will use the new options panel by default. bp-legacy will not due to
	 * layout issues, however can be forced to true with this filter. CSS
	 * will need to adjusted manually though.
	 *
	 * @since 4.0.0
	 *
	 * @param bool $retval True for Nouveau or bp-default themes.
	 */
	$retval = apply_filters( 'bpges_use_new_options_panel', function_exists( 'bp_nouveau' ) || current_theme_supports( 'buddypress' ) );

	return $retval;
}

/**
 * Enqueues van11y-accessible-modal-tooltip-aria.
 *
 * Used for new options panel.
 *
 * @since 4.0.0
 *
 * @link https://github.com/nico3333fr/van11y-accessible-modal-tooltip-aria/blob/master/LICENSE
 */
add_action( 'bp_enqueue_scripts', function() {
	if ( ! bpges_use_new_options_panel() ) {
		return;
	}

	wp_enqueue_script( 'van11y-accessible-modal-tooltip-aria', 'https://cdn.rawgit.com/nico3333fr/van11y-accessible-modal-tooltip-aria/e3518090/dist/van11y-accessible-modal-tooltip-aria.min.js' );
}, 9 );

// this adds the ajax-based subscription option in the group header, or group directory
function ass_group_subscribe_button() {
	global $bp, $groups_template;

	if( ! empty( $groups_template ) ) {
		$group =& $groups_template->group;
	}
	else {
		$group = groups_get_current_group();
	}

	if ( !is_user_logged_in() || !empty( $group->is_banned ) || !$group->is_member )
		return;

	// if we're looking at someone elses list of groups hide the subscription
	if ( bp_displayed_user_id() && ( bp_loggedin_user_id() != bp_displayed_user_id() ) )
		return;

	$group_status = ass_get_group_subscription_status( bp_loggedin_user_id(), $group->id );

	if ( $group_status == 'no' )
		$group_status = NULL;

	$status_desc = __( 'Your email status is ', 'buddypress-group-email-subscription' );
	$link_text   = __( 'change', 'buddypress-group-email-subscription' );

	$gemail_icon_class = ' gemail_icon';
	$sep               = '';

	if ( ! $group_status ) {
		//$status_desc = '';
		$link_text         = __( 'Get email updates', 'buddypress-group-email-subscription' );
		$gemail_icon_class = '';
	}

	$status = ass_subscribe_translate( $group_status );

	if ( bpges_use_new_options_panel() ) {
		$container_id      = 'ges-panel-' . $group->id;
		$container_classes = '';
		$ajax_class        = 'ges-ajax-processing';
	} else {
		$container_id      = 'gsubopt-' . $group->id;
		$container_classes = 'generic-button group-subscription-options';
		$ajax_class        = 'ajax-loader';
	}

	$ges_panel_class = bpges_use_new_options_panel() && ! function_exists( 'bp_nouveau' ) ? 'ges-panel' : '';

	$subscription_levels = bpges_subscription_levels();

	?>

	<div class="group-subscription-div <?php echo esc_attr( $ges_panel_class ); ?>">
		<span class="group-subscription-status-desc"><?php echo esc_html( $status_desc ); ?></span>
		<span class="group-subscription-status<?php echo esc_attr( $gemail_icon_class ); ?>" id="gsubstat-<?php echo esc_attr( $group->id ); ?>"><?php echo esc_html( $status ); ?></span> <?php echo esc_html( $sep ); ?>

		<?php if ( bpges_use_new_options_panel() ) : ?>

			<button class="js-tooltip ges-change" id="gestoggle-<?php echo esc_attr( $group->id ); ?>" data-tooltip-content-id="ges-panel-<?php echo esc_attr( $group->id ); ?>" data-tooltip-prefix-class="group-email" data-tooltip-title="<?php esc_html_e( 'Change email subscription for this group', 'buddypress-group-email-subscription' ); ?>" data-tooltip-close-text="<?php esc_html_e( 'Close', 'buddypress-group-email-subscription' ); ?>" data-tooltip-close-title="<?php esc_html_e( 'Close this window', 'buddypress-group-email-subscription' ); ?>"><?php esc_html_e( 'Change', 'buddypress-group-email-subscription' ); ?></button>

		<?php else : ?>

			(<a class="group-subscription-options-link js-tooltip" id="gestoggle-<?php echo esc_attr( $group->id ); ?>" data-tooltip-content-id="ges-panel-<?php echo esc_attr( $group->id ); ?>" data-tooltip-prefix-class="group-email" data-tooltip-title="<?php esc_html_e( 'Change email subscription for this group', 'buddypress-group-email-subscription' ); ?>" data-tooltip-close-text="<?php esc_html_e( 'Close', 'buddypress-group-email-subscription' ); ?>" data-tooltip-close-title="<?php esc_html_e( 'Close this window', 'buddypress-group-email-subscription' ); ?>" href="javascript:void(0);"><?php echo esc_html( $link_text ); ?></a>)

		<?php endif; ?>

		<span class="<?php echo esc_attr( $ajax_class ); ?>" id="gsubajaxload-<?php echo esc_attr( $group->id ); ?>"></span>
	</div>

	<div id="<?php echo esc_attr( $container_id ); ?>" style="display:none;" class="<?php echo esc_attr( $container_classes ); ?>">
		<div data-security="<?php echo esc_attr( wp_create_nonce( 'bpges-sub-' . $group->id ) ); ?>">
			<?php
			$levels_i     = 0;
			$levels_count = count( $subscription_levels );
			?>
			<?php foreach ( $subscription_levels as $level_slug => $level_data ) : ?>
				<a class="group-sub" id="<?php echo esc_attr( $level_slug ); ?>-<?php echo esc_attr( $group->id ); ?>" href="javascript:;"><?php echo esc_html( $level_data['label'] ); ?></a> <?php echo esc_html( $level_data['description_user'] ); ?>
				<?php
				++$levels_i;

				if ( $levels_i < $levels_count ) {
					echo '<br />';
				}
				?>
			<?php endforeach; ?>

			<?php if ( ! bpges_use_new_options_panel() ) : ?>
				<br><a class="group-subscription-close" id="gsubclose-<?php echo esc_attr( $group->id ); ?>"><?php esc_html_e( 'Close', 'buddypress-group-email-subscription' ); ?></a>
			<?php endif; ?>

		</div>
	</div>

	<?php
}
add_action ( 'bp_group_header_meta', 'ass_group_subscribe_button' );
add_action ( 'bp_directory_groups_actions', 'ass_group_subscribe_button' );
//add_action ( 'bp_directory_groups_item', 'ass_group_subscribe_button' );  //useful to put in different location with css abs pos

// give the user a notice if they are default subscribed to this group (does not work for invites or requests)
function ass_join_group_message( $group_id, $user_id ) {
	global $bp;

	if ( $user_id != bp_loggedin_user_id()  )
		return;

	$status = apply_filters( 'ass_default_subscription_level', groups_get_groupmeta( $group_id, 'ass_default_subscription' ), $group_id );

	if ( !$status )
		$status = 'no';

	bp_core_add_message( __( 'You successfully joined the group. Your group email status is: ', 'buddypress-group-email-subscription' ) . ass_subscribe_translate( $status ) );

}
add_action( 'groups_join_group', 'ass_join_group_message', 1, 2 );

// show group email subscription status on group member pages (for admins and mods only)
function ass_show_subscription_status_in_member_list( $user_id='' ) {
	global $bp, $members_template;

	$group_id = bp_get_current_group_id();

	if ( groups_is_user_admin( bp_loggedin_user_id() , $group_id ) || groups_is_user_mod( bp_loggedin_user_id() , $group_id ) || is_super_admin() ) {
		if ( !$user_id )
			$user_id = $members_template->member->user_id;
		$sub_type = ass_get_group_subscription_status( $user_id, $group_id );
		echo '<div class="ass_members_status">'.__('Email status:','buddypress-group-email-subscription'). ' ' . ass_subscribe_translate( $sub_type ) . '</div>';
	}
}
add_action( 'bp_group_members_list_item_action', 'ass_show_subscription_status_in_member_list', 100 );
