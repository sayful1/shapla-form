<?php

namespace DialogContactForm\Supports;

// Exit if accessed directly
use DialogContactForm\Fields\File;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Attachment {

	/**
	 * Get error message for each uploaded files
	 *
	 * @param UploadedFile $file
	 * @param File $field
	 * @param Config $config
	 *
	 * @return string
	 */
	private static function validateIndividualFile( $file, $field, $config ) {

		$messages = $config->getValidationMessages();

		// If file is required and no file uploaded, return require message
		if ( $file->getError() === UPLOAD_ERR_NO_FILE ) {
			if ( $field->isRequired() ) {
				return $messages['required_file'];
			} else {
				return '';
			}
		}

		// check file size here.
		if ( $file->getSize() > $field->getMaxFileSize() ) {
			return $messages['file_too_large'];
		}

		// Get file mime type for uploaded file
		$file_info = new \finfo( FILEINFO_MIME_TYPE );
		$mime_type = $file_info->file( $file->getFile() );

		// Get file extension from allowed mime types
		$ext = array_search( $mime_type, $field->getAllowedMimeTypes(), true );

		// Check if uploaded file mime type is allowed
		if ( false === strpos( $ext, $file->getClientExtension() ) ) {
			return $messages['invalid_file_format'];
		}

		return '';
	}

	/**
	 * Validate file field
	 *
	 * @param array|UploadedFile $file
	 * @param File $field
	 * @param Config|ContactForm $config
	 *
	 * @return array
	 */
	public static function validate( $file, $field, $config ) {

		$messages = $config->getValidationMessages();

		if ( is_array( $file ) && ! $field->isMultiple() ) {
			return array( $messages['unsupported_file_multi'] );
		}

		if ( $field->isRequired() && false === $file ) {
			return array( $messages['required_file'] );
		}

		$message = array();
		if ( $file instanceof UploadedFile ) {
			$message[] = self::validateIndividualFile( $file, $field, $config );
		}

		if ( is_array( $file ) ) {
			foreach ( $file as $_file ) {
				if ( $_file instanceof UploadedFile ) {
					$message[] = self::validateIndividualFile( $_file, $field, $config );
				}
			}
		}

		return array_filter( $message );
	}


	/**
	 * Upload attachments
	 *
	 * @param array $files
	 * @param array $fields
	 *
	 * @return array
	 */
	public static function upload( $files, $fields ) {
		$attachments = array();

		foreach ( $fields as $field ) {
			if ( 'file' !== $field['field_type'] ) {
				continue;
			}

			$file = isset( $files[ $field['field_name'] ] ) ? $files[ $field['field_name'] ] : false;

			if ( $file instanceof UploadedFile ) {
				$attachments[ $field['field_name'] ][] = self::uploadIndividualFile( $file );
			}
			if ( is_array( $file ) ) {
				foreach ( $file as $_file ) {
					if ( ! $_file instanceof UploadedFile ) {
						continue;
					}
					$attachments[ $field['field_name'] ][] = self::uploadIndividualFile( $_file );
				}
			}
		}

		return array_filter( $attachments );
	}

	/**
	 * Upload attachment
	 *
	 * @param UploadedFile $file
	 *
	 * @return array
	 */
	private static function uploadIndividualFile( $file ) {
		$upload_dir = wp_upload_dir();

		$attachment_dir = join( DIRECTORY_SEPARATOR, array( $upload_dir['basedir'], DIALOG_CONTACT_FORM_UPLOAD_DIR ) );
		$attachment_url = join( DIRECTORY_SEPARATOR, array( $upload_dir['baseurl'], DIALOG_CONTACT_FORM_UPLOAD_DIR ) );

		// Make attachment directory in upload directory if not already exists
		if ( ! file_exists( $attachment_dir ) ) {
			wp_mkdir_p( $attachment_dir );
		}

		// Check if attachment directory is writable
		if ( ! wp_is_writable( $attachment_dir ) ) {
			return array();
		}

		// Check file has no error
		if ( $file->getError() !== UPLOAD_ERR_OK ) {
			return array();
		}

		// Upload file
		try {
			$filename = wp_unique_filename( $attachment_dir, $file->getClientFilename() );
			$new_file = $file->moveUploadedFile( $attachment_dir, $filename );
			// Set correct file permissions.
			$stat  = stat( dirname( $new_file ) );
			$perms = $stat['mode'] & 0000666;
			@ chmod( $new_file, $perms );

			// Insert the attachment.
			$attachment    = array(
				'guid'           => join( DIRECTORY_SEPARATOR, array( $attachment_url, $filename ) ),
				'post_title'     => preg_replace( '/\.[^.]+$/', '', $file->getClientFilename() ),
				'post_status'    => 'inherit',
				'post_mime_type' => $file->getClientMediaType(),
			);
			$attachment_id = wp_insert_attachment( $attachment, $new_file );

			// Make sure that this file is included, as wp_generate_attachment_metadata() depends on it.
			require_once( ABSPATH . 'wp-admin/includes/image.php' );

			// Generate the metadata for the attachment, and update the database record.
			$attach_data = wp_generate_attachment_metadata( $attachment_id, $new_file );
			wp_update_attachment_metadata( $attachment_id, $attach_data );

			if ( ! is_wp_error( $attachment_id ) ) {
				$attachment['attachment_path'] = $new_file;
				$attachment['attachment_id']   = $attachment_id;
			}

			return $attachment;

		} catch ( \Exception $exception ) {
			return array();
		}
	}
}
