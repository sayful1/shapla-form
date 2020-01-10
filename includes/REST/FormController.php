<?php

namespace DialogContactForm\REST;

use DialogContactForm\Abstracts\Field;
use DialogContactForm\Abstracts\Template;
use DialogContactForm\Collections\Templates;
use DialogContactForm\Entries\Entry;
use DialogContactForm\Fields\File;
use DialogContactForm\Fields\Recaptcha2;
use DialogContactForm\Submission;
use DialogContactForm\Supports\Attachment;
use DialogContactForm\Supports\Config;
use DialogContactForm\Supports\ContactForm;
use DialogContactForm\Supports\UploadedFile;
use WP_REST_Request;
use WP_REST_Response;
use WP_REST_Server;

defined( 'ABSPATH' ) || exit;

class FormController extends ApiController {
	/**
	 * The instance of the class
	 *
	 * @var self
	 */
	private static $instance;

	/**
	 * Only one instance of the class can be loaded.
	 *
	 * @return self
	 */
	public static function init() {
		if ( is_null( self::$instance ) ) {
			self::$instance = new self;

			add_action( 'rest_api_init', array( self::$instance, 'register_routes' ) );
		}

		return self::$instance;
	}

	/**
	 * Registers the routes for the objects of the controller.
	 */
	public function register_routes() {
		register_rest_route( $this->namespace, '/forms', [
			[
				'methods'  => WP_REST_Server::READABLE,
				'callback' => [ $this, 'get_items' ],
				'args'     => $this->get_collection_params()
			],
			[
				'methods'  => WP_REST_Server::CREATABLE,
				'callback' => [ $this, 'create_item' ],
			],
		] );

		register_rest_route( $this->namespace, '/forms/batch', [
			[
				'methods'  => WP_REST_Server::CREATABLE,
				'callback' => [ $this, 'update_batch_items' ],
				'args'     => $this->get_batch_params()
			],
		] );

		register_rest_route( $this->namespace, '/forms/(?P<id>\d+)', [
			[
				'methods'  => WP_REST_Server::READABLE,
				'callback' => [ $this, 'get_item' ],
			],
			[
				'methods'  => WP_REST_Server::EDITABLE,
				'callback' => [ $this, 'update_item' ],
			],
			[
				'methods'  => WP_REST_Server::DELETABLE,
				'callback' => [ $this, 'delete_item' ],
			],
		] );

		register_rest_route( $this->namespace, '/forms/(?P<id>\d+)/feedback', [
			[
				'methods'  => WP_REST_Server::CREATABLE,
				'callback' => [ $this, 'create_feedback' ],
			],
		] );
	}

	/**
	 * Retrieves a collection of items.
	 *
	 * @param WP_REST_Request $request Full data about the request.
	 *
	 * @return WP_REST_Response Response object on success, or WP_Error object on failure.
	 */
	public function get_items( $request ) {
		if ( ! current_user_can( 'edit_posts' ) ) {
			return $this->respondForbidden();
		}

		$per_page = $request->get_param( 'per_page' );
		$page     = $request->get_param( 'page' );

		$args = array(
			'posts_per_page' => $per_page,
			'paged'          => $page,
		);

		$order   = $request->get_param( 'order' );
		$orderby = $request->get_param( 'orderby' );

		if ( null !== $order ) {
			$args['order'] = (string) $order;
		}

		if ( null !== $orderby ) {
			$args['orderby'] = (string) $orderby;
		}

		$search = $request->get_param( 'search' );
		if ( ! empty( $search ) ) {
			$args['s'] = (string) $search;
		}

		$status = $request->get_param( 'status' );
		$status = in_array( $status, [ 'publish', 'trash' ] ) ? $status : 'publish';

		$args['post_status'] = $status;

		$forms          = ContactForm::find( $args );
		$entries_counts = Entry::count_entries();

		$items = [];
		/** @var ContactForm $form */
		foreach ( $forms as $form ) {
			$items[] = [
				'id'        => $form->getId(),
				'title'     => $form->getTitle(),
				'shortcode' => sprintf( "[dialog_contact_form id='%s']", $form->getId() ),
				'entries'   => isset( $entries_counts[ $form->getId() ] ) ? $entries_counts[ $form->getId() ] : 0,
				'edit_url'  => add_query_arg( [
					'post'   => $form->getId(),
					'action' => 'edit'
				], admin_url( 'post.php' ) ),
			];
		}

		$counts = ContactForm::get_counts();

		$response = [
			'items'      => $items,
			'pagination' => self::get_pagination_data( $counts[ $status ], $per_page, $page ),
		];

		$metadata = $request->get_param( 'metadata' );
		if ( $metadata ) {
			$response['metaData'] = $this->get_collection_metadata( $counts, $status );
		}

		return $this->respondOK( $response );
	}

