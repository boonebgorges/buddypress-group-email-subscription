<?php

/**
 * Updater class.
 *
 * @since 3.7.0
 */
class GES_Updater {
	/**
	 * Constructor.
	 *
	 * @param bool $skip_admin_check Run updater code without admin check. Default: false.
	 *                               When false, our updater only runs on certain admin pages only. This
	 *                               currently includes the "Dashboard", "Dashboard > Updates" and
	 *                               "Plugins" pages. You should only set to true if you need to run the
	 *                               updater manually.
	 */
	public function __construct( $skip_admin_check = false ) {
		// Skip admin check and run updater code.
		if ( true === $skip_admin_check ) {
			$this->init();

		// Use admin check.
		} else {
			add_action( 'load-index.php',       array( $this, '_init' ) );
			add_action( 'load-update-core.php', array( $this, '_init' ) );
			add_action( 'load-plugins.php',     array( $this, '_init' ) );
		}
	}

	/**
	 * Stub initializer.
	 *
	 * This is designed to prevent access to the main, protected init method.
	 */
	public function _init() {
		if ( ! did_action( 'admin_init' ) ) {
			return;
		}

		$this->init();
	}

	/**
	 * Update routine.
	 */
	protected function init() {
		// Bail if BuddyPress isn't available.
		if ( ! function_exists( 'bp_get_option' ) ) {
			return;
		}

		$installed_date = (int) self::get_installed_revision_date();

		// Sept 28, 2016 - Install email post types.
		if ( $installed_date < 1475020800 && function_exists( 'bp_send_email' ) ) {
			ass_install_emails( true );
		}

		// 3.9.0 - Install subscription table and migrate data.
		if ( $installed_date < 1523891599 ) {
			bp_update_option( '_ges_installed_before_39', 1 );
			bpges_install_subscription_table();
			bpges_install_queued_items_table();
			bpges_39_launch_legacy_subscription_migration();
		}

		// Bump revision date in DB.
		self::bump_revision_date();
	}

	/** REVISION DATE *************************************************/

	/**
	 * Returns the current revision date as set in our loader.
	 *
	 * @return string The current revision date string (eg. 2014-01-01 01:00 UTC).
	 */
	public static function get_current_revision_date() {
		return constant( 'GES_REVISION_DATE' );
	}

	/**
	 * Returns the revision date for the GES install as saved in the DB.
	 *
	 * @return int|bool Integer of the installed unix timestamp on success.  Boolean false on failure.
	 */
	public static function get_installed_revision_date() {
		return strtotime( bp_get_option( '_ges_revision_date' ) );
	}

	/**
	 * Bumps the revision date in the DB
	 *
	 * @return void
	 */
	protected static function bump_revision_date() {
		bp_update_option( '_ges_revision_date', self::get_current_revision_date() );
	}
}
