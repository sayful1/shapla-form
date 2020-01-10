<?php

namespace DialogContactForm\Abstracts;

use DialogContactForm\Supports\Config;
use DialogContactForm\Supports\ContactForm;
use DialogContactForm\Supports\Metabox;

// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

abstract class Action {

	/**
	 * Unique slug for an action
	 *
	 * @var string
	 */
	protected $id;

	/**
	 * Title for an action
	 *
	 * @var string
	 */
	protected $title;

	/**
	 * Icon of the action
	 *
	 * @var string
	 */
	protected $icon;

	/**
	 * Priority of the action
	 *
	 * @var int
	 */
	protected $priority = 100;

	/**
	 * @var array
	 */
	protected $settings = array();

	/**
	 * Meta key name for holding current action settings
	 *
	 * @var string
	 */
	protected $meta_key;

	/**
	 * Meta group for sending metadata to save as action settings
	 *
	 * @var string
	 */
	protected $meta_group;

	/**
	 * Process current action
	 *
	 * @param Config|ContactForm $config Contact form configurations
	 * @param array $data User submitted sanitized data
	 *
	 * @return mixed
	 */
	public static function process( $config, $data ) {
		// TODO: Implement process() method.
	}

	/**
	 * Save action settings
	 *
	 * @param int $post_id
	 * @param null $data
	 */
	public function save( $post_id, $data = null ) {
		if ( func_num_args() === 1 ) {
			$data = isset( $_POST[ $this->meta_group ] ) ? $_POST[ $this->meta_group ] : array();
		}

		if ( ! empty( $data ) ) {
			$sanitize_data = $this->sanitizeSettings( $data );

			update_post_meta( $post_id, $this->meta_key, $sanitize_data );
		} else {
			delete_post_meta( $post_id, $this->meta_key );
		}
	}

	/**
	 * Sanitize action settings
	 *
	 * @param $data
	 *
	 * @return array
	 */
	protected function sanitizeSettings( $data ) {
		$sanitize_data = array();
		foreach ( $this->getSettings() as $setting ) {
			$value = isset( $data[ $setting['id'] ] ) ? $data[ $setting['id'] ] : null;

			if ( is_array( $value ) ) {
				$sanitize_data[ $setting['id'] ] = self::sanitizeArray( $value );
				continue;
			}

			if ( isset( $setting['type'] ) && 'select' == $setting['type'] ) {
				$valid_options = array_keys( $setting['options'] );
				$value         = in_array( $value, $valid_options ) ? $value : $setting['default'];

				$sanitize_data[ $setting['id'] ] = $value;
				continue;
			}

			$sanitize_method = 'sanitize_text_field';
			if ( isset( $setting['sanitize'] ) && is_callable( $setting['sanitize'] ) ) {
				$sanitize_method = $setting['sanitize'];
			}
			$sanitize_data[ $setting['id'] ] = call_user_func( $sanitize_method, $value );
		}

		return $sanitize_data;
	}

	/**
	 * Returns the settings for an action.
	 *
	 * @return array
	 */
	public function getSettings() {
		return $this->settings;
	}

	/**
	 * Sanitize meta value
	 *
	 * @param $input
	 *
	 * @return array|string
	 */
	protected static function sanitizeArray( $input ) {

		if ( is_array( $input ) ) {
			// Initialize the new array that will hold the sanitize values
			$new_input = array();

			// Loop through the input and sanitize each of the values
			foreach ( $input as $key => $value ) {
				if ( is_array( $value ) ) {
					$new_input[ $key ] = self::sanitizeArray( $value );
				} else {
					$new_input[ $key ] = sanitize_text_field( $value );
				}
			}

			return $new_input;
		}

		return sanitize_text_field( $input );
	}

	/**
	 * Get settings as array
	 *
	 * @return array
	 */
	public function toArray() {
		return array(
			'id'       => $this->getId(),
			'title'    => $this->getTitle(),
			'settings' => $this->getSettings()
		);
	}

	/**
	 * Returns the id of an action.
	 *
	 * @return string
	 */
	public function getId() {
		return $this->id;
	}

	/**
	 * Returns the title of an action.
	 *
	 * @return string
	 */
	public function getTitle() {
		return $this->title;
	}

	/**
	 * Get Priority
	 *
	 * Returns the priority for an action.
	 *
	 * @return int
	 */
	public function getPriority() {
		return intval( $this->priority );
	}

	/**
	 * Returns the url of a branded action's image.
	 *
	 * @return string
	 */
	public function getIcon() {
		return $this->icon;
	}

	/**
	 * Get action description
	 *
	 * @return string
	 */
	public function getDescription() {
		return '';
	}

	/**
	 * Get action meta key
	 *
	 * @return string
	 */
	public function getMetaKey() {
		return $this->meta_key;
	}

	/**
	 * Build metabox fields
	 */
	public function buildFields() {
		foreach ( $this->settings as $setting ) {
			$input_type = isset( $setting['type'] ) ? esc_attr( $setting['type'] ) : 'text';

			if ( method_exists( '\\DialogContactForm\\Supports\\Metabox', $input_type ) ) {
				Metabox::$input_type( $setting );
			}
		}
	}
}
