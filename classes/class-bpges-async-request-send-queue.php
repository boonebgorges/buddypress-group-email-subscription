<?php

class BPGES_Async_Request_Send_Queue extends WP_Async_Request {
	/**
	 * @var   string
	 * @since 3.9.0
	 */
	protected $action = 'bpges_send_queue';

	/**
	 * @var   int
	 * @since 3.9.0
	 */
	protected $batch_size;

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

		/**
		 * Filters the size of queue batches.
		 *
		 * @since 3.9.0
		 *
		 * @param int $batch_size Batch size, in number of users.
		 * @param string $queue_type Type of queue. 'immediate', 'dig', or 'sum'.
		 */
		$this->batch_size = apply_filters( 'bpges_send_queue_batch_size', 2, $queue_type );

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
		$query = new BPGES_Queued_Item_Query( array(
			'activity_id' => $activity_id,
			'per_page'    => $this->batch_size,
		) );

		$items = $query->get_results();

		// Queue is finished.
		if ( ! $items ) {
			return;
		}

		foreach ( $items as $item ) {
			bpges_generate_notification( $item );
			$item->delete();
		}

		bpges_send_queue()->data( array(
			'type'        => 'immediate',
			'activity_id' => $activity_id,
		) )->dispatch();
	}
}
