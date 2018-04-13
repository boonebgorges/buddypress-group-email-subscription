<?php

/**
 * Subscription database methods.
 */
class BPGES_Subscription {
	protected $data = array(
		'id'        => null,
		'user_id'   => null,
		'group_id'  => null,
		'type'      => '',
		'last_sent' => '',
		'items_dig' => array(),
		'items_sum' => array(),
	);

	protected $table_name;

	public function __construct( $id = null ) {
		$this->table_name = bp_core_get_table_prefix() . 'bpges_subscriptions';

		if ( null === $id ) {
			return;
		}

		$this->populate( $id );
	}

	public function __get( $key ) {
		switch ( $key ) {
			case 'id' :
			case 'user_id' :
			case 'group_id' :
				return (int) $this->data[ $key ];

			case 'type' :
			case 'last_sent' :
				return $this->data[ $key ];

			case 'items_dig' :
			case 'items_sum' :
				return array_map( 'intval', $this->data[ $key ] );
		}
	}

	public function __set( $key, $value ) {
		switch ( $key ) {
			case 'id' :
			case 'user_id' :
			case 'group_id' :
				$this->data[ $key ] = (int) $value;
			break;

			case 'type' :
			case 'last_sent' :
				$this->data[ $key ] = $value;
			break;

			case 'items_dig' :
			case 'items_sum' :
				$this->data[ $key ] = array_map( 'intval', $value );
			break;
		}
	}

	public function fill( $vars ) {
		foreach ( $vars as $key => $value ) {
			$this->{$key} = $value;
		}
	}

	protected function populate( $id ) {
		global $wpdb;

		$row = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$this->table_name} WHERE id = %d", $id ) );

		if ( ! $row ) {
			return;
		}

		foreach ( $row as $key => $value ) {
			switch ( $key ) {
				case 'items_dig' :
				case 'items_sum' :
					$this->{$key} = wp_parse_id_list( $value );
				break;

				default :
					$this->{$key} = $value;
				break;
			}
		}
	}

	public function save() {
		global $wpdb;

		$data = array(
			'user_id'   => $this->user_id,
			'group_id'  => $this->group_id,
			'type'      => $this->type,
			'last_sent' => $this->last_sent,
			'items_dig' => implode( ',', $this->items_dig ),
			'items_sum' => implode( ',', $this->items_sum ),
		);

		$formats = array(
			'%d',
			'%d',
			'%s',
			'%s',
			'%s',
			'%s',
		);

		if ( $this->id ) {

		} else {
			$inserted = $wpdb->insert(
				$this->table_name,
				$data,
				$formats
			);

			if ( $inserted ) {
				$retval = (int) $wpdb->insert_id;
			} else {
				$retval = false;
			}
		}

		return $retval;
	}
}
