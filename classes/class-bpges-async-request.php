<?php

class BPGES_Async_Request extends WP_Async_Request {
	public function __construct() {
		parent::__construct();

		add_filter( 'http_request_args', array( $this, 'maybe_filter_http_request_args' ), 10, 2 );
	}

	public function maybe_filter_http_request_args( $args, $url ) {
		$query = parse_url( $url, PHP_URL_QUERY );

		if ( empty( $query ) ) {
			return $args;
		}

		parse_str( $query, $parts );
		if ( ! isset( $parts['action'] ) ) {
			return $args;
		}

		if ( $parts['action'] !== $this->identifier ) {
			return $args;
		}

		$args['redirection'] = '10';
		$args['timeout']     = '10';

		/**
		 * Filters the args used for a BPGES HTTP request.
		 *
		 * @since 3.9.0
		 *
		 * @param array $args See 'http_request_args'.
		 */
		return apply_filters( 'bpges_async_request_http_request_args', $args );
	}

	protected function handle() {}
}
