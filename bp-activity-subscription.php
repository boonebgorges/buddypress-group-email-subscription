<?php
/*
Plugin Name: BuddyPress Group Email Subscription
Plugin URI: http://wordpress.org/extend/plugins/buddypress-group-email-subscription/
Description: Allows group members to receive email notifications for group activity and forum posts instantly or as daily digest or weekly summary.
Author: Deryk Wenaus, boonebgorges
Revision Date: February 23, 2011
Version: 2.8.1
Requires at least: WPMU 2.9, BuddyPress 1.2
Tested up to: WP 3.0.5, BuddyPress 1.2.7
*/

function activitysub_load_buddypress() {
	global $ass_activities;
	if ( function_exists( 'bp_core_setup_globals' ) ) {
		require_once ('bp-activity-subscription-main.php');
		return true;
	}
	/* Get the list of active sitewide plugins */
	$active_sitewide_plugins = maybe_unserialize( get_site_option( 'active_sitewide_plugins' ) );

	if ( !isset( $active_sidewide_plugins['buddypress/bp-loader.php'] ) )
		return false;

	if ( isset( $active_sidewide_plugins['buddypress/bp-loader.php'] ) && !function_exists( 'bp_core_setup_globals' ) ) {
		require_once( WP_PLUGIN_DIR . '/buddypress/bp-loader.php' );
		require_once ('bp-activity-subscription-main.php');
		return true;
	}

	return false;
}
add_action( 'plugins_loaded', 'activitysub_load_buddypress', 1 );


function activitysub_textdomain() {
	load_plugin_textdomain( 'bp-ass', false, dirname( plugin_basename( __FILE__ ) ) . '/languages/' );
}
add_action( 'init', 'activitysub_textdomain' );


function activitysub_setup_digest_defaults() {
	require_once( WP_PLUGIN_DIR.'/buddypress-group-email-subscription/bp-activity-subscription-digest.php' );
	ass_set_daily_digest_time( '05', '00' );
	ass_set_weekly_digest_time( '4' );
}
register_activation_hook( __FILE__, 'activitysub_setup_digest_defaults' );

function activitysub_unset_digests() {
	wp_clear_scheduled_hook( 'ass_digest_event' );
	wp_clear_scheduled_hook( 'ass_digest_event_weekly' );
}
register_deactivation_hook( __FILE__, 'activitysub_unset_digests' );


?>