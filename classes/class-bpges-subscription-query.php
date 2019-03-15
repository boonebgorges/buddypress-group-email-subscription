<?php

/**
 * Query for subscriptions.
 */
class BPGES_Subscription_Query {
	protected $query_vars;

	public function __construct( $args ) {
		$defaults = array(
			'user_id'    => null,
			'group_id'   => null,
			'type'       => null,
			'date_query' => null,
			'per_page'   => null,
		);

		$this->query_vars = array_merge( $defaults, $args );
	}

	public function get( $key ) {
		return isset( $this->query_vars[ $key ] ) ? $this->query_vars[ $key ] : null;
	}

	public function get_results() {
		global $wpdb;

		$table_name = bp_core_get_table_prefix() . 'bpges_subscriptions';

		$sql = array(
			'select' => "SELECT * FROM $table_name",
			'where' => array(),
			'limits' => '',
			'order' => ' ORDER BY id ASC',
		);

		$user_id = $this->get( 'user_id' );
		if ( ! is_null( $user_id ) ) {
			$sql['where']['user_id'] = $wpdb->prepare( 'user_id = %d', $user_id );
		}

		$group_id = $this->get( 'group_id' );
		if ( ! is_null( $group_id ) ) {
			$sql['where']['group_id'] = $wpdb->prepare( 'group_id = %d', $group_id );
		}

		$type = $this->get( 'type' );
		if ( ! is_null( $type ) ) {
			$sql['where']['type'] = $wpdb->prepare( 'type = %s', $type );
		}

		$where = '';
		if ( $sql['where'] ) {
			$where = ' WHERE ' . implode( ' AND ', $sql['where'] );
		}

		/*
		$page = $this->get( 'page' );
		$per_page = $this->get( 'per_page' );
		if ( $page && $per_page ) {
			$page = intval( $page );
			$per_page = intval( $per_page );
			$start = ( $per_page * ( $page - 1 ) );
			$sql['limits'] = " LIMIT $start, $per_page ";
		}
		*/

		$query = $sql['select'] . $where . $sql['order'] . $sql['limits'];

		$last_changed = wp_cache_get( 'last_changed', 'bpges_subscriptions' );
		if ( ! $last_changed ) {
			$last_changed = microtime();
		}
		$cache_key = md5( $query . $last_changed );

		$results = wp_cache_get( $cache_key, 'bpges_subscriptions' );
		if ( false === $results ) {
			$results = $wpdb->get_results( $query );
			wp_cache_set( $cache_key, $results, 'bpges_subscriptions' );
		}

		$retval = array();

		foreach ( $results as $found ) {
			$sub = new BPGES_Subscription();
			$sub->fill( $found );
			$retval[ $found->id ] = $sub;
		}

		return $retval;
	}
}
