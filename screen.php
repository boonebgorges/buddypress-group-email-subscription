<?php
/**
 * GES code meant to be run only on group pages.
 *
 * @since 3.7.0
 */

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

	$status_desc = __('Your email status is ', 'bp-ass');
	$link_text = __('change', 'bp-ass');
	$gemail_icon_class = ' gemail_icon';
	$sep = '';

	if ( !$group_status ) {
		//$status_desc = '';
		$link_text = __('Get email updates', 'bp-ass');
		$gemail_icon_class = '';
		$sep = '';
	}

	$status = ass_subscribe_translate( $group_status );
	?>

	<div class="group-subscription-div">
		<span class="group-subscription-status-desc"><?php echo $status_desc; ?></span>
		<span class="group-subscription-status<?php echo $gemail_icon_class ?>" id="gsubstat-<?php echo $group->id; ?>"><?php echo $status; ?></span> <?php echo $sep; ?>
		(<a class="group-subscription-options-link" id="gsublink-<?php echo $group->id; ?>" href="javascript:void(0);" title="<?php _e('Change your email subscription options for this group','bp-ass');?>"><?php echo $link_text; ?></a>)
		<span class="ajax-loader" id="gsubajaxload-<?php echo $group->id; ?>"></span>
	</div>
	<div class="generic-button group-subscription-options" id="gsubopt-<?php echo $group->id; ?>" style="display:none;">
		<a class="group-sub" id="no-<?php echo $group->id; ?>"><?php _e('No Email', 'bp-ass') ?></a> <?php _e('I will read this group on the web', 'bp-ass') ?><br>
		<a class="group-sub" id="sum-<?php echo $group->id; ?>"><?php _e('Weekly Summary', 'bp-ass') ?></a> <?php _e('Get a summary of topics each', 'bp-ass') ?> <?php echo ass_weekly_digest_week(); ?><br>
		<a class="group-sub" id="dig-<?php echo $group->id; ?>"><?php _e('Daily Digest', 'bp-ass') ?></a> <?php _e('Get the day\'s activity bundled into one email', 'bp-ass') ?><br>

		<?php if ( ass_get_forum_type() ) : ?>
			<a class="group-sub" id="sub-<?php echo $group->id; ?>"><?php _e('New Topics', 'bp-ass') ?></a> <?php _e('Send new topics as they arrive (but no replies)', 'bp-ass') ?><br>
		<?php endif; ?>

		<a class="group-sub" id="supersub-<?php echo $group->id; ?>"><?php _e('All Email', 'bp-ass') ?></a> <?php _e('Send all group activity as it arrives', 'bp-ass') ?><br>
		<a class="group-subscription-close" id="gsubclose-<?php echo $group->id; ?>"><?php _e('close', 'bp-ass') ?></a>
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

	bp_core_add_message( __( 'You successfully joined the group. Your group email status is: ', 'bp-ass' ) . ass_subscribe_translate( $status ) );

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
		echo '<div class="ass_members_status">'.__('Email status:','bp-ass'). ' ' . ass_subscribe_translate( $sub_type ) . '</div>';
	}
}
add_action( 'bp_group_members_list_item_action', 'ass_show_subscription_status_in_member_list', 100 );
