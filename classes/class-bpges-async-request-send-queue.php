<?php

class BPGES_Async_Request_Send_Queue extends BPGES_Async_Request {
	/**
	 * @var   string
	 * @since 3.9.0
	 */
	protected $query_url;

	/**
	 * @var   string
	 * @since 3.9.0
	 */
	protected $action = 'bpges_send_queue';

	/**
	 * Start time of current process.
	 *
	 * (default value: 0)
	 *
	 * @var int
	 * @access protected
	 * @since 3.9.0
	 */
	protected $start_time = 0;

	/**
	 * Constructor.
	 *
	 * @since 3.9.0
	 */
	public function __construct() {
		$this->start_time = time();

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
	 * Migrate a batch of outgoing notifications.
	 *
	 * @since 3.9.0
	 */
	protected function handle() {
		// Nothing to do.
		if ( ! isset( $_POST['type'] ) ) {
			return;
		}

		$queue_type = wp_unslash( $_POST['type'] );

		switch ( $queue_type ) {
			case 'immediate' :
				if ( ! isset( $_POST['activity_id'] ) ) {
					return;
				}

				$activity_id = (int) $_POST['activity_id'];
				$this->handle_immediate_queue( $activity_id );
			break;

			default :
				if ( ! isset( $_POST['type'] ) || ! isset( $_POST['timestamp'] ) ) {
					return;
				}

				$timestamp = wp_unslash( $_POST['timestamp'] );
				$type = wp_unslash( $_POST['type'] );
				if ( 'dig' !== $type && 'sum' !== $type ) {
					return;
				}

				$this->handle_digest_queue( $type, $timestamp );
			break;
		}
	}

	/**
	 * Processes 'immediate' queue batches.
	 *
	 * @since 3.9.0
	 *
	 * @param int $activity_id The activity ID that triggered this batch.
	 */
	protected function handle_immediate_queue( $activity_id ) {
		bpges_log( "Beginning batch of immediate notifications for $activity_id." );

		$total_for_activity = (int) bp_activity_get_meta( $activity_id, 'bpges_immediate_notification_count' );

		$run = true;
		$total_for_batch = 0;
		do {
			$query = new BPGES_Queued_Item_Query( array(
				'activity_id' => $activity_id,
				'per_page'    => 1,
				'type'        => 'immediate',
			) );

			$items = $query->get_results();

			// Queue is finished.
			if ( ! $items ) {
				bpges_log( "Finished sending immediate notifications for $activity_id. A total of $total_for_activity notifications were sent over all batches." );
				bp_activity_delete_meta( $activity_id, 'bpges_immediate_notification_count' );

				// @todo Should we take this opportunity to run a cleanup of other failed 'immediate' items? or maybe on a cron job
				return;
			}

			foreach ( $items as $item ) {
				bpges_generate_notification( $item );
				$item->delete();
				$total_for_batch++;
				$total_for_activity++;
			}

			if ( $this->time_exceeded() || $this->memory_exceeded() ) {
				$run = false;
			}
		} while ( $run );

		bpges_log( "Sent $total_for_batch immediate notifications for $activity_id this batch. Launching another batch...." );

		bp_activity_update_meta( $activity_id, 'bpges_immediate_notification_count', $total_for_activity );

		bpges_send_queue()->data( array(
			'type'        => 'immediate',
			'activity_id' => $activity_id,
		) )->dispatch();
	}

	/**
	 * Processes digest queue batches.
	 *
	 * @since 3.9.0
	 *
	 * @param string $type      The digest type.
	 * @param string $timestamp The run timestamp.
	 */
	protected function handle_digest_queue( $type, $timestamp ) {
		bpges_log( "Beginning digest batch of type $type for timestamp $timestamp." );

		$option_name = 'bpges_digest_count_' . $type . '_' . $timestamp;
		$total_for_run = (int) bp_get_option( $option_name, 0 );

		$run = true;
		$total_for_batch = 0;

		$processed_usermeta_key = "bpges_processed_digest_{$type}_{$timestamp}";

		do {
			$user_id = BPGES_Queued_Item_Query::get_user_with_pending_digest( $type, $timestamp );

			// Queue is finished.
			if ( ! $user_id ) {
				bpges_log( "Finished digest run of type $type for timestamp $timestamp. Digests were sent to a total of $total_for_run users." );
				bp_delete_option( $option_name );

				// Clean up "processed" flags.
				delete_metadata( 'user', 1, $processed_usermeta_key, '', true );

				// @todo Should we take this opportunity to run a cleanup of other failed 'immediate' items? or maybe on a cron job
				return;
			}

			update_user_meta( $user_id, $processed_usermeta_key, true );
			bpges_process_digest_for_user( $user_id, $type, $timestamp );

			$total_for_batch++;
			$total_for_run++;

			if ( $this->time_exceeded() || $this->memory_exceeded() ) {
				$run = false;
			}
		} while ( $run );

		bpges_log( "Sent $type digests to $total_for_batch users as part of this batch. Launching another batch...." );

		bp_update_option( $option_name, $total_for_run );

		bpges_send_queue()->data( array(
			'type'      => $type,
			'timestamp' => $timestamp,
		) )->dispatch();
	}

	/**
	 * Memory exceeded
	 *
	 * Ensures the batch process never exceeds 90%
	 * of the maximum WordPress memory.
	 *
	 * Copied from WP_Background_Process.
	 *
	 * @since 3.9.0
	 *
	 * @return bool
	 */
	protected function memory_exceeded() {
		$memory_limit   = $this->get_memory_limit() * 0.9; // 90% of max memory
		$current_memory = memory_get_usage( true );
		$return         = false;

		if ( $current_memory >= $memory_limit ) {
			$return = true;
		}

		return apply_filters( $this->identifier . '_memory_exceeded', $return );
	}

	/**
	 * Get memory limit
	 *
	 * Copied from WP_Background_Process.
	 *
	 * @since 3.9.0
	 *
	 * @return int
	 */
	protected function get_memory_limit() {
		if ( function_exists( 'ini_get' ) ) {
			$memory_limit = ini_get( 'memory_limit' );
		} else {
			// Sensible default.
			$memory_limit = '128M';
		}

		if ( ! $memory_limit || -1 === intval( $memory_limit ) ) {
			// Unlimited, set to 32GB.
			$memory_limit = '32000M';
		}

		return intval( $memory_limit ) * 1024 * 1024;
	}

	/**
	 * Time exceeded.
	 *
	 * Ensures the batch never exceeds a sensible time limit.
	 * A timeout limit of 30s is common on shared hosting.
	 *
	 * Copied mostly from WP_Background_Process.
	 *
	 * @since 3.9.0
	 *
	 * @return bool
	 */
	protected function time_exceeded() {
		if ( function_exists( 'ini_get' ) ) {
			$max_execution_time = ini_get( 'max_execution_time' );
		} else {
			// Sensible default.
			$max_execution_time = '30';
		}

		// There is no limit, which means that we can never exceed the limit.
		if ( '0' === $max_execution_time ) {
			return false;
		}

		$finish = $this->start_time + $max_execution_time;
		$return = false;

		$time = time();

		// Don't get within 10 seconds of max time.
		if ( $time >= ( $finish - 10 ) ) {
			$return = true;
		}

		return apply_filters( $this->identifier . '_time_exceeded', $return );
	}
}
