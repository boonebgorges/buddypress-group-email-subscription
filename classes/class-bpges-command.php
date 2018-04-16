<?php

class BPGES_Command extends WP_CLI_Command {
	/**
	 * Bring database schema up to date.
	 *
	 * @subcommand install-database
	 */
	public function install_database( $args, $assoc_args ) {
		if ( ! function_exists( 'bpges_install_subscription_table' ) ) {
			require_once dirname( dirname( __FILE__ ) ) . '/admin.php';
		}

		bpges_install_subscription_table();
	}

	/**
	 * Migrate legacy subscriptions to new database table introduced in BPGES 3.9.0.
	 *
	 * @subcommand migrate-legacy-subscriptions
	 */
	public function migrate_legacy_subscriptions( $args, $assoc_args ) {
		if ( ! function_exists( 'bpges_39_launch_legacy_subscription_migration' ) ) {
			require_once dirname( dirname( __FILE__ ) ) . '/admin.php';
		}

		bpges_39_launch_legacy_subscription_migration();
	}
}
