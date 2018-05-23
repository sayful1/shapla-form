<?php

namespace DialogContactForm\Fields;

class Hidden extends Text {

	/**
	 * Metabox fields
	 *
	 * @var array
	 */
	protected $metabox_fields = array(
		// Content
		'field_type',
		'field_id',
		'field_value',
		// Additional
		'field_title',
	);

	/**
	 * Field type
	 *
	 * @var string
	 */
	protected $type = 'hidden';
}