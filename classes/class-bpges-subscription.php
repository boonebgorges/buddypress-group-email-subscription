<?php

/**
 * Subscription database methods.
 */
class BPGES_Subscription extends BPGES_Database_Object {
	protected function get_table_name() {
		return bp_core_get_table_prefix() . 'bpges_subscriptions';
	}

	protected function get_columns() {
		return array(
			'id'       => '%d',
			'user_id'  => '%d',
			'group_id' => '%d',
			'type'     => '%s',
		);
	}
}
