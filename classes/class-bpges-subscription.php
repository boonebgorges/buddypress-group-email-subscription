<?php

/**
 * Subscription database methods.
 *
 * @since 3.9.0
 */
class BPGES_Subscription extends BPGES_Database_Object {
	/**
	 * Returns the table name.
	 *
	 * @since 3.9.0
	 *
	 * @return string
	 */
	protected static function get_table_name() {
		return bp_core_get_table_prefix() . 'bpges_subscriptions';
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
			'id'       => '%d',
			'user_id'  => '%d',
			'group_id' => '%d',
			'type'     => '%s',
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
		return 'bpges_subscriptions';
	}
}
