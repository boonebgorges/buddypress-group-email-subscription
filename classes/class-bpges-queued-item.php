<?php

/**
 * Queued item database methods.
 */
class BPGES_Queued_Item extends BPGES_Database_Object {
	protected function get_table_name() {
		return bp_core_get_table_prefix() . 'bpges_queued_items';
	}

	protected function get_columns() {
		return array(
			'id'            => '%d',
			'user_id'       => '%d',
			'activity_id'   => '%d',
			'type'          => '%s',
			'date_recorded' => '%s',
		);
	}
}
