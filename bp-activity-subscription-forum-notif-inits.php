<?php
/**
 * Initializes forum notification options in the users settings->notifications page
 * for all existing users upon plugin installation
 * only add the following options if BP's bundled forums are installed...
 * 
 * @see bp-activity-subscription-functions.php line 2014, function ass_group_subscription_notification_settings()
 * called by bp-activity-subscription.php line 77 during plugin activation
 * 
 * @return true if success, false if failure (not used)
 */
function ass_forum_notif_activation_init() {
	
	require_once( WP_PLUGIN_DIR.'/buddypress-group-email-subscription/bp-activity-subscription-functions.php' );
	
	// get forum type
	$forums = ass_get_forum_type();
	
	// no forums installed? stop now!
	if ( ! $forums ) {
		return false;
	}
	
	if ( $forums == 'buddypress' ) :
	
		$all_existing_users = get_users( array( 'fields'=>'ID' ) );
		//trigger_error( 'debug:' . serialize( $all_existing_users ) ); //debug YD
		
		if( count( $all_existing_users ) > 1000 )
			return false;	//we risk performance issue, so just don't try
	
		foreach( $all_existing_users as $user ) {
			add_user_meta( $user, 'ass_replies_to_my_topic', 'yes', true);
			add_user_meta( $user, 'ass_replies_after_me_topic', 'yes', true);
		}
	
	endif;
	return true;
}

/**
 * Initializes forum notification options in the users settings->notifications page
 * for a newly registered user, after plugin has been installed
 * 
 * called by the user_register hook
 * @see bp-activity-subscription-main.php line 66 in the plugin's main class constructor function
 */
function ass_forum_notif_new_user_init( $user_id ) {
	add_user_meta( $user_id, 'ass_replies_to_my_topic', 'yes', true);
	add_user_meta( $user_id, 'ass_replies_after_me_topic', 'yes', true);
}
?>