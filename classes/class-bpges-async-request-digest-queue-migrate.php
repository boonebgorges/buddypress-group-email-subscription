<?php

/**
 * Async process for migrating digest queue data in 3.9.0 upgrade.
 */
class BPGES_Async_Request_Digest_Queue_Migrate extends BPGES_Async_Request {
	/**
	 * @var   string
	 * @since 3.9.0
	 */
	protected $query_url;

	/**
	 * @var   string
	 * @since 3.9.0
	 */
	protected $action = 'bpges_migrate_digest_queue';

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
	 * Handles a migration batch.
	 *
	 * @since 3.9.0
	 *
	 * @param int $group_id ID of the group whose subscriptions are being migrated.
	 */
	protected function handle() {
		global $wpdb;

		$batch_size = 50;

		$user_ids = $wpdb->get_col( $wpdb->prepare( "SELECT um.user_id FROM {$wpdb->usermeta} um LEFT JOIN {$wpdb->usermeta} um1 ON ( um.user_id = um1.user_id AND um1.meta_key = '_ges_digest_queue_migrated' ) WHERE um.meta_key = 'ass_digest_items' AND um.meta_value IS NOT NULL AND um1.meta_value IS NULL LIMIT %d", $batch_size ) );

		if ( ! $user_ids ) {
			bp_update_option( '_ges_39_digest_queue_migrated', 1 );
			bp_delete_option( '_ges_39_digest_queue_migration_in_progress' );

			delete_metadata( 'user', 1, '_ges_digest_queue_migrated', false, true );

			return;
		}

		bp_update_option( '_ges_39_digest_queue_migration_in_progress', time() );

		foreach ( $user_ids as $user_id ) {
			bpges_39_migrate_user_queued_items( $user_id );
		}

		// Launch another item in the queue.
		$process = new self();
		$process->dispatch();
	}
}
