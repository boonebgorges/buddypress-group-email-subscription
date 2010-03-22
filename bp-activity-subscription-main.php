<?php

require_once( WP_PLUGIN_DIR.'/buddypress-group-email-subscription/bp-activity-subscription-functions.php' );

/****************************************************************************************************************
 *																												*
 * This extension allows users to turn activity stream update notifications on/off								*
 *																												*
 ****************************************************************************************************************/
class Group_Activity_Subscription extends BP_Group_Extension {	
		
	function group_activity_subscription() {
		global $bp;
		
		$this->name = 'Email Options';
		$this->slug = 'notifications';
		
		// Only enable the notifications nav item if the user is a member of the group
		if ( groups_is_user_member( $bp->loggedin_user->id , $bp->groups->current_group->id )  ) {
			$this->enable_nav_item = true;
		} else {
			$this->enable_nav_item = false;
		}
		
		$this->nav_item_position = 91;
		
		
		$this->enable_create_step = false;
		$this->enable_edit_item  = false;
		
		add_action ( 'wp_print_styles' , array( &$this , 'add_settings_stylesheet' ) );
		
	}

	public function add_settings_stylesheet() {
        $style_url = WP_PLUGIN_URL . '/buddypress-group-email-subscription/css/bp-activity-subscription-css.css';
        $style_file = WP_PLUGIN_DIR . '/buddypress-group-email-subscription/css/bp-activity-subscription-css.css';
        if (file_exists($style_file)) {
            wp_register_style('activity-subscription-style', $style_url);
            wp_enqueue_style('activity-subscription-style');
        }
    }
	
	function display() {
		/* Display the notification settings form for users here */
		global $bp;
		
		

		if ( groups_is_user_admin( $bp->loggedin_user->id , $bp->groups->current_group->id ) || groups_is_user_mod( $bp->loggedin_user->id , $bp->groups->current_group->id ) ) {
			?>
			Admin or moderator only: <a href="#">Send an email to everyone in the group</a>  {NEED TO ADD THIS BACK}
			<!--
			// DW: this should be moved to a newly created group admin section
			<h4 class="activity-subscription-settings-title">Send a Group Notification</h4>
			<p>As a group admin or moderator you can use the form below to send a notice to all group members, no matter what their notification settings are.</p>
			<form action="<?php echo $bp->root_domain; ?>/?ass_admin_notify=1" id="admin-group-notice-form" name="admin-group-notice-form" method="post">
				<?php wp_nonce_field( 'ass_admin_notice' ); ?>
				<input type="hidden" name="ass_group_id" value="<?php echo $bp->groups->current_group->id; ?>"/>
				<div class="activity-admin-notice-textdiv"><textarea value="" id="ass_admin_notice" name="ass_admin_notice" class="activity-admin-notice-textarea"></textarea></div>
				<input type="submit" name="submit" value="Send" />
			</form> -->
			<?php
		}

		?>
			<h3 class="activity-subscription-settings-title">Email Subscription Options</h3>
							
			<?php ass_group_subscribe_settings(); ?>
				
		<?php
	}
	
	/****************************************************************************************************************
	 *																												*
	 * The remaining group API functions aren't used for this plugin but have to be overriden or api won't work		*
	 *																												*
	 ****************************************************************************************************************/
	function create_screen() {
		return false;
	}

	function create_screen_save() {
		return false;
	}

	function edit_screen() {
		return false;
	}

	function edit_screen_save() {
		return false;
	}

	function widget_display() { 
		return false;
	}
}

bp_register_group_extension( 'Group_Activity_Subscription' );

?>