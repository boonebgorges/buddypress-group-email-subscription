<?php

/**
 * Async request for migrating subscription data in 3.9.0 upgrade.
 */
class BPGES_Async_Request_Subscription_Migrate extends WP_Async_Request {
	/**
	 * @var   string
	 * @since 3.9.0
	 */
	protected $action = 'bpges_migrate_subscriptions';

	/**
	 * Migrate a batch of group subscriptions.
	 *
	 * @since 3.9.0
	 */
	protected function handle() {
		global $wpdb;

		$bp = buddypress();

		$batch_count = 10;

		$group_ids = $wpdb->get_col( "SELECT g.id FROM {$bp->groups->table_name} g LEFT JOIN {$bp->groups->table_name_groupmeta} gm ON ( g.id = gm.group_id AND gm.meta_key = '_ges_subscriptions_migrated' ) INNER JOIN {$bp->groups->table_name_groupmeta} gm2 ON ( g.id = gm2.group_id ) WHERE gm.meta_value IS NULL AND gm2.meta_key = 'ass_subscribed_users' AND gm2.meta_value IS NOT NULL LIMIT $batch_count" );

		if ( ! $group_ids ) {
			bp_update_option( '_ges_39_subscriptions_migrated', 1 );

			$wpdb->delete(
				$bp->groups->table_name_groupmeta,
				array( 'meta_key' => '_ges_subscriptions_migrated' )
			);

			// The digest queue migration depends on this one, so we launch it from here.
			if ( ! function_exists( 'bpges_39_launch_legacy_digest_queue_migration' ) ) {
				require dirname( __FILE__ ) . '/../admin.php';
			}

			bpges_39_launch_legacy_digest_queue_migration();
		}

		foreach ( $group_ids as $group_id ) {
			$group_subscriptions = groups_get_groupmeta( $group_id, 'ass_subscribed_users', true );

			if ( is_array( $group_subscriptions ) ) {
				foreach ( $group_subscriptions as $user_id => $type ) {
					$query = new BPGES_Subscription_Query( array(
						'user_id'  => $user_id,
						'group_id' => $group_id,
					) );

					$existing = $query->get_results();
					if ( $existing ) {
						// Nothing to migrate.
						continue;
					}

					$subscription = new BPGES_Subscription();
					$subscription->user_id = $user_id;
					$subscription->group_id = $group_id;
					$subscription->type = $type;
					$subscription->save();
				}
			}

			groups_update_groupmeta( $group_id, '_ges_subscriptions_migrated', 1 );
		}

		$process = new self();
		$process->dispatch();
	}
}
