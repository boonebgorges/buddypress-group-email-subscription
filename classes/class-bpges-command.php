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
		bpges_install_queued_items_table();
	}

	/**
	 * Migrate legacy subscriptions to new database table introduced in BPGES 3.9.0.
	 *
	 * @subcommand migrate-legacy-subscriptions
	 */
	public function migrate_legacy_subscriptions( $args, $assoc_args ) {
		global $wpdb;

		if ( ! function_exists( 'bpges_39_migrate_group_subscriptions' ) ) {
			require_once dirname( dirname( __FILE__ ) ) . '/admin.php';
		}

		$in_progress = bp_get_option( '_ges_39_subscription_migration_in_progress' );
		if ( $in_progress ) {
			$message = sprintf(
				'A migration is currently in progress, with the last batch processed at %s. Continue anyway?',
				date( 'Y-m-d H:i:s', $in_progress )
			);
			WP_CLI::confirm( $message );
		}

		$already = bp_get_option( '_ges_39_subscriptions_migrated' );
		if ( $already ) {
			WP_CLI::confirm( 'It looks like this migration has already been performed. Continue anyway?' );
			bp_delete_option( '_ges_39_subscriptions_migrated' );
		}

		bp_delete_option( '_ges_39_subscription_migration_in_progress' );

		$bp = buddypress();

		$total = $wpdb->get_var( "SELECT COUNT(gm.group_id) FROM {$bp->groups->table_name_groupmeta} gm LEFT JOIN {$bp->groups->table_name_groupmeta} gm2 ON ( gm.group_id = gm2.group_id AND gm2.meta_key = '_ges_subscriptions_migrated' ) WHERE gm.meta_key = 'ass_subscribed_users' AND gm.meta_value IS NOT NULL AND gm2.meta_value IS NULL" );

		WP_CLI::line( "Beginning subscription migration for $total groups..." );

		$progress = \WP_CLI\Utils\make_progress_bar( 'Migration progress', $total );
		$i        = 0;
		while ( $i <= $total ) {
			$group_id = $wpdb->get_var( "SELECT gm.group_id FROM {$bp->groups->table_name_groupmeta} gm LEFT JOIN {$bp->groups->table_name_groupmeta} gm2 ON ( gm.group_id = gm2.group_id AND gm2.meta_key = '_ges_subscriptions_migrated' ) WHERE gm.meta_key = 'ass_subscribed_users' AND gm.meta_value IS NOT NULL AND gm2.meta_value IS NULL LIMIT 1" );
			if ( ! $group_id ) {
				break;
			}
			bpges_39_migrate_group_subscriptions( $group_id );
			$progress->tick();
			sleep( 1 );
			$i++;
		}


		bp_update_option( '_ges_39_subscriptions_migrated', 1 );
		WP_CLI::success( 'Migration complete.' );
	}

	/**
	 * Migrate legacy digest queue to new database table introduced in BPGES 3.9.0.
	 *
	 * @subcommand migrate-legacy-digest-queue
	 */
	public function migrate_legacy_digest_queue( $args, $assoc_args ) {
		global $wpdb;

		if ( ! function_exists( 'bpges_39_migrate_user_queued_items' ) ) {
			require_once dirname( dirname( __FILE__ ) ) . '/admin.php';
		}

		$in_progress = bp_get_option( '_ges_39_digest_queue_migration_in_progress' );
		if ( $in_progress ) {
			$message = sprintf(
				'A migration is currently in progress, with the last batch processed at %s. Continue anyway?',
				date( 'Y-m-d H:i:s', $in_progress )
			);
			WP_CLI::confirm( $message );
		}

		$already = bp_get_option( '_ges_39_digest_queue_migrated' );
		if ( $already ) {
			WP_CLI::confirm( 'It looks like this migration has already been performed. Continue anyway?' );
			bp_delete_option( '_ges_39_digest_queue_migrated' );
		}

		bp_delete_option( '_ges_39_digest_queue_migration_in_progress' );

		$bp = buddypress();

		$total = $wpdb->get_var( "SELECT um.user_id FROM {$wpdb->usermeta} um LEFT JOIN {$wpdb->usermeta} um1 ON ( um.user_id = um1.user_id AND um1.meta_key = '_ges_digest_queue_migrated' ) WHERE um.meta_key = 'ass_digest_items' AND um.meta_value IS NOT NULL AND um1.meta_value IS NULL" );

		WP_CLI::line( "Beginning queued item migration for $total users..." );

		$progress = \WP_CLI\Utils\make_progress_bar( 'Migration progress', $total );
		$i        = 0;
		while ( $i <= $total ) {
			$user_id = $wpdb->get_var( "SELECT um.user_id FROM {$wpdb->usermeta} um LEFT JOIN {$wpdb->usermeta} um1 ON ( um.user_id = um1.user_id AND um1.meta_key = '_ges_digest_queue_migrated' ) WHERE um.meta_key = 'ass_digest_items' AND um.meta_value IS NOT NULL AND um1.meta_value IS NULL LIMIT 1" );
			if ( ! $user_id ) {
				break;
			}
			bpges_39_migrate_user_queued_items( $user_id );
			$progress->tick();
			sleep( 1 );
			$i++;
		}

		bp_update_option( '_ges_39_digest_queue_migrated', 1 );
		WP_CLI::success( 'Migration complete.' );
	}
}
