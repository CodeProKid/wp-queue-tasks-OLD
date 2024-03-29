<?php
/**
 * Plugin Name:     WP Queue Tasks
 * Plugin URI:      https://github.com/dfmedia/wp-queue-tasks
 * Description:     Create's a task queue that gets processed on every shutdown hook.
 * Author:          Ryan Kanner, Digital First Media
 * Text Domain:     wp-queue-tasks
 * Domain Path:     /languages
 * Version:         0.2.0
 *
 * @package         WP_Queue_Tasks
 */

// ensure the wp environment is loaded properly
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'WPQueueTasks' ) ) {

	class WPQueueTasks {

		/**
		 * Stores the instance of the WPQueueTasks class
		 *
		 * @var Object $instance
		 * @access private
		 */
		private static $instance;

		/**
		 * Retrieves the instance of the WPQueueTasks class
		 *
		 * @access public
		 * @return Object|WPQueueTasks
		 */
		public static function instance() {

			/**
			 * Make sure we are only instantiating the class once
			 */
			if ( ! isset( self::$instance ) && ! ( self::$instance instanceof WPQueueTasks ) ) {
				self::$instance = new WPQueueTasks();
				self::$instance->setup_constants();
				self::$instance->includes();
				self::$instance->run();
			}

			/**
			 * Action that fires after we are done setting things up in the plugin. Extensions of
			 * this plugin should instantiate themselves on this hook to make sure the framework
			 * is available before they do anything.
			 *
			 * @param object $instance Instance of the current WPQueueTasks class
			 */
			do_action( 'wpqt_init', self::$instance );

			return self::$instance;

		}

		/**
		 * Sets up the constants for the plugin to use
		 *
		 * @access private
		 * @return void
		 */
		private function setup_constants() {

			// Plugin version.
			if ( ! defined( 'WPQT_VERSION' ) ) {
				define( 'WPQT_VERSION', '0.2.0' );
			}

			// Plugin Folder Path.
			if ( ! defined( 'WPQT_PLUGIN_DIR' ) ) {
				define( 'WPQT_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
			}

			// Plugin Folder URL.
			if ( ! defined( 'WPQT_PLUGIN_URL' ) ) {
				define( 'WPQT_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
			}

			// Plugin Root File.
			if ( ! defined( 'WPQT_PLUGIN_FILE' ) ) {
				define( 'WPQT_PLUGIN_FILE', __FILE__ );
			}

		}

		/**
		 * Load the autoloaded files as well as the access functions
		 *
		 * @access private
		 * @return void
		 * @throws Exception
		 */
		private function includes() {

			if ( file_exists( WPQT_PLUGIN_DIR . 'vendor/autoload.php' ) ) {
				require_once( WPQT_PLUGIN_DIR . 'vendor/autoload.php' );
			} else {
				throw new Exception( __( 'Could not find autoloader file to include all files' ) );
			}

			/**
			 * Require non-autoloaded files
			 */
			require_once( WPQT_PLUGIN_DIR . 'template-tags.php' );

		}

		/**
		 * Instantiate the main classes we need for the plugin
		 *
		 * @access private
		 * @return void
		 */
		private function run() {

			$register = new \WPQueueTasks\Register();
			$register->setup();

			$processor = new \WPQueueTasks\Processor();
			$processor->setup();

			$scheduler = new \WPQueueTasks\Scheduler();
			$scheduler->setup();

		}

	}

}

/**
 * Function to instantiate the WPQueueTasks class
 *
 * @return Object|WPQueueTasks Instance of the WPQueueTasks object
 * @access public
 */
function wpqt_init() {

	/**
	 * Returns an instance of the WPQueueTasks class
	 */
	return \WPQueueTasks::instance();

}

/**
 * Setup the class early within the after_setup_theme class so the access functions are available
 * for other plugins to use.
 */
add_action( 'after_setup_theme', 'wpqt_init', 1 );
