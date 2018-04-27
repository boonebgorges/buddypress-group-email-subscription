<?php

class BPGES_Async_Request_Send_Queue extends WP_Async_Request {
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
	 * Migrate a batch of outgoing notifications.
	 *
	 * @since 3.9.0
	 */
	protected function handle() {
		// Nothing to do.
		if ( ! isset( $_POST['type'] ) ) {
			return;
		}

		$this->start_time = time();

		$queue_type = wp_unslash( $_POST['type'] );

		switch ( $queue_type ) {
			case 'immediate' :
				if ( ! isset( $_POST['activity_id'] ) ) {
					return;
				}

				$activity_id = (int) $_POST['activity_id'];
				$this->handle_immediate_queue( $activity_id );
			break;

		}
	}

	protected function handle_immediate_queue( $activity_id ) {

		$run = true;
		$i = 1;
		do {
			$num_queries = $GLOBALS['wpdb']->num_queries;
			$i++;
			$query = new BPGES_Queued_Item_Query( array(
				'activity_id' => $activity_id,
				'per_page'    => 1,
			) );

			$items = $query->get_results();

			// Queue is finished.
			if ( ! $items ) {
				// @todo Should we take this opportunity to run a cleanup of other failed 'immediate' items? or maybe on a cron job
				return;
			}

			foreach ( $items as $item ) {
				bpges_generate_notification( $item );
				$item->delete();
			}

			if ( $this->time_exceeded() || $this->memory_exceeded() ) {
				$run = false;
			}
		} while ( $run );

		bpges_send_queue()->data( array(
			'type'        => 'immediate',
			'activity_id' => $activity_id,
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
	 * @return bool
	 */
	protected function time_exceeded() {
		if ( function_exists( 'ini_get' ) ) {
			$max_execution_time = ini_get( 'max_execution_time' );
		} else {
			// Sensible default.
			$max_execution_time = '30';
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
