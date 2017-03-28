<?php
/*
Plugin Name: BuddyPress Group Email Subscription
Plugin URI: http://wordpress.org/extend/plugins/buddypress-group-email-subscription/
Description: Allows group members to receive email notifications for group activity and forum posts instantly or as daily digest or weekly summary.
Author: Deryk Wenaus, boonebgorges, r-a-y
Revision Date: March 28, 2017
Version: 3.7.1
*/

/**
 * GES revision date.
 *
 * @since 3.7.0
 *
 * @var string Date string of last revision.
 */
define( 'GES_REVISION_DATE', '2017-03-28 16:00 UTC' );

/**
 * Main loader for the plugin.
 *
 * @since 2.9.0
 */
function ass_loader() {
	// Only supported in BP 1.5+.
	if ( version_compare( BP_VERSION, '1.3', '>' ) ) {
		// Make sure the group and activity components are active.
		if ( bp_is_active( 'groups' ) && bp_is_active( 'activity' ) ) {
			require_once( dirname( __FILE__ ) . '/bp-activity-subscription-main.php' );
		}

	// Show admin notice for those on BP 1.2.x.
	} else {
		$older_version_notice = sprintf( __( "Hey! BP Group Email Subscription v3.7.0 requires BuddyPress 1.5 or higher.  If you are still using BuddyPress 1.2 and you don't plan on upgrading, use <a href='%s'>BP Group Email Subscription v3.6.2 instead</a>.", 'bp-ass' ), 'https://downloads.wordpress.org/plugin/buddypress-group-email-subscription.3.6.1.zip' );

		add_action( 'admin_notices', create_function( '', "
			echo '<div class=\"error\"><p>" . $older_version_notice . "</p></div>';
		" ) );
	}
}
add_action( 'bp_include', 'ass_loader' );

/**
 * Textdomain loader.
 *
 * @since 2.5.3
 */
function activitysub_textdomain() {
	load_plugin_textdomain( 'bp-ass', false, dirname( plugin_basename( __FILE__ ) ) . '/languages/' );
}
add_action( 'init', 'activitysub_textdomain' );

/**
 * Activation hook.
 *
 * @since 2.5.3
 * @since 3.7.0 Renamed function to handle things other than digests.
 */
function activitysub_setup_defaults() {
	// Digests.
	require_once( dirname( __FILE__ ) . '/bp-activity-subscription-digest.php' );
	ass_set_daily_digest_time( '05', '00' );
	ass_set_weekly_digest_time( '4' );

	// Run updater on activation.
	require_once( dirname( __FILE__ ) . '/admin.php' );
	require_once( dirname( __FILE__ ) . '/updater.php' );
	new GES_Updater( true );
}
register_activation_hook( __FILE__, 'activitysub_setup_defaults' );

/**
 * Digest deactivation hook.
 *
 * @since 2.5.3
 */
function activitysub_unset_digests() {
	wp_clear_scheduled_hook( 'ass_digest_event' );
	wp_clear_scheduled_hook( 'ass_digest_event_weekly' );
}
register_deactivation_hook( __FILE__, 'activitysub_unset_digests' );
