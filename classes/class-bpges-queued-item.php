<?php

/**
 * Queued item database methods.
 *
 * @since 3.9.0
 */
class BPGES_Queued_Item extends BPGES_Database_Object {
	/**
	 * Returns the table name.
	 *
	 * @since 3.9.0
	 *
	 * @return string
	 */
	protected static function get_table_name() {
		return bp_core_get_table_prefix() . 'bpges_queued_items';
	}

	/**
	 * Returns the database table columns.
	 *
	 * @since 3.9.0
	 *
	 * @return array
	 */
	protected function get_columns() {
		return array(
			'id'            => '%d',
			'user_id'       => '%d',
			'group_id'      => '%d',
			'activity_id'   => '%d',
			'type'          => '%s',
			'date_recorded' => '%s',
		);
	}

	/**
	 * Returns the cache group for this item.
	 *
	 * @since 3.9.0
	 *
	 * @return string
	 */
	protected function get_cache_group() {
		return 'bpges_queued_items';
	}

	/**
	 * Insert multiple records in a single command.
	 *
	 * @since 3.9.0
	 *
	 * @param array $data Array of records, in associative array format. Each record should
	 *                    have `user_id`, `group_id`, `activity_id`, `type`, `date_recorded`.
	 */
	public static function bulk_insert( $data ) {
		global $wpdb;

		$values = array();
		foreach ( $data as $d ) {
			$values[] = $wpdb->prepare(
				'(%d, %d, %d, %s, %s)',
				$d['user_id'],
				$d['group_id'],
				$d['activity_id'],
				$d['type'],
				$d['date_recorded']
			);
		}

		$table_name = self::get_table_name();
		$values_sql = implode( ', ', $values );
		$sql = "INSERT IGNORE INTO {$table_name} (user_id, group_id, activity_id, type, date_recorded) VALUES {$values_sql}";
		return $wpdb->query( $sql );
	}

	/**
	 * Bulk deletion.
	 *
	 * @since 3.9.0
	 *
	 * @param array $ids Array of queued-item IDs.
	 */
	public static function bulk_delete( $ids ) {
		global $wpdb;

		$parsed_ids = implode( ',', wp_parse_id_list( $ids ) );
		$table_name = self::get_table_name();
		return $wpdb->query( "DELETE FROM {$table_name} WHERE id IN ({$parsed_ids})" );
	}
}
