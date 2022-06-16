<?php
/**
 * Plugin Name: Shapla Form
 * Plugin URI: https://github.com/sayful1/shapla-form
 * Description: Just another WordPress contact form plugin. Simple but flexible.
 * Version: 3.1.0
 * Author: Sayful Islam
 * Author URI: https://sayfulislam.com
 * Requires at least: 4.7
 * Tested up to: 6.0
 * Text Domain: shapla-form
 * License: GPLv3
 * License URI: https://www.gnu.org/licenses/gpl-3.0.txt
 */

// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'Dialog_Contact_Form' ) ) {

	final class Dialog_Contact_Form {

		/**
		 * The instance of the class
		 *
		 * @var self
		 */
		private static $instance;

		/**
		 * Plugin name slug
		 *
		 * @var string
		 */
		private $plugin_name = 'shapla-form';

		/**
		 * Plugin custom post type
		 *
		 * @var string
		 */
		private $post_type = 'dialog-contact-form';

		/**
		 * Plugin version
		 *
		 * @var string
		 */
		private $version = '3.1.0';

		/**
		 * Holds various class instances
		 *
		 * @var array
		 */
		private $container = array();

		/**
		 * Minimum PHP version required
		 *
		 * @var string
		 */
		private $min_php = '7.0';

		/**
		 * Ensures only one instance of the class is loaded or can be loaded.
		 *
		 * @return Dialog_Contact_Form
		 * @throws Exception
		 */
		public static function init() {
			if ( is_null( self::$instance ) ) {
				self::$instance = new self();

				// define constants
				self::$instance->define_constants();

				// Check if PHP version is supported for our plugin
				if ( ! self::$instance->is_supported_php() ) {
					register_activation_hook( __FILE__, array( self::$instance, 'auto_deactivate' ) );
					add_action( 'admin_notices', array( self::$instance, 'php_version_notice' ) );

					return self::$instance;
				}

				// Include Classes
				spl_autoload_register( array( self::$instance, 'include_classes' ) );

				// Register plugin activation activity
				register_activation_hook( __FILE__, array( 'DialogContactForm\\Activation', 'install' ) );

				// initialize the classes
				add_action( 'plugins_loaded', array( self::$instance, 'init_classes' ) );

				// Load plugin textdomain
				add_action( 'plugins_loaded', array( self::$instance, 'load_plugin_textdomain' ) );

				// Configure PHPMailer for sending mail over SMTP
				add_action( 'phpmailer_init', array( 'DialogContactForm\\PHPMailerConfig', 'config' ) );

				/*
                 * WP-CLI Commands
                 */
				if ( class_exists( 'WP_CLI' ) && class_exists( 'WP_CLI_Command' ) ) {
					WP_CLI::add_command( 'dialog-contact-form', 'DialogContactForm\\CLI\\Command' );
				}

				do_action( 'dialog_contact_form/loaded' );
			}

			return self::$instance;
		}

		/**
		 * Magic getter to bypass referencing plugin.
		 *
		 * @param string $property
		 *
		 * @return mixed
		 */
		public function __get( $property ) {
			if ( array_key_exists( $property, $this->container ) ) {
				return $this->container[ $property ];
			}

			return $this->{$property};
		}

		/**
		 * Define plugin constants
		 */
		private function define_constants() {
			define( 'DIALOG_CONTACT_FORM', $this->plugin_name );
			define( 'DIALOG_CONTACT_FORM_POST_TYPE', $this->post_type );
			define( 'DIALOG_CONTACT_FORM_VERSION', $this->version );
			define( 'DIALOG_CONTACT_FORM_FILE', __FILE__ );
			define( 'DIALOG_CONTACT_FORM_PATH', dirname( DIALOG_CONTACT_FORM_FILE ) );
			define( 'DIALOG_CONTACT_FORM_INCLUDES', DIALOG_CONTACT_FORM_PATH . '/includes' );
			define( 'DIALOG_CONTACT_FORM_TEMPLATES', DIALOG_CONTACT_FORM_PATH . '/templates' );
			define( 'DIALOG_CONTACT_FORM_URL', plugins_url( '', DIALOG_CONTACT_FORM_FILE ) );
			define( 'DIALOG_CONTACT_FORM_ASSETS', DIALOG_CONTACT_FORM_URL . '/assets' );
			define( 'DIALOG_CONTACT_FORM_UPLOAD_DIR', 'dcf-attachments' );
		}

		/**
		 * Get plugin version number
		 *
		 * @return string
		 */
		public function get_version() {
			if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
				return $this->version . '-' . time();
			}

			return $this->version;
		}

		/**
		 * Include classes
		 *
		 * @param string $className class name
		 */
		private function include_classes( $className ) {
			if ( class_exists( $className ) ) {
				return;
			}

			// project-specific namespace prefix
			$prefix = 'DialogContactForm\\';

			// base directory for the namespace prefix
			$base_dir = DIALOG_CONTACT_FORM_INCLUDES . DIRECTORY_SEPARATOR;

			// does the class use the namespace prefix?
			$len = strlen( $prefix );
			if ( strncmp( $prefix, $className, $len ) !== 0 ) {
				// no, move to the next registered autoloader
				return;
			}

			// get the relative class name
			$relative_class = substr( $className, $len );

			// replace the namespace prefix with the base directory, replace namespace
			// separators with directory separators in the relative class name, append
			// with .php
			$file = $base_dir . str_replace( '\\', '/', $relative_class ) . '.php';

			// if the file exists, require it
			if ( file_exists( $file ) ) {
				require_once $file;
			}
		}

		/**
		 * Instantiate the required classes
		 *
		 * @return void
		 */
		public function init_classes() {

			if ( $this->is_request( 'admin' ) ) {
				$this->container['admin']      = \DialogContactForm\Admin\Admin::init();
				$this->container['settings']   = \DialogContactForm\Admin\Settings::init();
				$this->container['ajax']       = \DialogContactForm\Admin\Ajax::init();
				$this->container['gutenblock'] = \DialogContactForm\Admin\GutenbergBlock::init();
				$this->container['entries']    = \DialogContactForm\Entries\EntryManager::init();
			}

			if ( $this->is_request( 'frontend' ) ) {
				$this->container['preview']      = \DialogContactForm\Display\Preview::init();
				$this->container['shortcode']    = \DialogContactForm\Display\Shortcode::init();
				$this->container['rest-form']    = \DialogContactForm\REST\FormController::init();
				$this->container['rest-entry']   = \DialogContactForm\REST\EntryController::init();
				$this->container['rest-setting'] = \DialogContactForm\REST\SettingController::init();
//				$this->container['rest']       = \DialogContactForm\REST\Controller::init();
			}

			$this->container['scripts']    = \DialogContactForm\Scripts::init();
			$this->container['submission'] = \DialogContactForm\Submission::init();
		}

		/**
		 * Load plugin textdomain
		 */
		public function load_plugin_textdomain() {
			// Traditional WordPress plugin locale filter
			$locale = apply_filters( 'plugin_locale', get_locale(), 'dialog-contact-form' );
			$mofile = sprintf( '%1$s-%2$s.mo', 'dialog-contact-form', $locale );

			// Setup paths to current locale file
			$mofile_global = WP_LANG_DIR . '/dialog-contact-form/' . $mofile;

			// Look in global /wp-content/languages/dialog-contact-form folder
			if ( file_exists( $mofile_global ) ) {
				load_textdomain( $this->plugin_name, $mofile_global );
			}
		}

		/**
		 * Show notice about PHP version
		 *
		 * @return void
		 */
		public function php_version_notice() {

			if ( $this->is_supported_php() || ! current_user_can( 'manage_options' ) ) {
				return;
			}

			$error = __( 'Your installed PHP Version is: ', 'dialog-contact-form' ) . PHP_VERSION . '. ';
			$error .= sprintf( __( 'The Dialog Contact Form plugin requires PHP version %s or greater.',
				'dialog-contact-form' ), $this->min_php );
			?>
            <div class="error">
                <p><?php printf( $error ); ?></p>
            </div>
			<?php
		}

		/**
		 * Bail out if the php version is lower than
		 *
		 * @return void
		 */
		public function auto_deactivate() {
			if ( $this->is_supported_php() ) {
				return;
			}

			deactivate_plugins( plugin_basename( __FILE__ ) );

			$error = '<h1>' . __( 'An Error Occurred', 'dialog-contact-form' ) . '</h1>';
			$error .= '<h2>' . __( 'Your installed PHP Version is: ', 'dialog-contact-form' ) . PHP_VERSION . '</h2>';
			$error .= '<p>' . sprintf( __( 'The Dialog Contact Form plugin requires PHP version %s or greater',
					'dialog-contact-form' ), $this->min_php ) . '</p>';
			$error .= '<p>' . sprintf( __( 'The version of your PHP is %s unsupported and old %s. ',
					'dialog-contact-form' ),
					'<a href="http://php.net/supported-versions.php" target="_blank"><strong>',
					'</strong></a>'
				);
			$error .= __( 'You should update your PHP software or contact your host regarding this matter.',
					'dialog-contact-form' ) . '</p>';

			wp_die( $error, __( 'Plugin Activation Error', 'dialog-contact-form' ), array( 'back_link' => true ) );
		}

		/**
		 * What type of request is this?
		 *
		 * @param string $type admin, ajax, cron or frontend.
		 *
		 * @return bool
		 */
		public function is_request( $type ) {
			switch ( $type ) {
				case 'admin':
					return is_admin();
				case 'ajax':
					return defined( 'DOING_AJAX' );
				case 'cron':
					return defined( 'DOING_CRON' );
				case 'frontend':
					return ( ! is_admin() || defined( 'DOING_AJAX' ) ) && ! defined( 'DOING_CRON' );
			}

			return false;
		}

		/**
		 * Check if the PHP version is supported
		 *
		 * @param null $min_php
		 *
		 * @return bool
		 */
		private function is_supported_php( $min_php = null ) {

			$min_php = $min_php ? $min_php : $this->min_php;

			if ( version_compare( PHP_VERSION, $min_php, '<=' ) ) {
				return false;
			}

			return true;
		}
	}
}

/**
 * Begins execution of the plugin.
 *
 * Since everything within the plugin is registered via hooks,
 * then kicking off the plugin from this point in the file does
 * not affect the page life cycle.
 */
function dialog_contact_form() {
	return Dialog_Contact_Form::init();
}

dialog_contact_form();
