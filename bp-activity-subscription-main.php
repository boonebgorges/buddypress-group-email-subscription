<?php

if ( ! class_exists( 'WP_Background_Process' ) ) {
	require_once( dirname( __FILE__ ) . '/lib/wp-background-processing/wp-background-processing.php' );
}

// Admin-related code.
if ( defined( 'WP_NETWORK_ADMIN' ) || defined( 'WP_ADMIN' ) ) {
	require_once( dirname( __FILE__ ) . '/admin.php' );

	// Updater.
	require_once( dirname( __FILE__ ) . '/updater.php' );
	new GES_Updater;
}

// Legacy forums.
if ( function_exists( 'bp_setup_forums' ) ) {
	require_once( dirname( __FILE__ ) . '/legacy-forums.php' );
}

// Core.
require_once( dirname( __FILE__ ) . '/bp-activity-subscription-functions.php' );
require_once( dirname( __FILE__ ) . '/bp-activity-subscription-digest.php' );
require_once( dirname( __FILE__ ) . '/classes/class-bpges-database-object.php' );
require_once( dirname( __FILE__ ) . '/classes/class-bpges-subscription.php' );
require_once( dirname( __FILE__ ) . '/classes/class-bpges-subscription-query.php' );
require_once( dirname( __FILE__ ) . '/classes/class-bpges-queued-item.php' );
require_once( dirname( __FILE__ ) . '/classes/class-bpges-queued-item-query.php' );

require_once( dirname( __FILE__ ) . '/classes/class-bpges-async-request.php' );

if ( ! bp_get_option( '_ges_39_subscriptions_migrated' ) ) {
	require( dirname( __FILE__ ) . '/classes/class-bpges-async-request-subscription-migrate.php' );
	$bpges_subscription_migration = new BPGES_Async_Request_Subscription_Migrate();
}

if ( ! bp_get_option( '_ges_39_digest_queue_migrated' ) ) {
	require( dirname( __FILE__ ) . '/classes/class-bpges-async-request-digest-queue-migrate.php' );
	$bpges_digest_queue_migration = new BPGES_Async_Request_Digest_Queue_Migrate();
}

require dirname( __FILE__ ) . '/classes/class-bpges-async-request-send-queue.php';
bpges_send_queue();

// CLI.
if ( defined( 'WP_CLI' ) ) {
	require_once( dirname( __FILE__ ) . '/classes/class-bpges-command.php' );
	WP_CLI::add_command( 'bpges', 'BPGES_Command' );
}

/**
 * Group extension for GES.
 *
 * @todo This should be moved into a separate file.
 */
class Group_Activity_Subscription extends BP_Group_Extension {

	public function __construct() {
		/**
		 * Filter whether the nav item should be visible.
		 *
		 * This is primarily for legacy support and must be translated internally
		 * into the newer BP_Group_Extension parameter structure.
		 *
		 * @since 4.2.0 Returning the default value `true` will tell BP to show the tab
		 *              only to members (the 'member' value for 'show_tab'). Returning
		 *              `false` will tell BP to hide the tab from everyone (the 'noone'
		 *              value for 'show_tab').
		 *
		 * @param bool $enable_nav_item Whether the nav item should be visible.
		 */
		$enable_nav_item = apply_filters( 'bp_group_email_subscription_enable_nav_item', true );

		$args = [
			'slug'              => 'notifications',
			'name'              => __( 'Email Options', 'buddypress-group-email-subscription' ),
			'show_tab'          => $enable_nav_item ? 'member' : 'noone',
			'nav_item_position' => 91,
		];

		$screens = [
			'edit' => [
				'enabled' => 'no' !== get_option( 'ass-admin-can-send-email' ),
			],
			'create' => [
				'enabled' => false,
			],
		];

		$args['screens'] = $screens;

		parent::init( $args );

		// hook in the css and js
		add_action( 'wp_enqueue_scripts', array( &$this , 'add_settings_stylesheet' ) );
		add_action( 'wp_enqueue_scripts', array( &$this , 'ass_add_javascript' ),1 );
	}

	public function add_settings_stylesheet() {
		if ( apply_filters( 'ass_load_assets', bp_is_groups_component() ) ) {
			$revision_date = '20200623';

			wp_register_style(
				'activity-subscription-style',
				plugins_url( basename( dirname( __FILE__ ) ) ) . '/css/bp-activity-subscription-css.css',
				array(),
				$revision_date
			);

			wp_enqueue_style( 'activity-subscription-style' );
		}
	}

	public function ass_add_javascript() {
		if ( apply_filters( 'ass_load_assets', bp_is_groups_component() ) ) {
			$revision_date = '20200623';

			wp_register_script(
				'bp-activity-subscription-js',
				plugins_url( basename( dirname( __FILE__ ) ) ) . '/bp-activity-subscription-js.js',
				array( 'jquery' ),
				$revision_date
			);

			wp_enqueue_script( 'bp-activity-subscription-js' );

			wp_localize_script( 'bp-activity-subscription-js', 'bp_ass', array(
				'mute'   => __( 'Mute', 'buddypress-group-email-subscription' ),
				'follow' => __( 'Follow', 'buddypress-group-email-subscription' ),
				'error'  => __( 'Error', 'buddypress-group-email-subscription' )
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

// Register our group extension.
function bpges_register_group_extension() {
	bp_register_group_extension( 'Group_Activity_Subscription' );
}
add_action( 'bp_init', 'bpges_register_group_extension' );

/**
 * Include files only if we're on a specific page.
 *
 * @since 3.7.0
 */
function ges_late_includes() {
	// Any group page.
	if ( bp_is_groups_component() ) {
		require_once( dirname( __FILE__ ) . '/screen.php' );

		// Group's "Email Options" page.
		if ( bp_is_current_action( 'notifications' ) ) {
			require_once( dirname( __FILE__ ) . '/screen-notifications.php' );
		}

		// Group creation page or any group's admin page.
		if ( bp_is_group_create() || bp_is_group_admin_page() ) {
			require_once( dirname( __FILE__ ) . '/screen-admin.php' );
		}

		// bbPress.
		if ( bp_is_group() && function_exists( 'bbpress' ) ) {
			require_once( dirname( __FILE__ ) . '/screen-bbpress.php' );
		}
	}

	// User's "Settings > Email" page.
	if ( bp_is_user_settings_notifications() ) {
		require_once( dirname( __FILE__ ) . '/screen-user-settings.php' );
	}
}
if ( function_exists( 'bp_setup_canonical_stack' ) ) {
	$load_hook = 'bp_setup_canonical_stack';
	$priority  = 20;
} else {
	$load_hook = 'bp_init';
	$priority  = 5;
}
add_action( $load_hook, 'ges_late_includes', $priority );