	/**
	 * Retrieves one item from the collection.
	 *
	 * @param WP_REST_Request $request Full data about the request.
	 *
	 * @return WP_REST_Response Response object on success, or WP_Error object on failure.
	 */
	public function get_item( $request ) {
		$id = $request->get_param( 'id' );

		if ( ! current_user_can( 'publish_pages', $id ) ) {
			return $this->respondForbidden( 'forbidden',
				__( "You are not allowed to access the requested contact form.", 'dialog-contact-form' ) );
		}

		$form = new ContactForm( $id );

		if ( ! $form->getId() ) {
			return $this->respondNotFound( 'not_found', __( "The requested contact form was not found.", 'dialog-contact-form' ) );
		}

		$response = $form->toArray();

		return $this->respondOK( $response );
	}

	/**
	 * Creates one item from the collection.
	 *
	 * @param WP_REST_Request $request Full data about the request.
	 *
	 * @return WP_REST_Response Response object on success, or WP_Error object on failure.
	 */
	public function create_item( $request ) {
		if ( ! current_user_can( 'publish_pages' ) ) {
			return $this->respondForbidden( 'forbidden',
				__( "You are not allowed to create a contact form.", 'dialog-contact-form' ) );
		}

		$templateManager = Templates::init();
		$template        = $request->get_param( 'template' );
		$template        = in_array( $template, array_keys( $templateManager->all() ) ) ? $template : 'blank';
		$className       = $templateManager->get( $template );
		$class           = new $className;

		if ( ! $class instanceof Template ) {
			return $this->respondNotFound( 'template_not_found',
				__( "Form template is not available.", 'dialog-contact-form' )
			);
		}

		$post_id = wp_insert_post( array(
			'post_title'     => $class->getTitle(),
			'post_status'    => 'publish',
			'post_type'      => DIALOG_CONTACT_FORM_POST_TYPE,
			'comment_status' => 'closed',
			'ping_status'    => 'closed',
		) );

		if ( is_wp_error( $post_id ) ) {
			return $this->respondInternalServerError();
		}

		$class->run( $post_id );

		$form     = new ContactForm( $post_id );
		$response = array(
			'id'    => $form->getId(),
			'title' => $form->getTitle(),
		);

		return rest_ensure_response( $response );
	}

	/**
	 * Update batch items
	 *
	 * @param WP_REST_Request $request
	 *
	 * @return WP_REST_Response
	 */
	public function update_batch_items( $request ) {
		return $this->respondOK();
	}

	/**
	 * Create feedback
	 *
	 * @param WP_REST_Request $request
	 *
	 * @return WP_REST_Response
	 */
	public function create_feedback( $request ) {
		$id = (int) $request->get_param( 'id' );

		$form = new ContactForm( $id );

		if ( ! $form->isValid() ) {
			return $this->respondNotFound( 'form_not_found', __( 'No form found with that id.', 'dialog-contact-form' ) );
		}

		/**
		 * @var ContactForm $form
		 */
		do_action( 'dialog_contact_form/before_validation', $form );

		$files = array();
		if ( $form->hasFile() ) {
			$files = UploadedFile::getUploadedFiles();
		}

		$submission = new Submission;

		/** @var Field[]|File[] $field */
		$fields = $form->getFormFields();
		$params = $request->get_params();

		$error_data = $submission->validate_fields( $params, $files, $form );

		// If Google reCAPTCHA enabled, verify it
		$g_response = $request->get_param( 'g-recaptcha-response' );
		if ( $form->hasRecaptcha() && ! ( new Recaptcha2 )->validate( $g_response ) ) {
			$error_data['g_recaptcha'] = array( $form->getInvalidRecaptchaMessage() );
		}

		/**
		 * @var Config|ContactForm $form
		 * @var array $error_data
		 */
		do_action( 'dialog_contact_form/after_validation', $form, $error_data );


		// Exit if there is any error
		if ( count( $error_data ) > 0 ) {
			return $this->respondUnprocessableEntity( 'incomplete_data', $form->getValidationErrorMessage(), $error_data );
		}

		// Sanitize form data
		$data = $submission->sanitize_fields_data( $params, $form );

		// If form upload a file, handle here
		if ( $form->hasFile() ) {
			$attachments = Attachment::upload( $files, $fields );
			$data        = $data + $attachments;
		}

		$response = $submission->run_form_actions( $data, $form );

		return $this->respondCreated( $response );
	}

