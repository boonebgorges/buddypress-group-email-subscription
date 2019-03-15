<?php

/**
 * Abstract class for BPGES database objects.
 */
abstract class BPGES_Database_Object {
	/**
	 * @var   array
	 * @since 3.9.0
	 */
	protected $data = array();

	/**
	 * @var   array
	 * @since 3.9.0
	 */
	protected static $table_name;

	/**
	 * Returns the table name.
	 *
	 * @since 3.9.0
	 *
	 * @return string
	 */
	protected static function get_table_name() {}

	/**
	 * Returns the database table columns.
	 *
	 * @since 3.9.0
	 *
	 * @return array
	 */
	abstract protected function get_columns();

	/**
	 * Returns the cache group for the object.
	 *
	 * @since 3.9.0
	 *
	 * @return string
	 */
	abstract protected function get_cache_group();

	/**
	 * Constructor.
	 *
	 * @since 3.9.0
	 *
	 * @param int $id Optional. Primary ID of the item.
	 */
	public function __construct( $id = null ) {
		$this->table_name = static::get_table_name();

		$cols = $this->get_columns();
		foreach ( $cols as $col => $col_type ) {
			$this->{$col} = null;
		}

		if ( null === $id ) {
			return;
		}

		$this->populate( $id );
	}

	/**
	 * Gets an object property.
	 *
	 * @since 3.9.0
	 *
	 * @param string $key Property name.
	 * @return mixed
	 */
	public function __get( $key ) {
		$cols = $this->get_columns();
		$col_type = isset( $cols[ $key ] ) ? $cols[ $key ] : '%s';

		if ( 'table_name' === $key ) {
			return static::get_table_name();
		}

		switch ( $col_type ) {
			case '%d' :
				return (int) $this->data[ $key ];

			default :
				return $this->data[ $key ];
		}
	}

	/**
	 * Sets an object property.
	 *
	 * @since 3.9.0
	 *
	 * @param string $key   Property key.
	 * @param mixed  $value Value.
	 */
	public function __set( $key, $value ) {
		$cols = $this->get_columns();
		$col_type = isset( $cols[ $key ] ) ? $cols[ $key ] : '%s';

		if ( 'table_name' === $key ) {
			static::$table_name = $value;
		}

		switch ( $key ) {
			case '%d' :
				$this->data[ $key ] = (int) $value;
			break;

			default :
				$this->data[ $key ] = $value;
			break;
		}
	}

	/**
	 * Fills the object based on vars pulled from the database query.
	 *
	 * @since 3.9.0
	 *
	 * @param array|object Object data.
	 */
	public function fill( $vars ) {
		foreach ( $vars as $key => $value ) {
			$this->{$key} = $value;
		}
	}

	/**
	 * Populates the object based on ID.
	 *
	 * @since 3.9.0
	 *
	 * @param int $id Primary object ID.
	 */
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

	/**
	 * Saves the object to the database.
	 *
	 * @since 3.9.0
	 *
	 * @return bool
	 */
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

		wp_cache_delete( 'last_changed', $this->get_cache_group() );

		return $retval;
	}

	/**
	 * Deletes the object from the database.
	 *
	 * @since 3.9.0
	 *
	 * @return bool
	 */
	public function delete() {
		global $wpdb;

		$deleted = $wpdb->delete(
			$this->table_name,
			array( 'id' => $this->id ),
			array( '%d' )
		);

		wp_cache_delete( 'last_changed', $this->get_cache_group() );

		return (bool) $deleted;
	}
}
