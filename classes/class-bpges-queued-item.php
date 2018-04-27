<?php

/**
 * Queued item database methods.
 */
class BPGES_Queued_Item extends BPGES_Database_Object {
	protected static function get_table_name() {
		return bp_core_get_table_prefix() . 'bpges_queued_items';
	}

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
	 * Insert multiple records in a single command.
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
		$sql = "INSERT INTO {$table_name} (user_id, group_id, activity_id, type, date_recorded) VALUES {$values_sql}";
		$wpdb->query( $sql );
	}
}
