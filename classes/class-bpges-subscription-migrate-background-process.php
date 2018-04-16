<?php

class BPGES_Subscription_Migrate_Background_Process extends WP_Background_Process {
	/**
	 * @var   string
	 * @since 3.9.0
	 */
	protected $action = 'bpges_migrate_subscriptions';

	/**
	 * Migration task.
	 *
	 * @since 3.9.0
	 *
	 * @param int $group_id ID of the group whose subscriptions are being migrated.
	 */
	protected function task( $group_id ) {
		$group_subscriptions = groups_get_groupmeta( $group_id, 'ass_subscribed_users', true );

		if ( ! is_array( $group_subscriptions ) ) {
			return false;
		}

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

		return false;
	}

	protected function complete() {
		bp_update_option( '_ges_39_subscriptions_migrated', 1 );
	}
}
