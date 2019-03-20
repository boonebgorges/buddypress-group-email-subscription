<?php

/**
 * Query for queued items.
 */
class BPGES_Queued_Item_Query {
	protected $query_vars;

	public function __construct( $args ) {
		$defaults = array(
			'user_id'     => null,
			'group_id'    => null,
			'activity_id' => null,
			'type'        => null,
			'before'      => null,
			'per_page'    => null,
			'paged'       => 1,
		);

		$this->query_vars = array_merge( $defaults, $args );
	}

	public function get( $key ) {
		return isset( $this->query_vars[ $key ] ) ? $this->query_vars[ $key ] : null;
	}

	public function get_results() {
		global $wpdb;

		$table_name = bp_core_get_table_prefix() . 'bpges_queued_items';

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

		$activity_id = $this->get( 'activity_id' );
		if ( ! is_null( $activity_id ) ) {
			$sql['where']['activity_id'] = $wpdb->prepare( 'activity_id = %d', $activity_id );
		}

		$type = $this->get( 'type' );
		if ( ! is_null( $type ) ) {
			$sql['where']['type'] = $wpdb->prepare( 'type = %s', $type );
		}

		$before = $this->get( 'before' );
		if ( ! is_null( $before ) ) {
			$sql['where']['before'] = $wpdb->prepare( 'date_recorded < %s', $before );
		}

		$where = '';
		if ( $sql['where'] ) {
			$where = ' WHERE ' . implode( ' AND ', $sql['where'] );
		}

		$page     = $this->get( 'paged' );
		$per_page = $this->get( 'per_page' );
		if ( $page && $per_page ) {
			$page     = intval( $page );
			$per_page = intval( $per_page );

			$start = ( $per_page * ( $page - 1 ) );
			$sql['limits'] = " LIMIT $start, $per_page ";
		}

		$query = $sql['select'] . $where . $sql['order'] . $sql['limits'];
		$results = $wpdb->get_results( $query );

		$retval = array();

		foreach ( $results as $found ) {
			$item = new BPGES_Queued_Item();
			$item->fill( $found );
			$retval[ $found->id ] = $item;
		}

		return $retval;
	}

	/**
	 * Get the ID of a user who has pending digest items of a given type.
	 *
	 * We look only at those items added to the queue before the $timestamp, in case
	 * items are queued while a digest is being processed.
	 *
	 * @param string $type      Digest type.
	 * @param string $timestamp Digest run timestamp, in Y-m-d H:i:s format.
	 * @return int
	 */
	public static function get_user_with_pending_digest( $type, $timestamp ) {
		$user_ids = self::get_users_with_pending_digest( $type, 1, $timestamp );
		return reset( $user_ids );
	}

	/**
	 * Get the IDs of users with pending digest items of a given type.
	 *
	 * @param string $type      Digest type.
	 * @param int    $count     Max number of users to return.
	 * @param string $timestamp Digest run timestamp, in Y-m-d H:i:s format.
	 * @return array
	 */
	public static function get_users_with_pending_digest( $type, $count, $timestamp ) {
		global $wpdb;

		$processed_usermeta_key = "bpges_processed_digest_{$type}_{$timestamp}";
		$processed_user_ids_raw = $wpdb->get_col( $wpdb->prepare( "SELECT user_id FROM {$wpdb->usermeta} WHERE meta_key = %s", $processed_usermeta_key ) );

		if ( $processed_user_ids_raw ) {
			$processed_user_ids = implode( ',', array_map( 'intval', $processed_user_ids_raw ) );
		} else {
			$processed_user_ids = '0';
		}

		$table_name = bp_core_get_table_prefix() . 'bpges_queued_items';
		$user_ids = $wpdb->get_col( $wpdb->prepare( "SELECT DISTINCT user_id FROM {$table_name} WHERE type = %s AND date_recorded < %s AND user_id NOT IN ({$processed_user_ids}) LIMIT %d", $type, $timestamp, $count ) );

		return array_map( 'intval', $user_ids );
	}
}
