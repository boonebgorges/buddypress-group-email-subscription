<?php

// Determine the BP version. It's been historically difficult in this plugin, so provide
// a fallback when not found
if ( defined( 'BP_VERSION' ) ) {
	$bpges_bp_version = (float) BP_VERSION;
} else {
	// Let's guess
	if ( function_exists( 'bp_is_action_variable' ) ) {
		$bpges_bp_version = (float) '1.5';
	} else {
		$bpges_bp_version = (float) '1.2.10';
	}
}

// Install is using BP 1.5
// Need abstraction for BP 1.6
if ( $bpges_bp_version < 1.6 ) {
	require_once( dirname( __FILE__ ) . '/1.6-abstraction.php' );
}

// Install is using BP 1.2
// Need abstraction for BP 1.5
if ( $bpges_bp_version < 1.5 ) {

	// Load the abstraction files, which define the necessary 1.5 functions
	require_once( dirname( __FILE__ ) . '/1.5-abstraction.php' );

	// Load the group extension in the legacy fashion
	add_action( 'init', 'ass_activate_extension' );
} else {
	// Load the group extension in the proper fashion
	bp_register_group_extension( 'Group_Activity_Subscription' );
}

require_once( dirname( __FILE__ ) . '/bp-activity-subscription-functions.php' );
require_once( dirname( __FILE__ ) . '/bp-activity-subscription-digest.php' );

class Group_Activity_Subscription extends BP_Group_Extension {

	function group_activity_subscription() {
		$this->name = __('Email Options', 'bp-ass');
		$this->slug = 'notifications';

		// Only enable the notifications nav item if the user is a member of the group
		if ( bp_is_group() && groups_is_user_member( bp_loggedin_user_id() , bp_get_current_group_id() )  ) {
			$enable_nav_item = true;
		} else {
			$enable_nav_item = false;
		}

		$this->enable_nav_item = apply_filters( 'bp_group_email_subscription_enable_nav_item', $enable_nav_item );
		$this->nav_item_position  = 91;
		$this->enable_create_step = false;

		if ( get_option('ass-admin-can-send-email') == 'no' )
			$this->enable_edit_item = false;

		// hook in the css and js
		add_action( 'wp_enqueue_scripts', array( &$this , 'add_settings_stylesheet' ) );
		add_action( 'wp_enqueue_scripts', array( &$this , 'ass_add_javascript' ),1 );
	}

	public function add_settings_stylesheet() {
		if ( bp_is_groups_component() ) {
			$revision_date = '20130729';

			wp_register_style(
				'activity-subscription-style',
				plugins_url( 'css/bp-activity-subscription-css.css', __FILE__ ),
				array(),
				$revision_date
			);

			wp_enqueue_style( 'activity-subscription-style' );
		}
	}

	public function ass_add_javascript() {
		if ( bp_is_groups_component() ) {
			$revision_date = '20130729';

			wp_register_script(
				'bp-activity-subscription-js',
				plugins_url( 'bp-activity-subscription-js.js', __FILE__ ),
				array( 'jquery' ),
				$revision_date
			);

			wp_enqueue_script( 'bp-activity-subscription-js' );

			wp_localize_script( 'bp-activity-subscription-js', 'bp_ass', array(
				'mute'   => __( 'Mute', 'bp-ass' ),
				'follow' => __( 'Follow', 'bp-ass' ),
				'error'  => __( 'Error', 'bp-ass' )
			) );
		}
	}

	// Display the notification settings form
	public function display( $group_id = null ) {
		ass_group_subscribe_settings();
	}

	// "Admin > Email Options" screen
	public function edit_screen( $group_id = null ) {
		// if ass-admin-can-send-email = no this won't show
		ass_admin_notice_form(); // removed for now because it was broken
	}

	// The remaining group API functions aren't used for this plugin but have to
	// be overriden or api won't work
	public function create_screen( $group_id = null ) {}

	public function create_screen_save( $group_id = null ) {}

	public function edit_screen_save( $group_id = null ) {}

	public function widget_display() {}

}

function ass_activate_extension() {
	$extension = new Group_Activity_Subscription;
	add_action( "wp", array( &$extension, "_register" ), 2 );
}

?>