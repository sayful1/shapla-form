<?php

namespace DialogContactForm\Actions;

use DialogContactForm\Abstracts\Action;
use DialogContactForm\Entries\Entry;
use DialogContactForm\Supports\Config;

// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class StoreSubmission extends Action {

	/**
	 * Redirect constructor.
	 */
	public function __construct() {
		$this->priority   = 10;
		$this->id         = 'store_submission';
		$this->title      = __( 'Store Submission', 'dialog-contact-form' );
		$this->meta_group = 'store_submission';
		$this->meta_key   = '_action_store_submission';
		$this->settings   = $this->settings();
	}

	/**
	 * Process current action
	 *
	 * @param Config $config Contact form configurations
	 * @param array $data User submitted sanitized data
	 *
	 * @return mixed
	 */
	public static function process( $config, $data ) {
		$entry = new Entry();

		$data['form_id'] = $config->getId();

		return $entry->create( $data );
	}

	/**
	 * Get action description
	 *
	 * @return string
	 */
	public function _getDescription() {
		$html = '<p class="description">';
		$html .= esc_html__( 'No settings are available for this action.', 'dialog-contact-form' );
		$html .= '</p>';

		return $html;
	}


	/**
	 * Action settings
	 *
	 * @return array
	 */
	private function settings() {
		global $post;
		$options = [];

		if ( $post instanceof \WP_Post ) {
			$_fields = (array) get_post_meta( $post->ID, '_contact_form_fields', true );
			foreach ( $_fields as $item ) {
				if ( ! empty( $item['field_id'] ) && ! empty( $item['field_title'] ) ) {
					$options[ $item['field_id'] ] = 'Field: ' . $item['field_title'];
				}
			}
		}

		$options['status']     = __( 'Entry: Status', 'dialog-contact-form' );
		$options['created_at'] = __( 'Entry: Date', 'dialog-contact-form' );

		return [
			'data_table_fields' => [
				'type'        => 'select',
				'multiple'    => true,
				'id'          => 'data_table_fields',
				'group'       => $this->meta_group,
				'meta_key'    => $this->meta_key,
				'label'       => __( 'Data Table Fields', 'dialog-contact-form' ),
				'description' => __( 'Choose fields that should display as columns on entry data table page.',
					'dialog-contact-form' ),
				'options'     => $options,
			]
		];
	}
}
