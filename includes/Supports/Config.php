<?php

namespace DialogContactForm\Supports;

// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Config extends ContactForm {

	/**
	 * The instance of the class
	 *
	 * @var self
	 */
	private static $instance;

	/**
	 * Ensures only one instance of the class is loaded or can be loaded.
	 *
	 * @param int|\WP_Post|null $post Optional. Post ID or post object.
	 *
	 * @return Config
	 */
	public static function init( $post = null ) {
		if ( is_null( self::$instance ) ) {
			self::$instance = new self( $post );
		}

		return self::$instance;
	}
}
