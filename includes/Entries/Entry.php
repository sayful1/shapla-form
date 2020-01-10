<?php

namespace DialogContactForm\Entries;

use ArrayAccess;
use DateTime;
use DateTimeZone;
use DialogContactForm\Supports\ContactForm;
use DialogContactForm\Supports\Utils;
use Exception;
use JsonSerializable;
use wpdb;

// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Entry implements JsonSerializable, ArrayAccess {

	/**
	 * WordPress database
	 *
	 * @var wpdb
	 */
	private $db;

	/**
	 * Entry database table name
	 *
	 * @var string
	 */
	private $table_name;

	/**
	 * Entry meta table name
	 *
	 * @var string
	 */
	private $meta_table_name;

	/**
	 * @var array
	 */
	private $entry = array(
		'id'           => 0,
		'form_id'      => 0,
		'user_id'      => 0,
		'user_ip'      => '127.0.0.1',
		'user_agent'   => null,
		'referer'      => null,
		'status'       => null,
		'created_at'   => null,
		'field_values' => array(),
	);

	/**
	 * Valid entry status
	 *
	 * @var array
	 */
	protected $statuses = [ 'read', 'unread', 'trash' ];

	/**
	 * @var ContactForm
	 */
	private $form;

	/**
	 * Entry constructor.
	 *
	 * @param array $entry
	 */
	public function __construct( $entry = null ) {
		global $wpdb;

		/*
         * Assign Database Tables using the DB prefix
         */
		$this->db              = $wpdb;
		$this->table_name      = $wpdb->prefix . 'dcf_entries';
		$this->meta_table_name = $wpdb->prefix . 'dcf_entry_meta';

		if ( ! is_null( $entry ) ) {
			$this->entry = $entry;
		}
	}

	/**
	 * @param $args
	 *
	 * @return array
	 */
	protected static function get_order_and_pagination_data( $args ) {
		$orderby      = isset( $args['orderby'] ) ? $args['orderby'] : 'created_at';
		$order        = isset( $args['order'] ) ? $args['order'] : 'desc';
		$per_page     = isset( $args['per_page'] ) ? absint( $args['per_page'] ) : 10;
		$current_page = isset( $args['page'] ) ? absint( $args['page'] ) : 1;
		$current_page = $current_page >= 1 ? $current_page : 1;
		$offset       = ( $current_page - 1 ) * $per_page;

		return array( $orderby, $order, $per_page, $offset );
	}

	/**
	 * Does this entry have a given key?
	 *
	 * @param string $key The data key
	 *
	 * @return bool
	 */
	public function has( $key ) {
		return isset( $this->entry[ $key ] );
	}

	/**
	 * Set collection item
	 *
	 * @param string $key The data key
	 * @param mixed $value The data value
	 */
	public function set( $key, $value ) {
		if ( is_null( $key ) ) {
			$this->entry[] = $value;
		} else {
			$this->entry[ $key ] = $value;
		}
	}

	/**
	 * Get entry item for key
	 *
	 * @param string $key The data key
	 * @param mixed $default The default value to return if data key does not exist
	 *
	 * @return mixed The key's value, or the default value
	 */
	public function get( $key, $default = null ) {
		return $this->has( $key ) ? $this->entry[ $key ] : $default;
	}

	/**
	 * Remove item from collection
	 *
	 * @param string $key The data key
	 */
	public function remove( $key ) {
		if ( $this->has( $key ) ) {
			unset( $this->entry[ $key ] );
		}
	}

	/**
	 * Get entry id
	 *
	 * @return int
	 */
	public function getId() {
		return intval( $this->get( 'id' ) );
	}

	/**
	 * Get form id
	 *
	 * @return int
	 */
	public function getFormId() {
		return intval( $this->get( 'form_id' ) );
	}

	/**
	 * Get form class related to entry
	 *
	 * @return ContactForm
	 */
	public function getForm() {
		if ( ! $this->form instanceof ContactForm ) {
			$this->form = new ContactForm( $this->getFormId() );
		}

		return $this->form;
	}

	/**
	 * Get user id
	 *
	 * @return int
	 */
	public function getUserId() {
		return intval( $this->get( 'user_id' ) );
	}

	/**
	 * Get user IP address
	 *
	 * @return int
	 */
	public function getUserIp() {
		return $this->get( 'user_ip' );
	}

	/**
	 * Get user agent
	 *
	 * @return string
	 */
	public function getUserAgent() {
		return $this->get( 'user_agent' );
	}

	/**
	 * Get form referer
	 *
	 * @return string
	 */
	public function getReferer() {
		return site_url( $this->get( 'referer' ) );
	}

	/**
	 * Get status
	 *
	 * @return string
	 */
	public function getStatus() {
		return $this->get( 'status' );
	}

	/**
	 * Get entry creation date
	 *
	 * @return DateTime
	 * @throws Exception
	 */
	public function getCreatedAt() {
		$created_at = $this->get( 'created_at' );
		$created_at = new DateTime( $created_at );

		$timezone_string       = get_option( 'timezone_string' );
		$valid_timezone_string = in_array( $timezone_string, DateTimeZone::listIdentifiers() );

		$gmt_offset = get_option( 'gmt_offset' );
		$hours      = (int) $gmt_offset;
		$minutes    = abs( ( $gmt_offset - (int) $gmt_offset ) * 60 );
		$offset     = sprintf( '%+03d:%02d', $hours, $minutes );

		if ( $valid_timezone_string ) {
			$created_at->setTimezone( new DateTimeZone( $timezone_string ) );
		} else {
			$created_at->setTimezone( new DateTimeZone( $offset ) );
		}

		return $created_at;
	}

	/**
	 * Get field values
	 *
	 * @param string $key
	 * @param mixed $default
	 *
	 * @return mixed
	 */
	public function getFieldValues( $key = null, $default = null ) {
		$field_values = $this->get( 'field_values' );

		if ( ! is_array( $field_values ) ) {
			return array();
		}

		if ( empty( $key ) ) {
			return $field_values;
		}

		return isset( $field_values[ $key ] ) ? $field_values[ $key ] : $default;
	}

	/**
	 * @param $value
	 *
	 * @return string
	 */
	public function formatFieldValue( $value ) {
		if ( empty( $value ) ) {
			return '';
		}
		if ( is_string( $value ) ) {
			return wpautop( $value );
		}

		if ( is_numeric( $value ) ) {
			if ( is_float( $value ) ) {
				return floatval( $value );
			}

			return intval( $value );
		}

		if ( is_object( $value ) ) {
			$value = json_decode( json_encode( $value ), true );
		}

		if ( is_array( $value ) ) {
			foreach ( $value as $v_key => $v_value ) {
				if ( is_string( $v_value ) ) {
					return wpautop( $v_value );
				}

				if ( is_array( $v_value ) ) {
					if ( isset( $v_value['attachment_id'] ) && is_numeric( $v_value['attachment_id'] ) ) {
						$url  = wp_get_attachment_url( $v_value['attachment_id'] );
						$html = '<a href="' . $url . '" target="_blank">';
						$html .= wp_get_attachment_image( $v_value['attachment_id'] );
						$html .= '</a>';

						return $html;
					}

					return implode( '<br>', $v_value );
				}
			}
		}

		return '';
	}

	/**
	 * Get entry data as array
	 *
	 * @return array
	 */
	public function toArray() {
		return array(
			'id'           => $this->getId(),
			'form_id'      => $this->getFormId(),
			'user_id'      => $this->getUserId(),
			'user_ip'      => $this->getUserIp(),
			'user_agent'   => $this->getUserAgent(),
			'referer'      => $this->getReferer(),
			'status'       => $this->getStatus(),
			'created_at'   => $this->getCreatedAt()->format( 'Y-m-d\TH:i:s' ),
			'field_values' => $this->getFieldValues(),
		);
	}

	/**
	 * Find entries
	 *
	 * @param array $args
	 *
	 * @return self[]
	 */
	public function find( $args = array() ) {
		list( $orderby, $order, $per_page, $offset ) = self::get_order_and_pagination_data( $args );

		$form_id = isset( $args['form_id'] ) ? intval( $args['form_id'] ) : 0;
		$status  = isset( $args['status'] ) ? $args['status'] : 'all';
		$search  = ! empty( $args['search'] ) ? esc_sql( $args['search'] ) : null;

		$sql = "SELECT * FROM {$this->table_name}";

		if ( ! empty( $search ) ) {
			$sql .= " RIGHT JOIN {$this->meta_table_name} ON {$this->table_name}.id = {$this->meta_table_name}.entry_id";
		}

		$sql .= " WHERE 1 = 1";

		if ( $form_id ) {
			$sql .= $this->db->prepare( " AND form_id = %d", $form_id );
		}

		if ( in_array( $status, $this->statuses ) ) {
			$sql .= $this->db->prepare( " AND status = %s", $args['status'] );
		} else {
			$sql .= $this->db->prepare( " AND status != %s", 'trash' );
		}

		if ( ! empty( $search ) ) {
			$sql .= $this->db->prepare( " AND {$this->meta_table_name}.meta_value LIKE %s", '%' . $search . '%' );
			$sql .= "GROUP BY {$this->meta_table_name}.entry_id";
		}

		$sql .= " ORDER BY {$orderby} {$order}";
		$sql .= " LIMIT $per_page OFFSET $offset";

		$items = $this->db->get_results( $sql, ARRAY_A );

		if ( ! $items ) {
			return array();
		}

		$ids     = Utils::array_column( $items, 'id' );
		$ids     = implode( ',', $ids );
		$sql     = "SELECT * FROM {$this->meta_table_name} WHERE entry_id IN($ids)";
		$entries = $this->db->get_results( $sql, ARRAY_A );

		$_meta = array();
		foreach ( $entries as $entry ) {
			if ( empty( $entry['meta_key'] ) ) {
				continue;
			}
			$_meta[ $entry['entry_id'] ][ $entry['meta_key'] ] = $this->unserialize( $entry['meta_value'] );
		}

		$data = array();
		foreach ( $items as $item ) {
			$_data  = $item + array( 'field_values' => $_meta[ $item['id'] ] );
			$data[] = new self( $_data );
		}

		return $data;
	}

	/**
	 * Get entry by entry ID
	 *
	 * @param int $entry_id
	 *
	 * @return Entry|false
	 */
	public function findById( $entry_id ) {
		$items = $this->db->get_row( $this->db->prepare(
			"SELECT * FROM {$this->table_name} WHERE id = %d", $entry_id ),
			ARRAY_A
		);

		$entries = $this->db->get_results( $this->db->prepare(
			"SELECT * FROM {$this->meta_table_name} WHERE entry_id = %d", $entry_id ),
			ARRAY_A
		);

		if ( ! $items ) {
			return false;
		}

		$_meta = array();
		foreach ( $entries as $entry ) {
			if ( empty( $entry['meta_key'] ) ) {
				continue;
			}
			$_meta[ $entry['meta_key'] ] = $this->unserialize( $entry['meta_value'] );
		}

		$_entries = $items + array( 'field_values' => $_meta );

		return new self( $_entries );
	}

	/**
	 * @param array $data
	 *
	 * @return int
	 */
	public function create( array $data ) {
		$_data = array(
			'form_id'    => intval( $data['form_id'] ),
			'user_id'    => get_current_user_id(),
			'user_ip'    => Utils::get_remote_ip(),
			'user_agent' => Utils::get_user_agent(),
			'referer'    => ! empty( $data['referer'] ) ? sanitize_text_field( $data['referer'] ) : '',
			'status'     => 'unread',
			'created_at' => current_time( 'mysql' ),
		);
		$this->db->insert( $this->table_name, $_data );

		$insert_id = $this->db->insert_id;
		if ( $insert_id ) {

			$meta_data = array();
			foreach ( $data as $key => $value ) {
				if ( in_array( $key, array_keys( $_data ) ) ) {
					continue;
				}
				$meta_data[] = array(
					'entry_id'   => $insert_id,
					'meta_key'   => $key,
					'meta_value' => $this->serialize( $value ),
				);
			}

			$this->insertMultipleRows( $this->meta_table_name, $meta_data );
		}

		return $insert_id;
	}

	/**
	 * Insert a row into a entries table with meta values.
	 *
	 * @param array $data Form data in $key => 'value' format
	 * array( $key1 => 'value 1', $key2 => 'value 2' )
	 *
	 * @return int last insert id
	 */
	public function insert( $data ) {
		$current_time = current_time( 'mysql' );
		$this->db->insert( $this->table_name, $this->getMetaInfo( $current_time ) );

		$insert_id = $this->db->insert_id;
		if ( $insert_id ) {

			$_data = array();
			foreach ( $data as $key => $value ) {
				$_data[] = array(
					'entry_id'   => $insert_id,
					'meta_key'   => $key,
					'meta_value' => $this->serialize( $value ),
				);
			}

			$this->insertMultipleRows( $this->meta_table_name, $_data );
		}

		return $insert_id;
	}

	/**
	 * @param array $data
	 * @param array $where
	 * @param string|array $format
	 * @param string|array $where_format
	 */
	public function update( $data, $where, $format = null, $where_format = null ) {
		$this->db->update( $this->table_name, $data, $where, $format, $where_format );
	}

	/**
	 * Mark entry as read
	 *
	 * @param int $id
	 */
	public function mark_as_read( $id ) {
		$this->update( [ 'status' => 'read' ], [ 'id' => $id ], '%s', '%d' );
	}

	/**
	 * Mark entry as unread
	 *
	 * @param int $id
	 */
	public function mark_as_unread( $id ) {
		$this->update( [ 'status' => 'unread' ], [ 'id' => $id ], '%s', '%d' );
	}

	/**
	 * Delete entry
	 *
	 * @param int $entry_id
	 *
	 * @return bool
	 */
	public function delete( $entry_id = 0 ) {
		$result = $this->db->delete( $this->table_name, array( 'id' => $entry_id ), '%d' );

		if ( false === $result ) {
			return false;
		}
		$this->db->delete( $this->meta_table_name, array( 'entry_id' => $entry_id ), '%d' );

		return true;
	}

	/**
	 * Get form meta information
	 *
	 * @param null $current_time
	 *
	 * @return array
	 */
	private function getMetaInfo( $current_time = null ) {
		if ( ! $current_time ) {
			$current_time = current_time( 'mysql' );
		}

		return array(
			'form_id'    => Utils::get_form_id(),
			'user_id'    => get_current_user_id(),
			'user_ip'    => Utils::get_remote_ip(),
			'user_agent' => Utils::get_user_agent(),
			'referer'    => Utils::get_referer(),
			'status'     => 'unread',
			'created_at' => $current_time,
		);
	}

	/**
	 * Get form entries counts
	 *
	 * @param int $form_id
	 *
	 * @return array
	 */
	public static function get_form_entries_counts( $form_id ) {
		$self    = new static();
		$table   = $self->table_name;
		$query   = "SELECT status, COUNT( * ) AS num_entries FROM {$table} WHERE 1 = 1";
		$query   .= $self->db->prepare( " AND form_id = %d", $form_id );
		$query   .= " GROUP BY status";
		$results = $self->db->get_results( $query, ARRAY_A );

		$default_count = array( 'unread' => 0, 'read' => 0, 'trash' => 0, );

		$counts = array();
		foreach ( $results as $row ) {
			$counts[ $row['status'] ] = intval( $row['num_entries'] );
		}

		$counts        = wp_parse_args( $counts, $default_count );
		$counts['all'] = ( $counts['unread'] + $counts['read'] );

		return $counts;
	}

	/**
	 * Count form entries
	 *
	 * @return array
	 */
	public static function count_entries() {
		$self  = new static();
		$table = $self->table_name;

		$query   = "SELECT form_id, COUNT( * ) AS num_entries";
		$query   .= " FROM {$table} WHERE status != 'trash' GROUP BY form_id";
		$results = $self->db->get_results( $query, ARRAY_A );

		$counts = array();
		foreach ( $results as $row ) {
			$counts[ $row['form_id'] ] = intval( $row['num_entries'] );
		}

		return $counts;
	}

	/**
	 * Serialize data, if needed.
	 *
	 * @param string|array|object $data Data that might be serialized.
	 *
	 * @return mixed
	 */
	private function serialize( $data ) {
		if ( is_array( $data ) || is_object( $data ) ) {
			return serialize( $data );
		}

		return $data;
	}

	/**
	 * Unserialize value only if it was serialized.
	 *
	 * @param string $original Maybe unserialized original, if is needed.
	 *
	 * @return mixed Unserialized data can be any type.
	 */
	private function unserialize( $original ) {
		// don't attempt to unserialize data that wasn't serialized going in
		if ( is_serialized( $original ) ) {
			return @unserialize( $original );
		}

		return $original;
	}

	/**
	 *  A method for inserting multiple rows into the specified table
	 *  Updated to include the ability to Update existing rows by primary key
	 *
	 *  Usage Example for insert:
	 *
	 *  $insert_arrays = array();
	 *  foreach($assets as $asset) {
	 *  $time = current_time( 'mysql' );
	 *  $insert_arrays[] = array(
	 *  'type' => "multiple_row_insert",
	 *  'status' => 1,
	 *  'name'=>$asset,
	 *  'added_date' => $time,
	 *  'last_update' => $time);
	 *
	 *  }
	 *
	 *
	 *  $this->_insert_rows( $table_name, $data );
	 *
	 *  Usage Example for update:
	 *
	 *  $this->_insert_rows( $table_name, $data, true, "primary_column" );
	 *
	 *
	 * @param string $table_name
	 * @param array $data
	 * @param boolean $update
	 * @param string $primary_key
	 *
	 * @return false|int
	 */
	private function insertMultipleRows( $table_name, $data = array(), $update = false, $primary_key = null ) {
		global $wpdb;
		$table_name = esc_sql( $table_name );
		// Setup arrays for Actual Values, and Placeholders
		$values        = array();
		$place_holders = array();
		$query         = "";
		$query_columns = "";

		$query .= "INSERT INTO `{$table_name}` (";
		foreach ( $data as $count => $row_array ) {
			foreach ( $row_array as $key => $value ) {
				if ( $count == 0 ) {
					if ( $query_columns ) {
						$query_columns .= ", " . $key . "";
					} else {
						$query_columns .= "" . $key . "";
					}
				}

				$values[] = $value;

				$symbol = "%s";
				if ( is_numeric( $value ) ) {
					if ( is_float( $value ) ) {
						$symbol = "%f";
					} else {
						$symbol = "%d";
					}
				}
				if ( isset( $place_holders[ $count ] ) ) {
					$place_holders[ $count ] .= ", '$symbol'";
				} else {
					$place_holders[ $count ] = "( '$symbol'";
				}
			}
			// mind closing the GAP
			$place_holders[ $count ] .= ")";
		}

		$query .= " $query_columns ) VALUES ";

		$query .= implode( ', ', $place_holders );

		if ( $update ) {
			$update = " ON DUPLICATE KEY UPDATE $primary_key=VALUES( $primary_key ),";
			$cnt    = 0;
			foreach ( $data[0] as $key => $value ) {
				if ( $cnt == 0 ) {
					$update .= "$key=VALUES($key)";
					$cnt    = 1;
				} else {
					$update .= ", $key=VALUES($key)";
				}
			}
			$query .= $update;
		}

		$sql = $wpdb->prepare( $query, $values );
		if ( $wpdb->query( $sql ) ) {
			return true;
		} else {
			return false;
		}
	}

	/**
	 * Get entry table columns label
	 *
	 * @return array
	 */
	public static function get_columns_label() {
		return [
			'id'         => __( 'ID', 'dialog-contact-form' ),
			'form_id'    => __( 'Form ID', 'dialog-contact-form' ),
			'user_id'    => __( 'User ID', 'dialog-contact-form' ),
			'user_ip'    => __( 'User IP', 'dialog-contact-form' ),
			'user_agent' => __( 'User Agent', 'dialog-contact-form' ),
			'referer'    => __( 'Referer', 'dialog-contact-form' ),
			'status'     => __( 'Status', 'dialog-contact-form' ),
			'created_at' => __( 'Date', 'dialog-contact-form' ),
		];
	}

	/********************************************************************************
	 * JsonSerializable interface
	 *******************************************************************************/

	/**
	 * Specify data which should be serialized to JSON
	 * @link http://php.net/manual/en/jsonserializable.jsonserialize.php
	 * @return mixed data which can be serialized by <b>json_encode</b>,
	 * which is a value of any type other than a resource.
	 * @since 5.4.0
	 */
	public function jsonSerialize() {
		return $this->toArray();
	}

	/********************************************************************************
	 * ArrayAccess interface
	 *******************************************************************************/

	/**
	 * Whether a offset exists
	 * @link http://php.net/manual/en/arrayaccess.offsetexists.php
	 *
	 * @param mixed $offset An offset to check for.
	 *
	 * @return boolean true on success or false on failure.
	 * @since 5.0.0
	 */
	public function offsetExists( $offset ) {
		return $this->has( $offset );
	}

	/**
	 * Offset to retrieve
	 * @link http://php.net/manual/en/arrayaccess.offsetget.php
	 *
	 * @param mixed $offset The offset to retrieve.
	 *
	 * @return mixed Can return all value types.
	 * @since 5.0.0
	 */
	public function offsetGet( $offset ) {
		return $this->get( $offset );
	}

	/**
	 * Offset to set
	 * @link http://php.net/manual/en/arrayaccess.offsetset.php
	 *
	 * @param mixed $offset The offset to assign the value to.
	 * @param mixed $value The value to set.
	 *
	 * @return void
	 * @since 5.0.0
	 */
	public function offsetSet( $offset, $value ) {
		$this->set( $offset, $value );
	}

	/**
	 * Offset to unset
	 * @link http://php.net/manual/en/arrayaccess.offsetunset.php
	 *
	 * @param mixed $offset The offset to unset.
	 *
	 * @return void
	 * @since 5.0.0
	 */
	public function offsetUnset( $offset ) {
		$this->remove( $offset );
	}
}