	/**
	 * Get collection metadata
	 *
	 * @param array $counts
	 * @param string $status
	 *
	 * @return array
	 */
	private function get_collection_metadata( $counts, $status = 'publish' ) {
		$data = [
			'columns'       => [
				[ 'key' => 'title', 'label' => __( 'Title', 'dialog-contact-form' ) ],
				[ 'key' => 'shortcode', 'label' => __( 'Shortcode', 'dialog-contact-form' ) ],
				[ 'key' => 'entries', 'label' => __( 'Entries', 'dialog-contact-form' ), 'numeric' => true ],
			],
			'primaryColumn' => 'title',
		];

		$data['statuses'] = [
			[ 'key' => 'publish', 'label' => __( 'Publish', 'dialog-contact-form' ) ],
			[ 'key' => 'trash', 'label' => __( 'Trash', 'dialog-contact-form' ) ],
		];

		foreach ( $data['statuses'] as $index => $_status ) {
			$data['statuses'][ $index ]['count']  = isset( $counts[ $_status['key'] ] ) ? $counts[ $_status['key'] ] : 0;
			$data['statuses'][ $index ]['active'] = ( $_status['key'] == $status );
		}

		if ( 'trash' == $status ) {
			$restore = [ 'key' => 'restore', 'label' => __( 'Restore', 'dialog-contact-form' ) ];
			$delete  = [ 'key' => 'delete', 'label' => __( 'Delete Permanently', 'dialog-contact-form' ) ];

			$data['actions'][] = $restore;
			$data['actions'][] = $delete;

			$data['bulk_actions'][] = $restore;
			$data['bulk_actions'][] = $delete;
		} else {

			$data['actions'][] = [ 'key' => 'edit', 'label' => __( 'Edit', 'dialog-contact-form' ) ];
			$data['actions'][] = [ 'key' => 'trash', 'label' => __( 'Trash', 'dialog-contact-form' ) ];

			$data['bulk_actions'][] = [ 'key' => 'trash', 'label' => __( 'Move to Trash', 'dialog-contact-form' ) ];
		}

		return $data;
	}

	/**
	 * Retrieves the query params for the collections.
	 *
	 * @return array Query parameters for the collection.
	 */
	public function get_collection_params() {
		$params = parent::get_collection_params();

		$params = array_merge( $params, [
			'order'    => [
				'description' => __( 'Designates the ascending or descending order.', 'dialog-contact-form' ),
				'required'    => false,
				'default'     => 'DESC',
				'enum'        => [ 'ASC', 'DESC' ],
				'type'        => 'string',
			],
			'orderby'  => [
				'description' => __( 'Sort retrieved forms by parameter.', 'dialog-contact-form' ),
				'required'    => false,
				'default'     => 'id',
				'enum'        => [ 'id' ],
				'type'        => 'string',
			],
			'metadata' => [
				'description'       => __( 'Include form metadata.', 'dialog-contact-form' ),
				'required'          => false,
				'default'           => false,
				'type'              => 'boolean',
				'validate_callback' => 'rest_validate_request_arg',
			],
		] );

		return $params;
	}

	/**
	 * Retrieves the query params for the batch operation.
	 *
	 * @return array Query parameters for the batch operation.
	 */
	public function get_batch_params() {
		return [
			'trash'   => [
				'description'       => __( 'List of items ids to be sent to trash.', 'dialog-contact-form' ),
				'required'          => false,
				'default'           => [],
				'type'              => 'array',
				'validate_callback' => 'rest_validate_request_arg',
			],
			'restore' => [
				'description'       => __( 'List of items ids to be restored from trash.', 'dialog-contact-form' ),
				'required'          => false,
				'default'           => [],
				'type'              => 'array',
				'validate_callback' => 'rest_validate_request_arg',
			],
			'delete'  => [
				'description'       => __( 'List of items ids to delete permanently.', 'dialog-contact-form' ),
				'required'          => false,
				'default'           => [],
				'type'              => 'array',
				'validate_callback' => 'rest_validate_request_arg',
			],
		];
	}
}