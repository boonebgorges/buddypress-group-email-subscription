<?php
/*
Plugin Name: BFC Group Email Subscription
Plugin URI: https://github.com/ContextInstitute/bfc-group-email-subscription
Forked from: http://wordpress.org/extend/plugins/buddypress-group-email-subscription/
Original authors: Deryk Wenaus, boonebgorges, r-a-y
Description: Allows group members to receive email notifications for group forum posts instantly or as daily digest or weekly summary.
Author: Robert Gilman
Revision Date: May 7, 2022
Version: 1.0
Text Domain: buddypress-group-email-subscription
Domain Path: /languages
*/

/**
 * GES revision date.
 *
 * @since 3.7.0
 *
 * @var string Date string of last revision.
 */
define( 'GES_REVISION_DATE', '2021-10-04 17:00 UTC' );

/**
 * Main loader for the plugin.
 *
 * @since 2.9.0
 */
function ass_loader() {
	if ( ! defined( 'BPGES_DEBUG_LOG_PATH' ) ) {
		$dir = wp_upload_dir( null, false );
		define( 'BPGES_DEBUG_LOG_PATH', trailingslashit( $dir['basedir'] ) . 'bpges-debug.log' );
	}

	$error = '';

	// Old BP.
	if ( version_compare( BP_VERSION, '1.3', '<' ) ) {
		$error = sprintf( __( "Hey! BP Group Email Subscription v3.7.0 requires BuddyPress 1.5 or higher.  If you are still using BuddyPress 1.2 and you don't plan on upgrading, use <a href='%s'>BP Group Email Subscription v3.6.2 instead</a>.", 'buddypress-group-email-subscription' ), 'https://downloads.wordpress.org/plugin/buddypress-group-email-subscription.3.6.1.zip' );
	} elseif ( ! bp_is_active( 'groups' ) || ! bp_is_active( 'activity' ) ) {
		$admin_url = bp_get_admin_url( add_query_arg( array( 'page' => 'bp-components' ), 'admin.php' ) );
		$error     = sprintf( __( 'BuddyPress Group Email Subscription requires the BP Groups and Activity components. Please <a href="%s">activate them</a> to use this plugin.', 'buddypress-group-email-subscription' ), esc_url( $admin_url ) );
	}

	if ( $error ) {
		if ( current_user_can( 'bp_moderate' ) ) {
			$error_cb = function() use ( $error ) {
				echo '<div class="error"><p>' . $error . '</p></div>';
			};

			add_action( 'admin_notices', $error_cb );
			add_action( 'network_admin_notices', $error_cb );
		}

		return;
	}

	require_once( dirname( __FILE__ ) . '/bp-activity-subscription-main.php' );
}
add_action( 'bp_include', 'ass_loader' );

/**
 * Textdomain loader.
 *
 * @since 2.5.3
 */
function activitysub_textdomain() {
	load_plugin_textdomain( 'buddypress-group-email-subscription', false, dirname( plugin_basename( __FILE__ ) ) . '/languages/' );
}
add_action( 'plugins_loaded', 'activitysub_textdomain' );

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
	ass_loader();
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
