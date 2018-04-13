<?php

class BPGES_Command extends WP_CLI_Command {
	/**
	 * @subcommand install-database
	 */
	public function install_database( $args, $assoc_args ) {
		if ( ! function_exists( 'bpges_install_subscription_table' ) ) {
			require_once dirname( dirname( __FILE__ ) ) . '/admin.php';
		}

		bpges_install_subscription_table();
	}
}
