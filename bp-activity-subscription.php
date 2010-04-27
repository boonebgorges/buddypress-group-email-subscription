<?php
/*
Plugin Name: BuddyPress Group Email Subscription
Plugin URI: http://wordpress.org/extend/plugins/buddypress-group-email-subscription/
Description: Allow BuddyPress members to get email updates for new forum topics, posts, activity updates and more with levels of control. 
Author: boonebgorges, dwenaus, David Cartwright
Revision Date: Apr 28, 2010
Version: 2.1b
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

?>