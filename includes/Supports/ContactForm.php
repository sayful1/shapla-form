<?php

namespace DialogContactForm\Supports;

use DialogContactForm\Abstracts\Action;
use DialogContactForm\Abstracts\Field;
use DialogContactForm\Collections\Actions;
use DialogContactForm\Collections\Fields;

// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class ContactForm {

	const POST_TYPE = 'dialog-contact-form';

	/**
	 * @var int
	 */
	protected static $found_items = 0;

	/**
	 * @var int
	 */
	protected $id = 0;

	/**
	 * @var string
	 */
	protected $title;

	/**
	 * Global form options
	 *
	 * @var array
	 */
	protected $options = array();

	/**
	 * Form validation messages
	 *
	 * @var array
	 */
	protected $validation_messages = array();

	/**
	 * Current form fields
	 *
	 * @var array
	 */
	protected $form_fields = array();

	/**
	 * Current form settings
	 *
	 * @var array
	 */
	protected $form_settings = array();

	/**
	 * List of supported actions
	 *
	 * @var array
	 */
	protected $form_actions = array();

	/**
	 * List of field types for current form
	 *
	 * @var array
	 */
	protected $field_types = array();

	/**
	 * Check current form has file field
	 *
	 * @var bool
	 */
	protected $has_file = false;

	/**
	 * Check if reCAPTCHA is enabled
	 *
	 * @var bool
	 */
	protected $has_recaptcha = false;

	/**
	 * MailChimp API key
	 *
	 * @var string
	 */
	protected $mailchimp_api_key = '';

	/**
	 * If form should reset after successful submission
	 *
	 * @var bool
	 */
	protected $reset_form = true;

	/**
	 * ContactForm constructor.
	 *
	 * @param int|\WP_Post|null $post Optional. Post ID or post object.
	 */
	public function __construct( $post = null ) {

		if ( ! $this->options ) {
			$this->options = Utils::get_option();
		}

		if ( ! $this->validation_messages ) {
			$default_messages = Utils::validation_messages();
			$messages         = array();
			foreach ( $default_messages as $key => $message ) {
				if ( ! empty( $this->options[ $key ] ) ) {
					$messages[ $key ] = $this->options[ $key ];
					unset( $this->options[ $key ] );
					continue;
				}
				$messages[ $key ] = $message;
			}

			$this->validation_messages = $messages;
		}

		$post = get_post( $post );

		if ( $post && self::POST_TYPE == get_post_type( $post ) ) {
			$this->id    = $post->ID;
			$this->title = $post->post_title;

			$this->form_settings = (array) get_post_meta( $this->id, '_contact_form_config', true );

			$fieldCollections = Fields::init();
			$form_fields      = (array) get_post_meta( $this->id, '_contact_form_fields', true );
			foreach ( $form_fields as $form_field ) {
				$type      = isset( $form_field['field_type'] ) ? $form_field['field_type'] : null;
				$className = $fieldCollections->get( $type );
				$field     = new $className;
				if ( ! $field instanceof Field ) {
					continue;
				}
				$field->setField( $form_field );
				$field->setFormId( $this->getId() );
				$this->form_fields[] = $field;
			}

			$form_actions = (array) get_post_meta( $this->id, '_contact_form_actions', true );
			if ( empty( $form_actions ) ) {
				$form_actions = array( 'email_notification', 'success_message', 'redirect' );
			}
			$this->form_actions = $form_actions;

			$messages = (array) get_post_meta( $this->id, '_contact_form_messages', true );
			foreach ( $messages as $message_key => $message ) {
				if ( ! empty( $message ) ) {
					$this->validation_messages[ $message_key ] = $message;
				}
			}

			// Check if current form has file
			if ( $this->form_fields ) {
				$this->field_types = array_unique( Utils::array_column( $this->form_fields, 'field_type' ) );

				if ( in_array( 'file', $this->field_types ) ) {
					$this->has_file = true;
				}
			}

			// Check if reCAPTCHA is enabled
			if ( 'yes' === $this->getSetting( 'recaptcha' ) ) {
				$this->has_recaptcha = true;
			}

			// If form should reset after submission
			if ( 'no' === $this->getSetting( 'reset_form' ) ) {
				$this->reset_form = false;
			}
		}
	}

	/**
	 * Get forms
	 *
	 * @param array $args
	 *
	 * @return array
	 */
	public static function find( $args = array() ) {
		$defaults = array(
			'post_status'    => 'any',
			'posts_per_page' => - 1,
			'offset'         => 0,
			'orderby'        => 'ID',
			'order'          => 'ASC',
		);

		$args = wp_parse_args( $args, $defaults );

		$args['post_type'] = self::POST_TYPE;

		$q     = new \WP_Query();
		$posts = $q->query( $args );

		self::$found_items = $q->found_posts;

		$forms = array();

		foreach ( (array) $posts as $post ) {
			$forms[] = new self( $post );
		}

		return $forms;
	}

	/**
	 * Get form counts
	 *
	 * @return array
	 */
	public static function get_counts() {
		global $wpdb;
		$query = $wpdb->prepare(
			"SELECT post_status, COUNT( * ) AS num_posts FROM {$wpdb->posts} WHERE post_type = %s",
			self::POST_TYPE
		);
		$query .= ' GROUP BY post_status';

		$results = (array) $wpdb->get_results( $query, ARRAY_A );
		$counts  = array_fill_keys( [ 'publish', 'trash' ], 0 );

		foreach ( $results as $row ) {
			$counts[ $row['post_status'] ] = intval( $row['num_posts'] );
		}

		return $counts;
	}

	/**
	 * Update current form
	 *
	 * @param int $form_id
	 * @param array $data
	 */
	public static function update( $form_id, array $data ) {

		// Update form settings
		if ( isset( $data['config'] ) && is_array( $data['config'] ) ) {
			update_post_meta( $form_id, '_contact_form_config', self::sanitize_value( $data['config'] ) );
		}

		// Update form validation messages
		if ( isset( $data['messages'] ) && is_array( $data['messages'] ) ) {
			update_post_meta( $form_id, '_contact_form_messages', self::sanitize_value( $data['messages'] ) );
		}

		// Update form fields settings
		if ( isset( $data['field'] ) && is_array( $data['field'] ) ) {
			$_data = array();
			foreach ( $data['field'] as $field ) {
				$_data[] = self::sanitize_field( $field );
			}

			update_post_meta( $form_id, '_contact_form_fields', $_data );
		} else {
			delete_post_meta( $form_id, '_contact_form_fields' );
		}

		// Update form actions
		if ( isset( $data['actions'] ) && is_array( $data['actions'] ) ) {

			$actions       = array();
			$actionManager = Actions::init();

			foreach ( $data['actions'] as $action_id => $action_data ) {
				$className = $actionManager->get( $action_id );
				$action    = new $className;
				if ( ! $action instanceof Action ) {
					continue;
				}

				$actions[] = $action_id;

				$action->save( $form_id, $action_data );
			}

			update_post_meta( $form_id, '_contact_form_actions', self::sanitize_value( $actions ) );
		}
	}

	/**
	 * Sanitize meta value
	 *
	 * @param $input
	 *
	 * @return array|string
	 */
	private static function sanitize_value( $input ) {
		// Initialize the new array that will hold the sanitize values
		$new_input = array();

		if ( is_array( $input ) ) {
			// Loop through the input and sanitize each of the values
			foreach ( $input as $key => $value ) {
				if ( is_array( $value ) ) {
					$new_input[ $key ] = self::sanitize_value( $value );
				} else {
					$new_input[ $key ] = sanitize_text_field( $value );
				}
			}
		} else {
			return sanitize_text_field( $input );
		}

		return $new_input;
	}

	/**
	 * @param $data
	 *
	 * @return array
	 */
	private static function sanitize_field( $data ) {
		$_data = array(
			'field_title'        => isset( $data['field_title'] ) ? sanitize_text_field( $data['field_title'] ) : '',
			'field_name'         => isset( $data['field_id'] ) ? sanitize_title_with_dashes( $data['field_id'] ) : '',
			'field_id'           => isset( $data['field_id'] ) ? sanitize_text_field( $data['field_id'] ) : '',
			'field_type'         => isset( $data['field_type'] ) ? sanitize_text_field( $data['field_type'] ) : '',
			'options'            => isset( $data['options'] ) ? wp_strip_all_tags( $data['options'] ) : '',
			'number_min'         => isset( $data['number_min'] ) ? sanitize_text_field( $data['number_min'] ) : '',
			'number_max'         => isset( $data['number_max'] ) ? sanitize_text_field( $data['number_max'] ) : '',
			'number_step'        => isset( $data['number_step'] ) ? sanitize_text_field( $data['number_step'] ) : '',
			'field_value'        => isset( $data['field_value'] ) ? sanitize_text_field( $data['field_value'] ) : '',
			'required_field'     => isset( $data['required_field'] ) ? sanitize_text_field( $data['required_field'] ) : '',
			'field_class'        => isset( $data['field_class'] ) ? sanitize_text_field( $data['field_class'] ) : '',
			'field_width'        => isset( $data['field_width'] ) ? sanitize_text_field( $data['field_width'] ) : '',
			'placeholder'        => isset( $data['placeholder'] ) ? sanitize_text_field( $data['placeholder'] ) : '',
			'autocomplete'       => isset( $data['autocomplete'] ) ? sanitize_text_field( $data['autocomplete'] ) : '',
			// Acceptance Field
			'acceptance_text'    => isset( $data['acceptance_text'] ) ? wp_kses_post( $data['acceptance_text'] ) : '',
			'checked_by_default' => isset( $data['checked_by_default'] ) ? sanitize_text_field( $data['checked_by_default'] ) : '',
			// Date Field
			'min_date'           => isset( $data['min_date'] ) ? sanitize_text_field( $data['min_date'] ) : '',
			'max_date'           => isset( $data['max_date'] ) ? sanitize_text_field( $data['max_date'] ) : '',
			// Date & Time Field
			'native_html5'       => isset( $data['native_html5'] ) ? sanitize_text_field( $data['native_html5'] ) : '',
			// File Field
			'max_file_size'      => isset( $data['max_file_size'] ) ? absint( $data['max_file_size'] ) : '',
			'allowed_file_types' => isset( $data['allowed_file_types'] ) ? self::sanitize_value( $data['allowed_file_types'] ) : array(),
			'rows'               => isset( $data['rows'] ) ? intval( $data['rows'] ) : '',
			// File & Email
			'multiple'           => isset( $data['multiple'] ) ? sanitize_text_field( $data['multiple'] ) : '',
			// HTML
			'html'               => isset( $data['html'] ) ? wp_kses_post( $data['html'] ) : '',
			// Depreciated
			'validation'         => isset( $data['validation'] ) ? self::sanitize_value( $data['validation'] ) : array(),
			'error_message'      => isset( $data['error_message'] ) ? sanitize_text_field( $data['error_message'] ) : '',
		);

		return array_filter( $_data );
	}

	/**
	 * Delete current form
	 *
	 * @param int $form_id
	 *
	 * @return bool
	 */
	public static function delete( $form_id ) {
		if ( wp_delete_post( $form_id, true ) ) {
			return true;
		}

		return false;
	}

	/**
	 * Get numbers of items found in current query
	 *
	 * @return int
	 */
	public static function count() {
		return self::$found_items;
	}

	/**
	 * Get form all settings data as array
	 *
	 * @return array
	 */
	public function toArray() {
		return array(
			'title'         => $this->getTitle(),
			'id'            => $this->getId(),
			'fields'        => $this->getFormFields(),
			'settings'      => $this->getSetting(),
			'actions'       => $this->getFormActionsSettings(),
			'messages'      => $this->getValidationMessages(),
			'has_file'      => $this->hasFile(),
			'has_recaptcha' => $this->hasRecaptcha(),
			'reset_form'    => $this->resetForm(),
		);
	}

	/**
	 * Get current form fields
	 *
	 * @return array
	 */
	public function getFormFields() {
		return $this->form_fields;
	}

	/**
	 * Get current form settings
	 *
	 * @param string $key
	 * @param mixed $default
	 *
	 * @return array
	 */
	public function getSetting( $key = null, $default = null ) {
		if ( empty( $key ) ) {
			return $this->form_settings;
		}

		return isset( $this->form_settings[ $key ] ) ? $this->form_settings[ $key ] : $default;
	}

	/**
	 * Get current form supported actions
	 *
	 * @return array
	 */
	public function getFormActions() {
		return $this->form_actions;
	}

	/**
	 * Get form actions settings
	 *
	 * @return array
	 */
	public function getFormActionsSettings() {
		$actions        = $this->getFormActions();
		$actionsManager = new Actions();

		$settings = [];
		foreach ( $actions as $action ) {
			$actionName  = $actionsManager->get( $action );
			$actionClass = new $actionName;
			if ( ! $actionClass instanceof Action ) {
				continue;
			}
			$settings[ $action ] = get_post_meta( $this->getId(), $actionClass->getMetaKey(), true );
		}

		return $settings;
	}

	/**
	 * Get validation messages
	 *
	 * @return array
	 */
	public function getValidationMessages() {
		return $this->validation_messages;
	}

	/**
	 * If current form has file field
	 *
	 * @return bool
	 */
	public function hasFile() {
		return $this->has_file;
	}

	/**
	 * If reCAPTCHA is enabled for the form
	 *
	 * @return bool
	 */
	public function hasRecaptcha() {
		return $this->has_recaptcha;
	}

	/**
	 * If form should reset after successful submission
	 *
	 * @return bool
	 */
	public function resetForm() {
		return $this->reset_form;
	}

	/**
	 * Get form available fields type
	 *
	 * @return array
	 */
	public function getFieldTypes() {
		return $this->field_types;
	}

	/**
	 * Get spam message
	 *
	 * @return string
	 */
	public function getSpamMessage() {
		return esc_attr( $this->validation_messages['spam_message'] );
	}

	/**
	 * Get invalid reCAPTCHA message
	 *
	 * @return string
	 */
	public function getInvalidRecaptchaMessage() {
		return esc_attr( $this->validation_messages['invalid_recaptcha'] );
	}

	/**
	 * Get validation error message
	 *
	 * @return string
	 */
	public function getValidationErrorMessage() {
		return esc_attr( $this->validation_messages['validation_error'] );
	}

	/**
	 * Get mail send fail message
	 *
	 * @return string
	 */
	public function getMailSendFailMessage() {
		return esc_attr( $this->validation_messages['mail_sent_ng'] );
	}

	/**
	 * Get MailChimp API key
	 *
	 * @return string
	 */
	public function getMailchimpApiKey() {
		return $this->getGlobalOption( 'mailchimp_api_key' );
	}

	/**
	 * Get form ID
	 *
	 * @return bool
	 */
	public function getFormId() {
		return $this->getId();
	}

	/**
	 * Get current form id
	 *
	 * @return int
	 */
	public function getId() {
		return $this->id;
	}

	/**
	 * Get current form title
	 *
	 * @return string
	 */
	public function getTitle() {
		return $this->title;
	}

	/**
	 * Check if current form is valid
	 *
	 * @return bool
	 */
	public function isValid() {
		return (bool) $this->getId();
	}

	/**
	 * Get global form option
	 *
	 * @param null $option
	 * @param bool $default
	 *
	 * @return mixed
	 */
	public function getGlobalOption( $option = null, $default = false ) {
		$option = trim( $option );
		if ( empty( $option ) ) {
			return $this->options;
		}

		$value = null;

		// Distinguish between `false` as a default, and not passing one.
		if ( func_num_args() > 1 ) {
			$value = $default;
		}

		if ( isset( $this->options[ $option ] ) ) {
			$value = $this->options[ $option ];
		}

		return $value;
	}
}
