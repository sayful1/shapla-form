<?php

namespace DialogContactForm\Fields;

use DialogContactForm\Abstracts\Abstract_Field;

class Number extends Abstract_Field {

	/**
	 * Render field html for frontend display
	 *
	 * @param $field
	 *
	 * @return string
	 */
	public function render( $field ) {
		$this->setField( $field );

		$html = sprintf(
			'<input id="%1$s" class="%2$s" name="%3$s" value="%4$s" type="number" %5$s %6$s %7$s %8$s %9$s>',
			$this->get_id(),
			$this->get_class( 'input' ),
			$this->get_name(),
			$this->get_value(),
			$this->get_placeholder(),
			$this->get_min(),
			$this->get_max(),
			$this->get_step(),
			$this->get_required()
		);

		return $html;
	}

	/**
	 * Validate field value
	 *
	 * @param mixed $value
	 *
	 * @return bool
	 */
	public function validate( $value ) {
		return is_numeric( $value );
	}

	/**
	 * Sanitize field value
	 *
	 * @param mixed $value
	 *
	 * @return int|string
	 */
	public function sanitize( $value ) {
		return is_numeric( $value ) ? $value : intval( $value );
	}

	/**
	 * Get field value
	 *
	 * @return mixed
	 */
	protected function get_value() {
		if ( empty( $_POST[ $this->field['field_name'] ] ) ) {
			return null;
		}

		return esc_attr( $_POST[ $this->field['field_name'] ] );
	}

	/**
	 * Get min attribute
	 *
	 * @return string
	 */
	protected function get_min() {
		if ( empty( $this->field['number_min'] ) ) {
			return '';
		}

		return sprintf( ' min="%s"', floatval( $this->field['number_min'] ) );
	}

	/**
	 * Get max attribute
	 *
	 * @return string
	 */
	protected function get_max() {
		if ( empty( $this->field['number_max'] ) ) {
			return '';
		}

		return sprintf( ' max="%s"', floatval( $this->field['number_max'] ) );
	}

	/**
	 * Get step attribute
	 *
	 * @return string
	 */
	protected function get_step() {
		if ( empty( $this->field['number_step'] ) ) {
			return '';
		}

		return sprintf( ' step="%s"', floatval( $this->field['number_step'] ) );
	}
}