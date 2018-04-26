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
			'date_query'  => null,
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
}
