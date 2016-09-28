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
	 * Only load our updater on certain admin pages only.  This currently includes
	 * the "Dashboard", "Dashboard > Updates" and "Plugins" pages.
	 */
	public function __construct() {
		add_action( 'load-index.php',       array( $this, '_init' ) );
		add_action( 'load-update-core.php', array( $this, '_init' ) );
		add_action( 'load-plugins.php',     array( $this, '_init' ) );
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
		$installed_date = (int) self::get_installed_revision_date();

		// Sept 28, 2016 - Install email post types.
		if ( $installed_date < 1475020800 && function_exists( 'bp_send_email' ) ) {
			ass_install_emails( true );
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