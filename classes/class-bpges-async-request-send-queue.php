<?php

class BPGES_Async_Request_Send_Queue extends WP_Async_Request {
	/**
	 * @var   string
	 * @since 3.9.0
	 */
	protected $action = 'bpges_send_queue';

	/**
	 * Migrate a batch of outgoing notifications.
	 *
	 * @since 3.9.0
	 */
	protected function handle() {
		$batch_count = 2;
	}

}
