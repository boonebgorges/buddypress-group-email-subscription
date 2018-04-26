<?php

/**
 * Abstract class for BPGES database objects.
 */
abstract class BPGES_Database_Object {
	protected $data = array();
	protected $table_name;

	abstract protected function get_table_name();
	abstract protected function get_columns();

	public function __construct( $id = null ) {
		$this->table_name = $this->get_table_name();

		$cols = $this->get_columns();
		foreach ( $cols as $col => $col_type ) {
			$this->{$col} = null;
		}

		if ( null === $id ) {
			return;
		}

		$this->populate( $id );
	}

	public function __get( $key ) {
		$cols = $this->get_columns();
		$col_type = $cols[ $key ];

		switch ( $col_type ) {
			case '%d' :
				return (int) $this->data[ $key ];

			default :
				return $this->data[ $key ];
		}
	}

	public function __set( $key, $value ) {
		$cols = $this->get_columns();
		$col_type = $cols[ $key ];

		switch ( $key ) {
			case '%d' :
				$this->data[ $key ] = (int) $value;
			break;

			default :
				$this->data[ $key ] = $value;
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
			$this->{$key} = $value;
		}
	}

	public function save() {
		global $wpdb;

		$cols = $this->get_columns();

		$data = $formats = array();
		foreach ( $cols as $col => $col_type ) {
			$data[ $col ] = $this->{$col};
			$formats[] = $col_type;
		}

		if ( $this->id ) {
			$updated = $wpdb->update(
				$this->table_name,
				$data,
				array( 'id' => $this->id ),
				$formats,
				array( '%d' )
			);

			$retval = (bool) $updated;
		} else {
			$inserted = $wpdb->insert(
				$this->table_name,
				$data,
				$formats
			);

			if ( $inserted ) {
				$retval = (int) $wpdb->insert_id;
				$this->id = $retval;
			} else {
				$retval = false;
			}
		}

		return $retval;
	}

	public function delete() {
		global $wpdb;

		$deleted = $wpdb->delete(
			$this->table_name,
			array( 'id' => $this->id ),
			array( '%d' )
		);

		return (bool) $deleted;
	}
}
