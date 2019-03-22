<?php

/**
 * Async request for migrating subscription data in 3.9.0 upgrade.
 */
class BPGES_Async_Request_Subscription_Migrate extends BPGES_Async_Request {
	/**
	 * @var   string
	 * @since 3.9.0
	 */
	protected $query_url;

	/**
	 * @var   string
	 * @since 3.9.0
	 */
	protected $action = 'bpges_migrate_subscriptions';

	/**
	 * Constructor.
	 *
	 * @since 3.9.0
	 */
	public function __construct() {
		/**
		 * Filters the query URL for BPGES async requests.
		 *
		 * @since 3.9.0
		 *
		 * @param string $url
		 */
		$this->query_url = apply_filters( 'bpges_async_request_query_url', get_admin_url( bp_get_root_blog_id(), 'admin-ajax.php' ) );

		parent::__construct();
	}

	/**
	 * Migrate a batch of group subscriptions.
	 *
	 * @since 3.9.0
	 */
	protected function handle() {
		global $wpdb;

		$bp = buddypress();

		$batch_count = 10;

		$group_ids = $wpdb->get_col( $wpdb->prepare( "SELECT gm.group_id FROM {$bp->groups->table_name_groupmeta} gm LEFT JOIN {$bp->groups->table_name_groupmeta} gm2 ON ( gm.group_id = gm2.group_id AND gm2.meta_key = '_ges_subscriptions_migrated' ) WHERE gm.meta_key = 'ass_subscribed_users' AND gm.meta_value IS NOT NULL AND gm2.meta_value IS NULL LIMIT %d", $batch_count ) );

		if ( ! $group_ids ) {
			bp_update_option( '_ges_39_subscriptions_migrated', 1 );
			bp_delete_option( '_ges_39_subscription_migration_in_progress' );

			groups_delete_groupmeta( 1, '_ges_subscriptions_migrated', false, true );

			// The digest queue migration depends on this one, so we launch it from here.
			if ( ! function_exists( 'bpges_39_launch_legacy_digest_queue_migration' ) ) {
				require dirname( __FILE__ ) . '/../admin.php';
			}

			bpges_39_launch_legacy_digest_queue_migration();
			return;
		}

		bp_update_option( '_ges_39_subscription_migration_in_progress', time() );

		foreach ( $group_ids as $group_id ) {
			bpges_39_migrate_group_subscriptions( $group_id );
		}

		$process = new self();
		$process->dispatch();
	}
}
