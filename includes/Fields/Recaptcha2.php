<?php

namespace DialogContactForm\Fields;

use DialogContactForm\Abstracts\Field;
use DialogContactForm\Supports\Utils;

// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Recaptcha2 extends Field {

	/**
	 * Google reCAPTCHA site verify API endpoint
	 */
	const SITE_VERIFY_URL = 'https://www.google.com/recaptcha/api/siteverify';

	/**
	 * Metabox fields
	 *
	 * @var array
	 */
	protected $metabox_fields = array(
		'field_type',
		'field_title',
		'field_id',
		'recaptcha_size',
		'recaptcha_style',
	);

	/**
	 * Recaptcha2 constructor.
	 */
	public function __construct() {
		$this->admin_id    = 'recaptcha';
		$this->admin_label = __( 'reCAPTCHA', 'dialog-contact-form' );
		$this->admin_icon  = '<svg><use href="#dcf-icon-recaptcha"></use></svg>';
	}

	/**
	 * Render field html for frontend display
	 *
	 * @param array $field
	 *
	 * @return string
	 */
	public function render( $field = array() ) {
		if ( ! empty( $field ) ) {
			$this->setField( $field );
		}

		$form_config = get_post_meta( $this->form_id, '_contact_form_config', true );

		if ( ! ( isset( $form_config['recaptcha'] ) && $form_config['recaptcha'] === 'yes' ) ) {
			return null;
		}

		$site_key   = Utils::get_option( 'recaptcha_site_key' );
		$secret_key = Utils::get_option( 'recaptcha_secret_key' );
		$theme      = Utils::get_option( 'recaptcha_theme' );

		if ( empty( $site_key ) || empty( $secret_key ) ) {
			return null;
		}

		wp_enqueue_script( 'dialog-contact-form-recaptcha' );

		$html = '<div class="dcf-column is-12">';
		$html .= '<div class="dcf-field">';
		$html .= sprintf( '<div class="g-recaptcha" data-sitekey="%1$s" data-theme="%2$s"></div>',
			esc_attr( $site_key ), esc_attr( $theme )
		);
		$html .= '<div class="dcf-control">';
		$html .= '<input type="hidden" name="dcf_recaptcha">';

		// Show error message if any
		if ( isset( $GLOBALS['_dcf_errors']['dcf_recaptcha'][0] ) ) {
			$html .= '<span class="dcf-error-message">' . esc_attr( $GLOBALS['_dcf_errors']['dcf_recaptcha'][0] ) . '</span>';
		}

		$html .= '</div></div></div>' . PHP_EOL;

		return apply_filters( 'dialog_contact_form/preview/field', $html, $this );
	}

	/**
	 * Validate field value
	 *
	 * @param mixed $value
	 *
	 * @return bool true on success, false on failure
	 */
	public function validate( $value ) {
		$secret_key = Utils::get_option( 'recaptcha_secret_key' );
		if ( empty( $value ) || empty( $secret_key ) ) {
			return false;
		}

		$response = wp_remote_post( self::SITE_VERIFY_URL, [
			'body' => [
				'secret'   => $secret_key,
				'response' => $value,
				'remoteip' => self::getRemoteIpAddress(),
			]
		] );

		$body = json_decode( wp_remote_retrieve_body( $response ), true );

		if ( isset( $body['success'] ) && true === $body['success'] ) {
			return true;
		}

		return false;
	}

	/**
	 * Sanitize field value
	 *
	 * @param mixed $value
	 *
	 * @return mixed
	 */
	public function sanitize( $value ) {
		return $value;
	}

	/**
	 * Get field value
	 *
	 * @return mixed
	 */
	protected function getValue() {
		if ( isset( $_POST['g-recaptcha-response'] ) ) {
			return esc_attr( $_POST['g-recaptcha-response'] );
		}

		return null;
	}

	/**
	 * Validate Google reCAPTCHA
	 *
	 * @return bool
	 */
	public static function _validate() {
		$captcha_code = isset( $_POST['g-recaptcha-response'] ) ? $_POST['g-recaptcha-response'] : null;

		return ( new static )->validate( $captcha_code );
	}

	/**
	 * Google reCAPTCHA languages
	 *
	 * @return array
	 */
	public static function lang() {
		return array(
			"ar"     => esc_html__( "Arabic", 'dialog-contact-form' ),
			"af"     => esc_html__( "Afrikaans", 'dialog-contact-form' ),
			"am"     => esc_html__( "Amharic", 'dialog-contact-form' ),
			"hy"     => esc_html__( "Armenian", 'dialog-contact-form' ),
			"az"     => esc_html__( "Azerbaijani", 'dialog-contact-form' ),
			"eu"     => esc_html__( "Basque", 'dialog-contact-form' ),
			"bn"     => esc_html__( "Bengali", 'dialog-contact-form' ),
			"bg"     => esc_html__( "Bulgarian", 'dialog-contact-form' ),
			"ca"     => esc_html__( "Catalan", 'dialog-contact-form' ),
			"zh-HK"  => esc_html__( "Chinese (Hong Kong)", 'dialog-contact-form' ),
			"zh-CN"  => esc_html__( "Chinese (Simplified)", 'dialog-contact-form' ),
			"zh-TW"  => esc_html__( "Chinese (Traditional)", 'dialog-contact-form' ),
			"hr"     => esc_html__( "Croatian", 'dialog-contact-form' ),
			"cs"     => esc_html__( "Czech", 'dialog-contact-form' ),
			"da"     => esc_html__( "Danish", 'dialog-contact-form' ),
			"nl"     => esc_html__( "Dutch", 'dialog-contact-form' ),
			"en-GB"  => esc_html__( "English (UK)", 'dialog-contact-form' ),
			"en"     => esc_html__( "English (US)", 'dialog-contact-form' ),
			"et"     => esc_html__( "Estonian", 'dialog-contact-form' ),
			"fil"    => esc_html__( "Filipino", 'dialog-contact-form' ),
			"fi"     => esc_html__( "Finnish", 'dialog-contact-form' ),
			"fr"     => esc_html__( "French", 'dialog-contact-form' ),
			"fr-CA"  => esc_html__( "French (Canadian)", 'dialog-contact-form' ),
			"gl"     => esc_html__( "Galician", 'dialog-contact-form' ),
			"ka"     => esc_html__( "Georgian", 'dialog-contact-form' ),
			"de"     => esc_html__( "German", 'dialog-contact-form' ),
			"de-AT"  => esc_html__( "German (Austria)", 'dialog-contact-form' ),
			"de-CH"  => esc_html__( "German (Switzerland)", 'dialog-contact-form' ),
			"el"     => esc_html__( "Greek", 'dialog-contact-form' ),
			"gu"     => esc_html__( "Gujarati", 'dialog-contact-form' ),
			"iw"     => esc_html__( "Hebrew", 'dialog-contact-form' ),
			"hi"     => esc_html__( "Hindi", 'dialog-contact-form' ),
			"hu"     => esc_html__( "Hungarain", 'dialog-contact-form' ),
			"is"     => esc_html__( "Icelandic", 'dialog-contact-form' ),
			"id"     => esc_html__( "Indonesian", 'dialog-contact-form' ),
			"it"     => esc_html__( "Italian", 'dialog-contact-form' ),
			"ja"     => esc_html__( "Japanese", 'dialog-contact-form' ),
			"kn"     => esc_html__( "Kannada", 'dialog-contact-form' ),
			"ko"     => esc_html__( "Korean", 'dialog-contact-form' ),
			"lo"     => esc_html__( "Laothian", 'dialog-contact-form' ),
			"lv"     => esc_html__( "Latvian", 'dialog-contact-form' ),
			"lt"     => esc_html__( "Lithuanian", 'dialog-contact-form' ),
			"ms"     => esc_html__( "Malay", 'dialog-contact-form' ),
			"ml"     => esc_html__( "Malayalam", 'dialog-contact-form' ),
			"mr"     => esc_html__( "Marathi", 'dialog-contact-form' ),
			"mn"     => esc_html__( "Mongolian", 'dialog-contact-form' ),
			"no"     => esc_html__( "Norwegian", 'dialog-contact-form' ),
			"fa"     => esc_html__( "Persian", 'dialog-contact-form' ),
			"pl"     => esc_html__( "Polish", 'dialog-contact-form' ),
			"pt"     => esc_html__( "Portuguese", 'dialog-contact-form' ),
			"pt-BR"  => esc_html__( "Portuguese (Brazil)", 'dialog-contact-form' ),
			"pt-PT"  => esc_html__( "Portuguese (Portugal)", 'dialog-contact-form' ),
			"ro"     => esc_html__( "Romanian", 'dialog-contact-form' ),
			"ru"     => esc_html__( "Russian", 'dialog-contact-form' ),
			"sr"     => esc_html__( "Serbian", 'dialog-contact-form' ),
			"si"     => esc_html__( "Sinhalese", 'dialog-contact-form' ),
			"sk"     => esc_html__( "Slovak", 'dialog-contact-form' ),
			"sl"     => esc_html__( "Slovenian", 'dialog-contact-form' ),
			"es"     => esc_html__( "Spanish", 'dialog-contact-form' ),
			"es-419" => esc_html__( "Spanish (Latin America)", 'dialog-contact-form' ),
			"sw"     => esc_html__( "Swahili", 'dialog-contact-form' ),
			"sv"     => esc_html__( "Swedish", 'dialog-contact-form' ),
			"ta"     => esc_html__( "Tamil", 'dialog-contact-form' ),
			"te"     => esc_html__( "Telugu", 'dialog-contact-form' ),
			"th"     => esc_html__( "Thai", 'dialog-contact-form' ),
			"tr"     => esc_html__( "Turkish", 'dialog-contact-form' ),
			"uk"     => esc_html__( "Ukrainian", 'dialog-contact-form' ),
			"ur"     => esc_html__( "Urdu", 'dialog-contact-form' ),
			"vi"     => esc_html__( "Vietnamese", 'dialog-contact-form' ),
			"zu"     => esc_html__( "Zulu", 'dialog-contact-form' ),
		);
	}

	/**
	 * Get user IP address
	 *
	 * @return string
	 */
	private static function getRemoteIpAddress() {
		$server_ip_keys = array(
			'HTTP_CLIENT_IP',
			'HTTP_X_FORWARDED_FOR',
			'HTTP_X_FORWARDED',
			'HTTP_X_CLUSTER_CLIENT_IP',
			'HTTP_FORWARDED_FOR',
			'HTTP_FORWARDED',
			'REMOTE_ADDR',
		);

		foreach ( $server_ip_keys as $key ) {
			if ( isset( $_SERVER[ $key ] ) && filter_var( $_SERVER[ $key ], FILTER_VALIDATE_IP ) ) {
				return $_SERVER[ $key ];
			}
		}

		// Fallback local ip.
		return '127.0.0.1';
	}
}
