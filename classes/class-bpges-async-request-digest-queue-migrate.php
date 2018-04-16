<?php

/**
 * Async process for migrating digest queue data in 3.9.0 upgrade.
 */
class BPGES_Async_Request_Digest_Queue_Migrate extends WP_Async_Request {
	/**
	 * @var   string
	 * @since 3.9.0
	 */
	protected $action = 'bpges_migrate_digest_queue';

	/**
	 * Handles a migration batch.
	 *
	 * @since 3.9.0
	 *
	 * @param int $group_id ID of the group whose subscriptions are being migrated.
	 */
	protected function handle() {
		global $wpdb;

		$batch_size = 2;

		$user_ids = $wpdb->get_col( $wpdb->prepare( "SELECT user_id FROM {$wpdb->usermeta} WHERE meta_key = 'ass_digest_items' LIMIT %d", $batch_size ) );

		if ( ! $user_ids ) {
			bp_update_option( '_ges_39_digest_queue_migrated', 1 );
			return;
		}

		foreach ( $user_ids as $user_id ) {
			$user_queues = bp_get_user_meta( $user_id, 'ass_digest_items', true );
			foreach ( $user_queues as $digest_type => $user_groups ) {
				foreach ( $user_groups as $group_id => $activity_ids ) {
					$query = new BPGES_Subscription_Query( array(
						'user_id'  => $user_id,
						'group_id' => $group_id,
					) );

					$existing = $query->get_results();
					if ( ! $existing ) {
						// Nothing to migrate.
						continue;
					}

					$subscription = reset( $existing );

					if ( 'dig' === $digest_type ) {
						$subscription->items_dig = $activity_ids;
					} else {
						$subscription->items_sum = $activity_ids;
					}

					$subscription->save();
				}
			}

			// Delete the legacy queue, to avoid double-processing.
			bp_delete_user_meta( $user_id, 'ass_digest_items' );
		}

		// Launch another item in the queue.
		$process = new self();
		$process->dispatch();
	}

	/**
	 * When 3.9.0 subscription migration task is complete, set a database flag.
	 *
	 * @since 3.9.0
	 *
	 * @param int $group_id ID of the group whose subscriptions are being migrated.
	 */
	protected function complete() {
		bp_update_option( '_ges_39_subscriptions_migrated', 1 );
	}
}
